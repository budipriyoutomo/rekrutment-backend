<?php

namespace App\Domains\Offer\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;
use App\Domains\Vacancy\Models\Vacancy;

class Offer extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** Status yang masih "hidup": satu kandidat hanya boleh punya satu offer seperti ini. */
    public const ACTIVE_STATUSES = [self::STATUS_DRAFT, self::STATUS_SENT];

    protected $table = 'offers';

    protected $hidden = ['token'];

    protected $casts = [
        'allowances' => 'array',
        'base_salary' => 'integer',
        'start_date' => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    /** Offer terkirim yang sudah lewat masa berlaku (expires_at berlaku s.d. akhir hari). */
    public function isPastExpiry(): bool
    {
        return $this->expires_at !== null && $this->expires_at->copy()->endOfDay()->isPast();
    }

    public function totalAllowances(): int
    {
        return (int) collect($this->allowances ?? [])->sum('amount');
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

    public function publicUrl(): ?string
    {
        return $this->token
            ? rtrim(config('app.frontend_url'), '/').'/offer/'.$this->token
            : null;
    }

    /** Nama file PDF yang aman, mis. "Offer_OFR-2026-10-0001.pdf". */
    public function pdfFileName(): string
    {
        return 'Offer_'.str_replace('/', '-', $this->number).'.pdf';
    }
}
