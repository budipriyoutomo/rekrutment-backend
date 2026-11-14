<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Core\Http\PerPage;
use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Actions\ImportSalarySlipsAction;
use App\Domains\SalarySlip\Actions\SendSalarySlipAction;
use App\Domains\SalarySlip\Exports\SalarySlipTemplateExport;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Requests\ImportSalarySlipRequest;
use App\Domains\SalarySlip\Requests\SendSalarySlipRequest;
use App\Domains\SalarySlip\Resources\SalarySlipResource;
use App\Domains\SalarySlip\Services\SalarySlipService;
use App\Enums\MenuPermission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalarySlipController extends BaseApiController
{
    public function __construct(
        private SalarySlipService $service,
        private ImportSalarySlipsAction $importAction,
        private SendSalarySlipAction $sendAction,
        private GeneratePayslipPdfAction $pdfAction,
    ) {}

    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new SalarySlipTemplateExport,
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
            $request->only(['periode', 'cabang', 'search', 'status']),
            PerPage::from($request, 50),
        );

        return $this->success([
            'data' => SalarySlipResource::collection($data->items()),
            'total' => $data->total(),
            'page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ], 'Data slip gaji berhasil diambil');
    }

    public function import(ImportSalarySlipRequest $request): JsonResponse
    {
        $summary = $this->importAction->execute($request->file('file'));

        return $this->success(
            $summary,
            "{$summary['success_rows']} baris berhasil diimport, {$summary['failed_rows']} gagal.",
        );
    }

    public function send(SendSalarySlipRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->sendAction->execute(
            $validated['ids'],
            $validated['mail_account_id'] ?? null,
            $request->boolean('resend'),
            resendOnly: ! $this->canSendNewSlips($request->user()),
        );

        return $this->success(
            $result,
            "{$result['dispatched']} slip dijadwalkan dikirim, {$result['skipped']} dilewati.",
        );
    }

    /**
     * Izin "Report Salary Slip" saja hanya boleh mengirim ULANG slip yang pernah
     * terkirim; pengiriman pertama butuh izin "Salary Slips" (admin bypass).
     */
    private function canSendNewSlips(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (in_array($user->role, [Role::SUPER_ADMIN->value, Role::ADMIN->value], true)) {
            return true;
        }

        return in_array(MenuPermission::SALARY_SLIPS->value, $user->permissions ?? [], true);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || ! is_array($ids)) {
            return $this->error('Parameter ids wajib diisi dan harus berupa array.', 422);
        }

        $count = $this->service->bulkDelete($ids);

        return $this->success(['deleted' => $count], "{$count} slip gaji berhasil dihapus");
    }
}
