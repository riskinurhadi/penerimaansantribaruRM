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

// Hanya Developer, Super Admin, dan Admin Keuangan yang boleh akses
if ($role != 'Developer' && $role != 'Super Admin' && $role != 'Admin Keuangan') {
    die("Akses ditolak! Anda tidak memiliki izin untuk melihat laporan keuangan.");
}

// Target Biaya Standar
$t_pendaftaran = 150000;
$t_bangunan = 2000000;
$t_fasilitas = 500000;
$t_tahunan = 1200000;
$t_bulanan = 550000;

// Data Laporan Kosong
$laporan = [
    'pendaftaran' => ['target' => $t_pendaftaran, 'terkumpul' => 0, 'lunas' => [], 'belum' => []],
    'bangunan' => ['target' => $t_bangunan, 'terkumpul' => 0, 'lunas' => [], 'belum' => []],
    'fasilitas' => ['target' => $t_fasilitas, 'terkumpul' => 0, 'lunas' => [], 'belum' => []],
    'tahunan' => ['target' => $t_tahunan, 'terkumpul' => 0, 'lunas' => [], 'belum' => []],
    'bulanan' => ['target' => $t_bulanan, 'terkumpul' => 0, 'lunas' => [], 'belum' => []],
    'seragam' => ['target' => 'Menyesuaikan Gender', 'terkumpul' => 0, 'lunas' => [], 'belum' => []]
];

$total_semua_target = 0;
$total_semua_terkumpul = 0;
$list_santri = [];

// Ambil Seluruh Data
$query = "
    SELECT p.no_pendaftaran, d.nama_lengkap, d.jenis_kelamin, b.* FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_pembayaran b ON p.id = b.pendaftaran_id
    ORDER BY d.nama_lengkap ASC
";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $nama = htmlspecialchars($row['nama_lengkap']);
        $t_seragam = ($row['jenis_kelamin'] == 'Laki-laki') ? 950000 : 1000000;
        
        // Target: Jika sudah ada target tersimpan di DB, gunakan itu. Jika belum, gunakan estimasi standar.
        $target_siswa = ($row['total_biaya'] > 0) ? $row['total_biaya'] : ($t_pendaftaran + $t_bangunan + $t_fasilitas + $t_tahunan + $t_bulanan + $t_seragam);
        $total_semua_target += $target_siswa;
        
        // PERBAIKAN: Total terkumpul mengambil langsung dari DB agar sinkron dengan halaman keuangan
        $total_semua_terkumpul += (float) ($row['jumlah_dibayar'] ?? 0);
        
        // Kalkulasi Masing-masing Kategori
        // Pendaftaran
        $b_daftar = $row['bayar_pendaftaran'] ?? 0;
        $laporan['pendaftaran']['terkumpul'] += $b_daftar;
        if ($b_daftar >= $t_pendaftaran) $laporan['pendaftaran']['lunas'][] = $nama; else $laporan['pendaftaran']['belum'][] = $nama;

        // Bangunan
        $b_bangunan = $row['bayar_bangunan'] ?? 0;
        $laporan['bangunan']['terkumpul'] += $b_bangunan;
        if ($b_bangunan >= $t_bangunan) $laporan['bangunan']['lunas'][] = $nama; else $laporan['bangunan']['belum'][] = $nama;

        // Fasilitas
        $b_fasilitas = $row['bayar_fasilitas'] ?? 0;
        $laporan['fasilitas']['terkumpul'] += $b_fasilitas;
        if ($b_fasilitas >= $t_fasilitas) $laporan['fasilitas']['lunas'][] = $nama; else $laporan['fasilitas']['belum'][] = $nama;

        // Tahunan
        $b_tahunan = $row['bayar_tahunan'] ?? 0;
        $laporan['tahunan']['terkumpul'] += $b_tahunan;
        if ($b_tahunan >= $t_tahunan) $laporan['tahunan']['lunas'][] = $nama; else $laporan['tahunan']['belum'][] = $nama;

        // Bulanan
        $b_bulanan = $row['bayar_bulanan'] ?? 0;
        $laporan['bulanan']['terkumpul'] += $b_bulanan;
        if ($b_bulanan >= $t_bulanan) $laporan['bulanan']['lunas'][] = $nama; else $laporan['bulanan']['belum'][] = $nama;

        // Seragam
        $b_seragam = $row['bayar_seragam'] ?? 0;
        $laporan['seragam']['terkumpul'] += $b_seragam;
        if ($b_seragam >= $t_seragam) $laporan['seragam']['lunas'][] = $nama; else $laporan['seragam']['belum'][] = $nama;

        // Data Tracking Individu
        $list_santri[] = [
            'nama' => $nama,
            'no' => $row['no_pendaftaran'],
            'prog_pendaftaran' => min(100, round(($b_daftar / $t_pendaftaran) * 100)),
            'prog_bangunan' => min(100, round(($b_bangunan / $t_bangunan) * 100)),
            'prog_fasilitas' => min(100, round(($b_fasilitas / $t_fasilitas) * 100)),
            'prog_tahunan' => min(100, round(($b_tahunan / $t_tahunan) * 100)),
            'prog_bulanan' => min(100, round(($b_bulanan / $t_bulanan) * 100)),
            'prog_seragam' => min(100, round(($b_seragam / $t_seragam) * 100))
        ];
    }
}
$total_santri = count($list_santri);

// Fungsi Bantu untuk Render Card
function renderProgressCard($id, $title, $icon, $color, $target_per_siswa, $terkumpul, $lunas_arr, $belum_arr, $total_siswa) {
    // Menghitung % dari orang yang lunas
    $pct_lunas = $total_siswa > 0 ? round((count($lunas_arr) / $total_siswa) * 100) : 0;
    
    echo "
    <div class='col-xl-4 col-md-6 mb-4'>
        <div class='card card-progress border-0 h-100 shadow-sm' style='border-radius:15px; overflow:hidden;'>
            <div class='card-body p-4'>
                <div class='d-flex align-items-center mb-3'>
                    <div style='width: 45px; height: 45px; border-radius: 12px; background-color: {$color}20; color: {$color}; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 15px;'>
                        <i class='{$icon}'></i>
                    </div>
                    <div>
                        <h6 class='fw-bold m-0 text-dark'>{$title}</h6>
                        <small class='text-muted'>Rp " . (is_numeric($target_per_siswa) ? number_format($target_per_siswa,0,',','.') : $target_per_siswa) . " / Siswa</small>
                    </div>
                </div>
                
                <div class='d-flex justify-content-between align-items-end mb-1 mt-4'>
                    <span class='text-muted' style='font-size:0.8rem;'>Santri Lunas</span>
                    <span class='fw-bold' style='color: {$color};'>{$pct_lunas}% (" . count($lunas_arr) . " org)</span>
                </div>
                <div class='progress' style='height: 8px; border-radius: 10px; background-color: #f1f5f9;'>
                    <div class='progress-bar' role='progressbar' style='width: {$pct_lunas}%; background-color: {$color}; border-radius: 10px;'></div>
                </div>
                
                <div class='d-flex justify-content-between mt-3 mb-3 border-top pt-3'>
                    <div>
                        <div class='text-muted' style='font-size:0.75rem;'>Total Masuk</div>
                        <div class='fw-bold text-dark' style='font-size:0.9rem;'>Rp " . number_format($terkumpul,0,',','.') . "</div>
                    </div>
                    <div class='text-end'>
                        <div class='text-muted' style='font-size:0.75rem;'>Belum Lunas</div>
                        <div class='fw-bold text-danger' style='font-size:0.9rem;'>" . count($belum_arr) . " org</div>
                    </div>
                </div>

                <a href='laporan_detail.php?kategori={$id}' class='btn w-100 fw-medium text-white shadow-sm' style='background-color: {$color}; border-radius: 50px; font-size: 0.85rem;'>
                    <i class='fas fa-list-ul me-2'></i> Lihat Daftar Santri
                </a>
            </div>
        </div>
    </div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Detail Keuangan - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root { --primary-green: #0da15b; --bg-body: #f4f7fa; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); color: var(--text-dark); font-size: 0.9rem; }
        .topbar-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; }
        
        .card-progress { transition: transform 0.3s; }
        .card-progress:hover { transform: translateY(-5px); }
        
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02);}
        .table-custom thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; padding: 15px; border-bottom: 1px solid #e2e8f0; border-top: none; white-space: nowrap; }
        .table-custom tbody td { padding: 12px 15px; vertical-align: middle; color: #334155; font-size: 0.8rem; font-weight: 500; border-bottom: 1px solid #f1f5f9; }
        
        .mini-progress-wrapper { margin-bottom: 3px; }
        .mini-label { font-size: 0.65rem; color: #64748b; display: flex; justify-content: space-between; margin-bottom: 2px;}
        .mini-progress { height: 5px; background-color: #e2e8f0; border-radius: 10px; overflow: hidden;}
        .m-bar { height: 100%; border-radius: 10px; }

        .list-group-item-custom { border: none; border-bottom: 1px solid #f1f5f9; padding: 12px 15px; font-weight: 500; color: #334155; font-size: 0.9rem; display: flex; align-items: center;}
        .list-group-item-custom i { width: 25px; color: #cbd5e1; }
        
        /* DT Fix */
        div.dataTables_wrapper div.dataTables_length select { border-radius: 8px; padding: 4px 30px 4px 10px; font-size: 0.85rem; border: 1px solid #cbd5e1; }
        div.dataTables_wrapper div.dataTables_filter input { border-radius: 50px; padding: 4px 15px; font-size: 0.85rem; border: 1px solid #cbd5e1; }
        .page-item.active .page-link { background-color: var(--primary-green); border-color: var(--primary-green); }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <a href="keuangan.php" class="btn btn-light me-3 shadow-sm" style="border-radius: 10px;"><i class="fas fa-arrow-left"></i></a>
                <h5 class="fw-bold text-dark m-0">Laporan Detail & Progress Keuangan</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
            </div>
        </div>

        <div class="alert bg-white border-0 shadow-sm p-4 d-flex align-items-center justify-content-between mb-4" style="border-radius: 15px;">
            <div>
                <h5 class="fw-bold text-dark"><i class="fas fa-chart-line text-primary-green me-2"></i> Progress Pendapatan Yayasan</h5>
                <p class="text-muted m-0">Total akumulasi seluruh setoran santri berbanding dengan target keseluruhan.</p>
            </div>
            <div class="text-end">
                <h3 class="fw-bold text-success m-0">Rp <?= number_format($total_semua_terkumpul, 0, ',', '.') ?></h3>
                <small class="text-muted">Target Aktual: Rp <?= number_format($total_semua_target, 0, ',', '.') ?></small>
            </div>
        </div>

        <!-- CARD PERSENTASE PER KATEGORI -->
        <h5 class="fw-bold text-dark mb-3">Persentase Kelulusan Biaya per Kategori</h5>
        <div class="row">
            <?php
                renderProgressCard('pendaftaran', 'Uang Pendaftaran', 'fas fa-id-badge', '#0ea5e9', $laporan['pendaftaran']['target'], $laporan['pendaftaran']['terkumpul'], $laporan['pendaftaran']['lunas'], $laporan['pendaftaran']['belum'], $total_santri);
                renderProgressCard('bangunan', 'Infaq Bangunan', 'fas fa-building', '#f59e0b', $laporan['bangunan']['target'], $laporan['bangunan']['terkumpul'], $laporan['bangunan']['lunas'], $laporan['bangunan']['belum'], $total_santri);
                renderProgressCard('fasilitas', 'Infaq Kursi & Meja', 'fas fa-chair', '#8b5cf6', $laporan['fasilitas']['target'], $laporan['fasilitas']['terkumpul'], $laporan['fasilitas']['lunas'], $laporan['fasilitas']['belum'], $total_santri);
                renderProgressCard('tahunan', 'Kegiatan Tahunan', 'fas fa-calendar-check', '#ec4899', $laporan['tahunan']['target'], $laporan['tahunan']['terkumpul'], $laporan['tahunan']['lunas'], $laporan['tahunan']['belum'], $total_santri);
                renderProgressCard('bulanan', 'Uang Makan/Asrama', 'fas fa-utensils', '#14b8a6', $laporan['bulanan']['target'], $laporan['bulanan']['terkumpul'], $laporan['bulanan']['lunas'], $laporan['bulanan']['belum'], $total_santri);
                renderProgressCard('seragam', 'Seragam Santri', 'fas fa-tshirt', '#f43f5e', $laporan['seragam']['target'], $laporan['seragam']['terkumpul'], $laporan['seragam']['lunas'], $laporan['seragam']['belum'], $total_santri);
            ?>
        </div>

        <!-- TRACKING INDIVIDU -->
        <h5 class="fw-bold text-dark mb-3 mt-4">Tracking Progress Pembayaran per Santri</h5>
        <div class="table-responsive bg-white p-3 rounded-4 shadow-sm">
            <table id="tabelTracking" class="table table-custom">
                <thead>
                    <tr>
                        <th width="5%">NO</th>
                        <th width="20%">SANTRI</th>
                        <th width="12%">PENDAFTARAN</th>
                        <th width="12%">BANGUNAN</th>
                        <th width="12%">FASILITAS</th>
                        <th width="12%">TAHUNAN</th>
                        <th width="12%">ASRAMA</th>
                        <th width="15%">SERAGAM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach($list_santri as $s) {
                        // Function to return color based on %
                        function getBarColor($pct) {
                            if($pct >= 100) return '#10b981'; // Green
                            if($pct > 0) return '#f59e0b'; // Yellow/Orange
                            return '#ef4444'; // Red
                        }
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <strong class="text-dark"><?= $s['nama'] ?></strong><br>
                            <small class="text-muted"><?= $s['no'] ?></small>
                        </td>
                        <td>
                            <div class="mini-label"><span>Progres</span> <span style="color:<?= getBarColor($s['prog_pendaftaran']) ?>; font-weight:bold;"><?= $s['prog_pendaftaran'] ?>%</span></div>
                            <div class="mini-progress"><div class="m-bar" style="width: <?= $s['prog_pendaftaran'] ?>%; background-color: <?= getBarColor($s['prog_pendaftaran']) ?>;"></div></div>
                        </td>
                        <td>
                            <div class="mini-label"><span>Progres</span> <span style="color:<?= getBarColor($s['prog_bangunan']) ?>; font-weight:bold;"><?= $s['prog_bangunan'] ?>%</span></div>
                            <div class="mini-progress"><div class="m-bar" style="width: <?= $s['prog_bangunan'] ?>%; background-color: <?= getBarColor($s['prog_bangunan']) ?>;"></div></div>
                        </td>
                        <td>
                            <div class="mini-label"><span>Progres</span> <span style="color:<?= getBarColor($s['prog_fasilitas']) ?>; font-weight:bold;"><?= $s['prog_fasilitas'] ?>%</span></div>
                            <div class="mini-progress"><div class="m-bar" style="width: <?= $s['prog_fasilitas'] ?>%; background-color: <?= getBarColor($s['prog_fasilitas']) ?>;"></div></div>
                        </td>
                        <td>
                            <div class="mini-label"><span>Progres</span> <span style="color:<?= getBarColor($s['prog_tahunan']) ?>; font-weight:bold;"><?= $s['prog_tahunan'] ?>%</span></div>
                            <div class="mini-progress"><div class="m-bar" style="width: <?= $s['prog_tahunan'] ?>%; background-color: <?= getBarColor($s['prog_tahunan']) ?>;"></div></div>
                        </td>
                        <td>
                            <div class="mini-label"><span>Progres</span> <span style="color:<?= getBarColor($s['prog_bulanan']) ?>; font-weight:bold;"><?= $s['prog_bulanan'] ?>%</span></div>
                            <div class="mini-progress"><div class="m-bar" style="width: <?= $s['prog_bulanan'] ?>%; background-color: <?= getBarColor($s['prog_bulanan']) ?>;"></div></div>
                        </td>
                        <td>
                            <div class="mini-label"><span>Progres</span> <span style="color:<?= getBarColor($s['prog_seragam']) ?>; font-weight:bold;"><?= $s['prog_seragam'] ?>%</span></div>
                            <div class="mini-progress"><div class="m-bar" style="width: <?= $s['prog_seragam'] ?>%; background-color: <?= getBarColor($s['prog_seragam']) ?>;"></div></div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tabelTracking').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json', search: "_INPUT_", searchPlaceholder: "Cari nama santri..." },
            "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "pageLength": 10
        });

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    });
</script>

</body>
</html>