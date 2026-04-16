<?php

namespace App\Domains\Application\DTO;

use App\Core\Http\Requests\BaseRequest;

class ApplicationDTO
{
    public function __construct(
        public readonly array $personalInfo,
        public readonly array $contactInfo,
        public readonly array $education,
        public readonly array $certifications,
        public readonly array $workExperience,
        public readonly array $parentInfo,
        public readonly array $spouseInfo,
        public readonly array $additionalInfo,
    ) {}

    public static function fromRequest(BaseRequest $request): self
    {
        return new self(
            personalInfo: $request->input('personalInfo'),
            contactInfo: $request->input('contactInfo'),
            education: $request->input('education', []),
            certifications: $request->input('certifications', []),
            workExperience: $request->input('workExperience', []),
            parentInfo: $request->input('parentInfo'),
            spouseInfo: $request->input('spouseInfo'),
            additionalInfo: $request->input('additionalInfo'),
        );
    }

    public function toArray(): array
    {
        return [
            'personal_info' => $this->personalInfo,
            'contact_info' => $this->contactInfo,
            'parent_info' => $this->parentInfo,
            'spouse_info' => $this->spouseInfo,
            'additional_info' => $this->additionalInfo,
            'status' => 'submitted',
        ];
    }
}