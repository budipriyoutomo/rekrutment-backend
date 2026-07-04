<?php

namespace App\Domains\SalarySlip\Services;

use App\Domains\SalarySlip\Models\MailAccount;
use Illuminate\Support\Collection;

class MailAccountService
{
    /** @return Collection<int,MailAccount> */
    public function list(bool $onlyActive = false): Collection
    {
        return MailAccount::query()
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
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
            'label', 'driver', 'from_email', 'from_name',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption',
            'is_default', 'is_active',
        ]));

        if (! empty($data['smtp_password'])) {
            $attrs['smtp_password_encrypted'] = $data['smtp_password'];
        }

        return $attrs;
    }

    /** Pastikan hanya ada satu akun default. */
    private function ensureSingleDefault(MailAccount $account): void
    {
        if (! $account->is_default) {
            return;
        }

        MailAccount::query()
            ->whereKeyNot($account->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
