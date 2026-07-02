<?php
session_start();

// Panggil config.php yang ada di folder luar (root)
require_once '../config.php';

// Jika admin sudah login, langsung arahkan ke dashboard (index.php)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    // Cari user berdasarkan username
    $query = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verifikasi password (Mendukung Bcrypt standar atau MD5 untuk pertama kali eksekusi SQL)
        if (password_verify($password, $user['password']) || md5($password) === $user['password']) {
            
            // Jika password masih MD5 (baru di-insert dari SQL), otomatis Upgrade ke Bcrypt agar lebih aman
            if (md5($password) === $user['password']) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password = '$new_hash' WHERE id = " . $user['id']);
            }

            // Set Session
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

            // Arahkan ke dashboard
            header("Location: index.php");
            exit;
        } else {
            $error_message = "Password yang Anda masukkan salah!";
        }
    } else {
        $error_message = "Username tidak ditemukan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - PSB Raudlatul Mutaallimin</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --primary-green: #0da15b; 
            --dark-green: #087d46;
            --light-green: #eafbf3;
            --text-dark: #2d3748;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-green);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(13, 161, 91, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
        }

        .login-left {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            padding: 50px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 50%;
        }

        .login-right {
            padding: 50px;
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(13, 161, 91, 0.25);
        }

        .btn-login {
            background-color: var(--primary-green);
            color: white;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
            border: none;
        }

        .btn-login:hover {
            background-color: var(--dark-green);
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                max-width: 400px;
                margin: 20px;
            }
            .login-left, .login-right {
                width: 100%;
                padding: 40px 30px;
            }
            .login-left {
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <!-- Bagian Kiri (Branding) -->
        <div class="login-left d-none d-md-flex">
            <div class="mb-4">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <h2 class="fw-bold">SAMARA</h2>
                <p class="mb-0" style="opacity: 0.9;">Santri Administration and Management of Raudlatul Mutaallimin</p>
            </div>
            <div class="mt-auto">
                <p class="mb-0" style="font-size: 0.85rem; opacity: 0.8;">&copy; <?= date('Y') ?> Pondok Pesantren Raudlatul Mutaallimin</p>
            </div>
        </div>

        <!-- Bagian Kanan (Form) -->
        <div class="login-right">
            <div class="text-center text-md-start mb-4">
                <h4 class="fw-bold text-dark">Selamat Datang</h4>
                <p class="text-muted" style="font-size: 0.9rem;">Silakan masuk ke akun Admin Anda.</p>
            </div>

            <form action="" method="POST" id="loginForm">
                <div class="mb-3">
                    <label class="form-label text-dark fw-medium" style="font-size: 0.9rem;">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" name="username" placeholder="Masukkan username" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-dark fw-medium" style="font-size: 0.9rem;">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" class="form-control border-start-0 ps-0 border-end-0" name="password" id="password" placeholder="Masukkan password" required>
                        <span class="input-group-text bg-transparent cursor-pointer" id="togglePassword" style="cursor: pointer;">
                            <i class="fas fa-eye-slash text-muted"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Masuk Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap & SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Fitur Tampilkan/Sembunyikan Password
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Tampilkan Error SweetAlert2 jika login gagal
        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Gagal!',
                text: '<?= $error_message ?>',
                confirmButtonColor: '#0da15b'
            });
        <?php endif; ?>
    </script>
</body>
</html>