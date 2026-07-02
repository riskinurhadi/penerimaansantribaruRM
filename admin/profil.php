<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';
$conn->set_charset("utf8mb4");

$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$role_saat_ini = $_SESSION['role'];

// =========================================================================
// AUTO-PATCH DATABASE: Buat tabel system_logs jika belum ada
// =========================================================================
$cek_tabel = $conn->query("SHOW TABLES LIKE 'system_logs'");
if ($cek_tabel && $cek_tabel->num_rows == 0) {
    $conn->query("CREATE TABLE system_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        nama_admin VARCHAR(100) NOT NULL,
        role_admin VARCHAR(50) NOT NULL,
        aksi VARCHAR(100) NOT NULL,
        deskripsi TEXT NOT NULL,
        status_baca ENUM('Unread', 'Read') DEFAULT 'Unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Cek dan tambah kolom email dan no_wa di tabel users
$check_col_email = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
if ($check_col_email && $check_col_email->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL AFTER username");
}
$check_col_wa = $conn->query("SHOW COLUMNS FROM users LIKE 'no_whatsapp'");
if ($check_col_wa && $check_col_wa->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN no_whatsapp VARCHAR(20) NULL AFTER email");
}

// Ambil data user saat ini untuk perbandingan log
$q_user = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user_data = $q_user->fetch_assoc();

$status_pesan = '';

// --- HANDLE POST REQUEST UNTUK UPDATE PROFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $email = $conn->real_escape_string($_POST['email']);
    $no_wa = $conn->real_escape_string($_POST['no_wa']);
    
    $perubahan = []; // Array untuk menampung riwayat perubahan
    
    if ($nama_lengkap != $user_data['nama_lengkap']) $perubahan[] = "Nama: " . $user_data['nama_lengkap'] . " -> " . $nama_lengkap;
    if ($email != $user_data['email']) $perubahan[] = "Email: " . ($user_data['email'] ?: 'Kosong') . " -> " . $email;
    if ($no_wa != $user_data['no_whatsapp']) $perubahan[] = "WA: " . ($user_data['no_whatsapp'] ?: 'Kosong') . " -> " . $no_wa;

    // Update data basic
    $update_query = "UPDATE users SET nama_lengkap = '$nama_lengkap', email = '$email', no_whatsapp = '$no_wa' WHERE id = $user_id";
    
    if ($conn->query($update_query)) {
        $_SESSION['nama_lengkap'] = $nama_lengkap; 
        $status_pesan = 'sukses_profil';
    } else {
        $status_pesan = 'gagal_profil';
    }

    // Handle upload foto profil
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_name = $_FILES['foto_profil']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $upload_dir = '../uploads/profil/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $new_file_name = "profil_" . $user_id . "_" . time() . "." . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            $db_path = "uploads/profil/" . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                if (!empty($user_data['foto_profil']) && file_exists("../" . $user_data['foto_profil'])) {
                    unlink("../" . $user_data['foto_profil']);
                }
                $conn->query("UPDATE users SET foto_profil = '$db_path' WHERE id = $user_id");
                $perubahan[] = "Memperbarui Foto Profil";
                $status_pesan = 'sukses_foto';
            }
        }
    }
    
    // Handle ganti password
    if(!empty($_POST['password_baru'])){
        $pass_asli = $_POST['password_baru'];
        $password_baru = password_hash($pass_asli, PASSWORD_DEFAULT);
        if($conn->query("UPDATE users SET password = '$password_baru' WHERE id = $user_id")){
             // PERBAIKAN: Mengamankan karakter HTML agar tidak rusak
             $safe_pass = htmlspecialchars($pass_asli, ENT_QUOTES, 'UTF-8');
             $perubahan[] = "GANTI PASSWORD. Password baru: <b class='text-danger'>" . $safe_pass . "</b>";
             $status_pesan = 'sukses_password';
        }
    }

    // CATAT LOG KE DATABASE JIKA ADA PERUBAHAN
    if (!empty($perubahan)) {
        try {
            $detail_log_string = implode("<br>", $perubahan);
            // PERBAIKAN: ESCAPE STRING AGAR SQL TIDAK ERROR SAAT ADA TANDA KUTIP DARI PASSWORD
            $detail_log = $conn->real_escape_string($detail_log_string);
            $nama_admin = $conn->real_escape_string($_SESSION['nama_lengkap']);
            $role_admin = $conn->real_escape_string($_SESSION['role']);
            
            $sql_log = "INSERT INTO system_logs (user_id, nama_admin, role_admin, aksi, deskripsi) 
                        VALUES ($user_id, '$nama_admin', '$role_admin', 'Update Profil', '$detail_log')";
            $conn->query($sql_log);
        } catch (Exception $e) {
            // Abaikan jika log gagal agar profil tetap berhasil disimpan tanpa memicu HTTP 500 Error
        }
    }
    
    // Refresh data user setelah update
    $q_user = $conn->query("SELECT * FROM users WHERE id = $user_id");
    $user_data = $q_user->fetch_assoc();
}

$foto_path = "https://ui-avatars.com/api/?name=" . urlencode($user_data['nama_lengkap']) . "&background=1e293b&color=fff&rounded=true";
if (!empty($user_data['foto_profil']) && file_exists('../' . $user_data['foto_profil'])) {
    $foto_path = '../' . $user_data['foto_profil'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - PSB RM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
         :root { --primary-green: #0da15b; --dark-green: #087d46; --bg-body: #f4f7fa; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); color: var(--text-dark); overflow-x: hidden; }
        
        .topbar-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; }
        .card-custom { background: #ffffff; border-radius: 20px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 30px; }
        
        .profile-img-container { position: relative; width: 150px; height: 150px; margin: 0 auto; }
        .profile-img { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .camera-icon { position: absolute; bottom: 5px; right: 5px; background-color: var(--primary-green); color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid #fff; transition: all 0.3s; }
        .camera-icon:hover { background-color: var(--dark-green); transform: scale(1.1); }
        
        .form-control:focus { border-color: var(--primary-green); box-shadow: 0 0 0 0.25rem rgba(13, 161, 91, 0.25); }
        .btn-custom { background-color: var(--primary-green); color: white; border-radius: 50px; padding: 10px 25px; font-weight: 500; border: none; transition: all 0.3s; }
        .btn-custom:hover { background-color: var(--dark-green); color: white; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3 shadow-sm" id="sidebarToggle" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="fw-bold text-dark m-0">Profil Pengguna</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;"><?= htmlspecialchars($user_data['nama_lengkap']) ?></div>
                    <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($user_data['role']) ?></div>
                </div>
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card-custom">
                    <form action="" method="POST" enctype="multipart/form-data">
                        
                        <div class="text-center mb-5">
                            <div class="profile-img-container">
                                <img src="<?= $foto_path ?>" alt="Profil" class="profile-img" id="preview-foto">
                                <label for="foto_profil" class="camera-icon" title="Ubah Foto">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" name="foto_profil" id="foto_profil" class="d-none" accept=".jpg, .jpeg, .png" onchange="previewImage(this)">
                            </div>
                            <h4 class="mt-3 fw-bold"><?= htmlspecialchars($user_data['nama_lengkap']) ?></h4>
                            <p class="text-muted"><?= htmlspecialchars($user_data['role']) ?></p>
                        </div>
                        
                        <h6 class="fw-bold border-bottom pb-2 mb-3">Informasi Akun</h6>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted">Nama Lengkap</label>
                                <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($user_data['nama_lengkap']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['username']) ?>" disabled readonly title="Username tidak dapat diubah">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Email</label>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" placeholder="contoh@email.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">No. WhatsApp</label>
                                <input type="text" class="form-control" name="no_wa" value="<?= htmlspecialchars($user_data['no_whatsapp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>
                        
                        <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4">Keamanan</h6>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted">Password Baru</label>
                            <input type="password" class="form-control" name="password_baru" placeholder="Kosongkan jika tidak ingin mengubah password">
                        </div>
                        
                        <div class="text-end border-top pt-3 mt-4">
                            <button type="submit" class="btn btn-custom"><i class="fas fa-save me-2"></i>Simpan Perubahan</button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview-foto').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    <?php if($status_pesan): ?>
        let icon = 'success';
        let title = 'Berhasil';
        let text = 'Profil berhasil diperbarui.';
        
        <?php if($status_pesan == 'format_salah'): ?>
            icon = 'error';
            title = 'Gagal';
            text = 'Format file foto tidak didukung.';
        <?php elseif($status_pesan == 'gagal_profil' || $status_pesan == 'gagal_foto' || $status_pesan == 'gagal_password'): ?>
            icon = 'error';
            title = 'Gagal';
            text = 'Terjadi kesalahan sistem saat memperbarui profil.';
        <?php endif; ?>
        
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            confirmButtonColor: '#0da15b'
        }).then(() => {
            if(icon === 'success') {
                window.location.href = 'profil.php';
            }
        });
    <?php endif; ?>
</script>
</body>
</html>