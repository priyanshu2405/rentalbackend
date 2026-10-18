<?php

namespace App\Services;

use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class VerificationCodeService
{
    public function issue(string $email, string $purpose): void
    {
        VerificationCode::where('email', $email)->where('purpose', $purpose)->whereNull('used_at')->delete();

        $code = (string) random_int(100000, 999999);
        $ttl = config('neighbours.verification_code_ttl_minutes');

        VerificationCode::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $subject = $purpose === VerificationCode::VERIFY_EMAIL ? 'Verify your email' : 'Reset your password';
        $text = "Your Neighbours Rental code is {$code}.\n\nIt expires in {$ttl} minutes. If you didn't request this, you can ignore this email.";

        Mail::raw($text, fn ($m) => $m->to($email)->subject("Neighbours Rental – {$subject}"));
    }

    public function consume(string $email, string $purpose, string $code): bool
    {
        $record = VerificationCode::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $record || $record->attempts >= 5) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return false;
        }

        $record->forceFill(['used_at' => now()])->save();

        return true;
    }
}
