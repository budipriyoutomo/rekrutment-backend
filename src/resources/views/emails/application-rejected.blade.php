<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informasi Hasil Seleksi</title>
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
            <h1>{{ $companyName }}</h1>
        </div>

        <div class="body">
            <p>Halo, <strong>{{ $name }}</strong>,</p>

            <p>
                Terima kasih atas minat dan waktu yang telah Anda berikan selama proses seleksi
                untuk posisi <strong>{{ $position }}</strong> di {{ $companyName }}.
            </p>

            <p>
                Setelah melalui pertimbangan yang cermat, dengan berat hati kami sampaikan bahwa
                kami belum dapat melanjutkan lamaran Anda ke tahap berikutnya untuk saat ini.
            </p>

            <p>
                Keputusan ini tidak mengurangi penghargaan kami atas kualifikasi dan pengalaman Anda.
                Data Anda akan kami simpan, dan kami akan senang bila Anda kembali melamar pada
                kesempatan lain yang sesuai.
            </p>

            <p>Semoga sukses dalam langkah karier Anda berikutnya.</p>

            <p style="margin-top:24px;">
                Hormat kami,<br>
                <strong>Tim Rekrutmen {{ $companyName }}</strong>
            </p>
        </div>

        <div class="footer">
            Email ini dikirim otomatis oleh sistem rekrutmen {{ $companyName }}. Mohon tidak membalas email ini.
        </div>
    </div>
</body>
</html>
