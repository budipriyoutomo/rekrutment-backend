<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f7; margin: 0; padding: 0; color: #333333; }
        .wrapper { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: #1a56db; padding: 32px 40px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; }
        .body { padding: 40px; }
        .body p { font-size: 15px; line-height: 1.7; margin: 0 0 16px; }
        .info-box { background-color: #f0f4ff; border-left: 4px solid #1a56db; border-radius: 4px; padding: 16px 20px; margin: 24px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; }
        .info-box .label { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-box .value { font-weight: 600; color: #111827; }
        .footer { background-color: #f9fafb; border-top: 1px solid #e5e7eb; padding: 24px 40px; text-align: center; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $companyName }}</h1>
        </div>

        <div class="body">
            <p>Halo, <strong>{{ $employee->full_name }}</strong>,</p>

            <p>Selamat datang di {{ $companyName }}! Kami sangat menantikan hari pertama Anda besok.</p>

            <div class="info-box">
                <p class="label">Hari Pertama</p>
                <p class="value">{{ $startDate }}</p>

                <p class="label" style="margin-top:12px;">Posisi</p>
                <p class="value">{{ $employee->position }}</p>

                @if ($employee->location)
                    <p class="label" style="margin-top:12px;">Lokasi</p>
                    <p class="value">{{ $employee->location }}</p>
                @endif

                <p class="label" style="margin-top:12px;">NIK Karyawan</p>
                <p class="value">{{ $employee->employee_number }}</p>
            </div>

            <p>Mohon bawa kartu identitas asli. Tim kami akan menyambut dan mendampingi Anda selama masa orientasi.</p>

            @if ($hrEmail || $hrPhone)
                <p>Bila ada pertanyaan, hubungi tim HR di {{ collect([$hrEmail, $hrPhone])->filter()->implode(' / ') }}.</p>
            @endif

            <p style="margin-top:24px;">Sampai jumpa besok!<br><strong>Tim HR {{ $companyName }}</strong></p>
        </div>

        <div class="footer">
            Email ini dikirim otomatis oleh sistem {{ $companyName }}.
        </div>
    </div>
</body>
</html>
