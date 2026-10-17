<?php

namespace App\Domains\Offer\Services;

use App\Core\Services\BaseService;
use App\Domains\Application\Models\Application;
use App\Domains\Offer\Models\Offer;
use App\Enums\PipelineStage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class OfferService extends BaseService
{
    public function __construct(Offer $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return Offer::query()
            ->with('application:id,personal_info,contact_info')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['applicationId'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                // ilike (case-insensitive) di Postgres; SQLite (test) memakai like yang sudah case-insensitive
                $like = $this->model->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                $q->where(function ($q) use ($search, $like) {
                    $q->where('position', $like, "%{$search}%")
                      ->orWhere('number', $like, "%{$search}%")
                      ->orWhereHas('application', fn ($a) => $a->where('personal_info->fullName', $like, "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function getDetail(string $id): Offer
    {
        return Offer::with('application')->findOrFail($id);
    }

    public function createOffer(array $data): Offer
    {
        $application = Application::findOrFail($data['application_id']);

        if ($application->stage !== PipelineStage::OFFER->value) {
            throw ValidationException::withMessages([
                'application_id' => 'Offer hanya bisa dibuat untuk kandidat di stage Offer.',
            ]);
        }

        $hasActive = Offer::where('application_id', $application->id)
            ->whereIn('status', Offer::ACTIVE_STATUSES)
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages([
                'application_id' => 'Kandidat ini masih punya offer aktif (draft/terkirim). Tarik offer lama terlebih dahulu.',
            ]);
        }

        $offer = $this->create([
            ...$data,
            'number'     => $this->nextNumber(),
            'vacancy_id' => $application->vacancy_id,
            'status'     => Offer::STATUS_DRAFT,
        ]);

        return $this->getDetail($offer->id);
    }

    public function updateOffer(string $id, array $data): Offer
    {
        $offer = Offer::findOrFail($id);
        $this->ensureDraft($offer, 'diubah');

        $offer->update($data);

        return $this->getDetail($offer->id);
    }

    public function deleteOffer(string $id): void
    {
        $offer = Offer::findOrFail($id);
        $this->ensureDraft($offer, 'dihapus');

        $offer->delete();
    }

    /** Tarik offer draft/terkirim; link kandidat otomatis tak berlaku lagi. */
    public function withdraw(string $id): Offer
    {
        $offer = Offer::findOrFail($id);

        if (! in_array($offer->status, Offer::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya offer draft atau terkirim yang bisa ditarik.',
            ]);
        }

        $offer->update(['status' => Offer::STATUS_WITHDRAWN]);

        return $this->getDetail($offer->id);
    }

    /** Tandai offer terkirim yang lewat masa berlaku sebagai expired. Mengembalikan jumlahnya. */
    public function expireOverdue(): int
    {
        return Offer::query()
            ->where('status', Offer::STATUS_SENT)
            ->whereDate('expires_at', '<', now()->toDateString())
            ->update(['status' => Offer::STATUS_EXPIRED, 'updated_at' => now()]);
    }

    private function ensureDraft(Offer $offer, string $verb): void
    {
        if ($offer->status !== Offer::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "Hanya offer berstatus draft yang bisa {$verb}.",
            ]);
        }
    }

    /** Nomor surat berurutan per bulan, mis. OFR/2026/10/0003. */
    private function nextNumber(): string
    {
        $prefix = 'OFR/' . now()->format('Y/m') . '/';
        $last = Offer::where('number', 'like', $prefix . '%')->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
