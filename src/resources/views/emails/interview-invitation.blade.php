<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Interview</title>
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
            <h1>{{ config('app.name') }}</h1>
        </div>

        <div class="body">
            <p>Halo, <strong>{{ $recipientName }}</strong>,</p>

            @if ($audience === 'interviewer')
                <p>
                    Anda dijadwalkan sebagai pewawancara (interviewer) untuk kandidat berikut.
                    Mohon menyiapkan diri sesuai jadwal di bawah ini.
                </p>
            @else
                <p>
                    Selamat! Tim HR kami mengundang Anda untuk mengikuti sesi interview sebagai bagian
                    dari proses seleksi. Berikut detail jadwal yang telah ditetapkan.
                </p>
            @endif

            <div class="info-box">
                @if ($audience === 'interviewer')
                    <p class="label">Kandidat</p>
                    <p class="value">{{ $interview->applicant_name }}</p>
                @endif

                <p class="label" @if ($audience === 'interviewer') style="margin-top:12px;" @endif>Posisi</p>
                <p class="value">{{ $interview->position ?: '-' }}</p>

                <p class="label" style="margin-top:12px;">Tanggal</p>
                <p class="value">{{ \Illuminate\Support\Carbon::parse($interview->date)->translatedFormat('l, d F Y') }}</p>

                <p class="label" style="margin-top:12px;">Waktu</p>
                <p class="value">{{ $interview->time }} WIB &middot; {{ $interview->duration ?: '60 min' }}</p>

                <p class="label" style="margin-top:12px;">Jenis Interview</p>
                <p class="value">
                    @switch($interview->type)
                        @case('online') Online @break
                        @case('offline') Offline (Tatap Muka) @break
                        @case('technical_test') Technical Test @break
                        @default {{ $interview->type }}
                    @endswitch
                </p>

                @if (!empty($interview->room))
                    <p class="label" style="margin-top:12px;">
                        {{ $interview->type === 'online' ? 'Link / Platform' : 'Lokasi / Ruangan' }}
                    </p>
                    <p class="value">{{ $interview->room }}</p>
                @endif
            </div>

            @if ($audience === 'interviewer')
                <p>Mohon konfirmasi ketersediaan Anda kepada tim HR bila berhalangan hadir.</p>
            @else
                <p>
                    Mohon hadir tepat waktu. Jika Anda berhalangan pada jadwal tersebut,
                    silakan hubungi tim HR kami untuk penjadwalan ulang.
                </p>
            @endif

            <p>Salam,<br><strong>Tim HR {{ config('app.name') }}</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Semua hak dilindungi.<br>
            Email ini dikirim secara otomatis, mohon tidak membalas pesan ini.
        </div>
    </div>
</body>
</html>
