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

// HANYA DEVELOPER & SUPER ADMIN YANG BISA MELIHAT LOG INI
if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak! Fitur ini khusus untuk Pemantauan Tingkat Tinggi.");
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

// Aksi: Tandai semua sudah dibaca
if (isset($_GET['action']) && $_GET['action'] == 'read_all') {
    $conn->query("UPDATE system_logs SET status_baca = 'Read' WHERE status_baca = 'Unread'");
    header("Location: notifikasi.php");
    exit;
}

// Aksi: Bersihkan riwayat
if (isset($_GET['action']) && $_GET['action'] == 'clear_all') {
    $conn->query("TRUNCATE TABLE system_logs");
    header("Location: notifikasi.php");
    exit;
}

// Ambil Data Log
$query = "SELECT * FROM system_logs ORDER BY created_at DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi & Log Sistem - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root { --primary-green: #0da15b; --bg-body: #f4f7fa; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); color: var(--text-dark); font-size: 0.9rem; }
        
        .topbar-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; }
        .card-custom { background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 25px; }

        /* Timeline/Log Styling */
        .timeline { position: relative; padding-left: 30px; margin-top: 20px;}
        .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        
        .timeline-item { position: relative; margin-bottom: 25px; }
        .timeline-icon { position: absolute; left: -39px; top: 0; width: 20px; height: 20px; border-radius: 50%; background: #ffffff; border: 4px solid var(--primary-green); z-index: 2; }
        .timeline-icon.unread { border-color: #ef4444; background: #fee2e2; }
        
        .timeline-content { background: #f8fafc; padding: 15px 20px; border-radius: 10px; border: 1px solid #e2e8f0; position: relative; }
        .timeline-item.unread .timeline-content { background: #ffffff; border-left: 4px solid #ef4444; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        
        .log-date { font-size: 0.75rem; color: #94a3b8; font-weight: 500; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;}
        .log-title { font-weight: 700; font-size: 0.95rem; color: var(--text-dark); margin-bottom: 8px; }
        .log-body { font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; }
        .log-body b.text-danger { background: #fee2e2; padding: 2px 5px; border-radius: 4px; font-family: monospace; font-size: 0.95rem; letter-spacing: 1px;}
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3 shadow-sm" id="sidebarToggle" style="border-radius: 10px;"><i class="fas fa-bars"></i></button>
                <h5 class="fw-bold text-dark m-0">Pusat Notifikasi & Log Aktivitas</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>

        <div class="card-custom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 border-bottom pb-3 gap-3">
                <div>
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-history text-primary-green me-2"></i> Riwayat Aktivitas Admin</h5>
                    <p class="text-muted m-0" style="font-size: 0.8rem; margin-top: 5px !important;">Pantau perubahan profil dan pembaruan password oleh seluruh admin.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="?action=read_all" class="btn btn-sm btn-outline-secondary rounded-pill fw-medium"><i class="fas fa-check-double me-1"></i> Tandai Semua Dibaca</a>
                    <button onclick="konfirmasiBersihkan()" class="btn btn-sm btn-danger rounded-pill fw-medium"><i class="fas fa-trash-alt me-1"></i> Bersihkan Log</button>
                </div>
            </div>

            <div class="timeline">
                <?php 
                if ($result && $result->num_rows > 0) {
                    while ($log = $result->fetch_assoc()) {
                        $is_unread = ($log['status_baca'] == 'Unread');
                        $class_item = $is_unread ? 'unread' : '';
                        
                        $waktu = date('d M Y - H:i', strtotime($log['created_at']));
                ?>
                    <div class="timeline-item <?= $class_item ?>">
                        <div class="timeline-icon <?= $class_item ?>"></div>
                        <div class="timeline-content">
                            <div class="log-date"><i class="far fa-clock"></i> <?= $waktu ?> WIB <?= $is_unread ? '<span class="badge bg-danger ms-2" style="font-size:0.6rem;">Baru</span>' : '' ?></div>
                            <div class="log-title"><?= htmlspecialchars($log['nama_admin']) ?> <span class="text-muted fw-normal fs-6">(<?= htmlspecialchars($log['role_admin']) ?>)</span></div>
                            <div class="log-body">
                                <?= $log['deskripsi'] ?>
                            </div>
                        </div>
                    </div>
                <?php 
                    }
                    // Jika ada load halaman ini, otomatis jalankan query update semua jadi Read untuk load berikutnya
                    $conn->query("UPDATE system_logs SET status_baca = 'Read' WHERE status_baca = 'Unread'");
                } else {
                    echo "<div class=".'text-center text-muted p-5'."><i class=".'fas fa-box-open fa-3x mb-3 opacity-25'."></i><br>Belum ada catatan aktivitas admin saat ini.</div>";
                }
                ?>
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

    function konfirmasiBersihkan() {
        Swal.fire({
            title: 'Bersihkan Riwayat?',
            text: "Seluruh catatan aktivitas admin akan dihapus permanen.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#cbd5e1',
            confirmButtonText: 'Ya, Bersihkan!',
            cancelButtonText: '<span class="text-dark">Batal</span>',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?action=clear_all';
            }
        });
    }
</script>
</body>
</html>