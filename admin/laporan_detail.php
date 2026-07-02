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

// Konfigurasi Kategori Berdasarkan Parameter URL
$kategori = $_GET['kategori'] ?? '';
$kategori_valid = [
    'pendaftaran' => ['judul' => 'Uang Pendaftaran', 'kolom' => 'bayar_pendaftaran', 'target' => 150000, 'ikon' => 'fas fa-id-badge', 'warna' => '#0ea5e9'],
    'bangunan' => ['judul' => 'Infaq Bangunan', 'kolom' => 'bayar_bangunan', 'target' => 2000000, 'ikon' => 'fas fa-building', 'warna' => '#f59e0b'],
    'fasilitas' => ['judul' => 'Infaq Kursi & Meja', 'kolom' => 'bayar_fasilitas', 'target' => 500000, 'ikon' => 'fas fa-chair', 'warna' => '#8b5cf6'],
    'tahunan' => ['judul' => 'Kegiatan Tahunan', 'kolom' => 'bayar_tahunan', 'target' => 1200000, 'ikon' => 'fas fa-calendar-check', 'warna' => '#ec4899'],
    'bulanan' => ['judul' => 'Uang Makan/Asrama', 'kolom' => 'bayar_bulanan', 'target' => 550000, 'ikon' => 'fas fa-utensils', 'warna' => '#14b8a6'],
    'seragam' => ['judul' => 'Seragam Santri', 'kolom' => 'bayar_seragam', 'target' => 'dinamis', 'ikon' => 'fas fa-tshirt', 'warna' => '#f43f5e']
];

// Validasi jika kategori tidak ditemukan
if (!array_key_exists($kategori, $kategori_valid)) {
    die("Kategori laporan tidak valid. Silakan kembali ke halaman sebelumnya.");
}

$info = $kategori_valid[$kategori];
$kolom_db = $info['kolom'];

// Ambil Data
$query = "
    SELECT p.no_pendaftaran, d.nama_lengkap, d.jenis_kelamin, p.pilihan_sekolah, b.$kolom_db as jumlah_bayar 
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_pembayaran b ON p.id = b.pendaftaran_id
    ORDER BY d.nama_lengkap ASC
";
$result = $conn->query($query);

$total_lunas = 0;
$total_belum = 0;
$total_uang_masuk = 0;
$data_tabel = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Tentukan Target
        if ($info['target'] === 'dinamis') {
            $target = ($row['jenis_kelamin'] == 'Laki-laki') ? 950000 : 1000000;
        } else {
            $target = $info['target'];
        }

        $dibayar = $row['jumlah_bayar'] ?? 0;
        $sisa = $target - $dibayar;
        if ($sisa < 0) $sisa = 0;

        $is_lunas = ($dibayar >= $target);
        
        if ($is_lunas) {
            $total_lunas++;
        } else {
            $total_belum++;
        }
        $total_uang_masuk += $dibayar;

        $data_tabel[] = [
            'no_daftar' => $row['no_pendaftaran'],
            'nama' => $row['nama_lengkap'],
            'gender' => $row['jenis_kelamin'],
            'jenjang' => $row['pilihan_sekolah'],
            'target' => $target,
            'dibayar' => $dibayar,
            'sisa' => $sisa,
            'lunas' => $is_lunas
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Santri - <?= $info['judul'] ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root { --primary-green: #0da15b; --bg-body: #f4f7fa; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); color: var(--text-dark); font-size: 0.9rem; }
        
        .topbar-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; }
        .card-custom { background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 20px 25px; }

        .stat-card { background: #ffffff; border-radius: 15px; padding: 20px; display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9;}
        .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; border-top: none; white-space: nowrap; }
        .table-custom tbody td { padding: 12px 15px; vertical-align: middle; color: #334155; font-size: 0.85rem; font-weight: 500; border-bottom: 1px solid #f1f5f9; }

        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; display: inline-block; white-space: nowrap; text-align: center; border: 1px solid transparent; }
        .bg-lunas { background-color: #dcfce7; color: #16a34a; border-color: #bbf7d0;}
        .bg-belum { background-color: #fee2e2; color: #dc2626; border-color: #fecaca;}

        /* DT Fix */
        div.dataTables_wrapper div.dataTables_length label { display: flex; align-items: center; gap: 8px; font-weight: 500;}
        div.dataTables_wrapper div.dataTables_length select { border-radius: 8px; padding: 4px 30px 4px 10px; font-size: 0.85rem; border: 1px solid #cbd5e1; }
        div.dataTables_wrapper div.dataTables_filter input { border-radius: 50px; padding: 4px 15px; font-size: 0.85rem; border: 1px solid #cbd5e1; }
        .page-item.active .page-link { background-color: <?= $info['warna'] ?>; border-color: <?= $info['warna'] ?>; }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <a href="laporan_keuangan.php" class="btn btn-light me-3 shadow-sm" style="border-radius: 10px;"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h5 class="fw-bold text-dark m-0">Rincian Santri: <?= $info['judul'] ?></h5>
                    <p class="text-muted m-0" style="font-size: 0.8rem;">Daftar status lunas/belum lunas untuk kategori ini.</p>
                </div>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
            </div>
        </div>

        <!-- STATISTIK KATEGORI -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card bg-white shadow-sm">
                    <div class="stat-icon" style="background-color: <?= $info['warna'] ?>20; color: <?= $info['warna'] ?>;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Total Masuk (Kategori Ini)</p>
                        <h4 class="fw-bold mb-0 text-dark">Rp <?= number_format($total_uang_masuk, 0, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-white shadow-sm" style="border-left: 4px solid #10b981;">
                    <div class="stat-icon" style="background-color: #dcfce7; color: #10b981;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Santri Sudah Lunas</p>
                        <h4 class="fw-bold mb-0 text-dark"><?= $total_lunas ?> Orang</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-white shadow-sm" style="border-left: 4px solid #ef4444;">
                    <div class="stat-icon" style="background-color: #fee2e2; color: #ef4444;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Santri Belum Lunas</p>
                        <h4 class="fw-bold mb-0 text-dark"><?= $total_belum ?> Orang</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <div class="table-responsive">
                <table id="tabelDetail" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="30%">NAMA CALON SANTRI</th>
                            <th width="15%">TARGET BIAYA</th>
                            <th width="15%" class="text-success">TELAH DIBAYAR</th>
                            <th width="15%" class="text-danger">SISA KEKURANGAN</th>
                            <th width="20%" class="text-center">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach($data_tabel as $row) {
                            $badge_class = $row['lunas'] ? 'bg-lunas' : 'bg-belum';
                            $status_text = $row['lunas'] ? 'LUNAS' : 'BELUM LUNAS';
                            $gender_icon = ($row['gender'] == 'Laki-laki') ? '<i class="fas fa-male text-primary"></i>' : '<i class="fas fa-female text-danger"></i>';
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= $row['nama'] ?></div>
                                    <div class="text-muted mt-1" style="font-size:0.75rem;">
                                        <?= $gender_icon ?> <?= $row['gender'] ?> | <span class="badge bg-light text-dark border"><?= $row['jenjang'] ?></span>
                                    </div>
                                </td>
                                <td class="fw-medium text-dark">Rp <?= number_format($row['target'], 0, ',', '.') ?></td>
                                <td class="fw-bold text-success">Rp <?= number_format($row['dibayar'], 0, ',', '.') ?></td>
                                <td class="fw-bold <?= $row['sisa'] > 0 ? 'text-danger' : 'text-muted' ?>">Rp <?= number_format($row['sisa'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <span class="badge-status <?= $badge_class ?>"><?= $status_text ?></span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tabelDetail').DataTable({
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