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

// Hanya Developer & Super Admin yang boleh akses
if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak! Anda tidak memiliki izin untuk melihat detail pendaftar.");
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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Pendaftaran tidak valid.");
}

$id_pendaftar = intval($_GET['id']);

// Ambil Data Lengkap (Hati-hati dengan nama kolom ID yang bentrok, kita select pendaftaran.id sebagai main_id)
$query = "
    SELECT p.id as main_id, p.*, d.*, a.*, s.*, k.*, b.* FROM pendaftaran p 
    LEFT JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_alamat a ON p.id = a.pendaftaran_id 
    LEFT JOIN sekolah_asal s ON p.id = s.pendaftaran_id 
    LEFT JOIN data_keluarga k ON p.id = k.pendaftaran_id 
    LEFT JOIN data_berkas b ON p.id = b.pendaftaran_id 
    WHERE p.id = $id_pendaftar
";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    die("Data pendaftar tidak ditemukan.");
}

$data = $result->fetch_assoc();

// Format Tanggal
function tgl_indo($tanggal){
    if(empty($tanggal)) return '-';
	$bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
	$pecahkan = explode('-', $tanggal);
	return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

// Badge Status
$badge_class = 'bg-warning text-dark';
if ($data['status_pendaftaran'] == 'Lengkap') $badge_class = 'bg-success text-white';
if ($data['status_pendaftaran'] == 'Proses Seleksi') $badge_class = 'bg-info text-dark';
if ($data['status_pendaftaran'] == 'Belum Lengkap') $badge_class = 'bg-danger text-white';
if ($data['status_pendaftaran'] == 'Batal') $badge_class = 'bg-secondary text-white';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Siswa: <?= htmlspecialchars($data['nama_lengkap']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

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
        .card-custom { background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 25px; margin-bottom: 25px; }

        .btn-outline-custom { background-color: #ffffff; border: 1.5px solid var(--primary-green); color: var(--primary-green); font-weight: 500; border-radius: 50px; padding: 8px 20px; font-size: 0.85rem; transition: all 0.3s; }
        .btn-outline-custom:hover { background-color: var(--primary-green) !important; color: #ffffff !important; }
        .btn-solid-custom { background-color: var(--primary-green); color: #ffffff !important; font-weight: 500; border-radius: 50px; padding: 8px 20px; font-size: 0.85rem; transition: all 0.3s; border: none;}
        .btn-solid-custom:hover { background-color: var(--dark-green); }

        .section-title { font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        
        .data-row { display: flex; margin-bottom: 12px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 8px; }
        .data-label { width: 40%; font-weight: 500; color: var(--text-muted); font-size: 0.85rem; }
        .data-value { width: 60%; font-weight: 600; color: var(--text-dark); font-size: 0.85rem; }

        .file-box { border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; text-align: center; height: 100%; transition: 0.3s; }
        .file-box:hover { border-color: var(--primary-green); box-shadow: 0 4px 10px rgba(13,161,91,0.1); }
        .file-icon { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px; }
        .file-title { font-size: 0.85rem; font-weight: 600; color: var(--text-dark); margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <a href="data_pendaftar.php" class="btn btn-light me-3 shadow-sm" style="border-radius: 10px;"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h5 class="fw-bold text-dark m-0">Detail Pendaftar</h5>
                    <p class="text-muted m-0" style="font-size: 0.8rem;">Informasi lengkap calon santri baru.</p>
                </div>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="<?= $foto_path ?>" alt="Avatar" width="45" height="45" style="border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <div class="d-flex align-items-center gap-3">
                <h4 class="fw-bold text-dark m-0"><?= htmlspecialchars($data['nama_lengkap']) ?></h4>
                <span class="badge <?= $badge_class ?> rounded-pill px-3 py-2 border"><?= htmlspecialchars($data['status_pendaftaran']) ?></span>
            </div>
            <div class="d-flex gap-2">
                <!-- Tombol Cetak Info mengarah ke file cetak_biodata.php yang baru -->
                <a href="cetak_biodata.php?id=<?= $data['main_id'] ?>" target="_blank" class="btn btn-outline-custom text-decoration-none">
                    <i class="fas fa-print me-2"></i> Cetak Info
                </a>
                <a href="edit_pendaftar.php?id=<?= $data['main_id'] ?>" class="btn btn-solid-custom text-decoration-none">
                    <i class="fas fa-edit me-2"></i> Edit Data
                </a>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- KOLOM KIRI -->
            <div class="col-lg-6">
                <!-- Data Diri -->
                <div class="card-custom">
                    <div class="section-title"><i class="fas fa-user text-primary-green"></i> Identitas Diri Santri</div>
                    <div class="data-row"><div class="data-label">Nomor Pendaftaran</div><div class="data-value text-primary-green fs-6"><?= htmlspecialchars($data['no_pendaftaran']) ?></div></div>
                    <div class="data-row"><div class="data-label">NIK</div><div class="data-value"><?= htmlspecialchars($data['nik']) ?></div></div>
                    <div class="data-row"><div class="data-label">NISN</div><div class="data-value"><?= htmlspecialchars($data['nisn']) ?></div></div>
                    <div class="data-row"><div class="data-label">Nama Lengkap</div><div class="data-value"><?= htmlspecialchars($data['nama_lengkap']) ?></div></div>
                    <div class="data-row"><div class="data-label">Tempat, Tgl Lahir</div><div class="data-value"><?= htmlspecialchars($data['tempat_lahir']) ?>, <?= tgl_indo($data['tanggal_lahir']) ?></div></div>
                    <div class="data-row"><div class="data-label">Jenis Kelamin</div><div class="data-value"><?= htmlspecialchars($data['jenis_kelamin']) ?></div></div>
                    <div class="data-row"><div class="data-label">Agama</div><div class="data-value"><?= htmlspecialchars($data['agama']) ?></div></div>
                    <div class="data-row"><div class="data-label">Anak Ke-</div><div class="data-value"><?= htmlspecialchars($data['anak_ke']) ?> dari <?= htmlspecialchars($data['jumlah_saudara']) ?> bersaudara</div></div>
                    <div class="data-row"><div class="data-label">Hobi</div><div class="data-value"><?= htmlspecialchars($data['hobi']) ?: '-' ?></div></div>
                    <div class="data-row border-0 mb-0 pb-0"><div class="data-label">Nomor KIP</div><div class="data-value"><?= htmlspecialchars($data['no_kip']) ?: '-' ?></div></div>
                </div>

                <!-- Alamat Domisili -->
                <div class="card-custom">
                    <div class="section-title"><i class="fas fa-map-marker-alt text-primary-green"></i> Alamat & Kontak</div>
                    <div class="data-row"><div class="data-label">Alamat Lengkap</div><div class="data-value"><?= htmlspecialchars($data['alamat_lengkap']) ?></div></div>
                    <div class="data-row"><div class="data-label">RT / RW</div><div class="data-value"><?= htmlspecialchars($data['rt']) ?> / <?= htmlspecialchars($data['rw']) ?></div></div>
                    <div class="data-row"><div class="data-label">Desa / Kelurahan</div><div class="data-value"><?= htmlspecialchars($data['desa_kelurahan']) ?></div></div>
                    <div class="data-row"><div class="data-label">Kecamatan</div><div class="data-value"><?= htmlspecialchars($data['kecamatan']) ?></div></div>
                    <div class="data-row"><div class="data-label">Kota / Kabupaten</div><div class="data-value"><?= htmlspecialchars($data['kota_kabupaten']) ?></div></div>
                    <div class="data-row"><div class="data-label">Provinsi</div><div class="data-value"><?= htmlspecialchars($data['provinsi']) ?></div></div>
                    <div class="data-row"><div class="data-label">Kode Pos</div><div class="data-value"><?= htmlspecialchars($data['kode_pos']) ?></div></div>
                    <div class="data-row"><div class="data-label">No. WhatsApp</div><div class="data-value text-primary-green fw-bold"><?= htmlspecialchars($data['no_whatsapp']) ?></div></div>
                    <div class="data-row border-0 mb-0 pb-0"><div class="data-label">Email</div><div class="data-value"><?= htmlspecialchars($data['email']) ?: '-' ?></div></div>
                </div>
            </div>

            <!-- KOLOM KANAN -->
            <div class="col-lg-6">
                <!-- Informasi Pendaftaran -->
                <div class="card-custom">
                    <div class="section-title"><i class="fas fa-clipboard-check text-primary-green"></i> Informasi Pendaftaran</div>
                    <div class="data-row"><div class="data-label">Jalur Masuk</div><div class="data-value"><?= htmlspecialchars($data['status_masuk']) ?></div></div>
                    <div class="data-row"><div class="data-label">Jenjang Sekolah</div><div class="data-value"><?= htmlspecialchars($data['pilihan_sekolah']) ?></div></div>
                    <div class="data-row"><div class="data-label">Program Takhosush</div><div class="data-value"><?= $data['program_takhosush'] == 'Ya' ? '<span class="badge bg-success">Ya (Ikut)</span>' : 'Tidak' ?></div></div>
                    <div class="data-row border-0 mb-0 pb-0"><div class="data-label">Tanggal Mendaftar</div><div class="data-value"><?= date('d M Y, H:i', strtotime($data['created_at'])) ?> WIB</div></div>
                </div>

                <!-- Sekolah Asal -->
                <div class="card-custom">
                    <div class="section-title"><i class="fas fa-school text-primary-green"></i> Riwayat Sekolah Asal</div>
                    <div class="data-row"><div class="data-label">Nama Sekolah</div><div class="data-value"><?= htmlspecialchars($data['nama_sekolah']) ?></div></div>
                    <div class="data-row"><div class="data-label">Tahun Lulus</div><div class="data-value"><?= htmlspecialchars($data['tahun_lulus']) ?></div></div>
                    <div class="data-row"><div class="data-label">NPSN Sekolah</div><div class="data-value"><?= htmlspecialchars($data['npsn_sekolah']) ?: '-' ?></div></div>
                    <div class="data-row border-0 mb-0 pb-0"><div class="data-label">No Ijazah / SKHU</div><div class="data-value"><?= htmlspecialchars($data['no_ijazah_skhu']) ?: '-' ?></div></div>
                </div>

                <!-- Data Keluarga -->
                <div class="card-custom">
                    <div class="section-title"><i class="fas fa-users text-primary-green"></i> Informasi Keluarga</div>
                    
                    <h6 class="fw-bold mt-2" style="font-size: 0.9rem;">Identitas Ayah</h6>
                    <div class="data-row"><div class="data-label">Nama Ayah</div><div class="data-value"><?= htmlspecialchars($data['ayah_nama']) ?> (<?= htmlspecialchars($data['ayah_status']) ?>)</div></div>
                    <?php if($data['ayah_status'] == 'Masih Hidup'): ?>
                        <div class="data-row"><div class="data-label">Pekerjaan</div><div class="data-value"><?= htmlspecialchars($data['ayah_pekerjaan']) ?></div></div>
                        <div class="data-row"><div class="data-label">No HP</div><div class="data-value"><?= htmlspecialchars($data['ayah_no_hp']) ?></div></div>
                    <?php endif; ?>

                    <h6 class="fw-bold mt-4" style="font-size: 0.9rem;">Identitas Ibu</h6>
                    <div class="data-row"><div class="data-label">Nama Ibu</div><div class="data-value"><?= htmlspecialchars($data['ibu_nama']) ?> (<?= htmlspecialchars($data['ibu_status']) ?>)</div></div>
                    <?php if($data['ibu_status'] == 'Masih Hidup'): ?>
                        <div class="data-row"><div class="data-label">Pekerjaan</div><div class="data-value"><?= htmlspecialchars($data['ibu_pekerjaan']) ?></div></div>
                        <div class="data-row"><div class="data-label">No HP</div><div class="data-value"><?= htmlspecialchars($data['ibu_no_hp']) ?></div></div>
                    <?php endif; ?>

                    <?php if(!empty($data['wali_nama'])): ?>
                        <h6 class="fw-bold mt-4" style="font-size: 0.9rem;">Identitas Wali</h6>
                        <div class="data-row"><div class="data-label">Nama Wali</div><div class="data-value"><?= htmlspecialchars($data['wali_nama']) ?> (<?= htmlspecialchars($data['wali_hubungan']) ?>)</div></div>
                        <div class="data-row"><div class="data-label">Pekerjaan</div><div class="data-value"><?= htmlspecialchars($data['wali_pekerjaan']) ?></div></div>
                        <div class="data-row mb-0 pb-0 border-0"><div class="data-label">No HP</div><div class="data-value"><?= htmlspecialchars($data['wali_no_hp']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DOKUMEN / BERKAS UPLOAD -->
            <div class="col-12">
                <div class="card-custom">
                    <div class="section-title mb-4"><i class="fas fa-folder-open text-primary-green"></i> Dokumen & Berkas Lampiran</div>
                    
                    <div class="row g-3">
                        <?php 
                        $dokumen = [
                            'Pas Foto (3x4)' => $data['pas_foto'],
                            'Kartu Keluarga' => $data['kartu_keluarga'],
                            'KTP Ortu/Wali' => $data['ktp_ortu'],
                            'Akta Kelahiran' => $data['akta_kelahiran'],
                            'Ijazah / SKHU' => $data['ijazah_skhu'],
                            'Piagam Prestasi' => $data['piagam_prestasi']
                        ];

                        foreach($dokumen as $judul => $path): 
                            $ada = (!empty($path) && file_exists('../'.$path));
                        ?>
                        <div class="col-md-4 col-lg-2">
                            <div class="file-box">
                                <?php if($ada): ?>
                                    <i class="fas fa-file-alt file-icon text-success"></i>
                                    <div class="file-title"><?= $judul ?></div>
                                    <a href="../<?= $path ?>" target="_blank" class="btn btn-sm btn-outline-success w-100 rounded-pill">Lihat Berkas</a>
                                <?php else: ?>
                                    <i class="fas fa-times-circle file-icon"></i>
                                    <div class="file-title text-muted"><?= $judul ?></div>
                                    <button class="btn btn-sm btn-light w-100 rounded-pill" disabled>Tidak Ada</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });
</script>

</body>
</html>