<?php
// Pastikan config sudah di-include di file utama yang memanggil sidebar ini
// require_once '../config.php'; 

// Ambil role dan nama file yang sedang aktif untuk efek menu aktif
$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

// Logika untuk menentukan menu mana yang aktif
$is_pendaftar_active = in_array($current_page, ['data_pendaftar.php', 'detail_pendaftar.php', 'edit_pendaftar.php']) ? 'active' : '';
$is_verifikasi_active = in_array($current_page, ['verifikasi_berkas.php', 'cek_berkas.php']) ? 'active' : '';
$is_keuangan_active = ($current_page == 'keuangan.php') ? 'active' : '';
$is_medis_active = ($current_page == 'rekam_medis.php') ? 'active' : '';
$is_surat_active = in_array($current_page, ['surat_perjanjian.php', 'cetak_perjanjian.php']) ? 'active' : '';
$is_user_active = ($current_page == 'manajemen_user.php') ? 'active' : '';
$is_profil_active = ($current_page == 'profil.php') ? 'active' : '';

// =========================================================================
// AUTO-PATCH DATABASE: Cek & Tambah kolom 'foto_profil' & 'last_active'
// =========================================================================
if (isset($conn)) {
    // 1. Cek Kolom Foto Profil
    $check_col_foto = $conn->query("SHOW COLUMNS FROM users LIKE 'foto_profil'");
    if ($check_col_foto && $check_col_foto->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN foto_profil VARCHAR(255) NULL");
    }

    // 2. Cek Kolom Status Online (last_active)
    $check_col_active = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
    if ($check_col_active && $check_col_active->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN last_active DATETIME NULL");
    }

    // 3. REKAM AKTIVITAS USER SAAT INI (Online Status)
    if (isset($_SESSION['user_id'])) {
        $uid = intval($_SESSION['user_id']);
        $conn->query("UPDATE users SET last_active = NOW() WHERE id = $uid");
    } elseif (isset($_SESSION['username'])) {
        // Fallback jika tidak ada user_id
        $uname = $conn->real_escape_string($_SESSION['username']);
        $conn->query("UPDATE users SET last_active = NOW() WHERE username = '$uname'");
    }
}

// =========================================================================
// AMBIL DATA USER ONLINE (Hanya yang aktif dalam 5 menit terakhir)
// =========================================================================
$avatars = [];
if (isset($conn)) {
    // Toleransi status online adalah 5 menit
    $q_users = $conn->query("SELECT nama_lengkap, foto_profil FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE ORDER BY last_active DESC LIMIT 4");
    if ($q_users) {
        while ($row = $q_users->fetch_assoc()) {
            $avatars[] = $row;
        }
    }
}
?>

<style>
    :root {
        --primary-green: #0da15b; 
        --light-green: #e8f5e9;
        --bg-body: #f4f7fa;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --sidebar-width: 260px;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Poppins', sans-serif;
        color: var(--text-dark);
        overflow-x: hidden;
    }

    /* Wrapper utama */
    .admin-wrapper {
        display: flex;
        width: 100%;
        min-height: 100vh;
    }

    /* Sidebar Styling */
    .sidebar {
        width: var(--sidebar-width);
        background: #ffffff;
        position: fixed;
        height: 100vh;
        left: 0;
        top: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #f1f5f9;
        padding-top: 30px;
        transition: all 0.3s;
    }

    .sidebar-brand {
        padding: 0 30px 20px 30px;
        font-size: 1.3rem;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 5px;
        overflow-y: auto;
        /* Menyembunyikan scrollbar tapi tetap bisa discroll */
        scrollbar-width: none; 
        -ms-overflow-style: none;
    }
    .sidebar-nav::-webkit-scrollbar {
        display: none; 
    }

    .nav-item {
        padding: 12px 20px 12px 30px;
        margin-right: 20px;
        border-radius: 0 25px 25px 0;
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        transition: all 0.3s;
        border-left: 4px solid transparent;
    }

    .nav-item:hover {
        background-color: #f8fafc;
        color: var(--text-dark);
    }

    /* Menu Aktif */
    .nav-item.active {
        background-color: var(--light-green);
        color: var(--primary-green);
        border-left: 4px solid var(--primary-green);
    }

    .nav-item i {
        width: 30px;
        font-size: 1.1rem;
    }

    /* --- SIDEBAR BOTTOM (AVATARS & LOGOUT) --- */
    .sidebar-bottom {
        padding: 20px 30px 30px 30px;
        margin-top: auto; /* Mendorong bagian ini selalu ke bawah */
        border-top: 1px solid #f8fafc;
        background: #ffffff;
    }

    .avatar-group {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .avatar-item {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        margin-left: -12px;
        object-fit: cover;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1rem;
        transition: transform 0.2s ease, z-index 0.2s ease;
        position: relative;
    }
    .avatar-item:first-child {
        margin-left: 0;
    }
    .avatar-item:hover {
        z-index: 10;
        transform: scale(1.1);
    }

    .btn-logout {
        background-color: #de3c4b; /* Merah sesuai gambar */
        color: #ffffff;
        border: none;
        border-radius: 25px;
        padding: 10px 15px;
        width: 100%;
        font-weight: 700;
        font-size: 1rem;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.3s;
        box-shadow: 0 4px 10px rgba(222, 60, 75, 0.3);
        cursor: pointer;
    }

    .btn-logout:hover {
        background-color: #c92a39;
        color: #ffffff;
        transform: translateY(-2px);
    }

    .sidebar-version {
        font-size: 0.65rem;
        color: #94a3b8;
        text-align: center;
        margin-top: 15px;
        line-height: 1.4;
    }

    /* Main Content Styling */
    .main-content {
        flex-grow: 1;
        margin-left: var(--sidebar-width);
        min-height: 100vh;
        padding: 30px;
        width: calc(100% - var(--sidebar-width));
        transition: all 0.3s;
    }

    /* Toggler Mobile */
    @media (max-width: 768px) {
        .sidebar {
            margin-left: calc(-1 * var(--sidebar-width));
        }
        .sidebar.active {
            margin-left: 0;
        }
        .main-content {
            margin-left: 0;
            width: 100%;
            padding: 15px;
        }
    }
</style>

<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div style="width: 35px; height: 35px; background-color: var(--primary-green); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
            <i class="fas fa-book-open" style="font-size: 1rem;"></i>
        </div>
        Admin RM
    </div>

    <div class="sidebar-nav mt-2">
        <!-- Dashboard (Semua Role Bisa Lihat) -->
        <a href="index.php" class="nav-item <?= ($current_page == 'index.php') ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>

        <!-- Data Pendaftar & Verifikasi (Developer, Super Admin, dan Admin Pendaftaran) -->
        <?php if ($role == 'Developer' || $role == 'Super Admin' || $role == 'Admin Pendaftaran') : ?>
            <a href="data_pendaftar.php" class="nav-item <?= $is_pendaftar_active ?>">
                <i class="fas fa-envelope"></i> Data Pendaftar
            </a>
            <a href="verifikasi_berkas.php" class="nav-item <?= $is_verifikasi_active ?>">
                <i class="fas fa-address-card"></i> Verifikasi Berkas
            </a>
        <?php endif; ?>

        <!-- Keuangan -->
        <?php if ($role == 'Developer' || $role == 'Super Admin' || $role == 'Admin Keuangan') : ?>
            <a href="keuangan.php" class="nav-item <?= $is_keuangan_active ?>">
                <i class="fas fa-wallet"></i> Keuangan
            </a>
            <!-- Tambahan Menu Laporan -->
            <!--<a href="laporan_keuangan.php" class="nav-item <?= ($current_page == 'laporan_keuangan.php' || $current_page == 'laporan_detail.php') ? 'active' : '' ?>" style="margin-left: 20px; font-size: 0.85rem; padding-top: 8px; padding-bottom: 8px;">-->
            <!--    <i class="fas fa-chart-bar" style="font-size: 0.9rem;"></i> Laporan Keuangan-->
            <!--</a>-->
        <?php endif; ?>

        <!-- Rekam Medis -->
        <?php if ($role == 'Developer' || $role == 'Super Admin' || $role == 'Admin Kesehatan') : ?>
            <a href="rekam_medis.php" class="nav-item <?= $is_medis_active ?>">
                <i class="fas fa-heartbeat"></i> Rekam Medis
            </a>
        <?php endif; ?>

        <!-- Surat Perjanjian -->
        <?php if ($role == 'Developer' || $role == 'Super Admin' || $role == 'Admin Keamanan') : ?>
            <a href="surat_perjanjian.php" class="nav-item <?= $is_surat_active ?>">
                <i class="fas fa-file-signature"></i> Surat Perjanjian
            </a>
        <?php endif; ?>

        <!-- Pengaturan Sistem (Hanya Developer & Super Admin) -->
        <?php if ($role == 'Developer' || $role == 'Super Admin') : ?>
            <a href="manajemen_user.php" class="nav-item <?= $is_user_active ?> mt-3">
                <i class="fas fa-users-cog"></i> Pengaturan User
            </a>
        <?php endif; ?>
        
        <!-- Pusat Data -->
        <?php if ($role == 'Developer' || $role == 'Super Admin') : ?>
            <a href="pusat_data.php" class="nav-item <?= ($current_page == 'pusat_data.php') ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Pusat Data
            </a>
        <?php endif; ?>
        
        <!-- Profil Saya (Semua Role Bisa Lihat) -->
        <a href="profil.php" class="nav-item <?= $is_profil_active ?>">
            <i class="fas fa-user-circle"></i> Profil Saya
        </a>
    </div>

    <!-- SIDEBAR BOTTOM: Avatars (Online Status) & Logout -->
    <div class="sidebar-bottom">
        <div class="avatar-group" title="Admin yang sedang aktif">
            <?php 
            // Menampilkan Foto Profil / Inisial User Online
            if (!empty($avatars)) {
                foreach($avatars as $index => $av): 
                    // Set warna background fallback
                    $colors = ['#86efac', '#cbd5e1', '#fde047', '#fbcfe8'];
                    $text_colors = ['#166534', '#334155', '#854d0e', '#9d174d'];
                    $bg = $colors[$index % 4];
                    $tc = $text_colors[$index % 4];

                    // Title untuk memunculkan nama saat di-hover
                    $tooltip_title = htmlspecialchars($av['nama_lengkap']) . ' (Online)';

                    // Cek jika user punya foto_profil yang valid
                    if(!empty($av['foto_profil']) && file_exists('../'.$av['foto_profil'])): ?>
                        <img src="../<?= $av['foto_profil'] ?>" class="avatar-item" alt="User" title="<?= $tooltip_title ?>">
                    <?php else: 
                        // Jika tidak ada foto, ambil 1 huruf pertama dari namanya
                        $inisial = strtoupper(substr($av['nama_lengkap'], 0, 1));
                    ?>
                        <div class="avatar-item" style="background-color: <?= $bg ?>; color: <?= $tc ?>;" title="<?= $tooltip_title ?>"><?= $inisial ?></div>
                    <?php endif; 
                endforeach; 
            } else {
                // Tampilan default (Seharusnya tidak kosong karena yang akses pasti sedang online)
                echo '<div class="avatar-item" style="background-color: #86efac; color: #166534;" title="Anda (Online)">A</div>';
            }
            ?>
        </div>

        <button onclick="konfirmasiLogout(event)" class="btn-logout">
            Log out <i class="fas fa-sign-out-alt"></i>
        </button>

        <div class="sidebar-version">
            Sistem Penerimaan Santri Baru (PSB)<br>
            PPRM Kasui Versi 1.0.1
        </div>
    </div>
</div>

<!-- SweetAlert2 JS untuk fungsi Logout -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function konfirmasiLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Keluar dari Sistem?',
            text: "Sesi Anda akan diakhiri.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#f1f5f9',
            confirmButtonText: 'Ya, Keluar!',
            cancelButtonText: '<span class="text-dark">Batal</span>',
            reverseButtons: true,
            customClass: {
                confirmButton: 'rounded-3 px-4',
                cancelButton: 'rounded-3 px-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        })
    }
</script>