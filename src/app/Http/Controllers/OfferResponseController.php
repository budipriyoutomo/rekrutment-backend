<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Offer\Actions\RespondToOfferAction;
use App\Domains\Offer\Resources\PublicOfferResource;
use App\Domains\Offer\Services\OfferLetterPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Endpoint publik untuk kandidat melihat & merespon offer lewat token dari email.
 * Token adalah kredensialnya; tidak ada autentikasi.
 */
class OfferResponseController extends BaseApiController
{
    /** Detail offer. Offer yang sudah direspon/ditarik tetap ditampilkan beserta statusnya. */
    public function show(string $token, RespondToOfferAction $action)
    {
        return $this->guarded(fn () => $this->success(
            new PublicOfferResource($action->findByToken($token)),
            'Offer ditemukan'
        ));
    }

    public function accept(string $token, RespondToOfferAction $action)
    {
        return $this->guarded(fn () => $this->success(
            new PublicOfferResource($action->accept($token)),
            'Terima kasih, offer telah Anda terima.'
        ));
    }

    public function decline(string $token, Request $request, RespondToOfferAction $action)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        return $this->guarded(fn () => $this->success(
            new PublicOfferResource($action->decline($token, $validated['reason'] ?? null)),
            'Respon Anda telah kami terima.'
        ));
    }

    public function pdf(string $token, RespondToOfferAction $action, OfferLetterPdf $pdf)
    {
        return $this->guarded(function () use ($token, $action, $pdf) {
            $offer = $action->findByToken($token);

            try {
                $content = $pdf->render($offer);
            } catch (\Throwable $e) {
                Log::error('Gagal merender PDF offer (publik): '.$e->getMessage(), ['offer_id' => $offer->id]);

                return $this->error('Gagal membuat PDF offer letter.', 500);
            }

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$offer->pdfFileName().'"',
            ]);
        });
    }

    private function guarded(callable $callback)
    {
        try {
            return $callback();
        } catch (HttpException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }
    }
}
