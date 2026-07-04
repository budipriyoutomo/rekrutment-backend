<?php

namespace App\Domains\SalarySlip\Services;

use App\Domains\SalarySlip\Models\MailAccount;

/**
 * Membangun mailer runtime dari sebuah MailAccount, sehingga pengiriman bisa
 * memakai kredensial SMTP per-akun (bukan satu konfigurasi global).
 *
 * Password didekripsi hanya saat runtime (via cast `encrypted` pada model) dan
 * TIDAK pernah di-log.
 */
class MailAccountResolver
{
    /**
     * Registrasikan konfigurasi mailer runtime untuk akun ini & kembalikan namanya.
     * Nama mailer dapat dipakai: Mail::mailer($name)->send(...).
     */
    public function build(MailAccount $account): string
    {
        $name = 'mail_account_' . $account->id;

        config([
            "mail.mailers.{$name}" => [
                'transport'  => 'smtp',
                'host'       => $account->smtp_host,
                'port'       => $account->smtp_port,
                'encryption' => $account->smtp_encryption, // tls | ssl | null
                'username'   => $account->smtp_username,
                'password'   => $account->smtp_password_encrypted, // didekripsi oleh cast
                'timeout'    => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ],
        ]);

        return $name;
    }
}
