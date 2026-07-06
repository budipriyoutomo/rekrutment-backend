<?php

namespace App\Domains\SalarySlip\Services;

use App\Domains\SalarySlip\Models\MailAccount;
use Illuminate\Support\Collection;

class MailAccountService
{
    /** @return Collection<int,MailAccount> */
    public function list(bool $onlyActive = false, ?string $purpose = null): Collection
    {
        return MailAccount::query()
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    /**
     * Entri default rekrutmen dari konfigurasi .env (mail global Laravel).
     * Read-only — sumber kebenaran tetap file .env, bukan tabel mail_accounts.
     * Dikembalikan sebagai array (bukan model) agar cast `encrypted` password
     * tidak ikut terlibat.
     *
     * @return array<string,mixed>
     */
    public function envRecruitmentDefault(): array
    {
        $smtp = config('mail.mailers.smtp', []);

        return [
            'id'             => 'env-recruitment',
            'label'          => 'Email Rekrutmen (dari .env)',
            'driver'         => config('mail.default', 'smtp'),
            'purpose'        => MailAccount::PURPOSE_RECRUITMENT,
            'fromEmail'      => config('mail.from.address'),
            'fromName'       => config('mail.from.name'),
            'smtpHost'       => $smtp['host'] ?? null,
            'smtpPort'       => isset($smtp['port']) ? (int) $smtp['port'] : null,
            'smtpUsername'   => $smtp['username'] ?? null,
            'smtpEncryption' => $smtp['encryption'] ?? env('MAIL_ENCRYPTION'),
            'hasPassword'    => ! empty($smtp['password'] ?? env('MAIL_PASSWORD')),
            'isDefault'      => true,
            'isActive'       => true,
            'isEnvDefault'   => true,
            'createdAt'      => null,
        ];
    }

    public function create(array $data): MailAccount
    {
        $account = MailAccount::create($this->mapAttributes($data));
        $this->ensureSingleDefault($account);

        return $account->refresh();
    }

    public function update(MailAccount $account, array $data): MailAccount
    {
        $account->update($this->mapAttributes($data, isUpdate: true));
        $this->ensureSingleDefault($account);

        return $account->refresh();
    }

    public function delete(MailAccount $account): void
    {
        $account->delete();
    }

    /**
     * Map input request -> kolom DB. Field `smtp_password` (write-only) dipetakan
     * ke `smtp_password_encrypted` (cast encrypted). Saat update, password kosong
     * berarti tidak diubah.
     */
    private function mapAttributes(array $data, bool $isUpdate = false): array
    {
        $attrs = array_intersect_key($data, array_flip([
            'label', 'driver', 'purpose', 'from_email', 'from_name',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption',
            'is_default', 'is_active',
        ]));

        if (! empty($data['smtp_password'])) {
            $attrs['smtp_password_encrypted'] = $data['smtp_password'];
        }

        return $attrs;
    }

    /** Pastikan hanya ada satu akun default per-purpose. */
    private function ensureSingleDefault(MailAccount $account): void
    {
        if (! $account->is_default) {
            return;
        }

        MailAccount::query()
            ->whereKeyNot($account->id)
            ->where('purpose', $account->purpose)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
