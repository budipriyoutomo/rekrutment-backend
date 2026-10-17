<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Penawaran Kerja</title>
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
        .btn-wrapper { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; background-color: #1a56db; color: #ffffff !important; text-decoration: none; padding: 14px 36px; border-radius: 6px; font-size: 15px; font-weight: 600; }
        .url-fallback { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px 16px; word-break: break-all; font-size: 13px; color: #4b5563; margin-top: 8px; }
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

            <p>
                Selamat! Dengan senang hati kami menyampaikan penawaran kerja untuk Anda.
                Surat penawaran lengkap terlampir dalam email ini (PDF).
            </p>

            <div class="info-box">
                <p class="label">Posisi</p>
                <p class="value">{{ $offer->position }}</p>

                <p class="label" style="margin-top:12px;">Tanggal Mulai</p>
                <p class="value">{{ $offer->start_date->locale('id')->translatedFormat('j F Y') }}</p>

                <p class="label" style="margin-top:12px;">Berlaku Sampai</p>
                <p class="value">{{ $offer->expires_at->locale('id')->translatedFormat('j F Y') }}</p>
            </div>

            <p>Silakan tinjau detail penawaran dan sampaikan keputusan Anda melalui tombol berikut:</p>

            <div class="btn-wrapper">
                <a href="{{ $offerUrl }}" class="btn">Lihat &amp; Respon Penawaran</a>
            </div>

            <p style="margin-top:24px;">Jika tombol di atas tidak berfungsi, salin tautan berikut ke browser Anda:</p>
            <div class="url-fallback">{{ $offerUrl }}</div>
        </div>

        <div class="footer">
            Email ini dikirim otomatis oleh sistem rekrutmen {{ $companyName }}. Link bersifat personal, mohon tidak dibagikan.
        </div>
    </div>
</body>
</html>
