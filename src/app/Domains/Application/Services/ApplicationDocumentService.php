<?php

namespace App\Domains\Application\Services;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class ApplicationDocumentService
{
    public function generateApplicationDocx(object $application, string $savePath): void
    {
        // Pastikan JSON di-decode menjadi array (jika belum otomatis di-cast oleh Model)
        $personal = is_string($application->personal_info) ? json_decode($application->personal_info, true) : (array) $application->personal_info;
        $contact = is_string($application->contact_info) ? json_decode($application->contact_info, true) : (array) $application->contact_info;
        $parent = is_string($application->parent_info) ? json_decode($application->parent_info, true) : (array) $application->parent_info;
        $spouse = is_string($application->spouse_info) ? json_decode($application->spouse_info, true) : (array) $application->spouse_info;
        $additional = is_string($application->additional_info) ? json_decode($application->additional_info, true) : (array) $application->additional_info;
        $documents = is_string($application->documents) ? json_decode($application->documents, true) : (array) ($application->documents ?? []);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        // Setup halaman (Margin standard)
        $section = $phpWord->addSection([
            'marginTop' => 1200, 'marginBottom' => 1200, 'marginLeft' => 1200, 'marginRight' => 1200
        ]);

        // Style untuk Judul Section
        $sectionTitleStyle = ['bold' => true, 'size' => 11, 'color' => '1F4E79'];
        $tableStyle = ['borderSize' => 6, 'borderColor' => 'D3D3D3', 'cellMargin' => 80];
        $phpWord->addTableStyle('MainTable', $tableStyle);

        // =========================================================================
        // JUDUL UTAMA
        // =========================================================================
        $section->addText("BIODATA PELAMAR (APPLICATION FORM)", ['bold' => true, 'size' => 14], ['alignment' => 'center', 'spaceAfter' => 200]);
        $section->addText("ID Pelamar: " . $application->id, ['italic' => true, 'size' => 9], ['spaceAfter' => 300]);

        // =========================================================================
        // 1. PERSONAL INFO
        // =========================================================================
        $section->addText("1. INFORMASI PRIBADI", $sectionTitleStyle, ['spaceAfter' => 60]);
        $table = $section->addTable('MainTable');
        $this->addRow($table, "Nama Lengkap", $personal['fullName'] ?? '-');
        $this->addRow($table, "Jenis Kelamin", ($personal['gender'] ?? '-') === 'L' ? 'Laki-Laki' : 'Perempuan');
        $this->addRow($table, "Agama", $personal['religion'] ?? '-');
        $this->addRow($table, "Tempat, Tanggal Lahir", ($personal['placeOfBirth'] ?? '-') . ', ' . ($personal['dateOfBirth'] ?? '-'));
        $this->addRow($table, "Status Pernikahan", $personal['maritalStatus'] ?? '-');
        $this->addRow($table, "No. KTP / NIK", $personal['idNumber'] ?? '-');
        $this->addRow($table, "No. Kartu Keluarga", $personal['familyCardNumber'] ?? '-');
        $this->addRow($table, "NPWP", $personal['npwp'] ?? '-');

        $section->addTextBreak(1);

        // =========================================================================
        // 2. CONTACT INFO
        // =========================================================================
        $section->addText("2. KONTAK DAN ALAMAT", $sectionTitleStyle, ['spaceAfter' => 60]);
        $table = $section->addTable('MainTable');
        $this->addRow($table, "Email", $contact['email'] ?? '-');
        $this->addRow($table, "No. Handphone", $contact['phone'] ?? '-');
        $this->addRow($table, "Media Sosial", $contact['socialMedia'] ?? '-');
        $this->addRow($table, "Alamat Rumah (KTP)", $contact['homeAddress'] ?? '-');
        $this->addRow($table, "Kode Pos", $contact['postalCode'] ?? '-');

        $section->addTextBreak(1);

        // =========================================================================
        // 3. PARENT INFO
        // =========================================================================
        $section->addText("3. DATA ORANG TUA / KELUARGA", $sectionTitleStyle, ['spaceAfter' => 60]);
        $table = $section->addTable('MainTable');
        $this->addRow($table, "Nama Ayah / Ibu", ($parent['fatherName'] ?? '-') . ' / ' . ($parent['motherName'] ?? '-'));
        $this->addRow($table, "Pekerjaan Ayah / Ibu", ($parent['fatherJob'] ?? '-') . ' / ' . ($parent['motherJob'] ?? '-'));
        $this->addRow($table, "Anak Ke / Dari", ($parent['childOrder'] ?? '-') . ' dari ' . ($parent['numberOfSiblings'] ?? '-') . ' bersaudara');
        $this->addRow($table, "Status Orang Tua", $parent['parentStatus'] ?? '-');

        // Jika statusnya menikah (punya pasangan), tampilkan data spouse
        if (($personal['maritalStatus'] ?? '') !== 'lajang') {
            $this->addRow($table, "Nama Suami/Istri", $spouse['spouseName'] ?? '-');
            $this->addRow($table, "Pekerjaan Pasangan", $spouse['spouseJob'] ?? '-');
        }

        $section->addTextBreak(1);

        // =========================================================================
        // 4. ADDITIONAL INFO
        // =========================================================================
        $section->addText("4. INFORMASI TAMBAHAN & KUALIFIKASI", $sectionTitleStyle, ['spaceAfter' => 60]);
        $table = $section->addTable('MainTable');
        $this->addRow($table, "Posisi Yang Dilamar", $additional['positionApplied'] ?? '-');
        $this->addRow($table, "Ekspektasi Gaji", "Rp " . number_format((int)($additional['expectedSalary'] ?? 0), 0, ',', '.'));
        $this->addRow($table, "Tanggal Siap Tersedia", $additional['availableDate'] ?? '-');
        $this->addRow($table, "Fresh Graduate", strtoupper($additional['freshGraduate'] ?? 'tidak'));
        $this->addRow($table, "Pernah Bekerja di Sini?", strtoupper($additional['workedAtCompany'] ?? 'tidak'));
        $this->addRow($table, "Memiliki Kendaraan?", strtoupper($additional['hasVehicle'] ?? 'tidak'));

        $this->addEducationSection($section, $application, $sectionTitleStyle);
        $this->addExperienceSection($section, $application, $sectionTitleStyle);
        $this->addCertificationSection($section, $application, $sectionTitleStyle);
        $this->addDocumentChecklist($section, $documents, $sectionTitleStyle);

        // Target Output file .docx
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($savePath);
    }

    /**
     * Helper baris tabel dua kolom
     */
    private function addRow($table, string $label, string $value): void
    {
        $table->addRow();
        $table->addCell(2500, ['bgColor' => 'F9F9F9'])->addText($label, ['bold' => true, 'size' => 9]);
        $table->addCell(5500)->addText($value, ['size' => 9]);
    }

    private function addEducationSection($section, object $application, array $sectionTitleStyle): void
    {
        $educations = $this->readRelation($application, 'educations');

        $section->addTextBreak(1);
        $section->addText('5. RIWAYAT PENDIDIKAN', $sectionTitleStyle, ['spaceAfter' => 60]);

        if ($educations === []) {
            $section->addText('-', ['size' => 9]);
            return;
        }

        $table = $section->addTable('MainTable');
        foreach ($educations as $education) {
            $this->addRow(
                $table,
                (string) ($education['level'] ?? 'Pendidikan'),
                trim($this->value($education, 'schoolName', 'school_name') . ' - ' . $this->value($education, 'major') . ' (' . $this->value($education, 'yearStart', 'year_start') . ' - ' . $this->value($education, 'yearEnd', 'year_end') . ')')
            );
        }
    }

    private function addExperienceSection($section, object $application, array $sectionTitleStyle): void
    {
        $experiences = $this->readRelation($application, 'experiences');

        $section->addTextBreak(1);
        $section->addText('6. PENGALAMAN KERJA', $sectionTitleStyle, ['spaceAfter' => 60]);

        if ($experiences === []) {
            $section->addText('-', ['size' => 9]);
            return;
        }

        $table = $section->addTable('MainTable');
        foreach ($experiences as $experience) {
            $this->addRow(
                $table,
                $this->value($experience, 'companyName', 'company_name', default: 'Perusahaan'),
                trim($this->value($experience, 'jobPosition', 'job_position') . ' (' . $this->value($experience, 'yearStart', 'year_start') . ' - ' . $this->value($experience, 'yearEnd', 'year_end') . ') - ' . $this->value($experience, 'jobDescription', 'job_description'))
            );
        }
    }

    private function addCertificationSection($section, object $application, array $sectionTitleStyle): void
    {
        $certifications = $this->readRelation($application, 'certifications');

        $section->addTextBreak(1);
        $section->addText('7. SERTIFIKASI', $sectionTitleStyle, ['spaceAfter' => 60]);

        if ($certifications === []) {
            $section->addText('-', ['size' => 9]);
            return;
        }

        $table = $section->addTable('MainTable');
        foreach ($certifications as $certification) {
            $this->addRow(
                $table,
                $this->value($certification, 'courseName', 'course_name', default: 'Sertifikasi'),
                trim($this->value($certification, 'organization') . ' - ' . $this->value($certification, 'year') . ' (' . $this->value($certification, 'duration') . ')')
            );
        }
    }

    private function addDocumentChecklist($section, array $documents, array $sectionTitleStyle): void
    {
        $labels = [
            'cv' => 'CV / Resume',
            'foto' => 'Foto Diri',
            'ktp' => 'Foto KTP',
            'ijazah' => 'Scan Ijazah',
        ];

        $section->addTextBreak(1);
        $section->addText('8. DOKUMEN TERLAMPIR', $sectionTitleStyle, ['spaceAfter' => 60]);

        $table = $section->addTable('MainTable');
        foreach ($labels as $key => $label) {
            $document = $documents[$key] ?? null;
            $fileName = is_array($document)
                ? ($document['file_name'] ?? $document['path'] ?? 'Ada')
                : ($document ?: '-');

            $this->addRow($table, $label, $fileName ? (string) $fileName : '-');
        }
    }

    private function readRelation(object $application, string $relation): array
    {
        if (!method_exists($application, 'relationLoaded') || !$application->relationLoaded($relation)) {
            return [];
        }

        return $application->{$relation}
            ->map(fn ($item) => $item->toArray())
            ->all();
    }

    private function value(array $data, string $key, ?string $fallbackKey = null, string $default = '-'): string
    {
        $value = $data[$key] ?? ($fallbackKey ? ($data[$fallbackKey] ?? null) : null);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_array($value)) {
            return implode(', ', array_filter($value));
        }

        return (string) $value;
    }
}
