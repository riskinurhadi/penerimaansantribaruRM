<?php
session_start();

// 1. Cek apakah user sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. Cek Role (Hanya Developer dan Super Admin yang boleh menghapus data)
$role = $_SESSION['role'];
if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak. Anda tidak memiliki izin untuk menghapus data.");
}

// 3. Include koneksi database
require_once '../config.php';

$status_hapus = false;
$pesan_error = "";

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_pendaftaran = intval($_GET['id']);

    // Mulai transaksi untuk keamanan
    $conn->begin_transaction();

    try {
        // --- A. AMBIL DATA FILE UNTUK DIHAPUS DARI SERVER ---
        $query_berkas = "SELECT pas_foto, ijazah_skhu, akta_kelahiran, ktp_ortu, kartu_keluarga, piagam_prestasi FROM data_berkas WHERE pendaftaran_id = $id_pendaftaran";
        $result_berkas = $conn->query($query_berkas);
        
        if ($result_berkas && $result_berkas->num_rows > 0) {
            $berkas = $result_berkas->fetch_assoc();
            
            // Loop semua kolom berkas dan hapus file fisiknya jika ada
            foreach ($berkas as $kolom => $path_file) {
                if (!empty($path_file) && file_exists("../" . $path_file)) {
                    unlink("../" . $path_file); // Menghapus file dari folder
                }
            }
        }

        // --- B. HAPUS DATA DARI DATABASE ---
        // Catatan: Karena di SQL kita menggunakan FOREIGN KEY ... ON DELETE CASCADE, 
        // menghapus data di tabel 'pendaftaran' akan otomatis menghapus data di tabel lain yang berelasi
        $query_hapus = "DELETE FROM pendaftaran WHERE id = $id_pendaftaran";
        
        if (!$conn->query($query_hapus)) {
            throw new Exception("Gagal menghapus data dari database: " . $conn->error);
        }

        // Commit transaksi jika semua berhasil
        $conn->commit();
        $status_hapus = true;

    } catch (Exception $e) {
        // Rollback jika terjadi kesalahan
        $conn->rollback();
        $status_hapus = false;
        $pesan_error = $e->getMessage();
    }
} else {
    $pesan_error = "ID Pendaftaran tidak valid.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memproses Hapus Data...</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f7fa; font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($status_hapus): ?>
            Swal.fire({
                icon: 'success',
                title: 'Dihapus!',
                text: 'Data pendaftar beserta berkasnya telah dihapus permanen.',
                confirmButtonColor: '#0da15b',
                showConfirmButton: false,
                timer: 2000,
                allowOutsideClick: false
            }).then(() => {
                window.location.href = 'data_pendaftar.php';
            });
        <?php else: ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menghapus Data',
                text: '<?= addslashes($pesan_error) ?>',
                confirmButtonColor: '#ef4444'
            }).then(() => {
                window.location.href = 'data_pendaftar.php';
            });
        <?php endif; ?>
    });
</script>

</body>
</html>