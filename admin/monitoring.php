<?php
session_start();

require_once '../config.php';
$conn->set_charset("utf8mb4");

date_default_timezone_set('Asia/Jakarta');

// ==========================================
// ENDPOINT AJAX UNTUK NOTIFIKASI REALTIME
// ==========================================
// Blok ini akan mengecek apakah ada pendaftar baru sejak ID terakhir yang dikirim JS
if (isset($_GET['ajax_new_reg'])) {
    $last_id = intval($_GET['last_id']);
    
    $q_new = $conn->query("
        SELECT p.id, d.nama_lengkap, p.status_pendaftaran, p.pilihan_sekolah, p.created_at 
        FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id 
        WHERE p.id > $last_id ORDER BY p.id ASC
    ");
    
    $new_data = [];
    if($q_new) {
        while($r = $q_new->fetch_assoc()){
            $new_data[] = $r;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($new_data);
    exit; // Hentikan eksekusi script HTML jika ini adalah request AJAX
}

// Cek Login untuk akses halaman utama
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// ==========================================
// 1. QUERY AGREGASI UTAMA (PENDAFTARAN)
// ==========================================
// Ambil ID Pendaftaran terakhir saat halaman pertama kali dimuat (untuk acuan AJAX nanti)
$max_id_query = $conn->query("SELECT MAX(id) as max_id FROM pendaftaran");
$initial_max_id = ($max_id_query && $max_id_query->num_rows > 0) ? $max_id_query->fetch_assoc()['max_id'] : 0;
$initial_max_id = $initial_max_id ?? 0;

$q_pendaftar = $conn->query("SELECT COUNT(*) as t FROM pendaftaran")->fetch_assoc()['t'] ?? 0;
$q_lulus = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE status_pendaftaran='Lengkap'")->fetch_assoc()['t'] ?? 0;
$q_verif = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE status_pendaftaran='Menunggu Verifikasi'")->fetch_assoc()['t'] ?? 0;
$q_batal = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE status_pendaftaran='Batal' OR status_pendaftaran='Belum Lengkap'")->fetch_assoc()['t'] ?? 0;

$q_lengkap = $conn->query("SELECT COUNT(*) as t FROM data_berkas WHERE pas_foto != '' AND kartu_keluarga != '' AND ktp_ortu != ''")->fetch_assoc()['t'] ?? 0;

// ==========================================
// 2. QUERY KEUANGAN
// ==========================================
$q_uang = $conn->query("SELECT SUM(jumlah_dibayar) as t FROM data_pembayaran")->fetch_assoc()['t'] ?? 0;
$q_lunas = $conn->query("SELECT COUNT(*) as t FROM data_pembayaran WHERE status_pembayaran='Lunas'")->fetch_assoc()['t'] ?? 0;
$q_cicil = $conn->query("SELECT COUNT(*) as t FROM data_pembayaran WHERE status_pembayaran='Cicilan Perbulan' OR status_pembayaran='Belum Lunas'")->fetch_assoc()['t'] ?? 0;

// ==========================================
// 3. QUERY MEDIS & SERAGAM
// ==========================================
$q_sehat = $conn->query("SELECT COUNT(*) as t FROM data_kesehatan WHERE catatan_kesehatan != '' AND kelainan_fisik = '' AND riwayat_penyakit = ''")->fetch_assoc()['t'] ?? 0;
$q_pantauan = $conn->query("SELECT COUNT(*) as t FROM data_kesehatan WHERE riwayat_penyakit != '' OR kelainan_fisik != ''")->fetch_assoc()['t'] ?? 0;
$q_ukur = $conn->query("SELECT COUNT(*) as t FROM data_seragam WHERE status_pengukuran='Sudah Diukur'")->fetch_assoc()['t'] ?? 0;

// ==========================================
// 4. QUERY DEMOGRAFI & JENJANG
// ==========================================
$q_putra = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE d.jenis_kelamin = 'Laki-laki'")->fetch_assoc()['t'] ?? 0;
$q_putri = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE d.jenis_kelamin = 'Perempuan'")->fetch_assoc()['t'] ?? 0;

$q_ra = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'RA'")->fetch_assoc()['t'] ?? 0;
$q_mi = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'MI'")->fetch_assoc()['t'] ?? 0;
$q_mts = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'MTs'")->fetch_assoc()['t'] ?? 0;
$q_ma = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'MA'")->fetch_assoc()['t'] ?? 0;
$q_smk = $conn->query("SELECT COUNT(*) as t FROM pendaftaran WHERE pilihan_sekolah = 'SMK'")->fetch_assoc()['t'] ?? 0;

// ==========================================
// 5. DATA TAKHOSUSH
// ==========================================
$q_takho_putra = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE p.program_takhosush = 'Ya' AND d.jenis_kelamin = 'Laki-laki'")->fetch_assoc()['t'] ?? 0;
$q_takho_putri = $conn->query("SELECT COUNT(*) as t FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE p.program_takhosush = 'Ya' AND d.jenis_kelamin = 'Perempuan'")->fetch_assoc()['t'] ?? 0;
$total_takho = $q_takho_putra + $q_takho_putri;

$pct_takho_putra = ($total_takho > 0) ? round(($q_takho_putra / $total_takho) * 100) : 0;
$pct_takho_putri = ($total_takho > 0) ? round(($q_takho_putri / $total_takho) * 100) : 0;

// ==========================================
// 6. DATA TAMBAHAN: TOP WILAYAH & PENDAFTAR TERBARU
// ==========================================
$top_wilayah = $conn->query("
    SELECT kota_kabupaten, COUNT(*) as total 
    FROM data_alamat 
    GROUP BY kota_kabupaten 
    ORDER BY total DESC LIMIT 4
");

$pendaftar_terbaru = $conn->query("
    SELECT p.no_pendaftaran, d.nama_lengkap, p.pilihan_sekolah, p.status_pendaftaran, p.created_at 
    FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id 
    ORDER BY p.id DESC LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMARA Command Center</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-color: #050b14;
            --card-bg: rgba(17, 24, 39, 0.6);
            --card-border: rgba(255, 255, 255, 0.05);
            --primary: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            --pink: #ec4899;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body { 
            background-color: var(--bg-color); 
            color: var(--text-main); 
            font-family: 'Poppins', sans-serif;
            overflow: hidden; /* Mengunci total agar tidak bisa discroll */
            margin: 0;
            padding: 0;
            height: 100vh;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 85% 30%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
        }

        /* HEADER */
        .cc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 20px; 
            background: linear-gradient(180deg, rgba(5,11,20,0.9) 0%, transparent 100%);
            border-bottom: 1px solid rgba(255,255,255,0.02);
            flex-shrink: 0;
        }
        
        .cc-brand { display: flex; align-items: center; gap: 15px; }
        
        .cc-brand-icon {
            width: 38px; height: 38px; background: var(--primary); border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }

        .cc-clock {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
            text-shadow: 0 0 15px rgba(16,185,129,0.4);
            line-height: 1;
        }

        /* GRID LAYOUT (Absolut membagi rata 100% Layar Tanpa Overflow) */
        .cc-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(3, minmax(0, 1fr)); /* Paksa 3 baris agar membagi rata tinggi sisa layar */
            gap: 12px; 
            padding: 10px 20px 15px 20px;
            flex-grow: 1; 
            min-height: 0; /* Penting agar flex item tidak merusak tinggi grid anak */
        }

        .cc-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid var(--card-border);
            padding: 12px 18px; 
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            height: 100%;
            min-height: 0; /* Mencegah card membesar sendiri */
        }

        .cc-card-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px; 
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .cc-value-huge {
            font-size: 2.8rem; 
            font-weight: 800;
            line-height: 1;
            margin: 0;
            color: var(--text-main);
        }

        /* NOTIFIKASI MELAYANG (FLOATING BUBBLE) - POSISI KANAN BAWAH */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            left: auto;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .floating-toast {
            background: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 12px 15px;
            width: 350px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            transform: translateX(120%); 
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .floating-toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-icon {
            width: 35px; height: 35px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }

        .toast-success .toast-icon { background: rgba(16,185,129,0.2); color: var(--success); border: 1px solid var(--success); box-shadow: 0 0 15px rgba(16,185,129,0.3); }
        .toast-danger .toast-icon { background: rgba(239,68,68,0.2); color: var(--danger); border: 1px solid var(--danger); box-shadow: 0 0 15px rgba(239,68,68,0.3); }

        .toast-body-custom { flex-grow: 1; line-height: 1.2; }
        .toast-title { font-size: 0.8rem; font-weight: 700; margin-bottom: 2px; color: var(--text-main); }
        .toast-desc { font-size: 0.7rem; color: var(--text-muted); }

        /* Custom Progress Bar */
        .prog-bar-container { width: 100%; height: 5px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; margin-top: 4px; }
        .prog-bar-fill { height: 100%; border-radius: 10px; }

        /* General Styles */
        .btn-fs { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; border-radius: 8px; padding: 5px 12px; font-size: 0.8rem; transition: 0.3s; }
        .btn-fs:hover { background: rgba(255,255,255,0.1); }
        
        .span-2 { grid-column: span 2; }
        .span-3 { grid-column: span 3; }
        
        .list-group-item-dark {
            background: transparent;
            border: none;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 5px 0;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .list-group-item-dark:last-child { border-bottom: none; }

        /* TABLE DARK KUSTOM */
        .table-dark-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            color: var(--text-main);
        }
        .table-dark-custom th {
            color: var(--text-muted);
            font-weight: 500;
            padding: 6px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: left;
        }
        .table-dark-custom td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.02);
            vertical-align: middle;
        }
        .table-dark-custom tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="cc-header">
    <div class="cc-brand">
        <div class="cc-brand-icon"><i class="fas fa-satellite-dish"></i></div>
        <div>
            <h6 class="m-0 fw-bold" style="letter-spacing: 1px;">SAMARA</h6>
            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Command & Monitoring Center</div>
        </div>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <div class="text-end">
            <div id="liveDate" style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Memuat...</div>
            <div id="liveClock" class="cc-clock">00:00:00</div>
        </div>
        <button class="btn-fs" onclick="toggleFullScreen()"><i class="fas fa-expand"></i></button>
        <a href="index.php" class="btn-fs text-decoration-none text-danger"><i class="fas fa-times"></i> Tutup</a>
    </div>
</div>

<!-- MAIN GRID -->
<div class="cc-grid">
    
    <!-- BARIS 1 -->
    <!-- CARD 1: TOTAL PENDAFTAR -->
    <div class="cc-card">
        <div class="cc-card-title"><i class="fas fa-users text-primary"></i> Total Calon Santri</div>
        <div class="d-flex flex-column justify-content-center flex-grow-1">
            <div class="cc-value-huge text-primary"><?= $q_pendaftar ?></div>
        </div>
        <div class="d-flex justify-content-between mt-auto pt-2 border-top border-secondary">
            <div class="text-center">
                <div class="fw-bold" style="color: var(--success); font-size: 1.3rem; line-height: 1.1;"><?= $q_lulus ?></div>
                <div style="font-size:0.7rem; color:var(--text-muted);">Berkas Lengkap</div>
            </div>
            <div class="text-center">
                <div class="fw-bold" style="color: var(--warning); font-size: 1.3rem; line-height: 1.1;"><?= $q_verif ?></div>
                <div style="font-size:0.7rem; color:var(--text-muted);">Verifikasi</div>
            </div>
            <div class="text-center">
                <div class="fw-bold" style="color: var(--danger); font-size: 1.3rem; line-height: 1.1;"><?= $q_batal ?></div>
                <div style="font-size:0.7rem; color:var(--text-muted);">Batal/Tolak</div>
            </div>
        </div>
    </div>

    <!-- CARD 2: DEMOGRAFI -->
    <div class="cc-card">
        <div class="cc-card-title"><i class="fas fa-venus-mars" style="color: var(--pink);"></i> Demografi Gender</div>
        <div class="d-flex justify-content-between align-items-center flex-grow-1">
            <div class="text-center w-50" style="border-right: 1px solid rgba(255,255,255,0.1);">
                <i class="fas fa-male mb-1" style="font-size: 2rem; color: #3b82f6;"></i>
                <div class="fw-bold text-white" style="font-size: 1.5rem; line-height: 1.1;"><?= $q_putra ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Putra</div>
            </div>
            <div class="text-center w-50">
                <i class="fas fa-female mb-1" style="font-size: 2rem; color: #ec4899;"></i>
                <div class="fw-bold text-white" style="font-size: 1.5rem; line-height: 1.1;"><?= $q_putri ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Putri</div>
            </div>
        </div>
    </div>

    <!-- CARD 3: KESIAPAN & PROGRES -->
    <div class="cc-card span-2">
        <div class="cc-card-title"><i class="fas fa-tasks text-purple"></i> Progres Kesiapan Calon Santri</div>
        <div class="row flex-grow-1 align-items-center m-0">
            <div class="col-4 text-center p-0">
                <div style="position: relative; width: 70px; height: 70px; margin: 0 auto;">
                    <canvas id="chartBerkas"></canvas>
                    <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); color: var(--purple); font-size: 0.9rem;"><i class="fas fa-folder"></i></div>
                </div>
                <div class="mt-2 fw-bold" style="font-size: 1rem;"><?= $q_lengkap ?> <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">/ <?= $q_pendaftar ?></span></div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">Berkas Lengkap</div>
            </div>
            <div class="col-4 text-center p-0">
                <div style="position: relative; width: 70px; height: 70px; margin: 0 auto;">
                    <canvas id="chartMedis"></canvas>
                    <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); color: var(--danger); font-size: 0.9rem;"><i class="fas fa-heartbeat"></i></div>
                </div>
                <div class="mt-2 fw-bold" style="font-size: 1rem;"><?= $q_sehat ?> <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">/ <?= $q_pendaftar ?></span></div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">Santri Sehat</div>
            </div>
            <div class="col-4 text-center p-0">
                <div style="position: relative; width: 70px; height: 70px; margin: 0 auto;">
                    <canvas id="chartSeragam"></canvas>
                    <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); color: var(--primary); font-size: 0.9rem;"><i class="fas fa-tshirt"></i></div>
                </div>
                <div class="mt-2 fw-bold" style="font-size: 1rem;"><?= $q_ukur ?> <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">/ <?= $q_pendaftar ?></span></div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">Seragam Diukur</div>
            </div>
        </div>
    </div>


    <!-- BARIS 2 -->
    <!-- CARD 4: KEUANGAN -->
    <div class="cc-card span-2">
        <div class="cc-card-title"><i class="fas fa-wallet text-warning"></i> Arus Kas & Keuangan</div>
        <div class="row flex-grow-1 align-items-center m-0">
            <div class="col-md-5 p-0">
                <div style="font-size: 0.75rem; color: var(--text-muted);">Total Dana Masuk</div>
                <div class="fw-bold text-warning" style="font-size: 1.6rem; line-height: 1.2;">Rp <?= number_format($q_uang, 0, ',', '.') ?></div>
            </div>
            <div class="col-md-7 p-0 ps-3">
                <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                    <span><i class="fas fa-check-circle text-success me-1"></i> Lunas (<?= $q_lunas ?>)</span>
                </div>
                <div class="prog-bar-container mb-2">
                    <div class="prog-bar-fill bg-success" style="width: <?= ($q_pendaftar > 0) ? ($q_lunas/$q_pendaftar*100) : 0 ?>%; box-shadow: 0 0 10px var(--success);"></div>
                </div>
                
                <div class="d-flex justify-content-between mb-1 mt-2" style="font-size: 0.75rem;">
                    <span><i class="fas fa-sync-alt text-warning me-1"></i> Mencicil (<?= $q_cicil ?>)</span>
                </div>
                <div class="prog-bar-container">
                    <div class="prog-bar-fill bg-warning" style="width: <?= ($q_pendaftar > 0) ? ($q_cicil/$q_pendaftar*100) : 0 ?>%; box-shadow: 0 0 10px var(--warning);"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 5: GRAFIK JENJANG SEKOLAH (CHART) -->
    <div class="cc-card">
        <div class="cc-card-title"><i class="fas fa-chart-bar text-success"></i> Distribusi Jenjang Sekolah</div>
        <div style="height: 100%; width: 100%; min-height: 80px; position: relative;">
            <canvas id="barSekolah"></canvas>
        </div>
    </div>

    <!-- CARD 6: STATISTIK TAKHOSUSH -->
    <div class="cc-card">
        <div class="cc-card-title"><i class="fas fa-book-quran text-success"></i> Statistik Program Takhosush</div>
        
        <div class="d-flex align-items-center mb-2">
            <div class="me-3" style="width: 35px; height: 35px; border-radius: 50%; border: 1.5px solid #10b981; display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.1);">
                <i class="fas fa-book-reader text-success" style="font-size: 1rem;"></i>
            </div>
            <div>
                <div class="text-white fw-bold d-flex align-items-baseline gap-2">
                    <span style="font-size: 1.5rem; line-height: 1;"><?= $total_takho ?></span> 
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">Santri</span>
                </div>
                <div style="color: #10b981; font-size: 0.65rem;">Total Mengikuti Program</div>
            </div>
        </div>

        <div class="mt-auto">
            <div class="mb-1">
                <div class="d-flex justify-content-between mb-1" style="font-size: 0.7rem; font-weight: 600;">
                    <span class="text-white"><i class="fas fa-male text-primary me-1"></i> Putra (<?= $q_takho_putra ?>)</span>
                    <span class="text-primary"><?= $pct_takho_putra ?>%</span>
                </div>
                <div class="prog-bar-container mt-0" style="height: 4px;">
                    <div class="prog-bar-fill bg-primary" style="width: <?= $pct_takho_putra ?>%; box-shadow: 0 0 8px var(--primary);"></div>
                </div>
            </div>
            <div class="mb-1 mt-2">
                <div class="d-flex justify-content-between mb-1" style="font-size: 0.7rem; font-weight: 600;">
                    <span class="text-white"><i class="fas fa-female text-pink me-1"></i> Putri (<?= $q_takho_putri ?>)</span>
                    <span class="text-pink"><?= $pct_takho_putri ?>%</span>
                </div>
                <div class="prog-bar-container mt-0" style="height: 4px;">
                    <div class="prog-bar-fill bg-pink" style="width: <?= $pct_takho_putri ?>%; box-shadow: 0 0 8px var(--pink);"></div>
                </div>
            </div>
        </div>
    </div>


    <!-- BARIS 3 -->
    <!-- CARD 7: TOP WILAYAH ASAL (LIMIT 4 BARIS) -->
    <div class="cc-card">
        <div class="cc-card-title"><i class="fas fa-map-marked-alt" style="color: #06b6d4;"></i> Top Wilayah Asal</div>
        <div class="d-flex flex-column justify-content-evenly flex-grow-1">
            <?php 
            if($top_wilayah && $top_wilayah->num_rows > 0) {
                while($wil = $top_wilayah->fetch_assoc()) {
                    $kota = htmlspecialchars($wil['kota_kabupaten']);
                    $kota = str_replace(['KABUPATEN ', 'KOTA '], '', strtoupper($kota));
                    echo '
                    <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size:0.75rem; color: var(--text-main);"><i class="fas fa-map-pin me-2 text-muted"></i> '.$kota.'</span>
                        <span class="badge" style="background: rgba(255,255,255,0.1); color: var(--text-main); font-weight: 500; font-size: 0.7rem;">'.$wil['total'].' Santri</span>
                    </div>';
                }
            } else {
                echo '<div class="text-muted" style="font-size:0.75rem;">Belum ada data wilayah.</div>';
            }
            ?>
        </div>
    </div>

    <!-- CARD 8: PENDAFTAR TERBARU (TABEL KUSTOM DARK MODE) -->
    <div class="cc-card span-3" style="overflow: hidden;">
        <div class="cc-card-title"><i class="fas fa-clock text-primary"></i> 5 Pendaftar Terakhir (Real-Time)</div>
        <div class="flex-grow-1" style="overflow-y: auto;">
            <table class="table-dark-custom">
                <thead>
                    <tr>
                        <th>No. Daftar</th>
                        <th>Nama Santri</th>
                        <th>Jenjang</th>
                        <th class="text-end">Waktu Pendaftaran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if($pendaftar_terbaru && $pendaftar_terbaru->num_rows > 0) {
                        while($pt = $pendaftar_terbaru->fetch_assoc()) {
                            echo '
                            <tr>
                                <td><span class="text-primary fw-bold">'.htmlspecialchars($pt['no_pendaftaran']).'</span></td>
                                <td class="fw-bold">'.htmlspecialchars($pt['nama_lengkap']).'</td>
                                <td>'.htmlspecialchars($pt['pilihan_sekolah']).'</td>
                                <td class="text-end text-white">'.date('d M Y, H:i', strtotime($pt['created_at'])).'</td>
                            </tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada pendaftar.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<!-- WADAH NOTIFIKASI MELAYANG (TOAST) -->
<div id="toast-container" class="toast-container">
    <!-- Bubble Toast akan di-generate oleh JavaScript di sini -->
</div>

<script>
    // --- 1. FUNGSI JAM & TANGGAL REALTIME ---
    function updateClock() {
        const now = new Date();
        const jam = String(now.getHours()).padStart(2, '0');
        const menit = String(now.getMinutes()).padStart(2, '0');
        const detik = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('liveClock').textContent = `${jam}:${menit}:${detik}`;

        const hariArr = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const bulanArr = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        document.getElementById('liveDate').textContent = `${hariArr[now.getDay()]}, ${now.getDate()} ${bulanArr[now.getMonth()]} ${now.getFullYear()}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // --- 2. FUNGSI FULLSCREEN ---
    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    // --- 3. INISIALISASI CHART.JS ---
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Poppins', sans-serif";

    // Chart Bar Sekolah
    const ctxSchool = document.getElementById('barSekolah').getContext('2d');
    const gradSchool = ctxSchool.createLinearGradient(0, 0, 0, 150);
    gradSchool.addColorStop(0, '#10b981'); gradSchool.addColorStop(1, 'rgba(16, 185, 129, 0.1)');

    new Chart(ctxSchool, {
        type: 'bar',
        data: {
            labels: ['RA', 'MI', 'MTs', 'MA', 'SMK'],
            datasets: [{
                data: [<?= $q_ra ?>, <?= $q_mi ?>, <?= $q_mts ?>, <?= $q_ma ?>, <?= $q_smk ?>],
                backgroundColor: gradSchool,
                borderRadius: 6,
                barThickness: 20
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: {size: 10} } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true, ticks: { stepSize: 5, font: {size: 10} } }
            }
        }
    });

    // Helper Donut Chart
    function createDonut(id, val1, val2, color) {
        new Chart(document.getElementById(id).getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Selesai', 'Belum'],
                datasets: [{
                    data: [val1, val2],
                    backgroundColor: [color, 'rgba(255,255,255,0.05)'],
                    borderWidth: 0, cutout: '75%', borderRadius: 20
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: {enabled: false} } }
        });
    }

    let total = <?= $q_pendaftar > 0 ? $q_pendaftar : 1 ?>; 
    createDonut('chartBerkas', <?= $q_lengkap ?>, total - <?= $q_lengkap ?>, '#8b5cf6');
    createDonut('chartMedis', <?= $q_sehat ?>, total - <?= $q_sehat ?>, '#ef4444');
    createDonut('chartSeragam', <?= $q_ukur ?>, total - <?= $q_ukur ?>, '#3b82f6');


    // --- 4. SISTEM NOTIFIKASI MELAYANG (AJAX POLLING) ---
    const toastContainer = document.getElementById('toast-container');
    let lastId = <?= $initial_max_id ?>;

    function showToast(data) {
        let isSuccess = (data.status_pendaftaran !== 'Batal' && data.status_pendaftaran !== 'Belum Lengkap');
        let toastClass = isSuccess ? 'toast-success' : 'toast-danger';
        let iconClass = isSuccess ? 'fas fa-check' : 'fas fa-times';
        let titleText = isSuccess ? 'Pendaftaran Baru Masuk!' : 'Pendaftaran Gagal/Batal';
        let descText = isSuccess ? `Siswa a.n <b>${data.nama_lengkap}</b> (${data.pilihan_sekolah}) baru saja terdaftar.` : `Pendaftaran atas nama <b>${data.nama_lengkap}</b> dibatalkan.`;

        const toastEl = document.createElement('div');
        toastEl.className = `floating-toast ${toastClass}`;
        toastEl.innerHTML = `
            <div class="toast-icon"><i class="${iconClass}"></i></div>
            <div class="toast-body-custom">
                <div class="toast-title">${titleText}</div>
                <div class="toast-desc">${descText}</div>
            </div>
        `;

        toastContainer.appendChild(toastEl);
        setTimeout(() => { toastEl.classList.add('show'); }, 100);

        setTimeout(() => {
            toastEl.classList.remove('show');
            setTimeout(() => { toastEl.remove(); }, 500);
        }, 7000);
    }

    setInterval(() => {
        fetch(`monitoring.php?ajax_new_reg=1&last_id=${lastId}`)
        .then(response => response.json())
        .then(newData => {
            if(newData.length > 0) {
                newData.forEach((item, index) => {
                    setTimeout(() => {
                        showToast(item);
                        if (parseInt(item.id) > lastId) {
                            lastId = parseInt(item.id);
                        }
                    }, index * 2000); 
                });

                setTimeout(() => {
                    window.location.reload();
                }, (newData.length * 2000) + 7000); 
            }
        })
        .catch(error => console.error("Gagal memuat notifikasi:", error));
    }, 5000);

</script>

</body>
</html>