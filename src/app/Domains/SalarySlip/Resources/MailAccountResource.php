<?php

namespace App\Domains\SalarySlip\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi mail account untuk API. TIDAK PERNAH mengekspos kredensial
 * (smtp_password_encrypted) — password bersifat write-only.
 */
class MailAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'label'          => $this->label,
            'driver'         => $this->driver,
            'fromEmail'      => $this->from_email,
            'fromName'       => $this->from_name,
            'smtpHost'       => $this->smtp_host,
            'smtpPort'       => $this->smtp_port,
            'smtpUsername'   => $this->smtp_username,
            'smtpEncryption' => $this->smtp_encryption,
            'hasPassword'    => ! empty($this->smtp_password_encrypted),
            'isDefault'      => (bool) $this->is_default,
            'isActive'       => (bool) $this->is_active,
            'createdAt'      => $this->created_at?->toIso8601String(),
        ];
    }
}
