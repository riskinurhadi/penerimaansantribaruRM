<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
// HAK AKSES: Developer, Super Admin, dan Admin Pendaftaran
if ($role != 'Developer' && $role != 'Super Admin' && $role != 'Admin Pendaftaran') {
    die("Anda tidak memiliki akses ke halaman ini.");
}

require_once '../config.php';
$conn->set_charset("utf8mb4");

$nama_lengkap_admin = $_SESSION['nama_lengkap'];
$username = $_SESSION['username'] ?? '';

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

// Ambil data pendaftar & hitung kelengkapan berkasnya
$query = "
    SELECT 
        p.id, 
        p.no_pendaftaran, 
        d.nama_lengkap, 
        p.status_pendaftaran,
        -- Menghitung jumlah file yang tidak kosong
        (IF(b.pas_foto != '', 1, 0) + 
         IF(b.kartu_keluarga != '', 1, 0) + 
         IF(b.ktp_ortu != '', 1, 0) + 
         IF(b.akta_kelahiran != '', 1, 0) + 
         IF(b.ijazah_skhu != '', 1, 0) + 
         IF(b.piagam_prestasi != '', 1, 0)) as jumlah_berkas
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_berkas b ON p.id = b.pendaftaran_id
    -- Urutkan yang Menunggu Verifikasi di paling atas
    ORDER BY CASE WHEN p.status_pendaftaran = 'Menunggu Verifikasi' THEN 1 ELSE 2 END, p.created_at DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Berkas - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0da15b; 
            --dark-green: #087d46;
            --bg-body: #f4f7fa;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .topbar-card {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 25px;
            margin-bottom: 25px;
        }

        .card-custom {
            background: #ffffff;
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            padding: 20px 25px;
        }

        /* Tabel DataTables Custom Styling */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th {
            background-color: #f8fafc; color: #64748b; font-size: 0.75rem;
            font-weight: 600; text-transform: uppercase; padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0; border-top: none; white-space: nowrap;
        }
        .table-custom thead th:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table-custom thead th:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        .table-custom tbody td {
            padding: 12px 15px; vertical-align: middle; color: #334155;
            font-size: 0.85rem; font-weight: 500; border-bottom: 1px solid #f1f5f9;
        }

        /* Badges Status */
        .badge-status {
            padding: 5px 12px; border-radius: 20px; font-weight: 600;
            font-size: 0.8rem; display: inline-block; white-space: nowrap; text-align: center;
        }
        .bg-lulus { background-color: #dcfce7; color: #16a34a; }
        .bg-menunggu { background-color: #fef3c7; color: #d97706; }
        .bg-proses { background-color: #e0f2fe; color: #0284c7; }
        .bg-tolak { background-color: #fee2e2; color: #dc2626; }
        .bg-batal { background-color: #f1f5f9; color: #64748b; }

        /* Badge Kelengkapan */
        .badge-berkas { padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .berkas-lengkap { background-color: #e8f5e9; color: var(--primary-green); border: 1px solid #c8e6c9; }
        .berkas-kurang { background-color: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }

        /* Custom Button */
        .btn-outline-custom { 
            border: 1.5px solid var(--primary-green); color: var(--primary-green); 
            font-weight: 500; border-radius: 50px; padding: 5px 15px;
            font-size: 0.8rem; transition: all 0.3s;
            display: inline-block; white-space: nowrap; 
        }
        .btn-outline-custom:hover { background-color: var(--primary-green); color: #ffffff; }

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
                <h5 class="fw-bold text-dark m-0">Verifikasi Berkas</h5>
            </div>
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>

        <div class="card-custom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <h5 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Daftar Antrean Verifikasi</h5>
            </div>

            <div class="table-responsive">
                <table id="tabelVerifikasi" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="15%">NO PENDAFTARAN</th>
                            <th width="30%">NAMA LENGKAP</th>
                            <th width="20%" class="text-center">KELENGKAPAN BERKAS</th>
                            <th width="15%" class="text-center">STATUS</th>
                            <th width="15%" class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                
                                $badge_class = 'bg-menunggu';
                                if ($row['status_pendaftaran'] == 'Lengkap') $badge_class = 'bg-lulus';
                                if ($row['status_pendaftaran'] == 'Proses Seleksi') $badge_class = 'bg-proses';
                                if ($row['status_pendaftaran'] == 'Belum Lengkap') $badge_class = 'bg-tolak';
                                if ($row['status_pendaftaran'] == 'Batal') $badge_class = 'bg-batal';

                                $jml_berkas = $row['jumlah_berkas'];
                                $berkas_class = ($jml_berkas >= 3) ? 'berkas-lengkap' : 'berkas-kurang';
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold" style="color: var(--primary-green);"><?= htmlspecialchars($row['no_pendaftaran']) ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td class="text-center">
                                    <span class="badge-berkas <?= $berkas_class ?>">
                                        <i class="fas fa-file-alt me-1"></i> <?= $jml_berkas ?> Dokumen
                                    </span>
                                </td>
                                <td class="text-center"><span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($row['status_pendaftaran']) ?></span></td>
                                <td class="text-center">
                                    <a href="cek_berkas.php?id=<?= $row['id'] ?>" class="btn btn-outline-custom">
                                        <i class="fas fa-check-square me-1"></i> Cek Berkas
                                    </a>
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

<script>
    $(document).ready(function() {
        $('#tabelVerifikasi').DataTable({
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
</script>

</body>
</html>