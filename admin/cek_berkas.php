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

// PERBAIKAN: Izinkan juga Admin Pendaftaran untuk mengakses halaman ini
if ($role != 'Developer' && $role != 'Super Admin' && $role != 'Admin Pendaftaran') {
    die("Akses ditolak! Anda tidak memiliki izin.");
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
    die("ID Pendaftaran tidak valid!");
}

$id_pendaftar = intval($_GET['id']);
$status_pesan = '';

// --- PROSES UPLOAD BERKAS OLEH ADMIN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_berkas'])) {
    $jenis_kolom = mysqli_real_escape_string($conn, trim($_POST['jenis_kolom']));
    $allowed_columns = ['pas_foto', 'kartu_keluarga', 'ktp_ortu', 'akta_kelahiran', 'ijazah_skhu', 'piagam_prestasi'];

    if (in_array($jenis_kolom, $allowed_columns) && isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        $dir_berkas = "../uploads/berkas/";
        if (!file_exists($dir_berkas)) mkdir($dir_berkas, 0777, true);

        // Ambil No Pendaftaran untuk penamaan file
        $q_no = $conn->query("SELECT no_pendaftaran FROM pendaftaran WHERE id = $id_pendaftar");
        $no_pendaftaran = $q_no->fetch_assoc()['no_pendaftaran'];

        $file_info = pathinfo($_FILES['file_upload']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

        if (in_array($ext, $allowed_ext)) {
            $new_filename = $no_pendaftaran . "_" . $jenis_kolom . "_" . time() . "." . $ext;
            $target_file = $dir_berkas . $new_filename;
            $db_filepath = "uploads/berkas/" . $new_filename;

            if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file)) {
                // Hapus file lama jika ada
                $q_old = $conn->query("SELECT $jenis_kolom FROM data_berkas WHERE pendaftaran_id = $id_pendaftar");
                if ($q_old && $q_old->num_rows > 0) {
                    $old_data = $q_old->fetch_assoc();
                    if (!empty($old_data[$jenis_kolom]) && file_exists("../" . $old_data[$jenis_kolom])) {
                        unlink("../" . $old_data[$jenis_kolom]);
                    }
                }

                // Update atau Insert ke database
                $cek_berkas = $conn->query("SELECT id FROM data_berkas WHERE pendaftaran_id = $id_pendaftar");
                if ($cek_berkas && $cek_berkas->num_rows > 0) {
                    $conn->query("UPDATE data_berkas SET $jenis_kolom = '$db_filepath' WHERE pendaftaran_id = $id_pendaftar");
                } else {
                    $conn->query("INSERT INTO data_berkas (pendaftaran_id, $jenis_kolom) VALUES ($id_pendaftar, '$db_filepath')");
                }
                $status_pesan = 'sukses_upload';
            } else {
                $status_pesan = 'gagal_upload';
            }
        } else {
            $status_pesan = 'format_salah';
        }
    }
}

// --- PROSES UPDATE STATUS JIKA TOMBOL DIVALIDASI ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['status_verifikasi'])) {
    $status_baru = mysqli_real_escape_string($conn, trim($_POST['status_verifikasi']));
    
    // Update status pendaftaran
    if ($conn->query("UPDATE pendaftaran SET status_pendaftaran='$status_baru' WHERE id=$id_pendaftar")) {
        $status_pesan = 'sukses_status';
    } else {
        $status_pesan = 'gagal_status';
    }
}

// Ambil Data Siswa
$q_utama = $conn->query("SELECT p.*, d.nama_lengkap FROM pendaftaran p JOIN data_diri d ON p.id = d.pendaftaran_id WHERE p.id = $id_pendaftar");
if ($q_utama->num_rows == 0) die("Data pendaftar tidak ditemukan.");
$data = $q_utama->fetch_assoc();

// Ambil Data Berkas (Di-refresh setelah proses POST)
$q_berkas = $conn->query("SELECT * FROM data_berkas WHERE pendaftaran_id = $id_pendaftar");
$berkas = $q_berkas->fetch_assoc() ?: [];

// List dokumen untuk ditampilkan secara looping, disertai ID Kolom
$list_dokumen = [
    'pas_foto' => ['judul' => 'Pas Foto (3x4)', 'path' => $berkas['pas_foto'] ?? ''],
    'kartu_keluarga' => ['judul' => 'Kartu Keluarga (KK)', 'path' => $berkas['kartu_keluarga'] ?? ''],
    'ktp_ortu' => ['judul' => 'KTP Orang Tua/Wali', 'path' => $berkas['ktp_ortu'] ?? ''],
    'akta_kelahiran' => ['judul' => 'Akta Kelahiran', 'path' => $berkas['akta_kelahiran'] ?? ''],
    'ijazah_skhu' => ['judul' => 'Ijazah / SKL', 'path' => $berkas['ijazah_skhu'] ?? ''],
    'piagam_prestasi' => ['judul' => 'Piagam Prestasi', 'path' => $berkas['piagam_prestasi'] ?? '']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Berkas - <?= htmlspecialchars($data['nama_lengkap']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
        
        /* Topbar Style */
        .topbar-card { 
            background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
            display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; 
        }
        
        /* Card Custom */
        .card-custom { 
            background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
            padding: 25px; margin-bottom: 20px; 
        }
        
        /* Document Box Styling */
        .doc-box {
            border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px;
            text-align: center; height: 100%; transition: 0.3s; background: #f8fafc;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .doc-box:hover { border-color: var(--primary-green); background: #ffffff; box-shadow: 0 5px 15px rgba(13,161,91,0.08); }
        
        .doc-preview {
            width: 100%; height: 220px; object-fit: cover; background: #e2e8f0; 
            border-radius: 8px; margin-bottom: 15px; cursor: pointer; border: 1px solid #cbd5e1;
        }
        
        .doc-pdf {
            width: 100%; height: 220px; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; background: #fee2e2; 
            color: #ef4444; border-radius: 8px; margin-bottom: 15px; border: 1px solid #fca5a5;
        }
        
        .doc-empty {
            width: 100%; height: 220px; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; border: 2px dashed #cbd5e1;
            color: #94a3b8; border-radius: 8px; margin-bottom: 15px; background: transparent;
        }

        /* Custom Buttons */
        .btn-solid-custom { 
            background-color: var(--primary-green); color: #ffffff !important; font-weight: 500; 
            border-radius: 50px; padding: 10px 20px; font-size: 0.85rem; transition: all 0.3s; border: none; width: 100%;
        }
        .btn-solid-custom:hover { background-color: var(--dark-green); box-shadow: 0 4px 10px rgba(13,161,91,0.2); }
        
        .btn-warning-custom { 
            background-color: #ffffff; color: #f59e0b !important; font-weight: 500; border: 1.5px solid #f59e0b;
            border-radius: 50px; padding: 8px 20px; font-size: 0.85rem; transition: all 0.3s; width: 100%;
        }
        .btn-warning-custom:hover { background-color: #f59e0b; color: #ffffff !important; }

        .btn-view-doc {
            border-radius: 50px; font-size: 0.8rem; font-weight: 500; padding: 6px 15px;
            border: 1px solid #cbd5e1; color: #475569; background: #ffffff; transition: 0.2s;
        }
        .btn-view-doc:hover { background: #f1f5f9; color: var(--text-dark); border-color: #94a3b8;}
        
        .btn-outline-primary {
            border: 1px solid #3b82f6; color: #3b82f6; background: #ffffff;
        }
        .btn-outline-primary:hover { background: #eff6ff; color: #2563eb; }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <!-- Header -->
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <a href="verifikasi_berkas.php" class="btn btn-light me-3 shadow-sm" style="border-radius: 10px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h5 class="fw-bold text-dark m-0">Verifikasi Dokumen: <?= htmlspecialchars($data['nama_lengkap']) ?></h5>
                    <p class="text-muted m-0" style="font-size: 0.8rem;">Status Saat Ini: <strong class="text-primary-green"><?= htmlspecialchars($data['status_pendaftaran']) ?></strong></p>
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

        <div class="row">
            <!-- AREA DOKUMEN KIRI -->
            <div class="col-xl-9 col-lg-8">
                <div class="card-custom">
                    <h6 class="fw-bold text-dark mb-4 border-bottom pb-2"><i class="fas fa-folder-open text-primary-green me-2"></i> Hasil Unggahan Calon Santri & Admin</h6>
                    
                    <div class="row g-4">
                        <?php foreach($list_dokumen as $kolom => $doc): 
                            $judul = $doc['judul'];
                            $path = $doc['path'];
                            $ada = !empty($path) && file_exists("../".$path);
                        ?>
                        <div class="col-md-4">
                            <div class="doc-box">
                                <div>
                                    <?php if($ada): 
                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                        if(in_array($ext, ['jpg', 'jpeg', 'png'])):
                                    ?>
                                        <!-- Jika Gambar -->
                                        <a href="../<?= $path ?>" target="_blank" title="Klik untuk memperbesar">
                                            <img src="../<?= $path ?>" class="doc-preview" alt="<?= $judul ?>">
                                        </a>
                                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.9rem;"><?= $judul ?></h6>
                                    
                                    <?php elseif($ext == 'pdf'): ?>
                                        <!-- Jika PDF -->
                                        <div class="doc-pdf">
                                            <i class="fas fa-file-pdf fa-3x mb-3"></i>
                                            <span class="fw-bold" style="font-size: 0.85rem;">Dokumen PDF</span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.9rem;"><?= $judul ?></h6>
                                    
                                    <?php endif; else: ?>
                                        <!-- Jika Kosong -->
                                        <div class="doc-empty">
                                            <i class="fas fa-times-circle fa-2x mb-2"></i>
                                            <span style="font-size: 0.8rem;">Belum Diunggah</span>
                                        </div>
                                        <h6 class="fw-bold text-muted mb-2" style="font-size: 0.9rem;"><?= $judul ?></h6>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <?php if($ada): ?>
                                        <div class="d-flex gap-2">
                                            <a href="../<?= $path ?>" target="_blank" class="btn btn-view-doc w-50" title="Buka Dokumen"><i class="fas fa-external-link-alt"></i> Buka</a>
                                            <button type="button" class="btn btn-view-doc btn-outline-primary w-50" onclick="bukaModalUpload('<?= $kolom ?>', '<?= $judul ?>')" title="Ganti/Ubah Dokumen"><i class="fas fa-edit"></i> Ganti</button>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-success w-100 btn-view-doc" onclick="bukaModalUpload('<?= $kolom ?>', '<?= $judul ?>')"><i class="fas fa-upload me-1"></i> Unggah Dokumen</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- AREA AKSI KANAN (Sticky) -->
            <div class="col-xl-3 col-lg-4">
                <div class="card-custom" style="position: sticky; top: 30px;">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-gavel text-primary-green me-2"></i> Keputusan Verifikasi</h6>
                    <p class="text-muted" style="font-size: 0.8rem; line-height: 1.5;">Periksa dokumen dengan saksama. Tentukan apakah berkas memenuhi syarat untuk ditandai Lengkap.</p>
                    
                    <form action="" method="POST" id="formVerifikasi">
                        
                        <!-- Tombol Berkas Lengkap -->
                        <div class="mb-4 mt-4">
                            <button type="button" onclick="konfirmasiValid()" class="btn btn-solid-custom">
                                <i class="fas fa-check-circle me-1"></i> Berkas Lengkap
                            </button>
                            <small class="text-muted mt-2" style="font-size: 0.75rem; display: block; text-align: center;">Ubah status menjadi <br><strong>Lengkap</strong></small>
                            <input type="hidden" name="status_verifikasi" id="inputStatus" value="">
                        </div>

                        <hr style="border-color: #cbd5e1;">

                        <!-- Tombol Berkas Kurang/Belum Lengkap -->
                        <div class="mb-2 mt-4">
                            <button type="button" onclick="tolakBerkas()" class="btn btn-warning-custom">
                                <i class="fas fa-exclamation-triangle me-1"></i> Belum Lengkap
                            </button>
                            <small class="text-muted mt-2" style="font-size: 0.75rem; display: block; text-align: center;">Ubah status menjadi <br><strong>Belum Lengkap</strong></small>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Unggah Dokumen (Untuk Admin) -->
<div class="modal fade" id="modalUpload" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <form action="" method="POST" enctype="multipart/form-data" id="formUpload">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-cloud-upload-alt text-primary-green me-2"></i> Lengkapi Berkas Santri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-2">
                    <input type="hidden" name="upload_berkas" value="1">
                    <input type="hidden" name="jenis_kolom" id="jenis_kolom" value="">
                    
                    <div class="alert bg-light border-0 mb-3" style="border-radius: 10px;">
                        Silakan pilih file untuk melengkapi dokumen:<br>
                        <strong id="nama_dokumen" class="fs-5 text-primary-green"></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih File (<span class="text-danger">JPG / PNG / PDF</span>)</label>
                        <input type="file" class="form-control" name="file_upload" accept=".jpg,.jpeg,.png,.pdf" required>
                        <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle text-primary"></i> Pastikan file terlihat jelas dan dapat dibaca. Admin dapat mengunggah file langsung jika santri membawa berkas fisik / menyusul dokumen.</small>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 mt-2">
                    <button type="button" class="btn text-muted fw-medium" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white px-4 btn-solid-custom w-auto" style="border-radius: 50px; font-weight: 500;" id="btnSubmitUpload"><i class="fas fa-save me-2"></i> Simpan Dokumen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // --- Membuka Modal Upload Dokumen ---
    function bukaModalUpload(kolom, judul) {
        document.getElementById('jenis_kolom').value = kolom;
        document.getElementById('nama_dokumen').innerText = judul;
        var uploadModal = new bootstrap.Modal(document.getElementById('modalUpload'));
        uploadModal.show();
    }

    // Ganti Teks Tombol saat mengunggah
    document.getElementById('formUpload').addEventListener('submit', function() {
        let btn = document.getElementById('btnSubmitUpload');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Mengunggah...';
        btn.classList.add('disabled');
    });

    // --- Konfirmasi Berkas LENGKAP ---
    function konfirmasiValid() {
        Swal.fire({
            title: 'Berkas Lengkap?',
            text: "Anda menyatakan bahwa seluruh berkas valid dan lengkap.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0da15b',
            cancelButtonColor: '#cbd5e1',
            confirmButtonText: 'Ya, Lengkap!',
            cancelButtonText: '<span class="text-dark">Batal</span>',
            reverseButtons: true,
            customClass: { confirmButton: 'rounded-pill px-4', cancelButton: 'rounded-pill px-4' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('inputStatus').value = 'Lengkap'; // Nilai baru ke DB
                document.getElementById('formVerifikasi').submit();
            }
        });
    }

    // --- Konfirmasi Berkas BELUM LENGKAP ---
    function tolakBerkas() {
        Swal.fire({
            title: 'Berkas Belum Lengkap?',
            html: "Calon santri ini akan ditandai dengan status <b>Belum Lengkap</b> dan diminta untuk melengkapinya nanti.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#cbd5e1',
            confirmButtonText: 'Ya, Belum Lengkap!',
            cancelButtonText: '<span class="text-dark">Batal</span>',
            reverseButtons: true,
            customClass: { confirmButton: 'rounded-pill px-4', cancelButton: 'rounded-pill px-4' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('inputStatus').value = 'Belum Lengkap'; // Nilai baru ke DB
                document.getElementById('formVerifikasi').submit();
            }
        });
    }

    // --- Notifikasi Hasil Simpan & Upload ---
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($status_pesan == 'sukses_status'): ?>
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Status verifikasi pendaftar berhasil diperbarui.', confirmButtonColor: '#0da15b', timer: 2000, showConfirmButton: false });
        <?php elseif ($status_pesan == 'gagal_status'): ?>
            Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan sistem saat memperbarui status.', confirmButtonColor: '#ef4444' });
        
        <?php elseif ($status_pesan == 'sukses_upload'): ?>
            Swal.fire({ icon: 'success', title: 'Tersimpan!', text: 'Dokumen berhasil diunggah/diperbarui.', confirmButtonColor: '#0da15b', timer: 2000, showConfirmButton: false });
        <?php elseif ($status_pesan == 'format_salah'): ?>
            Swal.fire({ icon: 'warning', title: 'Format Tidak Didukung!', text: 'Harap hanya unggah file JPG, PNG, atau PDF.', confirmButtonColor: '#f59e0b' });
        <?php elseif ($status_pesan == 'gagal_upload'): ?>
            Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi masalah saat menyimpan dokumen. Silakan coba lagi.', confirmButtonColor: '#ef4444' });
        <?php endif; ?>
    });
</script>

</body>
</html>