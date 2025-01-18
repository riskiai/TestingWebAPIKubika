<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Masuk</title>

    <style>
        body {
            background-color: grey;
        }
    </style>
</head>

<body>

    <div class="container">
        <header>
            <h1>PT KUBIKA</h1>
        </header>
        <section>
            <h3>Hallo, {{ $user->name }}</h3>
            <p><strong>Selamat <br> Password Akun Anda Telah Berhasil Diubah.</strong></p>
            <p><strong>Email : {{ $user->email }}</strong></p>
            <p><strong>Password Terbaru : {{ $user->passwordRecovery }}</strong></p>
            <p><Strong>Link Website : </Strong> <a href="https://kubikaexpo.id/login"
                    style="text-decoration: none; font-size:13px; font-weight:bold; color:blue;">kubikaexpo.id</a>
            </p>
            <p><strong>Terimakasih <br> Sudah menggunakan layanan kami.</strong></p>

            <h3>Hormat Kami <br><br> PT KUBIKA</h3>
        </section>
        <footer>
            <br>
            <p><strong>Terima kasih</strong></p>
        </footer>
    </div>

</body>

</html>
