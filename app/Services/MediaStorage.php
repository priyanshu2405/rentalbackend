<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Stores uploaded images (listing photos, avatars, chat attachments, condition-check
 * and support photos).
 *
 * • If CLOUDINARY_URL is set, files go to Cloudinary and the full https URL is saved in
 *   the database. They survive restarts and redeploys (Render's disk is wiped on both).
 * • Otherwise files go to the local "public" disk (fine for local development).
 *
 * Uses Cloudinary's REST API through Laravel's HTTP client, so no extra Composer package
 * is required.
 */
class MediaStorage
{
    private ?string $cloud = null;
    private ?string $key = null;
    private ?string $secret = null;
    private string $folder;

    public function __construct()
    {
        $url = config('services.cloudinary.url');
        if ($url && preg_match('#^cloudinary://([^:]+):([^@]+)@(.+)$#', trim($url), $m)) {
            [$this->key, $this->secret, $this->cloud] = [$m[1], $m[2], $m[3]];
        }
        $this->folder = trim((string) config('services.cloudinary.folder', 'neighbours-rental'), '/');
    }

    public function usesCloud(): bool
    {
        return $this->cloud !== null;
    }

    /** Save a file and return what should be stored in the database (URL or relative path). */
    public function store(UploadedFile $file, string $folder): string
    {
        if (! $this->usesCloud()) {
            return $file->store($folder, 'public');
        }

        $params = ['folder' => "{$this->folder}/{$folder}", 'timestamp' => time()];

        $response = Http::timeout(60)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName() ?: 'upload')
            ->post("https://api.cloudinary.com/v1_1/{$this->cloud}/auto/upload", $params + [
                'api_key' => $this->key,
                'signature' => $this->sign($params),
            ]);

        if (! $response->successful() || ! $response->json('secure_url')) {
            Log::error('Cloudinary upload failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new HttpException(502, 'Photo upload failed. Please try again.');
        }

        return $response->json('secure_url');
    }

    /** @param  UploadedFile[]  $files */
    public function storeMany(array $files, string $folder): array
    {
        return array_values(array_map(fn (UploadedFile $f) => $this->store($f, $folder), $files));
    }

    /** Public URL for a stored value (old relative paths keep working). */
    public function url(?string $stored): ?string
    {
        if (! $stored) {
            return null;
        }
        if (str_starts_with($stored, 'http')) {
            // Let Cloudinary serve a compressed, device-friendly format (much faster on phones).
            if (str_contains($stored, 'res.cloudinary.com/') && str_contains($stored, '/image/upload/') && ! str_contains($stored, '/upload/q_auto')) {
                return str_replace('/image/upload/', '/image/upload/q_auto,f_auto/', $stored);
            }

            return $stored;
        }

        return Storage::disk('public')->url($stored);
    }

    public function delete(?string $stored): void
    {
        if (! $stored) {
            return;
        }

        if (! str_starts_with($stored, 'http')) {
            Storage::disk('public')->delete($stored);

            return;
        }

        // https://res.cloudinary.com/{cloud}/{type}/upload/v123/folder/name.jpg
        if (! $this->usesCloud() || ! preg_match('#res\.cloudinary\.com/[^/]+/(image|video|raw)/upload/(?:v\d+/)?(.+)$#', $stored, $m)) {
            return;
        }
        [$type, $publicId] = [$m[1], $m[2]];
        if ($type !== 'raw') {
            $publicId = preg_replace('#\.[^./]+$#', '', $publicId);
        }

        $params = ['public_id' => $publicId, 'timestamp' => time()];
        try {
            Http::timeout(20)->asForm()->post("https://api.cloudinary.com/v1_1/{$this->cloud}/{$type}/destroy", $params + [
                'api_key' => $this->key,
                'signature' => $this->sign($params),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed', ['public_id' => $publicId, 'error' => $e->getMessage()]);
        }
    }

    /** Cloudinary signature: sha1 of the sorted params "a=1&b=2" followed by the API secret. */
    private function sign(array $params): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = "{$k}={$v}";
        }

        return sha1(implode('&', $pairs).$this->secret);
    }
}
