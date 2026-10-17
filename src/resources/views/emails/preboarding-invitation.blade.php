<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Karyawan Baru</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f7; margin: 0; padding: 0; color: #333333; }
        .wrapper { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: #1a56db; padding: 32px 40px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; }
        .body { padding: 40px; }
        .body p { font-size: 15px; line-height: 1.7; margin: 0 0 16px; }
        .info-box { background-color: #f0f4ff; border-left: 4px solid #1a56db; border-radius: 4px; padding: 16px 20px; margin: 24px 0; font-size: 14px; }
        .warn-box { background-color: #fff7ed; border-left: 4px solid #ea580c; border-radius: 4px; padding: 16px 20px; margin: 24px 0; font-size: 14px; }
        .info-box ul, .warn-box ul { margin: 8px 0 0; padding-left: 20px; }
        .btn-wrapper { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; background-color: #1a56db; color: #ffffff !important; text-decoration: none; padding: 14px 36px; border-radius: 6px; font-size: 15px; font-weight: 600; }
        .url-fallback { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px 16px; word-break: break-all; font-size: 13px; color: #4b5563; margin-top: 8px; }
        .note { font-size: 13px; color: #6b7280; text-align: center; }
        .footer { background-color: #f9fafb; border-top: 1px solid #e5e7eb; padding: 24px 40px; text-align: center; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $companyName }}</h1>
        </div>

        <div class="body">
            <p>Halo, <strong>{{ $name }}</strong>,</p>

            @if (count($rejected) > 0)
                <p>Terima kasih telah melengkapi data karyawan baru. Setelah kami tinjau, beberapa dokumen perlu diunggah ulang:</p>

                <div class="warn-box">
                    <ul>
                        @foreach ($rejected as $doc)
                            <li><strong>{{ $doc['label'] }}</strong>@if ($doc['note']) — {{ $doc['note'] }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p>
                    Selamat bergabung{{ $position ? ' sebagai ' . $position : '' }} di {{ $companyName }}!
                    Sebelum hari pertama, mohon lengkapi data karyawan baru Anda melalui tautan di bawah.
                </p>

                <div class="info-box">
                    <strong>Siapkan:</strong>
                    <ul>
                        <li>Data rekening bank untuk pembayaran gaji</li>
                        <li>Nomor NPWP dan BPJS (bila sudah punya)</li>
                        <li>Kontak darurat</li>
                        <li>Foto/scan dokumen: {{ implode(', ', $documents) }}</li>
                    </ul>
                </div>
            @endif

            <div class="btn-wrapper">
                <a href="{{ $url }}" class="btn">{{ count($rejected) > 0 ? 'Unggah Ulang Dokumen' : 'Lengkapi Data Sekarang' }}</a>
            </div>

            <p class="note">Link bersifat personal dan berlaku sampai {{ $expiresAt }}.</p>

            <p style="margin-top:24px;">Jika tombol di atas tidak berfungsi, salin tautan berikut ke browser Anda:</p>
            <div class="url-fallback">{{ $url }}</div>
        </div>

        <div class="footer">
            Email ini dikirim otomatis oleh sistem rekrutmen {{ $companyName }}. Mohon tidak membagikan link ini.
        </div>
    </div>
</body>
</html>
