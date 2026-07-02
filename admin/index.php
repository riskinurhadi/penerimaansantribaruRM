<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Include koneksi database
require_once '../config.php';

// Ambil data session
$nama_lengkap = $_SESSION['nama_lengkap'];
$role = $_SESSION['role'];
$username = $_SESSION['username'] ?? '';

// Cek jika user punya foto_profil yang valid
$foto_path = "https://ui-avatars.com/api/?name=" . urlencode($nama_lengkap) . "&background=1e293b&color=fff&rounded=true";
$q_foto = $conn->query("SELECT foto_profil FROM users WHERE username = '$username'");
if ($q_foto && $q_foto->num_rows > 0) {
    $user_data = $q_foto->fetch_assoc();
    if(!empty($user_data['foto_profil']) && file_exists('../'.$user_data['foto_profil'])) {
        $foto_path = '../' . $user_data['foto_profil'];
    }
}

// --- QUERY STATISTIK UNTUK DASHBOARD ---
$total_pendaftar = $conn->query("SELECT COUNT(*) as total FROM pendaftaran")->fetch_assoc()['total'];
$menunggu_verifikasi = $conn->query("SELECT COUNT(*) as total FROM pendaftaran WHERE status_pendaftaran = 'Menunggu Verifikasi'")->fetch_assoc()['total'];
$total_lulus = $conn->query("SELECT COUNT(*) as total FROM pendaftaran WHERE status_pendaftaran = 'Lengkap'")->fetch_assoc()['total'];
$total_takhosush = $conn->query("SELECT COUNT(*) as total FROM pendaftaran WHERE program_takhosush = 'Ya'")->fetch_assoc()['total'];

// Query tambahan untuk statistik jenis kelamin (Laki-laki & Perempuan)
$total_lk = $conn->query("SELECT COUNT(*) as total FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE d.jenis_kelamin = 'Laki-laki'")->fetch_assoc()['total'] ?? 0;
$total_pr = $conn->query("SELECT COUNT(*) as total FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE d.jenis_kelamin = 'Perempuan'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* Menggunakan variabel warna dari sidebar untuk konsistensi */
        :root {
            --primary-green: #0da15b; 
            --light-green: #e8f5e9;
            --bg-body: #f4f7fa;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --support-blue: #2563eb; /* Warna biru untuk widget support */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Card Styling Identik dengan Referensi */
        .card-custom {
            background: #ffffff;
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            padding: 30px;
        }

        .welcome-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        /* Stat Cards */
        .stat-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            padding: 25px;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }

        /* Bottom Panels */
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .panel-link {
            font-size: 0.85rem;
            color: var(--primary-green);
            font-weight: 600;
            text-decoration: none;
        }

        .list-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 20px;
            border: 1px solid #f1f5f9;
            border-radius: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background-color: #ffffff;
        }

        .list-item:hover {
            border-color: #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .list-icon {
            width: 45px;
            height: 45px;
            background: #f8fafc;
            color: var(--text-muted);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        /* Dropdown Profil */
        .dropdown-menu-custom {
            border: none;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 10px 0;
        }
        
        .dropdown-item-custom {
            padding: 10px 20px;
            font-weight: 500;
            color: var(--text-dark);
            transition: 0.2s;
        }
        
        .dropdown-item-custom:hover {
            background-color: var(--light-green);
            color: var(--primary-green);
        }

        /* ========================================= */
        /* WIDGET CHAT SUPPORT STYLING (DIPERKECIL) */
        /* ========================================= */
        .chat-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 55px;
            height: 55px;
            background-color: var(--support-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 5px 20px rgba(37, 99, 235, 0.4);
            cursor: pointer;
            z-index: 1050;
            transition: all 0.3s ease;
        }
        .chat-btn:hover {
            transform: scale(1.05) translateY(-5px);
        }
        
        .chat-popup {
            position: fixed;
            bottom: 85px; /* Diturunkan sedikit */
            right: 25px;
            width: 310px; /* Diperkecil lebarnya */
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 1049;
            overflow: hidden;
            display: none;
            flex-direction: column;
            transform-origin: bottom right;
            animation: scaleUp 0.3s ease forwards;
        }
        .chat-popup.show {
            display: flex;
        }

        @keyframes scaleUp {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }

        .chat-header {
            background: var(--support-blue);
            color: white;
            padding: 15px 15px 30px 15px; /* Jarak atas bawah diperkecil */
            position: relative;
        }
        .chat-header h4 {
            margin: 0 0 5px 0;
            font-weight: 700;
            font-size: 1.1rem; /* Huruf judul lebih kecil */
        }
        .chat-header p {
            margin: 0;
            font-size: 0.75rem; /* Teks deskripsi dikecilkan */
            line-height: 1.4;
            opacity: 0.9;
        }
        .chat-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            font-size: 0.8rem;
        }
        .chat-close:hover {
            background: rgba(255,255,255,0.4);
        }

        .chat-body {
            padding: 20px 15px 15px 15px; /* Padding sisi diperkecil */
            background: white;
            border-radius: 12px 12px 0 0;
            margin-top: -15px;
            position: relative;
        }
        .chat-form label {
            font-size: 0.75rem; /* Label dikecilkan */
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 3px;
        }
        .chat-form .form-control, .chat-form .form-select {
            font-size: 0.8rem; /* Font input dikecilkan */
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 8px 10px; /* Padding input dikecilkan */
            margin-bottom: 12px;
        }
        .chat-form .form-control:focus, .chat-form .form-select:focus {
            border-color: var(--support-blue);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.2);
        }
        .chat-submit {
            background: var(--support-blue);
            color: white;
            border: none;
            border-radius: 8px;
            width: 100%;
            padding: 10px; /* Tombol diperkecil sedikit */
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.3s;
            margin-top: 5px;
        }
        .chat-submit:hover {
            background: #1d4ed8;
        }

        /* Responsif Widget */
        @media (max-width: 576px) {
            .chat-popup {
                width: calc(100vw - 40px);
                right: 20px;
                bottom: 80px;
            }
            .chat-btn {
                right: 20px;
                bottom: 15px;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- PANGGIL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <!-- Header Welcome Card (Clean Topbar) -->
        <div class="card-custom welcome-card">
            <div>
                <div class="d-flex align-items-center mb-1">
                    <button class="btn btn-light d-md-none me-3 shadow-sm" id="sidebarToggle" style="border-radius: 10px;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="fw-bold text-dark m-0">Selamat Datang, <?= htmlspecialchars($nama_lengkap) ?>!</h4>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Berikut adalah ringkasan aktivitas terbaru di sistem Pendaftaran Anda.</p>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center dropdown">
                
                <?php 
                // FITUR NOTIFIKASI KHUSUS SUPER ADMIN & DEVELOPER
                if ($role == 'Developer' || $role == 'Super Admin'): 
                    // Hitung notifikasi unread
                    $q_notif = $conn->query("SELECT COUNT(*) as jml FROM system_logs WHERE status_baca = 'Unread'");
                    $unread = $q_notif ? $q_notif->fetch_assoc()['jml'] : 0;
                ?>
                <a href="notifikasi.php" class="position-relative me-4 text-dark" title="Pusat Notifikasi">
                    <div style="background: #f1f5f9; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.3s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                        <i class="fas fa-bell fs-5" style="color: #64748b;"></i>
                        <?php if($unread > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm" style="font-size: 0.65rem; padding: 4px 6px; transform: translate(-30%, 10%) !important; border: 2px solid white;">
                            <?= $unread ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endif; ?>

                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;"><?= htmlspecialchars($nama_lengkap) ?></div>
                    <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <a href="#" role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none;">
                    <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom" aria-labelledby="profileDropdown">
                    <li><a class="dropdown-item dropdown-item-custom" href="profil.php"><i class="fas fa-user-circle me-2"></i>Profil Saya</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item dropdown-item-custom text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Log out</a></li>
                </ul>
            </div>
        </div>

        <!-- Cards Statistik (Grid 3x2 sesuai referensi) -->
        <div class="row g-4 mb-4">
            
            <!-- Card 1 -->
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-dark"><?= $total_pendaftar ?></h2>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Total Calon Siswa</p>
                    </div>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #dcfce7; color: #16a34a;">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-dark"><?= $menunggu_verifikasi ?></h2>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Perlu Verifikasi</p>
                    </div>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #ffedd5; color: #ea580c;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-dark"><?= $total_lulus ?></h2>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Berkas Lengkap</p>
                    </div>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f3e8ff; color: #9333ea;">
                        <i class="fas fa-quran"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-dark"><?= $total_takhosush ?></h2>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Minat Takhosush</p>
                    </div>
                </div>
            </div>

            <!-- Card 5 -->
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #ccfbf1; color: #0d9488;">
                        <i class="fas fa-male"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-dark"><?= $total_lk ?></h2>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Calon Santri Putra</p>
                    </div>
                </div>
            </div>

            <!-- Card 6 -->
            <div class="col-xl-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fce7f3; color: #db2777;">
                        <i class="fas fa-female"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0 text-dark"><?= $total_pr ?></h2>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Calon Santri Putri</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Bottom Panels (Aktivitas & Info) -->
        <div class="row g-4">
            
            <!-- Panel Kiri: Pendaftar Terbaru -->
            <div class="col-lg-6">
                <div class="card-custom h-100 p-4">
                    <div class="panel-header">
                        <h5 class="panel-title">Pendaftar Terbaru</h5>
                        <a href="data_pendaftar.php" class="panel-link">Lihat Semua</a>
                    </div>
                    
                    <?php 
                    // Ambil 3 pendaftar terbaru dari database secara realtime
                    $q_recent = $conn->query("
                        SELECT p.status_pendaftaran, p.pilihan_sekolah, d.nama_lengkap 
                        FROM pendaftaran p 
                        JOIN data_diri d ON p.id = d.pendaftaran_id 
                        ORDER BY p.id DESC LIMIT 3
                    ");
                    if($q_recent && $q_recent->num_rows > 0) {
                        while($recent = $q_recent->fetch_assoc()) {
                            $badge_col = 'bg-warning text-dark';
                            if ($recent['status_pendaftaran'] == 'Lengkap') $badge_col = 'bg-success text-white';
                            if ($recent['status_pendaftaran'] == 'Proses Seleksi') $badge_col = 'bg-info text-dark';
                            if ($recent['status_pendaftaran'] == 'Belum Lengkap' || $recent['status_pendaftaran'] == 'Batal') $badge_col = 'bg-danger text-white';
                    ?>
                        <div class="list-item">
                            <div class="list-icon"><i class="fas fa-envelope-open-text"></i></div>
                            <div class="w-100 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;"><?= htmlspecialchars($recent['nama_lengkap']) ?></div>
                                    <div class="text-muted" style="font-size: 0.8rem;">Pilihan: <?= htmlspecialchars($recent['pilihan_sekolah']) ?></div>
                                </div>
                                <span class="badge <?= $badge_col ?> rounded-pill" style="font-size: 0.75rem;"><?= htmlspecialchars($recent['status_pendaftaran']) ?></span>
                            </div>
                        </div>
                    <?php 
                        }
                    } else {
                        echo '<div class="text-muted text-center my-4">Belum ada pendaftar terbaru.</div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Panel Kanan: Aktivitas Sistem -->
            <div class="col-lg-6">
                <div class="card-custom h-100 p-4">
                    <div class="panel-header">
                        <h5 class="panel-title">Aktivitas Sistem</h5>
                    </div>
                    
                    <div class="list-item">
                        <div class="list-icon"><i class="fas fa-newspaper"></i></div>
                        <div>
                            <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;">Gelombang Pendaftaran Dibuka</div>
                            <div class="text-muted" style="font-size: 0.8rem;">Sistem penerimaan siap digunakan.</div>
                        </div>
                    </div>

                    <div class="list-item">
                        <div class="list-icon"><i class="fas fa-shield-alt text-success"></i></div>
                        <div>
                            <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;">Keamanan Sistem Aktif</div>
                            <div class="text-muted" style="font-size: 0.8rem;">Sesi Anda terlindungi dengan aman.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- ========================================= -->
<!-- WIDGET CHAT SUPPORT / BANTUAN -->
<!-- ========================================= -->

<!-- Tombol Chat Mengambang -->
<div class="chat-btn" id="chatBtn" onclick="toggleChat()" title="Butuh Bantuan?">
    <i class="fas fa-comment-dots" id="chatIcon"></i>
</div>

<!-- Popup Chat Form -->
<div class="chat-popup" id="chatPopup">
    <div class="chat-header">
        <button class="chat-close" onclick="toggleChat()" title="Tutup"><i class="fas fa-times"></i></button>
        <h4>Halo 👋</h4>
        <p>Silakan isi data yang valid agar kami dapat melakukan <i>follow-up</i> dan memastikan kendala Anda tertangani dengan baik.</p>
    </div>
    <div class="chat-body">
        <form class="chat-form" onsubmit="kirimWhatsApp(event)">
            <label>Departemen Tujuan <span class="text-danger">*</span></label>
            <select class="form-select" id="chatTarget" required>
                <option value="">Pilih departemen...</option>
                <option value="superadmin">Super Admin (Rudi Santoso)</option>
                <option value="developer">Developer Sistem</option>
            </select>

            <label>Nama Lengkap <span class="text-danger">*</span></label>
            <!-- Auto-fill nama admin yang sedang login -->
            <input type="text" class="form-control" id="chatNama" value="<?= htmlspecialchars($nama_lengkap) ?>" required>

            <label>Kendala yang Dihadapi <span class="text-danger">*</span></label>
            <textarea class="form-control" id="chatKendala" rows="3" placeholder="Jelaskan kendala Anda..." required></textarea>

            <button type="submit" class="chat-submit">
                <i class="fab fa-whatsapp me-2 fs-5" style="vertical-align: middle;"></i> Hubungi Sekarang
            </button>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Script Toggle Sidebar di Mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // =========================================
    // FUNGSI WIDGET CHAT BANTUAN
    // =========================================
    function toggleChat() {
        const popup = document.getElementById('chatPopup');
        const icon = document.getElementById('chatIcon');
        
        popup.classList.toggle('show');
        
        // Ubah Ikon ketika dibuka/ditutup
        if (popup.classList.contains('show')) {
            icon.classList.remove('fa-comment-dots');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-comment-dots');
        }
    }

    function kirimWhatsApp(e) {
        e.preventDefault(); // Mencegah reload halaman
        
        const target = document.getElementById('chatTarget').value;
        const nama = document.getElementById('chatNama').value;
        const kendala = document.getElementById('chatKendala').value;

        // Tentukan Nomor WhatsApp Tujuan
        let phone = '';
        if(target === 'superadmin') {
            phone = '6282177592249'; // Super Admin: Rudi Santoso
        } else if(target === 'developer') {
            phone = '6282371869118'; // Developer Sistem
        }

        // Format Pesan WhatsApp
        const message = `Halo, saya *${nama}*.\nSaya ingin melaporkan kendala pada Sistem Informasi PSB:\n\n_"${kendala}"_`;
        
        // Buat Link API WhatsApp
        const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;

        // Buka WhatsApp di Tab Baru
        window.open(waUrl, '_blank');
        
        // Tutup dan reset widget setelah terkirim
        toggleChat();
        document.getElementById('chatKendala').value = '';
    }
</script>

</body>
</html>