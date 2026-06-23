<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\SalarySlip\Actions\ImportSalarySlipsAction;
use App\Domains\SalarySlip\Actions\SendSalarySlipAction;
use App\Domains\SalarySlip\DTO\SalarySlipDTO;
use App\Domains\SalarySlip\Requests\ImportSalarySlipRequest;
use App\Domains\SalarySlip\Requests\StoreSalarySlipRequest;
use App\Domains\SalarySlip\Resources\SalarySlipResource;
use App\Domains\SalarySlip\Services\SalarySlipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalarySlipController extends BaseApiController
{
    public function __construct(
        private SalarySlipService     $service,
        private ImportSalarySlipsAction $importAction,
        private SendSalarySlipAction    $sendAction,
    ) {}

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
        $count = $this->importAction->execute($request->file('file'));

        return $this->success(['imported' => $count], "{$count} slip gaji berhasil diimport");
    }

    public function send(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return $this->error('Parameter ids wajib diisi dan harus berupa array.', 422);
        }

        $count = $this->sendAction->execute($ids);

        return $this->success(['queued' => $count], "{$count} slip gaji telah dijadwalkan untuk dikirim");
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
