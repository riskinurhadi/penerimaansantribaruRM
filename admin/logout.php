<?php
session_start();

// Hapus semua data session
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keluar - PSB RM</title>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #f4f9f6; 
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body>
    <script>
        // Munculkan animasi SweetAlert2 sebelum mengarahkan kembali ke login.php
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Keluar',
            text: 'Anda telah keluar dari sistem Administrator.',
            showConfirmButton: false,
            timer: 1500,
            allowOutsideClick: false
        }).then(() => {
            window.location.href = 'login.php';
        });
    </script>
</body>
</html>