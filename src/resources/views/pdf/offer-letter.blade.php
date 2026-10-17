@php
    // LibreOffice (Writer/Web) mengabaikan sebagian CSS tabel, jadi border/warna
    // memakai atribut HTML (border, bgcolor, align) yang pasti dihormati.
    $rupiah = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
    $tanggal = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale('id')->translatedFormat('j F Y') : '-';
    $types = ['full-time' => 'Penuh waktu', 'part-time' => 'Paruh waktu', 'contract' => 'Kontrak', 'internship' => 'Magang'];
    $allowances = $offer->allowances ?? [];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Penawaran Kerja {{ $offer->number }}</title>
    <style>
        @page { size: A4; margin: 2cm; }
        body { font-family: Arial, sans-serif; font-size: 11pt; color: #222222; }
        h1 { font-size: 15pt; text-align: center; margin: 0 0 2pt; }
        p { line-height: 1.5; margin: 0 0 8pt; }
        .muted { color: #666666; font-size: 9pt; }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td valign="top">
                <b style="font-size:13pt">{{ $company['name'] }}</b><br>
                @if ($company['address'])<span class="muted">{{ $company['address'] }}</span><br>@endif
                @if ($company['phone'] || $company['email'])
                    <span class="muted">{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</span>
                @endif
            </td>
            <td valign="top" align="right" class="muted">
                No: {{ $offer->number }}<br>
                {{ $tanggal($offer->sent_at ?? now()) }}
            </td>
        </tr>
    </table>
    <hr>

    <h1>SURAT PENAWARAN KERJA</h1>
    <p align="center" class="muted">Offering Letter</p>

    <p>Kepada Yth.<br><b>{{ $offer->candidateName() }}</b></p>

    <p>
        Dengan hormat,<br>
        Berdasarkan hasil proses seleksi yang telah Anda ikuti, dengan senang hati kami menawarkan
        kepada Anda posisi di {{ $company['name'] }} dengan ketentuan sebagai berikut:
    </p>

    <table width="100%" border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse">
        <tr><td width="38%" bgcolor="#F2F4F8"><b>Posisi</b></td><td>{{ $offer->position }}</td></tr>
        @if ($offer->department)
            <tr><td bgcolor="#F2F4F8"><b>Departemen</b></td><td>{{ $offer->department }}</td></tr>
        @endif
        @if ($offer->location)
            <tr><td bgcolor="#F2F4F8"><b>Lokasi Kerja</b></td><td>{{ $offer->location }}</td></tr>
        @endif
        <tr><td bgcolor="#F2F4F8"><b>Status Kepegawaian</b></td><td>{{ $types[$offer->employment_type] ?? $offer->employment_type }}</td></tr>
        <tr><td bgcolor="#F2F4F8"><b>Tanggal Mulai Bekerja</b></td><td>{{ $tanggal($offer->start_date) }}</td></tr>
        <tr><td bgcolor="#F2F4F8"><b>Gaji Pokok</b></td><td>{{ $rupiah($offer->base_salary) }} / bulan</td></tr>
        @foreach ($allowances as $allowance)
            <tr><td bgcolor="#F2F4F8"><b>{{ $allowance['name'] }}</b></td><td>{{ $rupiah($allowance['amount']) }} / bulan</td></tr>
        @endforeach
        @if (count($allowances) > 0)
            <tr><td bgcolor="#F2F4F8"><b>Total Penghasilan</b></td><td><b>{{ $rupiah($offer->base_salary + $offer->totalAllowances()) }} / bulan</b></td></tr>
        @endif
    </table>

    @if ($offer->terms)
        <p style="margin-top:10pt"><b>Ketentuan lainnya:</b></p>
        <p>{!! nl2br(e($offer->terms)) !!}</p>
    @endif

    <p style="margin-top:10pt">
        Penawaran ini berlaku sampai dengan <b>{{ $tanggal($offer->expires_at) }}</b>. Silakan sampaikan
        keputusan Anda melalui tautan yang kami kirimkan lewat email sebelum tanggal tersebut.
    </p>

    <p>Kami berharap dapat segera menyambut Anda sebagai bagian dari tim kami.</p>

    <p style="margin-top:28pt">
        Hormat kami,<br><br><br><br>
        <b>Tim Human Resources</b><br>
        {{ $company['name'] }}
    </p>
</body>
</html>
