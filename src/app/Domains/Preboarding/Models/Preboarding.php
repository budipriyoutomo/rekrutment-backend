<?php

namespace App\Domains\Preboarding\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;
use App\Domains\Employee\Models\Employee;
use App\Domains\Offer\Models\Offer;

class Preboarding extends BaseModel
{
    public const STATUS_PENDING        = 'pending';
    public const STATUS_SUBMITTED      = 'submitted';
    public const STATUS_NEEDS_REVISION = 'needs_revision';
    public const STATUS_VERIFIED       = 'verified';

    public const DOC_PENDING  = 'pending';
    public const DOC_VALID    = 'valid';
    public const DOC_REJECTED = 'rejected';

    /** Dokumen wajib beserta labelnya. */
    public const REQUIRED_DOCUMENTS = [
        'ktp'           => 'KTP',
        'kk'            => 'Kartu Keluarga',
        'ijazah'        => 'Ijazah Terakhir',
        'foto'          => 'Pas Foto',
        'buku_tabungan' => 'Buku Tabungan (halaman depan)',
    ];

    /** Status di mana kandidat masih boleh mengisi/mengunggah. */
    public const EDITABLE_STATUSES = [self::STATUS_PENDING, self::STATUS_NEEDS_REVISION];

    public const TOKEN_VALID_DAYS = 30;

    protected $table = 'preboardings';

    protected $hidden = ['token'];

    protected $casts = [
        'data'             => 'array',
        'documents'        => 'array',
        'token_expires_at' => 'datetime',
        'sent_at'          => 'datetime',
        'submitted_at'     => 'datetime',
        'verified_at'      => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }

    public function isEditableByCandidate(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function document(string $type): ?array
    {
        return $this->documents[$type] ?? null;
    }

    /** Dokumen wajib yang belum diunggah. */
    public function missingDocuments(): array
    {
        return array_values(array_filter(
            array_keys(self::REQUIRED_DOCUMENTS),
            fn (string $type) => empty($this->documents[$type]['path'])
        ));
    }

    public function rejectedDocuments(): array
    {
        return array_keys(array_filter(
            $this->documents ?? [],
            fn (array $doc) => ($doc['status'] ?? null) === self::DOC_REJECTED
        ));
    }

    public function allDocumentsValid(): bool
    {
        foreach (array_keys(self::REQUIRED_DOCUMENTS) as $type) {
            if (($this->documents[$type]['status'] ?? null) !== self::DOC_VALID) {
                return false;
            }
        }

        return true;
    }

    public function candidateName(): string
    {
        return $this->application?->personal_info['fullName'] ?? 'Kandidat';
    }

    public function candidateEmail(): ?string
    {
        $email = trim((string) ($this->application?->contact_info['email'] ?? ''));

        return $email === '' ? null : $email;
    }

    public function publicUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/preboarding/' . $this->token;
    }

    /** Posisi & tanggal mulai diambil dari offer yang diterima (bila ada). */
    public function position(): ?string
    {
        return $this->offer?->position
            ?? $this->application?->vacancy?->title
            ?? $this->application?->additional_info['positionApplied']
            ?? null;
    }
}
