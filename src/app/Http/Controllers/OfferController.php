<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Offer\Actions\SendOfferAction;
use App\Domains\Offer\Requests\OfferRequest;
use App\Domains\Offer\Resources\OfferResource;
use App\Domains\Offer\Services\OfferLetterPdf;
use App\Domains\Offer\Services\OfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OfferController extends BaseApiController
{
    public function index(Request $request, OfferService $service)
    {
        $data = $service->getList(
            filters: $request->only(['status', 'applicationId', 'search']),
            perPage: (int) $request->get('per_page', $request->get('limit', 50))
        );

        return $this->successPaginated(OfferResource::collection($data), $data, 'Data offer berhasil diambil');
    }

    public function show(string $id, OfferService $service)
    {
        return $this->success(new OfferResource($service->getDetail($id)), 'Detail offer berhasil diambil');
    }

    public function store(OfferRequest $request, OfferService $service)
    {
        return $this->success(new OfferResource($service->createOffer($request->validated())), 'Offer draft berhasil dibuat');
    }

    public function update(string $id, OfferRequest $request, OfferService $service)
    {
        return $this->success(new OfferResource($service->updateOffer($id, $request->validated())), 'Offer berhasil diperbarui');
    }

    public function destroy(string $id, OfferService $service)
    {
        $service->deleteOffer($id);

        return $this->success(null, 'Offer berhasil dihapus');
    }

    public function send(string $id, OfferService $service, SendOfferAction $action)
    {
        $offer = $service->getDetail($id);
        $wasSent = $offer->sent_at !== null;
        $action->execute($offer);

        return $this->success(
            new OfferResource($service->getDetail($offer->id)),
            $wasSent ? 'Email offer dikirim ulang ke kandidat' : 'Offer sedang dikirim ke kandidat'
        );
    }

    public function withdraw(string $id, OfferService $service)
    {
        return $this->success(new OfferResource($service->withdraw($id)), 'Offer berhasil ditarik');
    }

    /** Preview PDF untuk HR. */
    public function pdf(string $id, OfferService $service, OfferLetterPdf $pdf)
    {
        $offer = $service->getDetail($id);

        try {
            $content = $pdf->render($offer);
        } catch (\Throwable $e) {
            Log::error('Gagal merender PDF offer: ' . $e->getMessage(), ['offer_id' => $id]);

            return $this->error('Gagal membuat PDF offer letter.', 500);
        }

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $offer->pdfFileName() . '"',
        ]);
    }
}
