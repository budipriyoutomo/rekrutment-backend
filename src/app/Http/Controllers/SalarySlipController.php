<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Actions\ImportSalarySlipsAction;
use App\Domains\SalarySlip\Actions\SendSalarySlipAction;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\DTO\SalarySlipDTO;
use App\Domains\SalarySlip\Exports\SalarySlipTemplateExport;
use App\Domains\SalarySlip\Requests\ImportSalarySlipRequest;
use App\Domains\SalarySlip\Requests\StoreSalarySlipRequest;
use App\Domains\SalarySlip\Resources\SalarySlipResource;
use App\Domains\SalarySlip\Services\SalarySlipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalarySlipController extends BaseApiController
{
    public function __construct(
        private SalarySlipService       $service,
        private ImportSalarySlipsAction $importAction,
        private SendSalarySlipAction    $sendAction,
        private GeneratePayslipPdfAction $pdfAction,
    ) {}

    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new SalarySlipTemplateExport(),
            'template_salary_slip.xlsx',
        );
    }

    public function pdf(string $id): BinaryFileResponse
    {
        $slip = SalarySlip::findOrFail($id);

        $path = $this->pdfAction->execute($slip);

        return response()->download(
            $path,
            "slip_gaji_{$slip->nik}_{$slip->periode}.pdf",
        );
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getList(
            $request->only(['periode', 'cabang', 'search']),
            (int) $request->get('per_page', 50),
        );

        return $this->success([
            'data'  => SalarySlipResource::collection($data->items()),
            'total' => $data->total(),
            'page'  => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ], 'Data slip gaji berhasil diambil');
    }

    public function store(StoreSalarySlipRequest $request): JsonResponse
    {
        $slip = $this->service->store(SalarySlipDTO::fromArray($request->validated()));

        return $this->success(new SalarySlipResource($slip), 'Slip gaji berhasil disimpan');
    }

    public function import(ImportSalarySlipRequest $request): JsonResponse
    {
        $summary = $this->importAction->execute($request->file('file'));

        return $this->success(
            $summary,
            "{$summary['success_rows']} baris berhasil diimport, {$summary['failed_rows']} gagal.",
        );
    }

    public function send(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return $this->error('Parameter ids wajib diisi dan harus berupa array.', 422);
        }

        $result = $this->sendAction->execute(
            $ids,
            $request->input('mail_account_id'),
            $request->boolean('resend'),
        );

        return $this->success(
            $result,
            "{$result['dispatched']} slip dijadwalkan dikirim, {$result['skipped']} dilewati (sudah terkirim).",
        );
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return $this->error('Parameter ids wajib diisi dan harus berupa array.', 422);
        }

        $count = $this->service->bulkDelete($ids);

        return $this->success(['deleted' => $count], "{$count} slip gaji berhasil dihapus");
    }
}
