<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';
$conn->set_charset("utf8mb4");

$nama_lengkap_admin = $_SESSION['nama_lengkap'];
$role = $_SESSION['role'];
$username = $_SESSION['username'] ?? '';

if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak! Anda tidak memiliki izin.");
}

// ==========================================
// MENDAPATKAN FOTO PROFIL ADMIN AKTIF
// ==========================================
$foto_path = "https://ui-avatars.com/api/?name=" . urlencode($nama_lengkap_admin) . "&background=1e293b&color=fff&rounded=true";
$q_foto = $conn->query("SELECT foto_profil FROM users WHERE username = '$username'");
if ($q_foto && $q_foto->num_rows > 0) {
    $user_data = $q_foto->fetch_assoc();
    if(!empty($user_data['foto_profil']) && file_exists('../'.$user_data['foto_profil'])) {
        $foto_path = '../' . $user_data['foto_profil'];
    }
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Pendaftaran tidak valid!");
}

$id_pendaftar = intval($_GET['id']);
$status_pesan = '';

// --- PROSES UPDATE STATUS JIKA TOMBOL DIVALIDASI ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['status_verifikasi'])) {
    $status_baru = mysqli_real_escape_string($conn, trim($_POST['status_verifikasi']));
    
    // Update status pendaftaran
    if ($conn->query("UPDATE pendaftaran SET status_pendaftaran='$status_baru' WHERE id=$id_pendaftar")) {
        $status_pesan = 'sukses';
    } else {
        $status_pesan = 'gagal';
    }
}

// Ambil Data Siswa
$q_utama = $conn->query("SELECT p.*, d.nama_lengkap FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE p.id = $id_pendaftar");
if ($q_utama->num_rows == 0) die("Data pendaftar tidak ditemukan.");
$data = $q_utama->fetch_assoc();

// Ambil Data Berkas
$q_berkas = $conn->query("SELECT * FROM data_berkas WHERE pendaftaran_id = $id_pendaftar");
$berkas = $q_berkas->fetch_assoc() ?: [];

// List dokumen untuk ditampilkan secara looping
$list_dokumen = [
    'Pas Foto (3x4)' => $berkas['pas_foto'] ?? '',
    'Kartu Keluarga (KK)' => $berkas['kartu_keluarga'] ?? '',
    'KTP Orang Tua/Wali' => $berkas['ktp_ortu'] ?? '',
    'Akta Kelahiran' => $berkas['akta_kelahiran'] ?? '',
    'Ijazah / SKL' => $berkas['ijazah_skhu'] ?? '',
    'Piagam Prestasi' => $berkas['piagam_prestasi'] ?? ''
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Berkas - <?= htmlspecialchars($data['nama_lengkap']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --primary-green: #0da15b; 
            --dark-green: #087d46;
            --bg-body: #f4f7fa;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); color: var(--text-dark); font-size: 0.9rem; }
        
        /* Topbar Style */
        .topbar-card { 
            background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
            display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; 
        }
        
        /* Card Custom */
        .card-custom { 
            background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
            padding: 25px; margin-bottom: 20px; 
        }
        
        /* Document Box Styling */
        .doc-box {
            border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px;
            text-align: center; height: 100%; transition: 0.3s; background: #f8fafc;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .doc-box:hover { border-color: var(--primary-green); background: #ffffff; box-shadow: 0 5px 15px rgba(13,161,91,0.08); }
        
        .doc-preview {
            width: 100%; height: 220px; object-fit: cover; background: #e2e8f0; 
            border-radius: 8px; margin-bottom: 15px; cursor: pointer; border: 1px solid #cbd5e1;
        }
        
        .doc-pdf {
            width: 100%; height: 220px; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; background: #fee2e2; 
            color: #ef4444; border-radius: 8px; margin-bottom: 15px; border: 1px solid #fca5a5;
        }
        
        .doc-empty {
            width: 100%; height: 220px; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; border: 2px dashed #cbd5e1;
            color: #94a3b8; border-radius: 8px; margin-bottom: 15px; background: transparent;
        }

        /* Custom Buttons */
        .btn-solid-custom { 
            background-color: var(--primary-green); color: #ffffff !important; font-weight: 500; 
            border-radius: 50px; padding: 10px 20px; font-size: 0.85rem; transition: all 0.3s; border: none; width: 100%;
        }
        .btn-solid-custom:hover { background-color: var(--dark-green); box-shadow: 0 4px 10px rgba(13,161,91,0.2); }
        
        .btn-warning-custom { 
            background-color: #ffffff; color: #f59e0b !important; font-weight: 500; border: 1.5px solid #f59e0b;
            border-radius: 50px; padding: 8px 20px; font-size: 0.85rem; transition: all 0.3s; width: 100%;
        }
        .btn-warning-custom:hover { background-color: #f59e0b; color: #ffffff !important; }

        .btn-view-doc {
            border-radius: 50px; font-size: 0.8rem; font-weight: 500; padding: 6px 15px;
            border: 1px solid #cbd5e1; color: #475569; background: #ffffff; transition: 0.2s;
        }
        .btn-view-doc:hover { background: #f1f5f9; color: var(--text-dark); border-color: #94a3b8;}
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <!-- Header -->
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <a href="verifikasi_berkas.php" class="btn btn-light me-3 shadow-sm" style="border-radius: 10px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h5 class="fw-bold text-dark m-0">Verifikasi Dokumen: <?= htmlspecialchars($data['nama_lengkap']) ?></h5>
                    <p class="text-muted m-0" style="font-size: 0.8rem;">Status Saat Ini: <strong class="text-primary-green"><?= htmlspecialchars($data['status_pendaftaran']) ?></strong></p>
                </div>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>

        <div class="row">
            <!-- AREA DOKUMEN KIRI -->
            <div class="col-xl-9 col-lg-8">
                <div class="card-custom">
                    <h6 class="fw-bold text-dark mb-4 border-bottom pb-2"><i class="fas fa-folder-open text-primary-green me-2"></i> Hasil Unggahan Calon Santri</h6>
                    
                    <div class="row g-4">
                        <?php foreach($list_dokumen as $judul => $path): ?>
                        <div class="col-md-4">
                            <div class="doc-box">
                                <div>
                                    <?php if(!empty($path) && file_exists("../".$path)): 
                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                        if(in_array($ext, ['jpg', 'jpeg', 'png'])):
                                    ?>
                                        <!-- Jika Gambar -->
                                        <a href="../<?= $path ?>" target="_blank" title="Klik untuk memperbesar">
                                            <img src="../<?= $path ?>" class="doc-preview" alt="<?= $judul ?>">
                                        </a>
                                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.9rem;"><?= $judul ?></h6>
                                    
                                    <?php elseif($ext == 'pdf'): ?>
                                        <!-- Jika PDF -->
                                        <div class="doc-pdf">
                                            <i class="fas fa-file-pdf fa-3x mb-3"></i>
                                            <span class="fw-bold" style="font-size: 0.85rem;">Dokumen PDF</span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.9rem;"><?= $judul ?></h6>
                                    
                                    <?php endif; else: ?>
                                        <!-- Jika Kosong -->
                                        <div class="doc-empty">
                                            <i class="fas fa-times-circle fa-2x mb-2"></i>
                                            <span style="font-size: 0.8rem;">Belum Diunggah</span>
                                        </div>
                                        <h6 class="fw-bold text-muted mb-2" style="font-size: 0.9rem;"><?= $judul ?></h6>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <?php if(!empty($path) && file_exists("../".$path)): ?>
                                        <a href="../<?= $path ?>" target="_blank" class="btn btn-view-doc w-100">Buka / Unduh</a>
                                    <?php else: ?>
                                        <button class="btn btn-view-doc w-100" disabled>Tidak Tersedia</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- AREA AKSI KANAN (Sticky) -->
            <div class="col-xl-3 col-lg-4">
                <div class="card-custom" style="position: sticky; top: 30px;">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-gavel text-primary-green me-2"></i> Keputusan Verifikasi</h6>
                    <p class="text-muted" style="font-size: 0.8rem; line-height: 1.5;">Periksa dokumen dengan saksama. Tentukan apakah berkas memenuhi syarat untuk ditandai Lengkap.</p>
                    
                    <form action="" method="POST" id="formVerifikasi">
                        
                        <!-- Tombol Berkas Lengkap -->
                        <div class="mb-4 mt-4">
                            <button type="button" onclick="konfirmasiValid()" class="btn btn-solid-custom">
                                <i class="fas fa-check-circle me-1"></i> Berkas Lengkap
                            </button>
                            <small class="text-muted mt-2" style="font-size: 0.75rem; display: block; text-align: center;">Ubah status menjadi <br><strong>Lengkap</strong></small>
                            <input type="hidden" name="status_verifikasi" id="inputStatus" value="">
                        </div>

                        <hr style="border-color: #cbd5e1;">

                        <!-- Tombol Berkas Kurang/Belum Lengkap -->
                        <div class="mb-2 mt-4">
                            <button type="button" onclick="tolakBerkas()" class="btn btn-warning-custom">
                                <i class="fas fa-exclamation-triangle me-1"></i> Belum Lengkap
                            </button>
                            <small class="text-muted mt-2" style="font-size: 0.75rem; display: block; text-align: center;">Ubah status menjadi <br><strong>Belum Lengkap</strong></small>
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
    // --- Konfirmasi Berkas LENGKAP ---
    function konfirmasiValid() {
        Swal.fire({
            title: 'Berkas Lengkap?',
            text: "Anda menyatakan bahwa seluruh berkas valid dan lengkap.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0da15b',
            cancelButtonColor: '#cbd5e1',
            confirmButtonText: 'Ya, Lengkap!',
            cancelButtonText: '<span class="text-dark">Batal</span>',
            reverseButtons: true,
            customClass: { confirmButton: 'rounded-pill px-4', cancelButton: 'rounded-pill px-4' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('inputStatus').value = 'Lengkap'; // Nilai baru ke DB
                document.getElementById('formVerifikasi').submit();
            }
        });
    }

    // --- Konfirmasi Berkas BELUM LENGKAP ---
    function tolakBerkas() {
        Swal.fire({
            title: 'Berkas Belum Lengkap?',
            html: "Calon santri ini akan ditandai dengan status <b>Belum Lengkap</b> dan diminta untuk melengkapinya nanti.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#cbd5e1',
            confirmButtonText: 'Ya, Belum Lengkap!',
            cancelButtonText: '<span class="text-dark">Batal</span>',
            reverseButtons: true,
            customClass: { confirmButton: 'rounded-pill px-4', cancelButton: 'rounded-pill px-4' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('inputStatus').value = 'Belum Lengkap'; // Nilai baru ke DB
                document.getElementById('formVerifikasi').submit();
            }
        });
    }

    // --- Notifikasi Hasil Simpan ---
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($status_pesan == 'sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Status verifikasi pendaftar berhasil diperbarui.',
                confirmButtonColor: '#0da15b',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'verifikasi_berkas.php';
            });
        <?php elseif ($status_pesan == 'gagal'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Terjadi kesalahan sistem saat memperbarui status.',
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>
    });
</script>

</body>
</html>