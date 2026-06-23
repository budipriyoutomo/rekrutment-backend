<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji {{ $salarySlip->periode }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: #1a56db; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 4px 0 0; font-size: 14px; opacity: 0.85; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #333; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td { padding: 10px 12px; font-size: 14px; border-bottom: 1px solid #eee; }
        td:first-child { color: #666; width: 45%; }
        td:last-child { color: #111; font-weight: 500; }
        .thp-row td { font-size: 16px; font-weight: bold; color: #1a56db; border-top: 2px solid #1a56db; }
        .footer { padding: 20px 32px; background: #f9fafb; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ $salarySlip->perusahaan }}</h1>
        <p>Slip Gaji – {{ $salarySlip->periode }}</p>
    </div>
    <div class="body">
        <p class="greeting">Yth. <strong>{{ $salarySlip->nama }}</strong>,</p>
        <p style="font-size:14px;color:#555;">Berikut adalah rincian slip gaji Anda untuk periode <strong>{{ $salarySlip->periode }}</strong>.</p>
        <table>
            <tr><td>NIK</td><td>{{ $salarySlip->nik }}</td></tr>
            <tr><td>Nama</td><td>{{ $salarySlip->nama }}</td></tr>
            <tr><td>Jabatan</td><td>{{ $salarySlip->jabatan }}</td></tr>
            <tr><td>Cabang</td><td>{{ $salarySlip->cabang }}</td></tr>
            <tr><td>Periode</td><td>{{ $salarySlip->periode }}</td></tr>
            <tr class="thp-row">
                <td>Take Home Pay</td>
                <td>Rp {{ number_format($salarySlip->take_home_pay, 0, ',', '.') }}</td>
            </tr>
        </table>
        <p style="font-size:13px;color:#777;">Jika ada pertanyaan, silakan hubungi bagian HRD.</p>
    </div>
    <div class="footer">
        Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.
    </div>
</div>
</body>
</html>
