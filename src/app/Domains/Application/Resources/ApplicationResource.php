<?php

namespace App\Domains\Application\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // =========================
            // BASIC
            // =========================
            'id' => $this->id,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,

            // =========================
            // PERSONAL INFO
            // =========================
            'personalInfo' => [
                'fullName' => $this->personal_info['fullName'] ?? null,
                'gender' => $this->personal_info['gender'] ?? null,
                'religion' => $this->personal_info['religion'] ?? null,
                'placeOfBirth' => $this->personal_info['placeOfBirth'] ?? null,
                'dateOfBirth' => $this->personal_info['dateOfBirth'] ?? null,
                'idNumber' => $this->personal_info['idNumber'] ?? null,
                'familyCardNumber' => $this->personal_info['familyCardNumber'] ?? null,
                'npwp' => $this->personal_info['npwp'] ?? null,
                'maritalStatus' => $this->personal_info['maritalStatus'] ?? null,
                'nationality' => $this->personal_info['nationality'] ?? null,
            ],

            // =========================
            // CONTACT INFO
            // =========================
            'contactInfo' => [
                'email' => $this->contact_info['email'] ?? null,
                'phone' => $this->contact_info['phone'] ?? null,
                'socialMedia' => $this->contact_info['socialMedia'] ?? null,
                'postalCode' => $this->contact_info['postalCode'] ?? null,
                'homeAddress' => $this->contact_info['homeAddress'] ?? null,
                'currentAddress' => $this->contact_info['currentAddress'] ?? null,
            ],

            // =========================
            // FAMILY
            // =========================
            'parentInfo' => [
                'fatherName' => $this->parent_info['fatherName'] ?? null,
                'fatherJob' => $this->parent_info['fatherJob'] ?? null,
                'motherName' => $this->parent_info['motherName'] ?? null,
                'motherJob' => $this->parent_info['motherJob'] ?? null,
                'numberOfSiblings' => $this->parent_info['numberOfSiblings'] ?? null,
                'childOrder' => $this->parent_info['childOrder'] ?? null,
            ],

            'spouseInfo' => [
                'spouseName' => $this->spouse_info['spouseName'] ?? null,
                'spouseJob' => $this->spouse_info['spouseJob'] ?? null,
                'numberOfChildren' => $this->spouse_info['numberOfChildren'] ?? null,
            ],

            // =========================
            // ADDITIONAL
            // =========================
            'additionalInfo' => [
                'freshGraduate' => $this->additional_info['freshGraduate'] ?? null,
                'medicalHistory' => $this->additional_info['medicalHistory'] ?? null,
                'workedAtCompany' => $this->additional_info['workedAtCompany'] ?? null,
                'hasVehicle' => $this->additional_info['hasVehicle'] ?? null,
                'expectedSalary' => $this->additional_info['expectedSalary'] ?? null,
                'positionApplied' => $this->additional_info['positionApplied'] ?? null,
                'availableDate' => $this->additional_info['availableDate'] ?? null,
            ],

            // =========================
            // DOCUMENTS
            // =========================
            'documents' => [
                'cv' => $this->documents['cv'] ?? null,
                'foto' => $this->documents['foto'] ?? null,
                'ktp' => $this->documents['ktp'] ?? null,
                'ijazah' => $this->documents['ijazah'] ?? null,
            ],

            // =========================
            // RELATIONS
            // =========================
            'education' => ApplicationEducationResource::collection(
                $this->whenLoaded('educations')
            ),

            'workExperience' => ApplicationExperienceResource::collection(
                $this->whenLoaded('experiences')
            ),

            'certifications' => ApplicationCertificationResource::collection(
                $this->whenLoaded('certifications')
            ),
        ];
    }
}