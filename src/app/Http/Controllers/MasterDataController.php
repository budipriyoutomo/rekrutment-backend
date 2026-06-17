<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\MasterData\Enums\MasterDataType;
use App\Domains\MasterData\Requests\MasterDataRequest;
use App\Domains\MasterData\Resources\MasterDataResource;
use App\Domains\MasterData\Services\MasterDataService;
use Illuminate\Http\Request;

class MasterDataController extends BaseApiController
{
    public function types()
    {
        $service = app(MasterDataService::class);

        return $this->success($service->getAllTypes(), 'Tipe master data');
    }

    public function index(Request $request, MasterDataService $service)
    {
        $typeValue = $request->get('type');

        if (!$typeValue) {
            return response()->json(['success' => false, 'message' => 'Parameter type wajib diisi.'], 422);
        }

        $type = MasterDataType::tryFrom($typeValue);
        if (!$type) {
            return response()->json(['success' => false, 'message' => "Tipe '{$typeValue}' tidak valid."], 422);
        }

        $onlyActive = filter_var($request->get('active', false), FILTER_VALIDATE_BOOLEAN);

        return $this->success(
            MasterDataResource::collection($service->getByType($type, $onlyActive)),
            'Data berhasil diambil'
        );
    }

    public function store(MasterDataRequest $request, MasterDataService $service)
    {
        return $this->success(
            new MasterDataResource($service->createItem($request->validated())),
            'Data berhasil ditambahkan'
        );
    }

    public function update(string $id, MasterDataRequest $request, MasterDataService $service)
    {
        return $this->success(
            new MasterDataResource($service->updateItem($id, $request->validated())),
            'Data berhasil diperbarui'
        );
    }

    public function destroy(string $id, MasterDataService $service)
    {
        $service->deleteItem($id);

        return $this->success(null, 'Data berhasil dihapus');
    }

    public function toggleActive(string $id, MasterDataService $service)
    {
        return $this->success(
            new MasterDataResource($service->toggleActive($id)),
            'Status berhasil diubah'
        );
    }
}
