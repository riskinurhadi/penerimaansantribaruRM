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

// Hanya Developer & Super Admin yang boleh edit
if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak!");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Pendaftaran tidak valid!");
}

$id_pendaftar = intval($_GET['id']);
$pesan = '';
$status_pesan = '';

// --- PROSES UPDATE DATA JIKA FORM DISUBMIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    function bersihkan($koneksi, $data) {
        return mysqli_real_escape_string($koneksi, trim($data ?? ''));
    }

    $conn->begin_transaction();

    try {
        // 1. Update Tabel Pendaftaran
        $status_pendaftaran = bersihkan($conn, $_POST['status_pendaftaran']);
        $pilihan_sekolah = bersihkan($conn, $_POST['pilihan_sekolah']);
        $program_takhosush = bersihkan($conn, $_POST['program_takhosush']);
        
        $sql_pendaftaran = "UPDATE pendaftaran SET status_pendaftaran='$status_pendaftaran', pilihan_sekolah='$pilihan_sekolah', program_takhosush='$program_takhosush' WHERE id=$id_pendaftar";
        $conn->query($sql_pendaftaran);

        // 2. Update Tabel Data Diri
        $nama_lengkap = bersihkan($conn, $_POST['nama_lengkap']);
        $nisn = bersihkan($conn, $_POST['nisn']);
        $nik = bersihkan($conn, $_POST['nik']);
        $tempat_lahir = bersihkan($conn, $_POST['tempat_lahir']);
        $tanggal_lahir = bersihkan($conn, $_POST['tanggal_lahir']);
        $jenis_kelamin = bersihkan($conn, $_POST['jenis_kelamin']);
        $hobi = bersihkan($conn, $_POST['hobi']);
        
        $sql_diri = "UPDATE data_diri SET nama_lengkap='$nama_lengkap', nisn='$nisn', nik='$nik', tempat_lahir='$tempat_lahir', tanggal_lahir='$tanggal_lahir', jenis_kelamin='$jenis_kelamin', hobi='$hobi' WHERE pendaftaran_id=$id_pendaftar";
        $conn->query($sql_diri);

        // 3. Update Tabel Alamat
        $alamat_lengkap = bersihkan($conn, $_POST['alamat_lengkap']);
        $no_whatsapp = bersihkan($conn, $_POST['no_whatsapp']);
        $desa_kelurahan = bersihkan($conn, $_POST['desa_kelurahan']);
        
        $sql_alamat = "UPDATE data_alamat SET alamat_lengkap='$alamat_lengkap', no_whatsapp='$no_whatsapp', desa_kelurahan='$desa_kelurahan' WHERE pendaftaran_id=$id_pendaftar";
        $conn->query($sql_alamat);

        // 4. Update Tabel Keluarga (Orang Tua)
        $ayah_nama = bersihkan($conn, $_POST['ayah_nama']);
        $ayah_pekerjaan = bersihkan($conn, $_POST['ayah_pekerjaan']);
        $ayah_no_hp = bersihkan($conn, $_POST['ayah_no_hp']);
        
        $ibu_nama = bersihkan($conn, $_POST['ibu_nama']);
        $ibu_pekerjaan = bersihkan($conn, $_POST['ibu_pekerjaan']);
        $ibu_no_hp = bersihkan($conn, $_POST['ibu_no_hp']);

        $sql_keluarga = "UPDATE data_keluarga SET 
            ayah_nama='$ayah_nama', ayah_pekerjaan='$ayah_pekerjaan', ayah_no_hp='$ayah_no_hp',
            ibu_nama='$ibu_nama', ibu_pekerjaan='$ibu_pekerjaan', ibu_no_hp='$ibu_no_hp'
            WHERE pendaftaran_id=$id_pendaftar";
        $conn->query($sql_keluarga);

        // 5. Update Tabel Sekolah Asal
        $nama_sekolah = bersihkan($conn, $_POST['nama_sekolah']);
        $tahun_lulus = bersihkan($conn, $_POST['tahun_lulus']);
        $npsn_sekolah = bersihkan($conn, $_POST['npsn_sekolah']);
        
        $sql_sekolah = "UPDATE sekolah_asal SET 
            nama_sekolah='$nama_sekolah', tahun_lulus='$tahun_lulus', npsn_sekolah='$npsn_sekolah'
            WHERE pendaftaran_id=$id_pendaftar";
        $conn->query($sql_sekolah);

        // Commit jika berhasil
        $conn->commit();
        $status_pesan = 'sukses';
        $pesan = "Data pendaftar berhasil diperbarui!";

    } catch (Exception $e) {
        $conn->rollback();
        $status_pesan = 'gagal';
        $pesan = "Gagal memperbarui data: " . $e->getMessage();
    }
}

// --- AMBIL DATA LAMA UNTUK DITAMPILKAN DI FORM ---
$q_utama = $conn->query("SELECT p.*, d.* FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE p.id = $id_pendaftar");
if ($q_utama->num_rows == 0) die("Data pendaftar tidak ditemukan.");
$data = $q_utama->fetch_assoc();

$q_alamat = $conn->query("SELECT * FROM data_alamat WHERE pendaftaran_id = $id_pendaftar");
$alamat = $q_alamat->fetch_assoc() ?: [];

$q_keluarga = $conn->query("SELECT * FROM data_keluarga WHERE pendaftaran_id = $id_pendaftar");
$keluarga = $q_keluarga->fetch_assoc() ?: [];

$q_sekolah = $conn->query("SELECT * FROM sekolah_asal WHERE pendaftaran_id = $id_pendaftar");
$sekolah = $q_sekolah->fetch_assoc() ?: [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pendaftar - <?= htmlspecialchars($data['nama_lengkap']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --primary-green: #0da15b; 
            --dark-green: #087d46;
            --light-green: #e8f5e9;
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
            padding: 25px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--dark-green);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 0.9rem;
            border: 1px solid #cbd5e1;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(13, 161, 91, 0.25);
        }

        .btn-solid-custom { 
            background-color: var(--primary-green); 
            color: #ffffff !important; 
            font-weight: 500; 
            border: 1.5px solid var(--primary-green);
            border-radius: 50px;
            padding: 8px 25px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .btn-solid-custom:hover { 
            background-color: var(--dark-green) !important; 
            border-color: var(--dark-green) !important;
        }

        .status-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }

        /* --- STYLING NAVIGASI TAB --- */
        .custom-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 25px;
            gap: 30px;
            overflow-x: auto; /* Untuk responsif di HP */
            white-space: nowrap;
        }

        .custom-tab-item {
            padding: 10px 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: #94a3b8; /* Abu-abu muted */
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            margin-bottom: -1px; /* Menutupi border div utama */
        }

        .custom-tab-item:hover {
            color: var(--primary-green);
        }

        .custom-tab-item.active {
            color: var(--primary-green);
            border-bottom-color: var(--primary-green);
        }

        /* Animasi Transisi Tab */
        .tab-content-section {
            display: none;
        }

        .tab-content-section.active {
            display: block;
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <!-- Topbar -->
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <a href="data_pendaftar.php" class="btn btn-light me-3 shadow-sm" style="border-radius: 10px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h5 class="fw-bold text-dark m-0">Edit Data Pendaftar</h5>
                    <p class="text-muted m-0" style="font-size: 0.8rem;">No. Daftar: <strong><?= htmlspecialchars($data['no_pendaftaran']) ?></strong></p>
                </div>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_lengkap_admin) ?>&background=1e293b&color=fff&rounded=true" width="40">
            </div>
        </div>

        <form action="" method="POST" id="formEdit">
            <div class="row">
                
                <!-- KOLOM KIRI (Pengaturan Status & Sekolah) -->
                <div class="col-xl-4 col-lg-4">
                    <div class="card-custom status-box mb-4">
                        <h6 class="section-title"><i class="fas fa-cog text-primary-green me-2"></i> Pengaturan Utama</h6>
                        
                        <div class="mb-3">
                            <label class="form-label text-dark fw-bold">Status Berkas Pendaftaran</label>
                            <select class="form-select border-primary" name="status_pendaftaran" style="border-width: 2px;">
                                <option value="Menunggu Verifikasi" <?= $data['status_pendaftaran'] == 'Menunggu Verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                                <option value="Proses Seleksi" <?= $data['status_pendaftaran'] == 'Proses Seleksi' ? 'selected' : '' ?>>Proses Seleksi</option>
                                <option value="Lengkap" <?= $data['status_pendaftaran'] == 'Lengkap' ? 'selected' : '' ?>>Lengkap</option>
                                <option value="Belum Lengkap" <?= $data['status_pendaftaran'] == 'Belum Lengkap' ? 'selected' : '' ?>>Belum Lengkap</option>
                                <option value="Batal" <?= $data['status_pendaftaran'] == 'Batal' ? 'selected' : '' ?>>Batal / Mengundurkan Diri</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pilihan Sekolah</label>
                            <select class="form-select" name="pilihan_sekolah">
                                <option value="RA" <?= $data['pilihan_sekolah'] == 'RA' ? 'selected' : '' ?>>RA</option>
                                <option value="MI" <?= $data['pilihan_sekolah'] == 'MI' ? 'selected' : '' ?>>MI</option>
                                <option value="MTs" <?= $data['pilihan_sekolah'] == 'MTs' ? 'selected' : '' ?>>MTs</option>
                                <option value="MA" <?= $data['pilihan_sekolah'] == 'MA' ? 'selected' : '' ?>>MA</option>
                                <option value="SMK" <?= $data['pilihan_sekolah'] == 'SMK' ? 'selected' : '' ?>>SMK</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Program Takhosush</label>
                            <select class="form-select" name="program_takhosush">
                                <option value="Ya" <?= $data['program_takhosush'] == 'Ya' ? 'selected' : '' ?>>Ya, Mengikuti</option>
                                <option value="Tidak" <?= $data['program_takhosush'] == 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-solid-custom w-100"><i class="fas fa-save me-2"></i> Simpan Perubahan</button>
                    </div>
                </div>

                <!-- KOLOM KANAN (Tab Form Edit) -->
                <div class="col-xl-8 col-lg-8">
                    <div class="card-custom">
                        
                        <!-- Navigasi Tab -->
                        <div class="custom-tabs">
                            <div class="custom-tab-item active" onclick="switchTab(event, 'tab-diri')">Data Diri & Alamat</div>
                            <div class="custom-tab-item" onclick="switchTab(event, 'tab-ortu')">Data Orang Tua</div>
                            <div class="custom-tab-item" onclick="switchTab(event, 'tab-sekolah')">Sekolah Asal</div>
                        </div>

                        <!-- TAB 1: DATA DIRI & ALAMAT -->
                        <div id="tab-diri" class="tab-content-section active">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($data['nama_lengkap']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">NISN</label>
                                    <input type="text" class="form-control" name="nisn" value="<?= htmlspecialchars($data['nisn']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">NIK Siswa</label>
                                    <input type="text" class="form-control" name="nik" value="<?= htmlspecialchars($data['nik']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tempat Lahir</label>
                                    <input type="text" class="form-control" name="tempat_lahir" value="<?= htmlspecialchars($data['tempat_lahir']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control" name="tanggal_lahir" value="<?= htmlspecialchars($data['tanggal_lahir']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jenis Kelamin</label>
                                    <select class="form-select" name="jenis_kelamin" required>
                                        <option value="Laki-laki" <?= $data['jenis_kelamin'] == 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                                        <option value="Perempuan" <?= $data['jenis_kelamin'] == 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hobi</label>
                                    <input type="text" class="form-control" name="hobi" value="<?= htmlspecialchars($data['hobi'] ?? '') ?>">
                                </div>
                                <div class="col-md-12">
                                    <hr class="mt-2 mb-2 text-muted">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Alamat Jalan / Dusun</label>
                                    <input type="text" class="form-control" name="alamat_lengkap" value="<?= htmlspecialchars($alamat['alamat_lengkap'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Desa / Kelurahan</label>
                                    <input type="text" class="form-control" name="desa_kelurahan" value="<?= htmlspecialchars($alamat['desa_kelurahan'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. WhatsApp / HP</label>
                                    <input type="text" class="form-control" name="no_whatsapp" value="<?= htmlspecialchars($alamat['no_whatsapp'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: DATA ORANG TUA -->
                        <div id="tab-ortu" class="tab-content-section">
                            <div class="row g-3">
                                <!-- Ayah -->
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark border-bottom pb-2 mt-2">Ayah Kandung</h6>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama Ayah</label>
                                    <input type="text" class="form-control" name="ayah_nama" value="<?= htmlspecialchars($keluarga['ayah_nama'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pekerjaan Ayah</label>
                                    <input type="text" class="form-control" name="ayah_pekerjaan" value="<?= htmlspecialchars($keluarga['ayah_pekerjaan'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. HP Ayah</label>
                                    <input type="text" class="form-control" name="ayah_no_hp" value="<?= htmlspecialchars($keluarga['ayah_no_hp'] ?? '') ?>">
                                </div>
                                
                                <!-- Ibu -->
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark border-bottom pb-2 mt-4">Ibu Kandung</h6>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama Ibu</label>
                                    <input type="text" class="form-control" name="ibu_nama" value="<?= htmlspecialchars($keluarga['ibu_nama'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pekerjaan Ibu</label>
                                    <input type="text" class="form-control" name="ibu_pekerjaan" value="<?= htmlspecialchars($keluarga['ibu_pekerjaan'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. HP Ibu</label>
                                    <input type="text" class="form-control" name="ibu_no_hp" value="<?= htmlspecialchars($keluarga['ibu_no_hp'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: SEKOLAH ASAL -->
                        <div id="tab-sekolah" class="tab-content-section">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Nama Sekolah Asal</label>
                                    <input type="text" class="form-control" name="nama_sekolah" value="<?= htmlspecialchars($sekolah['nama_sekolah'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tahun Lulus</label>
                                    <input type="text" class="form-control" name="tahun_lulus" value="<?= htmlspecialchars($sekolah['tahun_lulus'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">NPSN Sekolah</label>
                                    <input type="text" class="form-control" name="npsn_sekolah" value="<?= htmlspecialchars($sekolah['npsn_sekolah'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // --- FUNGSI NAVIGASI TAB ---
    function switchTab(event, tabId) {
        // Hapus class 'active' dari semua tombol tab
        let tabs = document.querySelectorAll('.custom-tab-item');
        tabs.forEach(tab => tab.classList.remove('active'));

        // Hapus class 'active' dari semua konten form
        let contents = document.querySelectorAll('.tab-content-section');
        contents.forEach(content => content.classList.remove('active'));

        // Tambahkan class 'active' ke tab yang diklik dan konten yang sesuai
        event.currentTarget.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Tampilkan notifikasi jika ada proses POST (Update Data)
        <?php if ($status_pesan == 'sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= $pesan ?>',
                confirmButtonColor: '#0da15b',
                confirmButtonText: 'Oke'
            }).then(() => {
                // Opsional: Arahkan ke halaman detail setelah diedit
                window.location.href = 'detail_pendaftar.php?id=<?= $id_pendaftar ?>';
            });
        <?php elseif ($status_pesan == 'gagal'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '<?= addslashes($pesan) ?>',
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>

        // Konfirmasi sebelum submit
        document.getElementById('formEdit').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Simpan Perubahan?',
                text: "Pastikan data yang Anda ubah sudah benar.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0da15b',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Simpan!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit(); // Lanjutkan proses submit
                }
            })
        });
    });
</script>

</body>
</html>