<?php
session_start();

// Cek login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';
$conn->set_charset("utf8mb4");

$nama_lengkap_admin = $_SESSION['nama_lengkap'];
$role = $_SESSION['role'];
$username = $_SESSION['username'] ?? '';

// HAK AKSES: Developer, Super Admin, dan Admin Pendaftaran
if ($role != 'Developer' && $role != 'Super Admin' && $role != 'Admin Pendaftaran') {
    die("Akses ditolak! Anda tidak memiliki izin untuk mengelola Data Pendaftar.");
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

// ==========================================
// LOGIKA FILTER BERDASARKAN JENJANG SEKOLAH
// ==========================================
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : '';
$where_clause = "";
if (in_array($filter, ['RA', 'MI', 'MTs', 'MA', 'SMK'])) {
    $where_clause = " WHERE p.pilihan_sekolah = '$filter' ";
}

// Ambil Data Pendaftar
$query = "
    SELECT p.id, p.no_pendaftaran, p.pilihan_sekolah, p.status_pendaftaran, p.created_at, d.nama_lengkap, d.jenis_kelamin 
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    $where_clause
    ORDER BY p.created_at DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pendaftar - PSB RM</title>
    
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

        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; border-top: none; white-space: nowrap; }
        .table-custom thead th:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table-custom thead th:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        .table-custom tbody td { padding: 12px 15px; vertical-align: middle; color: #334155; font-size: 0.85rem; font-weight: 500; border-bottom: 1px solid #f1f5f9; }

        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; display: inline-block; white-space: nowrap; text-align: center; }

        /* Custom Button */
        .btn-outline-custom { background-color: #ffffff; border: 1.5px solid var(--primary-green); color: var(--primary-green); font-weight: 500; border-radius: 50px; padding: 8px 20px; font-size: 0.85rem; transition: all 0.3s; }
        .btn-outline-custom:hover { background-color: var(--primary-green) !important; color: #ffffff !important; }
        
        .btn-solid-custom { background-color: var(--primary-green); color: #ffffff !important; font-weight: 500; border-radius: 50px; padding: 8px 20px; font-size: 0.85rem; transition: all 0.3s; border: none;}
        .btn-solid-custom:hover { background-color: var(--dark-green); }
        
        .btn-act { border: none; border-radius: 8px; padding: 6px 10px; font-size: 0.8rem; transition: 0.2s; color: white; margin-right: 3px;}
        .btn-act-view { background-color: #0ea5e9; }
        .btn-act-view:hover { background-color: #0284c7; transform: translateY(-2px);}
        .btn-act-edit { background-color: #fef08a; color: #ca8a04; }
        .btn-act-edit:hover { background-color: #fde047; color: #a16207; transform: translateY(-2px);}
        .btn-act-print { background-color: #1e293b; color: white; }
        .btn-act-print:hover { background-color: #0f172a; color: white; transform: translateY(-2px);}
        .btn-act-delete { background-color: #fee2e2; color: #dc2626; }
        .btn-act-delete:hover { background-color: #fca5a5; color: #b91c1c; transform: translateY(-2px);}

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
        
        <!-- TOPBAR (Dengan Identitas & Foto Profil) -->
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3 shadow-sm" id="sidebarToggle" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="fw-bold text-dark m-0">Semua Data Pendaftar</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="card-custom">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Manajemen Data Siswa</h5>
                    <!-- FILTER JENJANG SEKOLAH -->
                    <select class="form-select form-select-sm ms-3 fw-medium text-primary-green border-primary-green" onchange="window.location.href='?filter='+this.value" style="width: auto; border-radius: 8px;">
                        <option value="">Semua Jenjang</option>
                        <option value="RA" <?= $filter == 'RA' ? 'selected' : '' ?>>Khusus RA</option>
                        <option value="MI" <?= $filter == 'MI' ? 'selected' : '' ?>>Khusus MI</option>
                        <option value="MTs" <?= $filter == 'MTs' ? 'selected' : '' ?>>Khusus MTs</option>
                        <option value="MA" <?= $filter == 'MA' ? 'selected' : '' ?>>Khusus MA</option>
                        <option value="SMK" <?= $filter == 'SMK' ? 'selected' : '' ?>>Khusus SMK</option>
                    </select>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="export_excel.php?filter=<?= $filter ?>" class="btn btn-outline-custom text-decoration-none">
                        <i class="fas fa-file-excel me-2"></i> Export Excel
                    </a>
                    <a href="../step1.php" class="btn btn-solid-custom text-decoration-none">
                        <i class="fas fa-plus-circle me-2"></i> Tambah Data
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tabelPendaftar" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="15%">NO DAFTAR</th>
                            <th width="30%">NAMA CALON SANTRI</th>
                            <th width="15%" class="text-center">JENJANG</th>
                            <th width="15%" class="text-center">STATUS</th>
                            <th width="20%" class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                
                                $badge_class = 'bg-warning text-dark';
                                if ($row['status_pendaftaran'] == 'Lengkap') $badge_class = 'bg-success text-white';
                                if ($row['status_pendaftaran'] == 'Proses Seleksi') $badge_class = 'bg-info text-dark';
                                if ($row['status_pendaftaran'] == 'Belum Lengkap') $badge_class = 'bg-danger text-white';
                                if ($row['status_pendaftaran'] == 'Batal') $badge_class = 'bg-secondary text-white';

                                $gender_icon = ($row['jenis_kelamin'] == 'Laki-laki') ? '<i class="fas fa-male text-primary"></i>' : '<i class="fas fa-female text-danger"></i>';
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold text-primary-green"><?= htmlspecialchars($row['no_pendaftaran']) ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['nama_lengkap']) ?> <br>
                                    <small class="text-muted fw-normal" style="font-size: 0.75rem;">
                                        <?= $gender_icon ?> <?= htmlspecialchars($row['jenis_kelamin']) ?> | <?= date('d M Y', strtotime($row['created_at'])) ?>
                                    </small>
                                </td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['pilihan_sekolah']) ?></span></td>
                                <td class="text-center"><span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($row['status_pendaftaran']) ?></span></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center">
                                        <!-- Tombol Cetak KTS menggantikan Lihat Detail -->
                                        <a href="cetak_kts.php?id=<?= $row['id'] ?>" target="_blank" class="btn-act btn-act-view" title="Cetak Kartu Tanda Santri (KTS)"><i class="fas fa-id-badge"></i></a>
                                        
                                        <a href="edit_pendaftar.php?id=<?= $row['id'] ?>" class="btn-act btn-act-edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="../cetak_bukti.php?id=<?= $row['id'] ?>" target="_blank" class="btn-act btn-act-print" title="Cetak Bukti / Kartu Kendali"><i class="fas fa-print"></i></a>
                                        
                                        <?php if ($role == 'Developer' || $role == 'Super Admin'): ?>
                                            <button class="btn-act btn-act-delete" title="Hapus" onclick="konfirmasiHapus(<?= $row['id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>')"><i class="fas fa-trash"></i></button>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        $('#tabelPendaftar').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json', search: "_INPUT_", searchPlaceholder: "Cari data siswa..." },
            "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "pageLength": 10
        });

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    });

    // Fitur Konfirmasi Hapus Data
    function konfirmasiHapus(id, nama) {
        Swal.fire({
            title: 'Hapus Data Ini?',
            html: `Apakah Anda yakin ingin menghapus seluruh data pendaftaran milik <b>${nama}</b> secara permanen? Data dan berkas yang dihapus tidak dapat dikembalikan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Hapus Data!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `proses_hapus.php?id=${id}`;
            }
        })
    }
</script>

</body>
</html>