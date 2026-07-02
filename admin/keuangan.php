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

// Hanya Developer, Super Admin, dan Admin Keuangan yang boleh akses
if ($role != 'Developer' && $role != 'Super Admin' && $role != 'Admin Keuangan') {
    die("Akses ditolak! Anda tidak memiliki izin untuk mengelola keuangan.");
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

// =========================================================================
// AUTO-PATCH DATABASE: Tambah kolom rincian biaya & petugas jika belum ada
// =========================================================================
$check_col = $conn->query("SHOW COLUMNS FROM data_pembayaran LIKE 'bayar_pendaftaran'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE data_pembayaran 
        ADD COLUMN bayar_pendaftaran DECIMAL(15,2) DEFAULT 0 AFTER status_pembayaran,
        ADD COLUMN bayar_bangunan DECIMAL(15,2) DEFAULT 0 AFTER bayar_pendaftaran,
        ADD COLUMN bayar_fasilitas DECIMAL(15,2) DEFAULT 0 AFTER bayar_bangunan,
        ADD COLUMN bayar_tahunan DECIMAL(15,2) DEFAULT 0 AFTER bayar_fasilitas,
        ADD COLUMN bayar_bulanan DECIMAL(15,2) DEFAULT 0 AFTER bayar_tahunan,
        ADD COLUMN bayar_seragam DECIMAL(15,2) DEFAULT 0 AFTER bayar_bulanan
    ");
}

// Auto-Patch Kolom Petugas
$check_col_petugas = $conn->query("SHOW COLUMNS FROM data_pembayaran LIKE 'petugas'");
if ($check_col_petugas && $check_col_petugas->num_rows == 0) {
    $conn->query("ALTER TABLE data_pembayaran ADD COLUMN petugas VARCHAR(100) NULL AFTER catatan_kesepakatan");
}

$status_pesan = '';

// --- PROSES UPDATE DATA KEUANGAN JIKA FORM MODAL DISUBMIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_pendaftaran'])) {
    $id_update = intval($_POST['id_pendaftaran']);
    $jenis_kelamin = $_POST['jenis_kelamin_siswa']; 
    
    $skema_pembayaran = mysqli_real_escape_string($conn, trim($_POST['skema_pembayaran']));
    $catatan_kesepakatan = mysqli_real_escape_string($conn, trim($_POST['catatan_kesepakatan']));
    
    // Ambil nama petugas yang login
    $petugas_simpan = mysqli_real_escape_string($conn, $nama_lengkap_admin . ' (' . $role . ')');

    $b_daftar = (float) preg_replace('/[^0-9]/', '', $_POST['bayar_pendaftaran']);
    $b_bangunan = (float) preg_replace('/[^0-9]/', '', $_POST['bayar_bangunan']);
    $b_fasilitas = (float) preg_replace('/[^0-9]/', '', $_POST['bayar_fasilitas']);
    $b_tahunan = (float) preg_replace('/[^0-9]/', '', $_POST['bayar_tahunan']);
    $b_bulanan = (float) preg_replace('/[^0-9]/', '', $_POST['bayar_bulanan']);
    $b_seragam = (float) preg_replace('/[^0-9]/', '', $_POST['bayar_seragam']);

    $jumlah_dibayar = $b_daftar + $b_bangunan + $b_fasilitas + $b_tahunan + $b_bulanan + $b_seragam;

    $target_seragam = ($jenis_kelamin == 'Laki-laki') ? 950000 : 1000000;
    $total_biaya = 150000 + 2000000 + 500000 + 1200000 + 550000 + $target_seragam;

    $sisa_tagihan = $total_biaya - $jumlah_dibayar;
    if ($sisa_tagihan < 0) $sisa_tagihan = 0;

    if ($jumlah_dibayar == 0) {
        $status_bayar = 'Belum Bayar';
    } elseif ($sisa_tagihan <= 0) {
        $status_bayar = 'Lunas';
    } else {
        $status_bayar = 'Belum Lunas';
    }

    $cek = $conn->query("SELECT id FROM data_pembayaran WHERE pendaftaran_id = $id_update");
    if ($cek && $cek->num_rows > 0) {
        $sql = "UPDATE data_pembayaran SET 
                skema_pembayaran='$skema_pembayaran', total_biaya=$total_biaya, jumlah_dibayar=$jumlah_dibayar, 
                sisa_tagihan=$sisa_tagihan, status_pembayaran='$status_bayar', catatan_kesepakatan='$catatan_kesepakatan',
                bayar_pendaftaran=$b_daftar, bayar_bangunan=$b_bangunan, bayar_fasilitas=$b_fasilitas, 
                bayar_tahunan=$b_tahunan, bayar_bulanan=$b_bulanan, bayar_seragam=$b_seragam, petugas='$petugas_simpan'
                WHERE pendaftaran_id=$id_update";
    } else {
        $sql = "INSERT INTO data_pembayaran (pendaftaran_id, skema_pembayaran, total_biaya, jumlah_dibayar, sisa_tagihan, status_pembayaran, catatan_kesepakatan, bayar_pendaftaran, bayar_bangunan, bayar_fasilitas, bayar_tahunan, bayar_bulanan, bayar_seragam, petugas) 
                VALUES ($id_update, '$skema_pembayaran', $total_biaya, $jumlah_dibayar, $sisa_tagihan, '$status_bayar', '$catatan_kesepakatan', $b_daftar, $b_bangunan, $b_fasilitas, $b_tahunan, $b_bulanan, $b_seragam, '$petugas_simpan')";
    }

    if ($conn->query($sql)) {
        $status_pesan = 'sukses';
    } else {
        $status_pesan = 'gagal';
    }
}

// --- AMBIL DATA PENDAFTAR & PEMBAYARAN LENGKAP ---
$query = "
    SELECT 
        p.id, 
        p.no_pendaftaran, 
        d.nama_lengkap, 
        d.jenis_kelamin,
        p.pilihan_sekolah,
        IFNULL(b.skema_pembayaran, 'Lunas') as skema_pembayaran,
        IFNULL(b.total_biaya, 0) as total_biaya,
        IFNULL(b.jumlah_dibayar, 0) as jumlah_dibayar,
        IFNULL(b.sisa_tagihan, 0) as sisa_tagihan,
        IFNULL(b.status_pembayaran, 'Belum Bayar') as status_pembayaran,
        IFNULL(b.catatan_kesepakatan, '') as catatan_kesepakatan,
        IFNULL(b.bayar_pendaftaran, 0) as bayar_pendaftaran,
        IFNULL(b.bayar_bangunan, 0) as bayar_bangunan,
        IFNULL(b.bayar_fasilitas, 0) as bayar_fasilitas,
        IFNULL(b.bayar_tahunan, 0) as bayar_tahunan,
        IFNULL(b.bayar_bulanan, 0) as bayar_bulanan,
        IFNULL(b.bayar_seragam, 0) as bayar_seragam,
        IFNULL(b.petugas, 'Belum diproses') as petugas
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_pembayaran b ON p.id = b.pendaftaran_id
    ORDER BY p.created_at DESC
";
$result = $conn->query($query);

// --- QUERY STATISTIK ---
$q_lunas = $conn->query("SELECT COUNT(*) as total FROM data_pembayaran WHERE status_pembayaran = 'Lunas'")->fetch_assoc()['total'] ?? 0;
$q_belum_lunas = $conn->query("SELECT COUNT(*) as total FROM data_pembayaran WHERE status_pembayaran = 'Belum Lunas'")->fetch_assoc()['total'] ?? 0;
$q_belum_bayar = $conn->query("SELECT COUNT(*) as total FROM pendaftaran p LEFT JOIN data_pembayaran b ON p.id=b.pendaftaran_id WHERE b.status_pembayaran = 'Belum Bayar' OR b.id IS NULL")->fetch_assoc()['total'] ?? 0;
$q_total_uang = $conn->query("SELECT SUM(jumlah_dibayar) as total FROM data_pembayaran")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Keuangan - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root { --primary-green: #0da15b; --dark-green: #087d46; --bg-body: #f4f7fa; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); color: var(--text-dark); font-size: 0.9rem; }
        
        .topbar-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; }
        .card-custom { background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 20px 25px; }

        .stat-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 20px; display: flex; align-items: center; gap: 15px; transition: transform 0.3s; border: 1px solid rgba(0,0,0,0.03); }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 55px; height: 55px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; border-top: none; white-space: nowrap; }
        .table-custom tbody td { padding: 12px 15px; vertical-align: middle; color: #334155; font-size: 0.85rem; font-weight: 500; border-bottom: 1px solid #f1f5f9; }

        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; display: inline-block; white-space: nowrap; text-align: center; }
        .bg-lunas { background-color: #dcfce7; color: #16a34a; }
        .bg-belum-lunas { background-color: #fef3c7; color: #d97706; }
        .bg-belum-bayar { background-color: #fee2e2; color: #dc2626; }
        .badge-skema { padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 500; background-color: #f1f5f9; color: var(--text-muted); border: 1px solid #e2e8f0; }

        /* Action Buttons */
        .btn-act { border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.8rem; transition: 0.2s; color: white;}
        .btn-act-view { background-color: #0ea5e9; }
        .btn-act-view:hover { background-color: #0284c7; transform: translateY(-2px);}
        .btn-act-edit { background-color: #fef08a; color: #ca8a04; }
        .btn-act-edit:hover { background-color: #fde047; color: #a16207; transform: translateY(-2px);}
        .btn-act-print { background-color: #1e293b; }
        .btn-act-print:hover { background-color: #0f172a; transform: translateY(-2px);}

        /* Styling Form Bayar Rincian */
        .fee-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; margin-bottom: 10px; }
        .fee-label { font-size: 0.85rem; color: var(--text-dark); font-weight: 500; }
        .fee-target { font-size: 0.75rem; color: var(--text-muted); }
        .input-nominal { width: 130px; text-align: right; font-weight: bold; color: var(--primary-green); border-radius: 8px; border: 1px solid #cbd5e1; padding: 5px 10px;}
        .input-nominal:focus { border-color: var(--primary-green); outline: none; box-shadow: 0 0 0 2px rgba(13,161,91,0.2); }
        .nominal-read { font-weight: bold; color: var(--text-dark); font-size: 0.9rem; }

        div.dataTables_wrapper div.dataTables_length label { display: flex; align-items: center; gap: 8px; }
        div.dataTables_wrapper div.dataTables_length select, div.dataTables_wrapper div.dataTables_filter input { border-radius: 8px; border: 1px solid #cbd5e1; }
        .page-item.active .page-link { background-color: var(--primary-green); border-color: var(--primary-green); }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3 shadow-sm" id="sidebarToggle" style="border-radius: 10px;"><i class="fas fa-bars"></i></button>
                <h5 class="fw-bold text-dark m-0">Keuangan & Pembayaran</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <!-- Menampilkan Foto Profil Asli -->
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #dcfce7; color: #16a34a;"><i class="fas fa-wallet"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">Rp <?= number_format($q_total_uang, 0, ',', '.') ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Total Dana Masuk</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_lunas ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Siswa Lunas</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fef3c7; color: #d97706;"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_belum_lunas ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Belum Lunas (Cicil)</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fee2e2; color: #dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_belum_bayar ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Siswa Belum Bayar</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <!-- Header area: Title and Detail Laporan button -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <h5 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Rincian Tagihan Siswa</h5>
                <a href="laporan_keuangan.php" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold" style="font-size: 0.85rem;">
                    <i class="fas fa-chart-bar me-2"></i> Laporan Detail & Progres Keuangan
                </a>
            </div>

            <div class="table-responsive">
                <table id="tabelKeuangan" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="15%">NO DAFTAR</th>
                            <th width="25%">NAMA LENGKAP</th>
                            <th width="20%">RINGKASAN BIAYA</th>
                            <th width="12%" class="text-center">STATUS</th>
                            <th width="20%" class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                $badge_class = 'bg-belum-bayar';
                                if ($row['status_pembayaran'] == 'Lunas') $badge_class = 'bg-lunas';
                                if ($row['status_pembayaran'] == 'Belum Lunas') $badge_class = 'bg-belum-lunas';

                                // Hitung total tagihan seharusnya (Tergantung Gender)
                                $target_seragam = ($row['jenis_kelamin'] == 'Laki-laki') ? 950000 : 1000000;
                                $target_total = 150000 + 2000000 + 500000 + 1200000 + 550000 + $target_seragam;
                                
                                // Jika DB masih kosong/belum update, gunakan hitungan target. Jika sudah ada, gunakan dari DB.
                                $display_total = ($row['total_biaya'] > 0) ? $row['total_biaya'] : $target_total;
                                $display_sisa = ($row['total_biaya'] > 0) ? $row['sisa_tagihan'] : $target_total;
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold" style="color: var(--primary-green);"><?= htmlspecialchars($row['no_pendaftaran']) ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['nama_lengkap']) ?> <br>
                                    <span class="badge-skema mt-1"><i class="fas fa-venus-mars me-1"></i> <?= $row['jenis_kelamin'] ?></span>
                                </td>
                                <td>
                                    <div class="text-muted" style="font-size: 0.8rem;">Tagihan: Rp <?= number_format($display_total, 0, ',', '.') ?></div>
                                    <div class="fw-bold text-success">Dibayar: Rp <?= number_format($row['jumlah_dibayar'], 0, ',', '.') ?></div>
                                    <?php if($display_sisa > 0): ?>
                                        <div class="text-danger fw-bold" style="font-size: 0.8rem;">Sisa: Rp <?= number_format($display_sisa, 0, ',', '.') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($row['status_pembayaran']) ?></span></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <!-- Tombol Lihat Detail -->
                                        <button type="button" class="btn-act btn-act-view" title="Lihat Detail Pembayaran" 
                                            onclick="bukaModalDetail('<?= addslashes($row['nama_lengkap']) ?>', '<?= $row['jenis_kelamin'] ?>', '<?= $row['skema_pembayaran'] ?>', '<?= addslashes($row['catatan_kesepakatan']) ?>', <?= $row['bayar_pendaftaran'] ?>, <?= $row['bayar_bangunan'] ?>, <?= $row['bayar_fasilitas'] ?>, <?= $row['bayar_tahunan'] ?>, <?= $row['bayar_bulanan'] ?>, <?= $row['bayar_seragam'] ?>, <?= $display_total ?>, <?= $row['jumlah_dibayar'] ?>, <?= $display_sisa ?>, '<?= addslashes($row['petugas']) ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- Tombol Update / Input -->
                                        <button type="button" class="btn-act btn-act-edit" title="Input Pembayaran" 
                                            onclick="bukaModalEdit(<?= $row['id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>', '<?= $row['jenis_kelamin'] ?>', '<?= $row['skema_pembayaran'] ?>', '<?= addslashes($row['catatan_kesepakatan']) ?>', <?= $row['bayar_pendaftaran'] ?>, <?= $row['bayar_bangunan'] ?>, <?= $row['bayar_fasilitas'] ?>, <?= $row['bayar_tahunan'] ?>, <?= $row['bayar_bulanan'] ?>, <?= $row['bayar_seragam'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <!-- Tombol Cetak Kwitansi -->
                                        <a href="cetak_kwitansi.php?id=<?= $row['id'] ?>" target="_blank" class="btn-act btn-act-print" title="Cetak Kuitansi/Struk">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Edit Pembayaran -->
<div class="modal fade" id="modalKeuangan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <form action="" method="POST" id="formKeuangan">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-invoice-dollar text-primary-green me-2"></i> Form Input Pembayaran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body bg-light rounded m-3 p-3 pt-2">
            
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <div class="fw-bold fs-5 text-dark" id="nama_siswa">Nama Siswa</div>
                <span class="badge bg-secondary" id="badge_gender">L/P</span>
            </div>
            
            <input type="hidden" name="id_pendaftaran" id="id_pendaftaran">
            <input type="hidden" name="jenis_kelamin_siswa" id="jenis_kelamin_siswa">

            <div class="row">
                <div class="col-md-7 border-end pe-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-list-ul me-1 text-muted"></i> Daftar Tagihan & Setoran</h6>
                    
                    <div class="fee-row">
                        <div><div class="fee-label">Pendaftaran</div><div class="fee-target">Biaya: Rp 150.000</div></div>
                        <input type="text" class="input-nominal item-bayar" name="bayar_pendaftaran" id="bayar_pendaftaran" value="0" onkeyup="formatRupiah(this); hitungSemua();">
                    </div>
                    <div class="fee-row">
                        <div><div class="fee-label">Infaq Bangunan</div><div class="fee-target">Biaya: Rp 2.000.000</div></div>
                        <input type="text" class="input-nominal item-bayar" name="bayar_bangunan" id="bayar_bangunan" value="0" onkeyup="formatRupiah(this); hitungSemua();">
                    </div>
                    <div class="fee-row">
                        <div><div class="fee-label">Infaq Kursi & Meja</div><div class="fee-target">Biaya: Rp 500.000</div></div>
                        <input type="text" class="input-nominal item-bayar" name="bayar_fasilitas" id="bayar_fasilitas" value="0" onkeyup="formatRupiah(this); hitungSemua();">
                    </div>
                    <div class="fee-row">
                        <div><div class="fee-label">Kegiatan Tahunan</div><div class="fee-target">Biaya: Rp 1.200.000</div></div>
                        <input type="text" class="input-nominal item-bayar" name="bayar_tahunan" id="bayar_tahunan" value="0" onkeyup="formatRupiah(this); hitungSemua();">
                    </div>
                    <div class="fee-row">
                        <div><div class="fee-label">Uang Makan/Asrama</div><div class="fee-target">Bulan Pertama: Rp 550.000</div></div>
                        <input type="text" class="input-nominal item-bayar" name="bayar_bulanan" id="bayar_bulanan" value="0" onkeyup="formatRupiah(this); hitungSemua();">
                    </div>
                    <div class="fee-row border-0 mb-0 pb-0">
                        <div><div class="fee-label">Seragam Santri</div><div class="fee-target" id="lbl_target_seragam">Biaya: -</div></div>
                        <input type="text" class="input-nominal item-bayar" name="bayar_seragam" id="bayar_seragam" value="0" onkeyup="formatRupiah(this); hitungSemua();">
                    </div>
                </div>

                <div class="col-md-5 ps-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-calculator me-1 text-muted"></i> Ringkasan</h6>
                    <div class="bg-white p-3 rounded border mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted" style="font-size: 0.85rem;">Total Tagihan:</span>
                            <span class="fw-bold text-dark" id="sum_tagihan">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted" style="font-size: 0.85rem;">Total Disetor:</span>
                            <span class="fw-bold text-success" id="sum_disetor">Rp 0</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold text-danger" style="font-size: 0.85rem;">SISA KURANG:</span>
                            <span class="fw-bold text-danger fs-5" id="sum_sisa">Rp 0</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-dark" style="font-size: 0.8rem; font-weight: 500;">Skema Kesepakatan</label>
                        <select class="form-select form-select-sm" name="skema_pembayaran" id="skema_pembayaran">
                            <option value="Lunas">Pelunasan Tunai</option>
                            <option value="Cicilan Perbulan">Cicilan Bertahap</option>
                            <option value="Kesepakatan Khusus">Kesepakatan Khusus Pimpinan</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-dark" style="font-size: 0.8rem; font-weight: 500;">Catatan Admin (Opsional)</label>
                        <textarea class="form-control form-control-sm" name="catatan_kesepakatan" id="catatan_kesepakatan" rows="2" placeholder="Catatan pembayaran..."></textarea>
                    </div>
                </div>
            </div>

          </div>
          <div class="modal-footer border-top-0 pt-0 pe-4 pb-3">
            <button type="button" class="btn text-muted fw-medium" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn text-white px-4" style="background-color: var(--primary-green); border-radius: 50px; font-weight: 500;"><i class="fas fa-save me-2"></i> Simpan Transaksi</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Lihat Detail (Read Only) -->
<div class="modal fade" id="modalDetailKeuangan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 15px; border: none;">
        <div class="modal-header border-bottom-0 pb-0">
          <h5 class="modal-title fw-bold text-dark"><i class="fas fa-info-circle text-info me-2"></i> Detail Histori Pembayaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body bg-light rounded m-3 p-4 pt-3">
          
          <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
              <div>
                  <div class="fw-bold fs-5 text-dark" id="det_nama_siswa">Nama Siswa</div>
                  <span class="badge bg-secondary mt-1" id="det_badge_gender">L/P</span>
              </div>
              <div class="text-end">
                  <div class="text-muted" style="font-size: 0.8rem;">Skema Kesepakatan</div>
                  <div class="fw-bold text-primary" id="det_skema">Lunas</div>
              </div>
          </div>
          
          <div class="row">
              <div class="col-md-6 border-end pe-4">
                  <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-money-check-alt me-2 text-muted"></i> Rincian Dana Masuk</h6>
                  
                  <div class="fee-row">
                      <div class="fee-label">1. Biaya Pendaftaran</div>
                      <div class="nominal-read" id="det_pendaftaran">Rp 0</div>
                  </div>
                  <div class="fee-row">
                      <div class="fee-label">2. Infaq Bangunan</div>
                      <div class="nominal-read" id="det_bangunan">Rp 0</div>
                  </div>
                  <div class="fee-row">
                      <div class="fee-label">3. Infaq Kursi & Meja</div>
                      <div class="nominal-read" id="det_fasilitas">Rp 0</div>
                  </div>
                  <div class="fee-row">
                      <div class="fee-label">4. Kegiatan Tahunan</div>
                      <div class="nominal-read" id="det_tahunan">Rp 0</div>
                  </div>
                  <div class="fee-row">
                      <div class="fee-label">5. Uang Asrama (Bln 1)</div>
                      <div class="nominal-read" id="det_bulanan">Rp 0</div>
                  </div>
                  <div class="fee-row border-0">
                      <div class="fee-label">6. Seragam Santri</div>
                      <div class="nominal-read" id="det_seragam">Rp 0</div>
                  </div>
              </div>

              <div class="col-md-6 ps-4">
                  <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-chart-pie me-2 text-muted"></i> Status Akhir</h6>
                  
                  <div class="bg-white p-3 rounded border mb-3">
                      <div class="d-flex justify-content-between mb-2">
                          <span class="text-muted">Total Tagihan Seharusnya</span>
                          <span class="fw-bold text-dark" id="det_sum_tagihan">Rp 0</span>
                      </div>
                      <div class="d-flex justify-content-between mb-2">
                          <span class="text-muted">Total Telah Disetor</span>
                          <span class="fw-bold text-success" id="det_sum_disetor">Rp 0</span>
                      </div>
                      <hr class="my-2">
                      <div class="d-flex justify-content-between">
                          <span class="fw-bold text-danger">Kekurangan / Sisa</span>
                          <span class="fw-bold text-danger fs-5" id="det_sum_sisa">Rp 0</span>
                      </div>
                  </div>

                  <div class="mt-4">
                      <div class="text-muted mb-1" style="font-size: 0.8rem;"><i class="fas fa-comment-dots me-1"></i> Catatan Admin:</div>
                      <div class="p-2 bg-white border rounded" style="min-height: 60px; font-size: 0.85rem;" id="det_catatan">-</div>
                  </div>
                  
                  <div class="mt-3 p-2 bg-white border rounded d-flex align-items-center gap-3">
                      <div style="width: 35px; height: 35px; background: var(--light-green); color: var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                          <i class="fas fa-user-shield"></i>
                      </div>
                      <div>
                          <div class="text-muted" style="font-size: 0.75rem; margin-bottom: 2px;">Diproses Oleh / Kasir:</div>
                          <div class="fw-bold text-dark" style="font-size: 0.85rem;" id="det_petugas">-</div>
                      </div>
                  </div>

              </div>
          </div>

        </div>
        <div class="modal-footer border-top-0 pt-0 pe-4 pb-3">
          <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Tutup</button>
        </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let targetSeragam = 0;
    const targetFix = 150000 + 2000000 + 500000 + 1200000 + 550000;

    $(document).ready(function() {
        $('#tabelKeuangan').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json', search: "_INPUT_", searchPlaceholder: "Cari nama / no daftar..." },
            "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "pageLength": 10,
            "columnDefs": [
                { "orderable": false, "targets": [5] } 
            ]
        });

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        <?php if ($status_pesan == 'sukses'): ?>
            Swal.fire({ icon: 'success', title: 'Tersimpan!', text: 'Rincian pembayaran berhasil diperbarui.', confirmButtonColor: '#0da15b', timer: 2000, showConfirmButton: false });
        <?php elseif ($status_pesan == 'gagal'): ?>
            Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan sistem saat memproses data.', confirmButtonColor: '#ef4444' });
        <?php endif; ?>
    });

    function bukaModalEdit(id, nama, gender, skema, catatan, pendaftaran, bangunan, fasilitas, tahunan, bulanan, seragam) {
        document.getElementById('id_pendaftaran').value = id;
        document.getElementById('nama_siswa').innerText = nama;
        document.getElementById('jenis_kelamin_siswa').value = gender;
        document.getElementById('badge_gender').innerText = gender;
        
        document.getElementById('skema_pembayaran').value = skema;
        document.getElementById('catatan_kesepakatan').value = catatan;

        document.getElementById('bayar_pendaftaran').value = formatRupiahString(pendaftaran.toString());
        document.getElementById('bayar_bangunan').value = formatRupiahString(bangunan.toString());
        document.getElementById('bayar_fasilitas').value = formatRupiahString(fasilitas.toString());
        document.getElementById('bayar_tahunan').value = formatRupiahString(tahunan.toString());
        document.getElementById('bayar_bulanan').value = formatRupiahString(bulanan.toString());
        document.getElementById('bayar_seragam').value = formatRupiahString(seragam.toString());

        if (gender === 'Laki-laki') {
            targetSeragam = 950000;
            document.getElementById('lbl_target_seragam').innerText = "Biaya Putra: Rp 950.000";
        } else {
            targetSeragam = 1000000;
            document.getElementById('lbl_target_seragam').innerText = "Biaya Putri: Rp 1.000.000";
        }

        hitungSemua();
        new bootstrap.Modal(document.getElementById('modalKeuangan')).show();
    }

    function bukaModalDetail(nama, gender, skema, catatan, pendaftaran, bangunan, fasilitas, tahunan, bulanan, seragam, tot_tagihan, tot_bayar, tot_sisa, petugas) {
        document.getElementById('det_nama_siswa').innerText = nama;
        document.getElementById('det_badge_gender').innerText = gender;
        document.getElementById('det_skema').innerText = skema;
        document.getElementById('det_catatan').innerText = catatan ? catatan : '-';
        document.getElementById('det_petugas').innerText = petugas ? petugas : 'Belum ada data (Transaksi Lama)';

        document.getElementById('det_pendaftaran').innerText = "Rp " + formatRupiahString(pendaftaran.toString());
        document.getElementById('det_bangunan').innerText = "Rp " + formatRupiahString(bangunan.toString());
        document.getElementById('det_fasilitas').innerText = "Rp " + formatRupiahString(fasilitas.toString());
        document.getElementById('det_tahunan').innerText = "Rp " + formatRupiahString(tahunan.toString());
        document.getElementById('det_bulanan').innerText = "Rp " + formatRupiahString(bulanan.toString());
        document.getElementById('det_seragam').innerText = "Rp " + formatRupiahString(seragam.toString());

        document.getElementById('det_sum_tagihan').innerText = "Rp " + formatRupiahString(tot_tagihan.toString());
        document.getElementById('det_sum_disetor').innerText = "Rp " + formatRupiahString(tot_bayar.toString());
        document.getElementById('det_sum_sisa').innerText = "Rp " + formatRupiahString(tot_sisa.toString());

        new bootstrap.Modal(document.getElementById('modalDetailKeuangan')).show();
    }

    function hitungSemua() {
        let totalTagihan = targetFix + targetSeragam;
        let inputs = document.querySelectorAll('.item-bayar');
        let totalDisetor = 0;

        inputs.forEach(input => {
            let val = parseInt(input.value.replace(/[^0-9]/g, '')) || 0;
            totalDisetor += val;
        });

        let sisa = totalTagihan - totalDisetor;
        if(sisa < 0) sisa = 0;

        document.getElementById('sum_tagihan').innerText = "Rp " + formatRupiahString(totalTagihan.toString());
        document.getElementById('sum_disetor').innerText = "Rp " + formatRupiahString(totalDisetor.toString());
        document.getElementById('sum_sisa').innerText = "Rp " + formatRupiahString(sisa.toString());
    }

    function formatRupiah(angka) {
        let number_string = angka.value.replace(/[^,\d]/g, '').toString(),
            split   = number_string.split(','),
            sisa    = split[0].length % 3,
            rupiah  = split[0].substr(0, sisa),
            ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

        if(ribuan){
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        angka.value = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    }

    function formatRupiahString(angka) {
        let number_string = angka.replace(/[^,\d]/g, '').toString(),
            split   = number_string.split(','),
            sisa    = split[0].length % 3,
            rupiah  = split[0].substr(0, sisa),
            ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

        if(ribuan){
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        return split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    }
</script>

</body>
</html>