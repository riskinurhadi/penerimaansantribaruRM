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

// Hanya Developer, Super Admin, dan Admin Kesehatan yang boleh akses
if ($role != 'Developer' && $role != 'Super Admin' && $role != 'Admin Kesehatan') {
    die("Akses ditolak! Anda tidak memiliki izin untuk mengelola Rekam Medis.");
}

// =========================================================================
// AUTO-PATCH DATABASE: Tambah kolom petugas_medis jika belum ada
// =========================================================================
$check_col = $conn->query("SHOW COLUMNS FROM data_kesehatan LIKE 'petugas_medis'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE data_kesehatan ADD COLUMN petugas_medis VARCHAR(100) NULL AFTER catatan_kesehatan");
}

// MENDAPATKAN FOTO PROFIL ADMIN AKTIF UNTUK HEADER
$foto_path = "https://ui-avatars.com/api/?name=" . urlencode($nama_lengkap_admin) . "&background=1e293b&color=fff&rounded=true";
$q_foto = $conn->query("SELECT foto_profil FROM users WHERE username = '$username'");
if ($q_foto && $q_foto->num_rows > 0) {
    $user_data = $q_foto->fetch_assoc();
    if(!empty($user_data['foto_profil']) && file_exists('../'.$user_data['foto_profil'])) {
        $foto_path = '../' . $user_data['foto_profil'];
    }
}

$status_pesan = '';

// --- PROSES UPDATE DATA KESEHATAN JIKA FORM MODAL DISUBMIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_pendaftaran'])) {
    $id_update = intval($_POST['id_pendaftaran']);
    
    // Identitas Petugas Medis
    $petugas_simpan = mysqli_real_escape_string($conn, $nama_lengkap_admin . ' (' . $role . ')');

    $tinggi_badan = intval($_POST['tinggi_badan']);
    $berat_badan = intval($_POST['berat_badan']);
    $golongan_darah = mysqli_real_escape_string($conn, trim($_POST['golongan_darah']));
    $riwayat_penyakit = mysqli_real_escape_string($conn, trim($_POST['riwayat_penyakit']));
    $kelainan_fisik = mysqli_real_escape_string($conn, trim($_POST['kelainan_fisik']));
    $catatan_kesehatan = mysqli_real_escape_string($conn, trim($_POST['catatan_kesehatan']));

    $cek = $conn->query("SELECT id FROM data_kesehatan WHERE pendaftaran_id = $id_update");
    
    if ($cek && $cek->num_rows > 0) {
        // Update
        $sql = "UPDATE data_kesehatan SET 
                tinggi_badan=$tinggi_badan, 
                berat_badan=$berat_badan, 
                golongan_darah='$golongan_darah', 
                riwayat_penyakit='$riwayat_penyakit', 
                kelainan_fisik='$kelainan_fisik', 
                catatan_kesehatan='$catatan_kesehatan',
                petugas_medis='$petugas_simpan'
                WHERE pendaftaran_id=$id_update";
    } else {
        // Insert baru
        $sql = "INSERT INTO data_kesehatan (pendaftaran_id, tinggi_badan, berat_badan, golongan_darah, riwayat_penyakit, kelainan_fisik, catatan_kesehatan, petugas_medis) 
                VALUES ($id_update, $tinggi_badan, $berat_badan, '$golongan_darah', '$riwayat_penyakit', '$kelainan_fisik', '$catatan_kesehatan', '$petugas_simpan')";
    }

    if ($conn->query($sql)) {
        $status_pesan = 'sukses';
    } else {
        $status_pesan = 'gagal';
    }
}

// --- AMBIL DATA PENDAFTAR & KESEHATAN ---
$query = "
    SELECT 
        p.id, 
        p.no_pendaftaran, 
        d.nama_lengkap, 
        p.pilihan_sekolah,
        IFNULL(k.tinggi_badan, 0) as tinggi_badan,
        IFNULL(k.berat_badan, 0) as berat_badan,
        IFNULL(k.golongan_darah, '-') as golongan_darah,
        IFNULL(k.riwayat_penyakit, '') as riwayat_penyakit,
        IFNULL(k.kelainan_fisik, '') as kelainan_fisik,
        IFNULL(k.catatan_kesehatan, '') as catatan_kesehatan,
        IFNULL(k.petugas_medis, '') as petugas_medis
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_kesehatan k ON p.id = k.pendaftaran_id
    ORDER BY p.created_at DESC
";
$result = $conn->query($query);

// --- QUERY STATISTIK ---
$q_diperiksa = 0; $q_ada_riwayat = 0; $q_belum = 0; $q_total_siswa = 0;
$res_diperiksa = $conn->query("SELECT COUNT(*) as total FROM data_kesehatan WHERE catatan_kesehatan IS NOT NULL AND catatan_kesehatan != ''");
if ($res_diperiksa) { $q_diperiksa = $res_diperiksa->fetch_assoc()['total']; }
$res_riwayat = $conn->query("SELECT COUNT(*) as total FROM data_kesehatan WHERE riwayat_penyakit IS NOT NULL AND riwayat_penyakit != ''");
if ($res_riwayat) { $q_ada_riwayat = $res_riwayat->fetch_assoc()['total']; }
$res_belum = $conn->query("SELECT COUNT(*) as total FROM pendaftaran p LEFT JOIN data_kesehatan k ON p.id = k.pendaftaran_id WHERE k.catatan_kesehatan IS NULL OR k.catatan_kesehatan = ''");
if ($res_belum) { $q_belum = $res_belum->fetch_assoc()['total']; }
$res_total = $conn->query("SELECT COUNT(*) as total FROM pendaftaran");
if ($res_total) { $q_total_siswa = $res_total->fetch_assoc()['total']; }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekam Medis Santri - PSB RM</title>
    
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
        .bg-diperiksa { background-color: #dcfce7; color: #16a34a; border-color: #bbf7d0; }
        .bg-belum { background-color: #fee2e2; color: #dc2626; border-color: #fecaca; }

        .fisik-info { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 2px; }
        .fisik-info strong { color: var(--text-dark); }

        /* Buttons Action (DI PERBAIKI AGAR SANGAT SIMETRIS) */
        .btn-action-edit, .btn-action-print { 
            border: none; 
            border-radius: 8px; 
            width: 34px; 
            height: 34px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 0.85rem; 
            transition: 0.2s; 
            text-decoration: none;
        }
        .btn-action-edit { background-color: #e0f2fe; color: #0284c7; }
        .btn-action-edit:hover { background-color: #bae6fd; color: #0369a1; transform: translateY(-2px);}
        
        .btn-action-print { background-color: #1e293b; color: white; }
        .btn-action-print:hover { background-color: #0f172a; color: white; transform: translateY(-2px);}

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
                <h5 class="fw-bold text-dark m-0">Rekam Medis & Kesehatan</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>

        <!-- Cards Statistik -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_total_siswa ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Total Siswa</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #dcfce7; color: #16a34a;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_diperiksa ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Sudah Diperiksa</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fef3c7; color: #d97706;">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_ada_riwayat ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Ada Riwayat Penyakit</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fee2e2; color: #dc2626;">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_belum ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Belum Diperiksa</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="card-custom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <h5 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Daftar Rekam Medis Calon Santri</h5>
            </div>

            <div class="table-responsive">
                <table id="tabelKesehatan" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="20%">NAMA LENGKAP</th>
                            <th width="20%">DATA FISIK</th>
                            <th width="25%">RIWAYAT / KELAINAN</th>
                            <th width="20%" class="text-center">CATATAN MEDIS</th>
                            <th width="10%" class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                
                                // Handling text kosong
                                $riwayat = empty($row['riwayat_penyakit']) ? '-' : htmlspecialchars($row['riwayat_penyakit']);
                                $kelainan = empty($row['kelainan_fisik']) ? '-' : htmlspecialchars($row['kelainan_fisik']);
                                $catatan = empty($row['catatan_kesehatan']) ? '' : htmlspecialchars($row['catatan_kesehatan']);
                                
                                $badge_class = empty($catatan) ? 'bg-belum' : 'bg-diperiksa';
                                $status_text = empty($catatan) ? 'Belum Diperiksa' : 'Sudah Diperiksa';
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['nama_lengkap']) ?> <br>
                                    <small class="text-muted fw-normal" style="font-size: 0.75rem;"><i class="fas fa-id-card me-1"></i> <?= htmlspecialchars($row['no_pendaftaran']) ?></small>
                                </td>
                                <td>
                                    <div class="fisik-info">TB: <strong><?= $row['tinggi_badan'] > 0 ? $row['tinggi_badan'].' cm' : '-' ?></strong></div>
                                    <div class="fisik-info">BB: <strong><?= $row['berat_badan'] > 0 ? $row['berat_badan'].' kg' : '-' ?></strong></div>
                                    <div class="fisik-info">Gol. Darah: <strong class="text-danger"><?= htmlspecialchars($row['golongan_darah']) ?></strong></div>
                                </td>
                                <td>
                                    <div class="fisik-info">Riwayat: <strong><?= $riwayat ?></strong></div>
                                    <div class="fisik-info">Kelainan: <strong class="text-danger"><?= $kelainan ?></strong></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge-status <?= $badge_class ?> mb-1"><?= $status_text ?></span>
                                    <?php if(!empty($catatan)): ?>
                                        <div class="text-muted text-start mt-1" style="font-size: 0.75rem; white-space: normal; line-height: 1.4; border-left: 2px solid var(--primary-green); padding-left: 8px;">
                                            <?= strlen($catatan) > 50 ? substr($catatan, 0, 50).'...' : $catatan ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn-action-edit" title="Update Medis" 
                                            onclick="bukaModalEdit(<?= $row['id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>', <?= $row['tinggi_badan'] ?>, <?= $row['berat_badan'] ?>, '<?= $row['golongan_darah'] ?>', '<?= addslashes($row['riwayat_penyakit']) ?>', '<?= addslashes($row['kelainan_fisik']) ?>', '<?= addslashes($row['catatan_kesehatan']) ?>')">
                                            <i class="fas fa-stethoscope"></i>
                                        </button>
                                        
                                        <!-- Tombol Cetak (Class Diperbaiki agar Simetris) -->
                                        <?php if(!empty($catatan)): ?>
                                            <a href="cetak_medis.php?id=<?= $row['id'] ?>" target="_blank" class="btn-action-print" title="Cetak Surat Kesehatan">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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

<!-- Modal Update Kesehatan -->
<div class="modal fade" id="modalKesehatan" tabindex="-1" aria-labelledby="modalKesehatanLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <form action="" method="POST" id="formKesehatan">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold text-dark" id="modalKesehatanLabel"><i class="fas fa-heartbeat text-primary-green me-2"></i> Update Rekam Medis</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert bg-light-green text-dark" style="background-color: var(--light-green); border: 1px solid #c8e6c9; border-radius: 10px;">
                Calon Santri: <strong id="nama_siswa" class="fs-6"></strong>
            </div>
            
            <input type="hidden" name="id_pendaftaran" id="id_pendaftaran">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Tinggi Badan</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="tinggi_badan" id="tinggi_badan" min="0" placeholder="0">
                        <span class="input-group-text bg-light">cm</span>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Berat Badan</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="berat_badan" id="berat_badan" min="0" placeholder="0">
                        <span class="input-group-text bg-light">kg</span>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Golongan Darah</label>
                    <select class="form-select" name="golongan_darah" id="golongan_darah">
                        <option value="">Belum Tahu / -</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="AB">AB</option>
                        <option value="O">O</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Riwayat Penyakit Menahun</label>
                    <textarea class="form-control" name="riwayat_penyakit" id="riwayat_penyakit" rows="2" placeholder="Contoh: Asma, Maag, dll. Kosongkan jika tidak ada."></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Kelainan Fisik</label>
                    <textarea class="form-control" name="kelainan_fisik" id="kelainan_fisik" rows="2" placeholder="Kosongkan jika tidak ada."></textarea>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Catatan Kesehatan Tambahan / Keputusan Medis</label>
                    <textarea class="form-control border-primary" name="catatan_kesehatan" id="catatan_kesehatan" rows="3" placeholder="Contoh: Santri dalam keadaan sehat, atau Santri perlu pantauan khusus terkait Asma." required></textarea>
                </div>
            </div>
          </div>
          <div class="modal-footer border-top-0 pt-0 mt-3">
            <button type="button" class="btn text-muted" data-bs-dismiss="modal" style="font-weight: 500;">Batal</button>
            <button type="submit" class="btn text-white px-4" style="background-color: var(--primary-green); border-radius: 50px; font-weight: 500;"><i class="fas fa-save me-2"></i> Simpan Data</button>
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
        $('#tabelKesehatan').DataTable({
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
                text: 'Rekam medis santri berhasil diperbarui.',
                confirmButtonColor: '#0da15b',
                timer: 2000,
                showConfirmButton: false
            });
        <?php elseif ($status_pesan == 'gagal'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Terjadi kesalahan sistem saat menyimpan data medis.',
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>
    });

    // FUNGSI MEMBUKA MODAL EDIT
    function bukaModalEdit(id, nama, tb, bb, gol_darah, riwayat, kelainan, catatan) {
        document.getElementById('id_pendaftaran').value = id;
        document.getElementById('nama_siswa').innerText = nama;
        document.getElementById('tinggi_badan').value = tb > 0 ? tb : '';
        document.getElementById('berat_badan').value = bb > 0 ? bb : '';
        
        // Memastikan select gol darah tersetting benar
        const golSelect = document.getElementById('golongan_darah');
        let optionExists = Array.from(golSelect.options).some(opt => opt.value === gol_darah);
        if (optionExists && gol_darah !== '') {
            golSelect.value = gol_darah;
        } else {
            golSelect.value = "";
        }
        
        document.getElementById('riwayat_penyakit').value = riwayat;
        document.getElementById('kelainan_fisik').value = kelainan;
        document.getElementById('catatan_kesehatan').value = catatan;
        
        var modal = new bootstrap.Modal(document.getElementById('modalKesehatan'));
        modal.show();
    }
</script>

</body>
</html>