<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Profil Lamaran</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f7;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header {
            background-color: #1a56db;
            padding: 32px 40px;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }
        .body {
            padding: 40px;
        }
        .body p {
            font-size: 15px;
            line-height: 1.7;
            margin: 0 0 16px;
        }
        .info-box {
            background-color: #f0f4ff;
            border-left: 4px solid #1a56db;
            border-radius: 4px;
            padding: 16px 20px;
            margin: 24px 0;
        }
        .info-box p {
            margin: 4px 0;
            font-size: 14px;
        }
        .info-box .label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-box .value {
            font-weight: 600;
            color: #111827;
        }
        .btn-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #1a56db;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
        }
        .url-fallback {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 12px 16px;
            word-break: break-all;
            font-size: 13px;
            color: #4b5563;
            margin-top: 8px;
        }
        .expiry-note {
            font-size: 13px;
            color: #6b7280;
            text-align: center;
            margin-top: -8px;
        }
        .footer {
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 24px 40px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>

        <div class="body">
            <p>Halo, <strong>{{ $application->personal_info['fullName'] ?? 'Pelamar' }}</strong>,</p>

            <p>
                Terima kasih telah melamar posisi di perusahaan kami. Tim HR kami telah meninjau lamaran Anda
                dan ingin mengundang Anda untuk melengkapi profil lamaran Anda agar proses seleksi dapat dilanjutkan.
            </p>

            <div class="info-box">
                <p class="label">Posisi yang Dilamar</p>
                <p class="value">{{ $application->additional_info['positionApplied'] ?? '-' }}</p>

                <p class="label" style="margin-top:12px;">Email Terdaftar</p>
                <p class="value">{{ $application->contact_info['email'] ?? '-' }}</p>
            </div>

            <p>
                Silakan klik tombol di bawah ini untuk melengkapi profil Anda. Link ini bersifat personal
                dan hanya dapat digunakan oleh Anda.
            </p>

            <div class="btn-wrapper">
                <a href="{{ $completionUrl }}" class="btn">Lengkapi Profil Sekarang</a>
            </div>

            <p class="expiry-note">
                Link berlaku selama <strong>7 hari</strong> sejak email ini dikirim.<br>
                Kedaluwarsa pada: <strong>{{ now()->addDays(7)->format('d M Y, H:i') }} WIB</strong>
            </p>

            <p style="margin-top: 24px;">
                Jika tombol di atas tidak berfungsi, salin dan tempelkan URL berikut ke browser Anda:
            </p>
            <div class="url-fallback">{{ $completionUrl }}</div>

            <p style="margin-top: 28px;">
                Jika Anda merasa tidak pernah melamar atau mendapat email ini karena kesalahan,
                abaikan saja pesan ini.
            </p>

            <p>Salam,<br><strong>Tim HR {{ config('app.name') }}</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Semua hak dilindungi.<br>
            Email ini dikirim secara otomatis, mohon tidak membalas pesan ini.
        </div>
    </div>
</body>
</html>
