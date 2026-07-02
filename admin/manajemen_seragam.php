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

// Hanya Developer dan Super Admin yang boleh akses (Bisa disesuaikan jika ada Admin Seragam)
if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak! Anda tidak memiliki izin untuk mengelola Manajemen Seragam.");
}

$status_pesan = '';

// --- PROSES UPDATE DATA SERAGAM JIKA FORM MODAL DISUBMIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_pendaftaran'])) {
    $id_update = intval($_POST['id_pendaftaran']);
    
    // Standar
    $status_pengukuran = mysqli_real_escape_string($conn, trim($_POST['status_pengukuran']));
    $ukuran_baju = mysqli_real_escape_string($conn, trim($_POST['ukuran_baju']));
    $ukuran_celana_rok = mysqli_real_escape_string($conn, trim($_POST['ukuran_celana_rok']));
    $ukuran_peci_jilbab = mysqli_real_escape_string($conn, trim($_POST['ukuran_peci_jilbab']));
    
    // Custom Kemeja & Celana Formal
    $lebar_bahu = mysqli_real_escape_string($conn, trim($_POST['lebar_bahu']));
    $lingkar_dada = mysqli_real_escape_string($conn, trim($_POST['lingkar_dada']));
    $lingkar_perut = mysqli_real_escape_string($conn, trim($_POST['lingkar_perut']));
    $panjang_lengan = mysqli_real_escape_string($conn, trim($_POST['panjang_lengan']));
    $panjang_baju = mysqli_real_escape_string($conn, trim($_POST['panjang_baju']));
    $lingkar_pinggang = mysqli_real_escape_string($conn, trim($_POST['lingkar_pinggang']));
    $lingkar_paha = mysqli_real_escape_string($conn, trim($_POST['lingkar_paha']));
    $panjang_celana_rok_custom = mysqli_real_escape_string($conn, trim($_POST['panjang_celana_rok_custom']));
    
    $catatan_ukuran = mysqli_real_escape_string($conn, trim($_POST['catatan_ukuran']));

    // Cek apakah data seragam untuk siswa ini sudah ada
    $cek = $conn->query("SELECT id FROM data_seragam WHERE pendaftaran_id = $id_update");
    
    if ($cek && $cek->num_rows > 0) {
        // Update
        $sql = "UPDATE data_seragam SET 
                status_pengukuran='$status_pengukuran', 
                ukuran_baju='$ukuran_baju', 
                ukuran_celana_rok='$ukuran_celana_rok', 
                ukuran_peci_jilbab='$ukuran_peci_jilbab', 
                lebar_bahu='$lebar_bahu',
                lingkar_dada='$lingkar_dada',
                lingkar_perut='$lingkar_perut',
                panjang_lengan='$panjang_lengan',
                panjang_baju='$panjang_baju',
                lingkar_pinggang='$lingkar_pinggang',
                lingkar_paha='$lingkar_paha',
                panjang_celana_rok='$panjang_celana_rok_custom',
                catatan_ukuran='$catatan_ukuran'
                WHERE pendaftaran_id=$id_update";
    } else {
        // Insert baru
        $sql = "INSERT INTO data_seragam (pendaftaran_id, status_pengukuran, ukuran_baju, ukuran_celana_rok, ukuran_peci_jilbab, lebar_bahu, lingkar_dada, lingkar_perut, panjang_lengan, panjang_baju, lingkar_pinggang, lingkar_paha, panjang_celana_rok, catatan_ukuran) 
                VALUES ($id_update, '$status_pengukuran', '$ukuran_baju', '$ukuran_celana_rok', '$ukuran_peci_jilbab', '$lebar_bahu', '$lingkar_dada', '$lingkar_perut', '$panjang_lengan', '$panjang_baju', '$lingkar_pinggang', '$lingkar_paha', '$panjang_celana_rok_custom', '$catatan_ukuran')";
    }

    if ($conn->query($sql)) {
        $status_pesan = 'sukses';
    } else {
        $status_pesan = 'gagal';
    }
}

// --- AMBIL DATA PENDAFTAR & SERAGAM ---
$query = "
    SELECT 
        p.id, 
        p.no_pendaftaran, 
        d.nama_lengkap, 
        d.jenis_kelamin,
        IFNULL(s.status_pengukuran, 'Belum Diukur') as status_pengukuran,
        IFNULL(s.ukuran_baju, '') as ukuran_baju,
        IFNULL(s.ukuran_celana_rok, '') as ukuran_celana_rok,
        IFNULL(s.ukuran_peci_jilbab, '') as ukuran_peci_jilbab,
        IFNULL(s.lebar_bahu, '') as lebar_bahu,
        IFNULL(s.lingkar_dada, '') as lingkar_dada,
        IFNULL(s.lingkar_perut, '') as lingkar_perut,
        IFNULL(s.panjang_lengan, '') as panjang_lengan,
        IFNULL(s.panjang_baju, '') as panjang_baju,
        IFNULL(s.lingkar_pinggang, '') as lingkar_pinggang,
        IFNULL(s.lingkar_paha, '') as lingkar_paha,
        IFNULL(s.panjang_celana_rok, '') as panjang_celana_rok_custom,
        IFNULL(s.catatan_ukuran, '') as catatan_ukuran
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_seragam s ON p.id = s.pendaftaran_id
    ORDER BY p.created_at DESC
";
$result = $conn->query($query);

// --- QUERY STATISTIK ---
$q_sudah = 0; 
$q_belum = 0; 
$q_total_siswa = 0;

$res_sudah = $conn->query("SELECT COUNT(*) as total FROM data_seragam WHERE status_pengukuran = 'Sudah Diukur'");
if ($res_sudah) { $q_sudah = $res_sudah->fetch_assoc()['total']; }

$res_belum = $conn->query("SELECT COUNT(*) as total FROM pendaftaran p LEFT JOIN data_seragam s ON p.id = s.pendaftaran_id WHERE s.status_pengukuran IS NULL OR s.status_pengukuran = 'Belum Diukur'");
if ($res_belum) { $q_belum = $res_belum->fetch_assoc()['total']; }

$res_total = $conn->query("SELECT COUNT(*) as total FROM pendaftaran");
if ($res_total) { $q_total_siswa = $res_total->fetch_assoc()['total']; }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Seragam - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        
        .topbar-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; }
        .card-custom { background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 20px 25px; }

        /* Stat Cards */
        .stat-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 20px; display: flex; align-items: center; gap: 15px; transition: transform 0.3s; border: 1px solid rgba(0,0,0,0.03); }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 55px; height: 55px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        /* Table */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; border-top: none; white-space: nowrap; }
        .table-custom thead th:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table-custom thead th:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        .table-custom tbody td { padding: 12px 15px; vertical-align: middle; color: #334155; font-size: 0.85rem; font-weight: 500; border-bottom: 1px solid #f1f5f9; }

        /* Badges */
        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; display: inline-block; white-space: nowrap; text-align: center; border: 1px solid transparent; }
        .bg-sudah { background-color: #dcfce7; color: #16a34a; border-color: #bbf7d0; }
        .bg-belum { background-color: #fee2e2; color: #dc2626; border-color: #fecaca; }

        .ukuran-box {
            display: inline-block;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            color: var(--text-dark);
            font-weight: 600;
        }
        .ukuran-label { font-size: 0.75rem; color: var(--text-muted); font-weight: normal; margin-right: 3px; }

        /* Buttons */
        .btn-outline-custom { border: 1.5px solid var(--primary-green); color: var(--primary-green); font-weight: 500; border-radius: 50px; padding: 6px 18px; font-size: 0.85rem; transition: all 0.3s; display: inline-block; }
        .btn-outline-custom:hover { background-color: var(--primary-green); color: #ffffff !important; }
        .btn-action-edit { background-color: #e0f2fe; color: #0284c7; border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.8rem; transition: 0.2s;}
        .btn-action-edit:hover { background-color: #bae6fd; color: #0369a1; }

        /* Form Modal Custom Input Group */
        .form-section-title { font-size: 0.95rem; font-weight: 600; color: var(--dark-green); border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 15px; margin-top: 20px;}
        .input-group-text { font-size: 0.8rem; background-color: #f8fafc; }

        /* DT Fix */
        div.dataTables_wrapper div.dataTables_length label { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        div.dataTables_wrapper div.dataTables_length select { border-radius: 8px; padding: 4px 30px 4px 10px; font-size: 0.85rem; width: auto; border: 1px solid #cbd5e1; }
        div.dataTables_wrapper div.dataTables_filter input { border-radius: 50px; padding: 4px 15px; font-size: 0.85rem; border: 1px solid #cbd5e1; }
        div.dataTables_wrapper div.dataTables_filter input:focus { border-color: var(--primary-green); outline: none; box-shadow: 0 0 0 0.25rem rgba(13, 161, 91, 0.25); }
        .page-item.active .page-link { background-color: var(--primary-green); border-color: var(--primary-green); }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3 shadow-sm" id="sidebarToggle" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="fw-bold text-dark m-0">Manajemen Seragam</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_lengkap_admin) ?>&background=1e293b&color=fff&rounded=true" width="40">
            </div>
        </div>

        <!-- Cards Statistik -->
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_total_siswa ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Total Calon Santri</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #dcfce7; color: #16a34a;">
                        <i class="fas fa-tshirt"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_sudah ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Sudah Diukur</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fee2e2; color: #dc2626;">
                        <i class="fas fa-ruler-combined"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_belum ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Belum Diukur</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="card-custom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <h5 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Data Pengukuran Seragam</h5>
                <button class="btn btn-outline-custom bg-white"><i class="fas fa-print me-2"></i> Cetak Rekap Seragam</button>
            </div>

            <div class="table-responsive">
                <table id="tabelSeragam" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="20%">NAMA & GENDER</th>
                            <th width="45%">DETAIL UKURAN (STANDAR & CUSTOM)</th>
                            <th width="15%" class="text-center">STATUS</th>
                            <th width="15%" class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                
                                $badge_class = ($row['status_pengukuran'] == 'Sudah Diukur') ? 'bg-sudah' : 'bg-belum';
                                
                                // Handling Text Kosong
                                $u_baju = empty($row['ukuran_baju']) ? '-' : htmlspecialchars($row['ukuran_baju']);
                                $u_celana = empty($row['ukuran_celana_rok']) ? '-' : htmlspecialchars($row['ukuran_celana_rok']);
                                $u_peci = empty($row['ukuran_peci_jilbab']) ? '-' : htmlspecialchars($row['ukuran_peci_jilbab']);
                                $gender_icon = ($row['jenis_kelamin'] == 'Laki-laki') ? '<i class="fas fa-male text-primary"></i>' : '<i class="fas fa-female text-danger"></i>';
                                
                                // Status Custom Kemeja
                                $is_custom = (!empty($row['lebar_bahu']) || !empty($row['lingkar_dada']));
                                $status_custom = $is_custom ? '<span class="text-success"><i class="fas fa-check-circle"></i> Sudah Diukur</span>' : '<span class="text-danger"><i class="fas fa-times-circle"></i> Belum Diukur</span>';
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['nama_lengkap']) ?> <br>
                                    <small class="text-muted fw-normal" style="font-size: 0.75rem;">
                                        <?= $gender_icon ?> <?= htmlspecialchars($row['jenis_kelamin']) ?> | <?= htmlspecialchars($row['no_pendaftaran']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($row['status_pengukuran'] == 'Sudah Diukur'): ?>
                                        <div class="mb-2">
                                            <div class="ukuran-box"><span class="ukuran-label">Batik/Kaos:</span> <?= $u_baju ?></div>
                                            <div class="ukuran-box"><span class="ukuran-label">Cln Olahraga:</span> <?= $u_celana ?></div>
                                            <div class="ukuran-box"><span class="ukuran-label">Peci/Jilbab:</span> <?= $u_peci ?></div>
                                        </div>
                                        <div style="font-size: 0.8rem; background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                            <span class="fw-bold text-dark">Kemeja & Bawahan Formal:</span> <?= $status_custom ?>
                                            <?php if($is_custom): ?>
                                                <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                                                    (LB: <?= $row['lebar_bahu'] ?>cm, LD: <?= $row['lingkar_dada'] ?>cm, PB: <?= $row['panjang_baju'] ?>cm, dll)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.85rem; font-style: italic;">Siswa belum melakukan pengukuran fisik.</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($row['status_pengukuran']) ?></span>
                                </td>
                                <td class="text-center">
                                    <!-- Menggunakan Data Attributes agar kode JS lebih rapi -->
                                    <button class="btn-action-edit" title="Input Ukuran" 
                                        onclick="bukaModalEdit(this)"
                                        data-id="<?= $row['id'] ?>"
                                        data-nama="<?= htmlspecialchars($row['nama_lengkap']) ?>"
                                        data-gender="<?= $row['jenis_kelamin'] ?>"
                                        data-status="<?= $row['status_pengukuran'] ?>"
                                        data-baju="<?= htmlspecialchars($row['ukuran_baju']) ?>"
                                        data-celana="<?= htmlspecialchars($row['ukuran_celana_rok']) ?>"
                                        data-peci="<?= htmlspecialchars($row['ukuran_peci_jilbab']) ?>"
                                        data-lbahu="<?= htmlspecialchars($row['lebar_bahu']) ?>"
                                        data-ldada="<?= htmlspecialchars($row['lingkar_dada']) ?>"
                                        data-lperut="<?= htmlspecialchars($row['lingkar_perut']) ?>"
                                        data-plengan="<?= htmlspecialchars($row['panjang_lengan']) ?>"
                                        data-pbaju="<?= htmlspecialchars($row['panjang_baju']) ?>"
                                        data-lpinggang="<?= htmlspecialchars($row['lingkar_pinggang']) ?>"
                                        data-lpaha="<?= htmlspecialchars($row['lingkar_paha']) ?>"
                                        data-pcelana="<?= htmlspecialchars($row['panjang_celana_rok_custom']) ?>"
                                        data-catatan="<?= htmlspecialchars($row['catatan_ukuran']) ?>">
                                        <i class="fas fa-ruler"></i> Input
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            }
                        } 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Update Seragam -->
<div class="modal fade" id="modalSeragam" tabindex="-1" aria-labelledby="modalSeragamLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <form action="" method="POST" id="formSeragam">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold text-dark" id="modalSeragamLabel"><i class="fas fa-tshirt text-primary-green me-2"></i> Form Ukuran Seragam</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body pb-2">
            <div class="alert bg-light-green text-dark mb-3" style="background-color: var(--light-green); border: 1px solid #c8e6c9; border-radius: 10px;">
                Calon Santri: <strong id="nama_siswa" class="fs-6"></strong> <br>
                <small id="gender_siswa" class="text-muted"></small>
            </div>
            
            <input type="hidden" name="id_pendaftaran" id="id_pendaftaran">

            <div class="mb-2">
                <label class="form-label fw-bold text-dark" style="font-size: 0.9rem;">Status Pengukuran Keseluruhan</label>
                <select class="form-select border-primary" name="status_pengukuran" id="status_pengukuran" style="border-width: 2px;" required>
                    <option value="Belum Diukur">Belum Diukur</option>
                    <option value="Sudah Diukur">Sudah Diukur</option>
                </select>
            </div>

            <!-- SECTION: STANDAR -->
            <div class="form-section-title mt-4"><i class="fas fa-tags me-1"></i> Ukuran Standar (Kaos Olahraga, Batik, Peci/Jilbab)</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Kaos & Batik</label>
                    <input type="text" list="ukuran_list" class="form-control text-uppercase" name="ukuran_baju" id="ukuran_baju" placeholder="S/M/L/XL" maxlength="10">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Celana Olahraga</label>
                    <input type="text" list="ukuran_list" class="form-control text-uppercase" name="ukuran_celana_rok" id="ukuran_celana_rok" placeholder="S/M/L/XL" maxlength="10">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;" id="label_peci">Peci / Jilbab</label>
                    <input type="text" list="ukuran_peci_list" class="form-control text-uppercase" name="ukuran_peci_jilbab" id="ukuran_peci_jilbab" placeholder="Input" maxlength="10">
                </div>
            </div>

            <!-- SECTION: CUSTOM -->
            <div class="form-section-title mt-4"><i class="fas fa-cut me-1"></i> Ukuran Custom Manual (Kemeja & Bawahan Formal)</div>
            <div class="row g-3">
                
                <!-- Kemeja -->
                <div class="col-md-4">
                    <label class="form-label text-muted" style="font-size: 0.8rem;">Lebar Bahu</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="lebar_bahu" id="lebar_bahu" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted" style="font-size: 0.8rem;">Lingkar Dada</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="lingkar_dada" id="lingkar_dada" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted" style="font-size: 0.8rem;">Lingkar Perut</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="lingkar_perut" id="lingkar_perut" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted" style="font-size: 0.8rem;">Panjang Lengan</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="panjang_lengan" id="panjang_lengan" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted" style="font-size: 0.8rem;">Panjang Baju (Kemeja)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="panjang_baju" id="panjang_baju" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>

                <div class="col-12"><hr class="my-1 text-muted"></div>

                <!-- Celana / Rok -->
                <div class="col-md-4">
                    <label class="form-label text-muted" style="font-size: 0.8rem;">Lingkar Pinggang</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="lingkar_pinggang" id="lingkar_pinggang" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted" style="font-size: 0.8rem;">Lingkar Paha</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="lingkar_paha" id="lingkar_paha" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted" style="font-size: 0.8rem;" id="label_celana_custom">Panjang Celana/Rok</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.1" class="form-control" name="panjang_celana_rok_custom" id="panjang_celana_rok_custom" placeholder="0">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>
            </div>

            <!-- CATATAN TAMBAHAN -->
            <div class="form-section-title mt-4"><i class="fas fa-comment-dots me-1"></i> Catatan Tambahan</div>
            <div class="row g-3">
                <div class="col-md-12">
                    <textarea class="form-control" name="catatan_ukuran" id="catatan_ukuran" rows="2" placeholder="Contoh: Kemeja minta diperlebar sedikit / pinggang karet dll."></textarea>
                </div>
            </div>

            <!-- Datalist untuk Autocomplete Cepat -->
            <datalist id="ukuran_list">
                <option value="S"></option>
                <option value="M"></option>
                <option value="L"></option>
                <option value="XL"></option>
                <option value="XXL"></option>
                <option value="3XL"></option>
            </datalist>

            <datalist id="ukuran_peci_list">
                <option value="S"></option>
                <option value="M"></option>
                <option value="L"></option>
                <option value="XL"></option>
                <option value="No. 4"></option>
                <option value="No. 5"></option>
                <option value="No. 6"></option>
                <option value="No. 7"></option>
                <option value="No. 8"></option>
            </datalist>

          </div>
          <div class="modal-footer border-top-0 pt-0 mt-3">
            <button type="button" class="btn text-muted" data-bs-dismiss="modal" style="font-weight: 500;">Batal</button>
            <button type="submit" class="btn text-white px-4" style="background-color: var(--primary-green); border-radius: 50px; font-weight: 500;"><i class="fas fa-save me-2"></i> Simpan Seluruh Ukuran</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        $('#tabelSeragam').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json', search: "_INPUT_", searchPlaceholder: "Cari nama santri..." },
            "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "pageLength": 10
        });

        // Script Toggle Sidebar di Mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Notifikasi Hasil Submit Form
        <?php if ($status_pesan == 'sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Tersimpan!',
                text: 'Data ukuran seragam (standar & custom) berhasil diperbarui.',
                confirmButtonColor: '#0da15b',
                timer: 2000,
                showConfirmButton: false
            });
        <?php elseif ($status_pesan == 'gagal'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Terjadi kesalahan sistem saat menyimpan data.',
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>
    });

    // FUNGSI MEMBUKA MODAL EDIT MENGGUNAKAN DATA ATTRIBUTE (LEBIH RAPI)
    function bukaModalEdit(btn) {
        // Data Utama
        document.getElementById('id_pendaftaran').value = btn.getAttribute('data-id');
        document.getElementById('nama_siswa').innerText = btn.getAttribute('data-nama');
        const gender = btn.getAttribute('data-gender');
        document.getElementById('gender_siswa').innerText = "Jenis Kelamin: " + gender;
        
        // Ubah Label berdasarkan jenis kelamin
        if(gender === 'Laki-laki') {
            document.getElementById('label_peci').innerText = "Ukuran Peci";
            document.getElementById('label_celana_custom').innerText = "Panjang Celana Formal";
        } else {
            document.getElementById('label_peci').innerText = "Ukuran Jilbab";
            document.getElementById('label_celana_custom').innerText = "Panjang Rok Formal";
        }

        // Set Values
        document.getElementById('status_pengukuran').value = btn.getAttribute('data-status');
        
        // Ukuran Standar
        document.getElementById('ukuran_baju').value = btn.getAttribute('data-baju');
        document.getElementById('ukuran_celana_rok').value = btn.getAttribute('data-celana');
        document.getElementById('ukuran_peci_jilbab').value = btn.getAttribute('data-peci');
        
        // Ukuran Custom
        document.getElementById('lebar_bahu').value = btn.getAttribute('data-lbahu');
        document.getElementById('lingkar_dada').value = btn.getAttribute('data-ldada');
        document.getElementById('lingkar_perut').value = btn.getAttribute('data-lperut');
        document.getElementById('panjang_lengan').value = btn.getAttribute('data-plengan');
        document.getElementById('panjang_baju').value = btn.getAttribute('data-pbaju');
        document.getElementById('lingkar_pinggang').value = btn.getAttribute('data-lpinggang');
        document.getElementById('lingkar_paha').value = btn.getAttribute('data-lpaha');
        document.getElementById('panjang_celana_rok_custom').value = btn.getAttribute('data-pcelana');

        document.getElementById('catatan_ukuran').value = btn.getAttribute('data-catatan');
        
        // Tampilkan Modal
        var modal = new bootstrap.Modal(document.getElementById('modalSeragam'));
        modal.show();
    }
</script>

</body>
</html>