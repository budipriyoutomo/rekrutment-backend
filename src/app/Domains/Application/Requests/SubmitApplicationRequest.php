<?php

namespace App\Domains\Application\Requests;

use App\Core\Http\Requests\BaseRequest;
use App\Core\Support\CityPrefix;
use App\Domains\MasterData\Enums\MasterDataType;
use App\Domains\MasterData\Models\MasterData;
use Illuminate\Contracts\Validation\Validator;

class SubmitApplicationRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'personalInfo.fullName' => ['required'],
            'contactInfo.email' => ['required', 'email'],
            'contactInfo.phone' => ['required'],
            'education' => ['array'],
            'workExperience' => ['array'],
            'vacancyId' => ['nullable', 'uuid'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateWorkLocationCity($v);
            $this->validateCityLocationPosition($v);
        });
    }

    /** Lokasi kerja wajib sekota dengan posisi yang dilamar. */
    private function validateWorkLocationCity(Validator $validator): void
    {
        $position = $this->input('additionalInfo.positionApplied');
        $location = $this->input('additionalInfo.workLocation');

        if (! CityPrefix::mismatch($position, $location)) {
            return;
        }

        $validator->errors()->add(
            'additionalInfo.workLocation',
            sprintf(
                'Lokasi kerja harus berada di kota yang sama dengan posisi yang dilamar (%s).',
                CityPrefix::of($position)
            )
        );
    }

    /**
     * Form bertingkat /apply mengirim `additionalInfo.city`: lokasi harus milik
     * kota itu dan posisi harus ter-mapping ke lokasinya. Hanya diperiksa bila
     * ketiganya terdaftar di master data, dan dilewati untuk lamaran dari kartu
     * lowongan (posisi lowongan bisa di luar mapping).
     */
    private function validateCityLocationPosition(Validator $validator): void
    {
        $cityName = $this->input('additionalInfo.city');

        if (! is_string($cityName) || $cityName === '' || $this->filled('vacancyId')) {
            return;
        }

        $city = $this->findMaster(MasterDataType::CITY, $cityName);
        $location = $this->findMaster(MasterDataType::LOCATION, $this->input('additionalInfo.workLocation'));
        $position = $this->findMaster(MasterDataType::POSITION, $this->input('additionalInfo.positionApplied'));

        if (! $city || ! $location || ! $position) {
            return;
        }

        if ($location->parent_id !== $city->id) {
            $validator->errors()->add(
                'additionalInfo.workLocation',
                sprintf('Lokasi kerja tidak berada di kota %s.', $city->name)
            );

            return;
        }

        if (! $location->positions()->whereKey($position->id)->exists()) {
            $validator->errors()->add(
                'additionalInfo.positionApplied',
                'Posisi yang dilamar tidak tersedia di lokasi kerja yang dipilih.'
            );
        }
    }

    private function findMaster(MasterDataType $type, mixed $name): ?MasterData
    {
        return is_string($name) && $name !== ''
            ? MasterData::where('type', $type->value)->where('name', $name)->first()
            : null;
    }
}
