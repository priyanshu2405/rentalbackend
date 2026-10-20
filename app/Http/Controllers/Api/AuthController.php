<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private VerificationCodeService $codes) {}

    /** Sign up → sends a 6-digit code to verify the email. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()],
            'agree_terms' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        $this->codes->issue($user->email, VerificationCode::VERIFY_EMAIL);

        return $this->tokenResponse($user, $data['device_name'] ?? 'mobile', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $this->ensureNotSuspended($user);

        return $this->tokenResponse($user, $data['device_name'] ?? 'mobile');
    }

    /**
     * "Continue with Google": the app sends the Google ID token from google_sign_in.
     * We verify it with Google and sign in / create the account.
     */
    public function google(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $data['id_token']]);
        $payload = $response->json();

        $allowed = config('neighbours.google_client_ids');
        if (! $response->ok() || empty($payload['email']) || ($allowed && ! in_array($payload['aud'] ?? null, $allowed, true))) {
            throw ValidationException::withMessages(['id_token' => 'Google sign-in could not be verified.']);
        }

        $email = strtolower($payload['email']);
        $user = User::where('google_id', $payload['sub'])->orWhere('email', $email)->first();

        $created = false;
        if (! $user) {
            $user = User::create(['name' => $payload['name'] ?? explode('@', $email)[0], 'email' => $email]);
            $created = true;
        }

        $this->ensureNotSuspended($user);

        $user->forceFill([
            'google_id' => $payload['sub'],
            'email_verified_at' => $user->email_verified_at ?? (($payload['email_verified'] ?? 'false') === 'true' ? now() : null),
        ])->save();

        return $this->tokenResponse($user, $data['device_name'] ?? 'mobile', $created ? 201 : 200);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified.', 'user' => new UserResource($user)]);
        }

        if (! $this->codes->consume($user->email, VerificationCode::VERIFY_EMAIL, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is invalid or has expired.']);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json(['message' => 'Email verified.', 'user' => new UserResource($user)]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->email_verified_at) {
            $this->codes->issue($user->email, VerificationCode::VERIFY_EMAIL);
        }

        return response()->json(['message' => 'A new code has been sent.', 'resend_after_seconds' => 30]);
    }

    /** "Change email address" on the verify screen. */
    public function changeEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['email' => ['required', 'email', 'max:191', 'unique:users,email,'.$user->id]]);

        $user->forceFill(['email' => strtolower($data['email']), 'email_verified_at' => null])->save();
        $this->codes->issue($user->email, VerificationCode::VERIFY_EMAIL);

        return response()->json(['message' => 'Email updated. Check your inbox for a new code.', 'user' => new UserResource($user)]);
    }

    /** Always responds the same way so emails can't be enumerated. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($data['email']);

        if (User::where('email', $email)->exists()) {
            $this->codes->issue($email, VerificationCode::RESET_PASSWORD);
        }

        return response()->json(['message' => "If an account exists for {$email}, we've sent a reset code."]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();

        if (! $user || ! $this->codes->consume($email, VerificationCode::RESET_PASSWORD, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is invalid or has expired.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'email_verified_at' => $user->email_verified_at ?? now(), // they proved inbox access
        ])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password updated. Please sign in.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'counts' => [
                'unread_notifications' => $user->appNotifications()->whereNull('read_at')->count(),
                'favorites' => $user->favoriteItems()->count(),
                'listings' => $user->items()->count(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function ensureNotSuspended(User $user): void
    {
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['email' => 'This account has been suspended. Contact support@neighboursrental.ca.']);
        }
    }

    private function tokenResponse(User $user, string $device, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], $status);
    }
}
