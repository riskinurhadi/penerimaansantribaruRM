<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';
$conn->set_charset("utf8mb4");

$nama_admin = $_SESSION['nama_lengkap'];

// ==========================================
// PENGATURAN WAKTU & UCAPAN (ZONA JAKARTA)
// ==========================================
date_default_timezone_set('Asia/Jakarta');
$jam_sekarang = date('H');

if ($jam_sekarang >= 5 && $jam_sekarang < 11) {
    $ucapan = "Selamat Pagi,";
} elseif ($jam_sekarang >= 11 && $jam_sekarang < 15) {
    $ucapan = "Selamat Siang,";
} elseif ($jam_sekarang >= 15 && $jam_sekarang < 18) {
    $ucapan = "Selamat Sore,";
} else {
    $ucapan = "Selamat Malam,";
}

// Konversi Hari dan Bulan ke Bahasa Indonesia
$hari_inggris = date('l');
$hari_indo = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$hari_ini = $hari_indo[$hari_inggris];

$bulan_inggris = date('F');
$bulan_indo = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
$tanggal_ini = date('d') . ' ' . $bulan_indo[$bulan_inggris] . ' ' . date('Y');


// ==========================================
// 1. QUERY DATA PENDAFTARAN & BERKAS
// ==========================================
$q_pendaftar = $conn->query("SELECT COUNT(*) as t FROM pendaftaran")->fetch_assoc()['t'] ?? 0;
$q_lulus = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE status_pendaftaran='Lengkap'")->fetch_assoc()['t'] ?? 0;
$q_verif = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE status_pendaftaran='Menunggu Verifikasi'")->fetch_assoc()['t'] ?? 0;

// Logika simulasi kelengkapan berkas (Minimal 3 wajib)
$q_lengkap = $conn->query("SELECT COUNT(*) as t FROM data_berkas WHERE pas_foto != '' AND kartu_keluarga != '' AND ktp_ortu != ''")->fetch_assoc()['t'] ?? 0;
$q_belum_lengkap = $q_pendaftar - $q_lengkap;
$pct_lengkap = ($q_pendaftar > 0) ? round(($q_lengkap / $q_pendaftar) * 100) : 0;

// ==========================================
// 2. QUERY DATA KEUANGAN
// ==========================================
$q_uang = $conn->query("SELECT SUM(jumlah_dibayar) as t FROM data_pembayaran")->fetch_assoc()['t'] ?? 0;
$q_lunas = $conn->query("SELECT COUNT(*) as t FROM data_pembayaran WHERE status_pembayaran='Lunas'")->fetch_assoc()['t'] ?? 0;
$q_cicil = $conn->query("SELECT COUNT(*) as t FROM data_pembayaran WHERE status_pembayaran='Cicilan Perbulan' OR status_pembayaran='Belum Lunas'")->fetch_assoc()['t'] ?? 0;
$q_bbayar = $conn->query("SELECT COUNT(*) as t FROM data_pembayaran WHERE status_pembayaran='Belum Bayar'")->fetch_assoc()['t'] ?? 0;

// ==========================================
// 3. QUERY DATA REKAM MEDIS
// ==========================================
$q_diperiksa = $conn->query("SELECT COUNT(*) as t FROM data_kesehatan WHERE catatan_kesehatan != ''")->fetch_assoc()['t'] ?? 0;
$q_riwayat = $conn->query("SELECT COUNT(*) as t FROM data_kesehatan WHERE riwayat_penyakit != '' OR kelainan_fisik != ''")->fetch_assoc()['t'] ?? 0;
$q_belum_medis = $q_pendaftar - $q_diperiksa;

// ==========================================
// 4. QUERY DATA SERAGAM
// ==========================================
$q_ukur = $conn->query("SELECT COUNT(*) as t FROM data_seragam WHERE status_pengukuran='Sudah Diukur'")->fetch_assoc()['t'] ?? 0;
$pct_ukur = ($q_pendaftar > 0) ? round(($q_ukur / $q_pendaftar) * 100) : 0;

// ==========================================
// 5. QUERY DEMOGRAFI, SEKOLAH & TAKHOSUSH
// ==========================================
$q_putra = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE d.jenis_kelamin = 'Laki-laki'")->fetch_assoc()['t'] ?? 0;
$q_putri = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE d.jenis_kelamin = 'Perempuan'")->fetch_assoc()['t'] ?? 0;

$q_ra = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'RA'")->fetch_assoc()['t'] ?? 0;
$q_mi = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'MI'")->fetch_assoc()['t'] ?? 0;
$q_mts = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'MTs'")->fetch_assoc()['t'] ?? 0;
$q_ma = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'MA'")->fetch_assoc()['t'] ?? 0;
$q_smk = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'SMK'")->fetch_assoc()['t'] ?? 0;

$q_takho_putra = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE p.program_takhosush = 'Ya' AND d.jenis_kelamin = 'Laki-laki'")->fetch_assoc()['t'] ?? 0;
$q_takho_putri = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE p.program_takhosush = 'Ya' AND d.jenis_kelamin = 'Perempuan'")->fetch_assoc()['t'] ?? 0;
$total_takho = $q_takho_putra + $q_takho_putri;

$pct_takho_putra = ($total_takho > 0) ? round(($q_takho_putra / $total_takho) * 100) : 0;
$pct_takho_putri = ($total_takho > 0) ? round(($q_takho_putri / $total_takho) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Data Analitik - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* =========================================
           OVERRIDE TEMA GELAP KHUSUS HALAMAN INI
           ========================================= */
        body { 
            background-color: #0b0f19 !important; 
            color: #f8fafc !important; 
            font-family: 'Poppins', sans-serif;
        }
        
        .main-content { 
            background-color: transparent !important; 
            padding-top: 15px !important;
        }

        /* Menyembunyikan Topbar Putih Bawaan */
        .topbar-card { display: none !important; }

        /* Mengubah Sidebar menjadi Mode Gelap */
        .sidebar { background: #111522 !important; border-right: 1px solid #1e2433 !important; }
        .sidebar-brand { color: #f8fafc !important; }
        .nav-item { color: #94a3b8 !important; }
        .nav-item:hover { background: #1a2030 !important; color: #f8fafc !important; }
        .nav-item.active { background: rgba(59, 130, 246, 0.15) !important; color: #3b82f6 !important; border-left-color: #3b82f6 !important; }
        .sidebar-brand div { background: #3b82f6 !important; }

        /* Efek Latar Belakang Menyala (Glow) */
        .bg-glow-1 { position: fixed; top: -100px; left: 20%; width: 400px; height: 400px; background: rgba(59, 130, 246, 0.15); filter: blur(100px); z-index: -1; }
        .bg-glow-2 { position: fixed; bottom: 10%; right: -50px; width: 300px; height: 300px; background: rgba(245, 158, 11, 0.1); filter: blur(100px); z-index: -1; }

        /* =========================================
           STYLING DASHBOARD GRID & CARD
           ========================================= */
        .dash-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .d-card {
            background: #131826;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.03);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s;
        }

        .d-card:hover { transform: translateY(-3px); }

        .d-card-title {
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chart-container { position: relative; width: 100%; }

        /* Styling Komponen Khusus */
        .circular-progress {
            width: 130px; height: 130px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.15);
            margin: 0 auto;
        }
        .inner-circle {
            width: 110px; height: 110px; background: #131826; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 1.8rem; font-weight: 700; color: #fff;
        }

        .task-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .task-circle { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; }

        .shortcut-btn {
            background: rgba(255,255,255,0.03); border-radius: 12px; padding: 15px 5px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #94a3b8; text-decoration: none; transition: 0.3s; border: 1px solid transparent;
        }
        .shortcut-btn i { font-size: 1.3rem; margin-bottom: 10px; color: #f8fafc; }
        .shortcut-btn span { font-size: 0.7rem; font-weight: 500; }
        .shortcut-btn:hover { background: rgba(59,130,246,0.1); border-color: rgba(59,130,246,0.3); color: #3b82f6; }
        .shortcut-btn:hover i { color: #3b82f6; }

        @media (max-width: 992px) {
            .dash-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .dash-grid { grid-template-columns: 1fr; }
            .col-span-2 { grid-column: span 1 !important; }
        }
    </style>
</head>
<body>

<div class="bg-glow-1"></div>
<div class="bg-glow-2"></div>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <!-- Header Mode Gelap -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn d-md-none text-white p-0" id="sidebarToggleDark" style="font-size: 1.5rem;"><i class="fas fa-bars"></i></button>
            <h4 class="fw-bold m-0 d-none d-md-block" style="color: #f8fafc;">Pusat Data Analitik</h4>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <!-- Ucapan Dinamis Sesuai Waktu -->
                    <div style="font-size: 0.8rem; color: #94a3b8;"><?= $ucapan ?></div>
                    <div class="fw-bold" style="font-size: 1rem;"><?= htmlspecialchars($nama_admin) ?></div>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_admin) ?>&background=3b82f6&color=fff&rounded=true" width="45">
            </div>
        </div>

        <div class="dash-grid">
            
            <!-- KARTU 1: TREND PENDAFTARAN (Line Chart) -->
            <div class="d-card" style="grid-column: span 2;">
                <div class="d-card-title">
                    <span>Tren Pendaftaran Harian</span>
                    <span class="badge" style="background: rgba(255,255,255,0.1); color: #fff; font-weight: 500;">Tahun Ajaran Ini</span>
                </div>
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <h1 class="m-0 fw-bold" style="font-size: 3rem; color: #f8fafc; line-height: 1;">
                            <?= $q_pendaftar ?> 
                            <span style="font-size: 1.2rem; color: #94a3b8; font-weight: normal;">Total Pendaftar</span>
                        </h1>
                        <div class="mt-2" style="color: #3b82f6; font-size: 0.85rem;"><i class="fas fa-chart-line me-1"></i> Data terus dipantau secara realtime</div>
                    </div>
                    <div class="text-end">
                        <h3 class="m-0 fw-bold" style="color: #10B981;"><?= $q_lulus ?></h3>
                        <div style="font-size: 0.85rem; color: #94a3b8;">Berkas Lengkap</div>
                    </div>
                </div>
                <div class="chart-container" style="height: 150px; margin-top: 10px;">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>

            <!-- KARTU 2: INFO KEUANGAN -->
            <div class="d-card justify-content-between">
                <div class="d-flex justify-content-between">
                    <div>
                        <!-- Tanggal dan Hari Dinamis Bahasa Indonesia -->
                        <div style="color: #f8fafc; font-size: 1.1rem; font-weight: 600;"><?= $tanggal_ini ?></div>
                        <div style="color: #94a3b8; font-size: 0.85rem;"><?= $hari_ini ?></div>
                    </div>
                    <div class="text-end">
                        <!-- Jam Sesuai Zona Waktu Jakarta -->
                        <div style="font-size: 1.2rem; font-weight: 600; color: #f8fafc;"><?= date('H:i') ?> <span style="font-size:0.8rem; color:#94a3b8;">WIB</span></div>
                    </div>
                </div>
                <div class="text-center my-4">
                    <i class="fas fa-wallet fa-3x mb-3" style="color: #F59E0B; filter: drop-shadow(0 0 15px rgba(245,158,11,0.4));"></i>
                    <h3 class="fw-bold text-white mb-0">Rp <?= number_format($q_uang, 0, ',', '.') ?></h3>
                    <div style="color: #94a3b8; font-size: 0.85rem; margin-top: 5px;">Total Arus Dana Masuk</div>
                </div>
                <div class="d-flex justify-content-between text-center mt-auto" style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px;">
                    <div>
                        <div class="text-white fw-bold"><?= $q_lunas ?></div>
                        <div style="color: #10B981; font-size: 0.7rem;"><i class="fas fa-circle" style="font-size:0.4rem;"></i> Lunas</div>
                    </div>
                    <div>
                        <div class="text-white fw-bold"><?= $q_cicil ?></div>
                        <div style="color: #F59E0B; font-size: 0.7rem;"><i class="fas fa-circle" style="font-size:0.4rem;"></i> Cicilan</div>
                    </div>
                    <div>
                        <div class="text-white fw-bold"><?= $q_bbayar ?></div>
                        <div style="color: #EF4444; font-size: 0.7rem;"><i class="fas fa-circle" style="font-size:0.4rem;"></i> Belum</div>
                    </div>
                </div>
            </div>

            <!-- KARTU 3: KELENGKAPAN BERKAS (Donut Chart) -->
            <div class="d-card">
                <div class="d-card-title">
                    <span>Penyelesaian Berkas</span>
                    <i class="fas fa-folder-open text-muted"></i>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-3">
                    <div>
                        <h2 class="text-white fw-bold m-0"><?= $pct_lengkap ?><span style="font-size:1.2rem;">%</span></h2>
                        <div style="color: #94a3b8; font-size: 0.8rem;">Berkas Lengkap</div>
                        
                        <div class="mt-4">
                            <div class="text-white fw-bold fs-5"><?= $q_verif ?></div>
                            <div style="color: #f59e0b; font-size: 0.75rem;">Menunggu Verifikasi</div>
                        </div>
                    </div>
                    <div style="position: relative; width: 120px; height: 120px;">
                        <canvas id="donutChart"></canvas>
                        <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); color: #8b5cf6; font-weight: bold; font-size: 1.5rem;">
                            <i class="fas fa-check-double"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KARTU 4: MANAJEMEN SERAGAM (Circular Progress) -->
            <div class="d-card align-items-center justify-content-center">
                <div class="d-card-title w-100 m-0">Target Ukur Seragam</div>
                <div class="circular-progress my-4" style="background: conic-gradient(#f59e0b <?= $pct_ukur ?>%, #1e2433 0);">
                    <div class="inner-circle">
                        <?= $pct_ukur ?><span style="font-size: 1rem; color: #94a3b8;">%</span>
                    </div>
                </div>
                <div class="w-100 d-flex justify-content-between text-center mt-2 px-3">
                    <div>
                        <div class="text-white fw-bold"><?= $q_ukur ?></div>
                        <div style="color: #94a3b8; font-size: 0.75rem;">Sudah Diukur</div>
                    </div>
                    <div>
                        <div class="text-white fw-bold"><?= $q_pendaftar - $q_ukur ?></div>
                        <div style="color: #94a3b8; font-size: 0.75rem;">Belum Diukur</div>
                    </div>
                </div>
            </div>

            <!-- KARTU 5: REKAM MEDIS (Task List) -->
            <div class="d-card">
                <div class="d-card-title">
                    <span>Status Kesehatan</span>
                    <i class="fas fa-heartbeat text-muted"></i>
                </div>
                <div class="mt-2">
                    <div class="task-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="task-circle bg-primary text-white"><i class="fas fa-check"></i></div>
                            <span class="text-white" style="font-size: 0.9rem;">Telah Diperiksa</span>
                        </div>
                        <span style="color: #94a3b8; font-size: 0.8rem;"><?= $q_diperiksa ?> Siswa</span>
                    </div>
                    
                    <div class="task-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="task-circle" style="border: 2px solid #f59e0b; color: #f59e0b;"><i class="fas fa-exclamation"></i></div>
                            <span class="text-white" style="font-size: 0.9rem;">Ada Riwayat/Kelainan</span>
                        </div>
                        <span style="color: #f59e0b; font-size: 0.8rem;"><?= $q_riwayat ?> Siswa</span>
                    </div>
                    
                    <div class="task-item border-0 mb-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="task-circle" style="border: 2px solid #64748b;"></div>
                            <span class="text-white" style="font-size: 0.9rem;">Belum Diperiksa</span>
                        </div>
                        <span style="color: #94a3b8; font-size: 0.8rem;"><?= $q_belum_medis ?> Siswa</span>
                    </div>
                </div>
            </div>

            <!-- KARTU 6: DEMOGRAFI SANTRI (Putra vs Putri) -->
            <div class="d-card">
                <div class="d-card-title">
                    <span>Demografi Gender</span>
                    <i class="fas fa-venus-mars text-muted"></i>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <div>
                        <div class="mb-4">
                            <div class="text-white fw-bold fs-4"><?= $q_putra ?> <span style="color: #94a3b8; font-size: 0.75rem; font-weight: normal;">Siswa</span></div>
                            <div style="color: #3b82f6; font-size: 0.8rem;"><i class="fas fa-male me-1"></i> Santri Putra</div>
                        </div>
                        <div>
                            <div class="text-white fw-bold fs-4"><?= $q_putri ?> <span style="color: #94a3b8; font-size: 0.75rem; font-weight: normal;">Siswi</span></div>
                            <div style="color: #ec4899; font-size: 0.8rem;"><i class="fas fa-female me-1"></i> Santri Putri</div>
                        </div>
                    </div>
                    <div style="position: relative; width: 110px; height: 110px;">
                        <canvas id="genderChart"></canvas>
                        <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); color: #fff; font-size: 1.2rem; opacity: 0.8;">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KARTU 7: DISTRIBUSI JENJANG SEKOLAH (Bar Chart) -->
            <div class="d-card">
                <div class="d-card-title">
                    <span>Distribusi Pilihan Sekolah</span>
                    <i class="fas fa-school text-muted"></i>
                </div>
                <div class="chart-container mt-2" style="height: 150px;">
                    <canvas id="schoolChart"></canvas>
                </div>
            </div>

            <!-- KARTU 8: STATISTIK TAKHOSUSH (Glowing Progress Bar) -->
            <div class="d-card">
                <div class="d-card-title">
                    <span>Statistik Program Takhosush</span>
                    <i class="fas fa-quran text-muted"></i>
                </div>
                
                <div class="d-flex align-items-center mb-4">
                    <div class="task-circle me-3" style="width: 45px; height: 45px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; font-size: 1.2rem; color: #10b981;">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <div>
                        <h3 class="text-white fw-bold m-0"><?= $total_takho ?> <span style="font-size: 0.9rem; font-weight: normal; color: #94a3b8;">Santri</span></h3>
                        <div style="color: #10b981; font-size: 0.8rem;">Total Mengikuti Program</div>
                    </div>
                </div>

                <!-- Progress Bar Putra -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-2" style="font-size: 0.8rem;">
                        <span class="text-white"><i class="fas fa-male" style="color: #3b82f6;"></i> Putra (<?= $q_takho_putra ?>)</span>
                        <span style="color: #3b82f6; font-weight: 600;"><?= $pct_takho_putra ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px; background-color: #1e2433; border-radius: 10px; overflow: visible;">
                        <div class="progress-bar" role="progressbar" style="width: <?= $pct_takho_putra ?>%; background-color: #3b82f6; box-shadow: 0 0 10px rgba(59,130,246,0.8); border-radius: 10px;" aria-valuenow="<?= $pct_takho_putra ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Progress Bar Putri -->
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-2" style="font-size: 0.8rem;">
                        <span class="text-white"><i class="fas fa-female" style="color: #ec4899;"></i> Putri (<?= $q_takho_putri ?>)</span>
                        <span style="color: #ec4899; font-weight: 600;"><?= $pct_takho_putri ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px; background-color: #1e2433; border-radius: 10px; overflow: visible;">
                        <div class="progress-bar" role="progressbar" style="width: <?= $pct_takho_putri ?>%; background-color: #ec4899; box-shadow: 0 0 10px rgba(236,72,153,0.8); border-radius: 10px;" aria-valuenow="<?= $pct_takho_putri ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>

            <!-- KARTU 9: SHORTCUTS -->
            <div class="d-card" style="grid-column: span 3;">
                <div class="d-card-title m-0">Akses Modul Cepat</div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-top: 20px;">
                    <a href="data_pendaftar.php" class="shortcut-btn"><i class="fas fa-user-graduate"></i><span>Pendaftar</span></a>
                    <a href="verifikasi_berkas.php" class="shortcut-btn"><i class="fas fa-folder-open"></i><span>Berkas</span></a>
                    <a href="keuangan.php" class="shortcut-btn"><i class="fas fa-wallet"></i><span>Keuangan</span></a>
                    <a href="rekam_medis.php" class="shortcut-btn"><i class="fas fa-stethoscope"></i><span>Medis</span></a>
                    <a href="manajemen_seragam.php" class="shortcut-btn"><i class="fas fa-tshirt"></i><span>Seragam</span></a>
                    <?php if($role == 'Super Admin' || $role == 'Developer'): ?>
                        <a href="manajemen_user.php" class="shortcut-btn"><i class="fas fa-cog"></i><span>Pengaturan</span></a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Script Chart.js & Logic -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. Toggle Sidebar Mobile (Menyesuaikan dengan id dark)
        document.getElementById('sidebarToggleDark').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // 2. LINE CHART (Tren Pendaftaran Simulasi 7 Hari Terakhir)
        const ctxLine = document.getElementById('lineChart').getContext('2d');
        const gradientLine = ctxLine.createLinearGradient(0, 0, 0, 150);
        gradientLine.addColorStop(0, 'rgba(59, 130, 246, 0.5)'); // Blue Glow
        gradientLine.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                datasets: [{
                    label: 'Pendaftar Baru',
                    data: [2, 5, 3, 8, 4, 12, <?= $q_pendaftar ?>], // Simulasi data terakhir adalah data asli
                    borderColor: '#3b82f6',
                    backgroundColor: gradientLine,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4, // Membuat garis melengkung (smooth)
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#7a859e', font: {size: 10} } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false }, ticks: { color: '#7a859e', font: {size: 10}, stepSize: 5 } }
                }
            }
        });

        // 3. DONUT CHART (Persentase Berkas Lengkap)
        const ctxDonut = document.getElementById('donutChart').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Lengkap', 'Belum Lengkap'],
                datasets: [{
                    data: [<?= $q_lengkap ?>, <?= $q_belum_lengkap ?>],
                    backgroundColor: ['#8b5cf6', '#1e2433'], // Purple & Dark Muted
                    borderWidth: 0,
                    cutout: '75%', // Ketebalan donut
                    borderRadius: 20 // Edge rounded
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: { animateScale: true }
            }
        });

        // 4. DONUT CHART (Gender/Demografi)
        const ctxGender = document.getElementById('genderChart').getContext('2d');
        new Chart(ctxGender, {
            type: 'doughnut',
            data: {
                labels: ['Putra', 'Putri'],
                datasets: [{
                    data: [<?= $q_putra ?>, <?= $q_putri ?>],
                    backgroundColor: ['#3b82f6', '#ec4899'], // Blue & Pink
                    borderWidth: 0,
                    cutout: '75%', 
                    borderRadius: 15
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } },
                animation: { animateScale: true }
            }
        });

        // 5. BAR CHART (Distribusi Jenjang Sekolah)
    const ctxSchool = document.getElementById('schoolChart').getContext('2d');
    const gradientBar = ctxSchool.createLinearGradient(0, 0, 0, 150);
    gradientBar.addColorStop(0, '#10b981'); // Emerald
    gradientBar.addColorStop(1, 'rgba(16, 185, 129, 0.1)');

    new Chart(ctxSchool, {
        type: 'bar',
        data: {
            labels: ['RA', 'MI', 'MTs', 'MA', 'SMK'],
            datasets: [{
                label: 'Total Siswa',
                data: [<?= $q_ra ?>, <?= $q_mi ?>, <?= $q_mts ?>, <?= $q_ma ?>, <?= $q_smk ?>],
                backgroundColor: gradientBar,
                borderRadius: 6,
                barThickness: 25
            }]
        },
        options: {
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: {weight: 'bold'} } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false }, ticks: { color: '#7a859e', stepSize: 5 } }
                }
            }
        });

    });
</script>

</body>
</html>