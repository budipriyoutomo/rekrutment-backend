<?php

namespace App\Domains\ProfileCompletion\Actions;

use App\Domains\Application\Models\Application;
use App\Domains\ProfileCompletion\Mail\ProfileCompletionMail;
use App\Domains\ProfileCompletion\Models\ProfileCompletionToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendProfileCompletionEmailAction
{
    public function execute(Application $application): ProfileCompletionToken
    {
        $email = $application->contact_info['email'] ?? null;

        if (empty($email)) {
            throw new \InvalidArgumentException('Pelamar tidak memiliki alamat email yang valid.');
        }

        // Hapus token lama yang belum dipakai untuk application ini
        ProfileCompletionToken::where('application_id', $application->id)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(64);

        $record = ProfileCompletionToken::create([
            'application_id' => $application->id,
            'email'          => $email,
            'token'          => $token,
            'expires_at'     => now()->addDays(7),
        ]);

        $completionUrl = rtrim(config('app.frontend_url'), '/') . '/profile-completion/' . $token;

        Mail::to($email)->send(new ProfileCompletionMail($application, $token, $completionUrl));

        return $record;
    }
}
