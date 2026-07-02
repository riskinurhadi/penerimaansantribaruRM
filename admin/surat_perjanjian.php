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

// PERBAIKAN: Menambahkan Hak Akses untuk Admin Keamanan
if ($role != 'Developer' && $role != 'Super Admin' && $role != 'Admin Keamanan') {
    die("Akses ditolak! Anda tidak memiliki izin untuk mengelola Surat Perjanjian.");
}

// =========================================================================
// AUTO-PATCH DATABASE: Cek & Tambah kolom 'surat_perjanjian' jika belum ada
// =========================================================================
$check_col = $conn->query("SHOW COLUMNS FROM data_berkas LIKE 'surat_perjanjian'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE data_berkas ADD COLUMN surat_perjanjian VARCHAR(255) NULL AFTER piagam_prestasi");
}

$status_pesan = '';
$pesan_final = '';

// =========================================================================
// PROSES UNGGAH FILE ARSIP SURAT PERJANJIAN (MULTI IMAGE TO PDF)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_pendaftaran']) && isset($_FILES['file_arsip'])) {
    $id_upload = intval($_POST['id_pendaftaran']);
    $files = $_FILES['file_arsip'];
    $file_count = count($files['name']);

    if ($file_count > 0 && $files['error'][0] != 4) { 
        // Ambil no pendaftaran untuk nama file
        $q_no = $conn->query("SELECT no_pendaftaran FROM pendaftaran WHERE id = $id_upload");
        $no_daftar = $q_no->fetch_assoc()['no_pendaftaran'];

        $dir = "../uploads/perjanjian/";
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        
        $filename = "Perjanjian_" . $no_daftar . "_" . time() . ".pdf"; // Hasil akhir WAJIB PDF
        $filepath = $dir . $filename;
        $db_filepath = "uploads/perjanjian/" . $filename;

        $has_pdf = false;
        $all_images = true;
        
        // Cek tipe file yang diunggah
        for($i = 0; $i < $file_count; $i++) {
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if($ext == '') $ext = 'jpg'; 
            
            if ($ext == 'pdf') $has_pdf = true;
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) $all_images = false;
        }

        $berhasil_disimpan = false;
        $pesan_tambahan = ""; // Untuk menampung status sistem Python

        if ($file_count == 1 && $has_pdf) {
            if (move_uploaded_file($files['tmp_name'][0], $filepath)) {
                $berhasil_disimpan = true;
            }
        } 
        elseif ($all_images && !$has_pdf) {
            require('fpdf/fpdf.php'); 
            // Ukuran F4 sebagai standar dasar canvas
            $pdf = new FPDF('P', 'mm', array(215, 330)); 
            
            // PERBAIKAN: Deteksi keamanan server (aaPanel)
            // Mengecek apakah fungsi shell_exec diblokir oleh php.ini
            $disabled_functions = explode(',', ini_get('disable_functions'));
            $disabled_functions = array_map('trim', $disabled_functions);
            $shell_enabled = !in_array('shell_exec', $disabled_functions) && is_callable('shell_exec');
            
            if (isset($_POST['use_python_scanner']) && $_POST['use_python_scanner'] == '1' && !$shell_enabled) {
                $pesan_tambahan = " Namun, efek Scanner dimatikan karena pengaturan keamanan VPS/aaPanel (Fungsi 'shell_exec' didisable).";
            }

            for($i = 0; $i < $file_count; $i++) {
                $tmp_name = $files['tmp_name'][$i];
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if($ext == '') $ext = 'jpg';
                $type = strtoupper($ext);
                if($type == 'JPG') $type = 'JPEG';
                
                // PERBAIKAN ANTI-CRASH: 
                // Pindahkan gambar dari folder temporary sistem ke folder web kita
                // agar Python tidak terhalang masalah hak akses folder /tmp
                $safe_temp_file = $dir . "temp_scan_" . time() . "_" . $i . "." . $ext;
                move_uploaded_file($tmp_name, $safe_temp_file);
                
                // ========================================================
                // TRIGGER ENGINE PYTHON (OPENCV) JIKA SCANNER DIAKTIFKAN
                // ========================================================
                if (isset($_POST['use_python_scanner']) && $_POST['use_python_scanner'] == '1' && $shell_enabled && in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $python_script = __DIR__ . '/scan.py'; 
                    if (file_exists($python_script)) {
                        // Memanggil Python dan menangkap output error (2>&1) agar tidak menyebabkan Error 500
                        $cmd = "python3 " . escapeshellarg($python_script) . " " . escapeshellarg($safe_temp_file) . " 2>&1";
                        $output = shell_exec($cmd);
                        
                        // Fallback VPS: Kadang perintahnya hanya 'python' bukan 'python3'
                        if (stripos($output, 'not found') !== false || stripos($output, 'is not recognized') !== false) {
                            $cmd2 = "python " . escapeshellarg($python_script) . " " . escapeshellarg($safe_temp_file) . " 2>&1";
                            shell_exec($cmd2);
                        }
                    }
                }
                // ========================================================

                $pdf->AddPage();
                
                // PERBAIKAN ANTI-CRASH LENGKAP: 
                // Jika Python gagal atau merusak file, getimagesize() akan gagal.
                // Hal ini mencegah FPDF memunculkan fatal error dan HTTP 500
                if (file_exists($safe_temp_file) && @getimagesize($safe_temp_file) !== false) {
                    $pdf->Image($safe_temp_file, 0, 0, 215, 330, $type);
                }
                
                // Hapus file temp setelah masuk PDF untuk hemat ruang server
                if (file_exists($safe_temp_file)) {
                    unlink($safe_temp_file);
                }
            }
            $pdf->Output('F', $filepath);
            $berhasil_disimpan = true;
        } else {
            $status_pesan = 'format_salah';
        }

        if ($berhasil_disimpan) {
            $q_old = $conn->query("SELECT surat_perjanjian FROM data_berkas WHERE pendaftaran_id = $id_upload");
            if ($q_old && $q_old->num_rows > 0) {
                $old_file = $q_old->fetch_assoc()['surat_perjanjian'];
                if (!empty($old_file) && file_exists("../" . $old_file)) unlink("../" . $old_file);
            }

            $conn->query("UPDATE data_berkas SET surat_perjanjian = '$db_filepath' WHERE pendaftaran_id = $id_upload");
            
            // Set notifikasi sukses
            $status_pesan = empty($pesan_tambahan) ? 'sukses' : 'sukses_dengan_catatan';
            $pesan_final  = "Arsip berhasil disimpan." . $pesan_tambahan;
            
        } elseif (empty($status_pesan)) {
            $status_pesan = 'gagal_upload';
        }
    }
}

// --- AMBIL DATA PENDAFTAR, ORANG TUA, & STATUS ARSIP ---
$query = "
    SELECT 
        p.id, 
        p.no_pendaftaran, 
        d.nama_lengkap as nama_santri, 
        p.pilihan_sekolah,
        k.ayah_nama,
        k.wali_nama,
        b.surat_perjanjian
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    JOIN data_keluarga k ON p.id = k.pendaftaran_id
    LEFT JOIN data_berkas b ON p.id = b.pendaftaran_id
    ORDER BY p.created_at DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Surat Perjanjian - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">

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

        .btn-outline-custom { border: 1.5px solid var(--primary-green); color: var(--primary-green); font-weight: 500; border-radius: 50px; padding: 6px 18px; font-size: 0.85rem; transition: all 0.3s; display: inline-block; }
        .btn-outline-custom:hover { background-color: var(--primary-green); color: #ffffff !important; }
        
        /* Action Buttons */
        .btn-act { border: none; border-radius: 8px; padding: 6px 12px; font-size: 0.8rem; transition: 0.2s; color: white;}
        .btn-act-print { background-color: #1e293b; }
        .btn-act-print:hover { background-color: #0f172a; transform: translateY(-2px);}
        .btn-act-upload { background-color: #0da15b; }
        .btn-act-upload:hover { background-color: #087d46; transform: translateY(-2px);}
        .btn-act-view { background-color: #0284c7; }
        .btn-act-view:hover { background-color: #0369a1; transform: translateY(-2px);}

        /* Camera Settings */
        #videoContainer {
            position: relative; overflow: hidden; border-radius: 8px; background-color: #000;
            display: flex; justify-content: center; align-items: center; min-height: 400px;
        }
        #videoStream {
            width: 100%; 
            max-height: 70vh; 
            object-fit: cover;
        }
        
        /* Cropper Settings */
        .img-container {
            max-height: 70vh;
            width: 100%;
        }
        .img-container img {
            display: block;
            max-width: 100%;
        }
        
        .camera-guide {
            position: absolute;
            top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 80%; max-width: 400px; 
            aspect-ratio: 215 / 330; 
            border: 2px dashed rgba(255, 255, 255, 0.4);
            pointer-events: none; border-radius: 8px;
        }

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
                <h5 class="fw-bold text-dark m-0">Arsip & Dokumen Perjanjian</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="card-custom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h5 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Daftar Surat Perjanjian Santri</h5>
                    <p class="text-muted m-0 mt-1" style="font-size: 0.8rem;">Cetak blanko otomatis, lalu unggah kembali dokumen yang sudah ditandatangani.</p>
                </div>
                <button class="btn btn-outline-custom bg-white" onclick="window.location.reload()"><i class="fas fa-sync-alt me-2"></i> Segarkan Data</button>
            </div>

            <div class="table-responsive">
                <table id="tabelSurat" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="15%">NO DAFTAR</th>
                            <th width="25%">NAMA CALON SANTRI</th>
                            <th width="20%">PENANGGUNG JAWAB</th>
                            <th width="15%" class="text-center">STATUS ARSIP</th>
                            <th width="20%" class="text-center">AKSI SURAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                $ortu_utama = !empty($row['wali_nama']) ? $row['wali_nama'] . ' (Wali)' : $row['ayah_nama'] . ' (Ayah)';
                                $is_uploaded = !empty($row['surat_perjanjian']);
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold text-primary-green"><?= htmlspecialchars($row['no_pendaftaran']) ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['nama_santri']) ?> <br>
                                    <span class="badge bg-light border text-muted mt-1 fw-normal"><?= htmlspecialchars($row['pilihan_sekolah']) ?></span>
                                </td>
                                <td><i class="fas fa-user-shield text-muted me-2"></i> <?= htmlspecialchars($ortu_utama) ?></td>
                                
                                <td class="text-center">
                                    <?php if($is_uploaded): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success" style="padding: 6px 12px; font-weight: 500;"><i class="fas fa-check-circle me-1"></i> Tersimpan</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary" style="padding: 6px 12px; font-weight: 500;"><i class="fas fa-clock me-1"></i> Belum Ada</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="cetak_perjanjian.php?id=<?= $row['id'] ?>" target="_blank" class="btn-act btn-act-print" title="Cetak Blanko Surat">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        
                                        <button class="btn-act btn-act-upload" title="Unggah Arsip" onclick="bukaModalUpload(<?= $row['id'] ?>, '<?= addslashes($row['nama_santri']) ?>')">
                                            <i class="fas fa-camera"></i>
                                        </button>

                                        <?php if($is_uploaded): ?>
                                            <a href="../<?= $row['surat_perjanjian'] ?>" target="_blank" class="btn-act btn-act-view" title="Lihat Dokumen Arsip">
                                                <i class="fas fa-eye"></i>
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

<!-- Modal Unggah Arsip -->
<div class="modal fade" id="modalUpload" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <form action="" method="POST" enctype="multipart/form-data" id="formUpload" onsubmit="showLoading(event)">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-upload text-primary-green me-2"></i> Arsip Surat Perjanjian</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert text-dark mb-3" style="background-color: var(--light-green); border: 1px solid #c8e6c9; border-radius: 10px;">
                Upload berkas untuk santri: <br>
                <strong id="nama_siswa_upload" class="fs-6"></strong>
            </div>
            
            <input type="hidden" name="id_pendaftaran" id="id_pendaftaran_upload">

            <!-- Tombol Pilih File atau Kamera -->
            <div class="d-flex gap-2 mb-3">
                <input type="file" class="d-none" name="file_arsip[]" id="file_arsip" multiple accept=".pdf, .jpg, .jpeg, .png">
                
                <button type="button" class="btn btn-outline-secondary w-50 fw-bold" onclick="document.getElementById('file_arsip').click()">
                    <i class="fas fa-folder-open me-1"></i> Pilih File
                </button>
                <button type="button" class="btn btn-outline-success w-50 fw-bold" onclick="openCameraModal()">
                    <i class="fas fa-camera me-1"></i> Kamera & Crop
                </button>
            </div>

            <small class="text-muted d-block mb-3" style="font-size: 0.75rem;">
                <i class="fas fa-info-circle text-primary"></i> <b>TIPS:</b> Jepret 3 halaman surat, atur crop/potong agar rapi, dan sistem akan menggabungkannya menjadi 1 PDF utuh secara otomatis.
            </small>

            <!-- Fitur Baru: Integrasi Python OpenCV di Server Side -->
            <div class="form-check form-switch mb-3 p-3 bg-light rounded border">
                <input class="form-check-input ms-0 me-2" type="checkbox" name="use_python_scanner" id="usePythonScanner" value="1" checked style="width: 2.5em; height: 1.25em; cursor:pointer;">
                <label class="form-check-label fw-bold text-dark" for="usePythonScanner" style="cursor:pointer;"><i class="fas fa-magic text-warning me-1"></i> Gunakan Engine Python (CamScanner)</label>
                <small class="d-block text-muted mt-1" style="font-size: 0.75rem;">VPS LiteSpeed akan memproses dan menjernihkan foto untuk menghapus bayangan agar teks terlihat jelas (High Contrast).</small>
            </div>

            <!-- Area Preview Gambar -->
            <div id="previewContainer" class="d-flex gap-2 flex-wrap p-2 border rounded bg-light" style="min-height: 100px;">
                <div class="text-muted w-100 text-center mt-4" id="emptyPreviewText" style="font-size: 0.8rem;">Belum ada dokumen yang dipilih.</div>
            </div>

          </div>
          <div class="modal-footer border-top-0 pt-0 mt-2">
            <button type="button" class="btn text-muted" data-bs-dismiss="modal" style="font-weight: 500;">Batal</button>
            <button type="submit" class="btn text-white px-4" id="btnSimpanArsip" style="background-color: var(--primary-green); border-radius: 50px; font-weight: 500;"><i class="fas fa-cloud-upload-alt me-2"></i> Simpan Arsip</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL KAMERA & CROPPER MURNI (Tanpa Filter JS) -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: var(--primary-green);">
                <h5 class="modal-title"><i class="fas fa-crop-alt me-2"></i> Kamera Scanner</h5>
                <button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="closeCameraModal()"></button>
            </div>
            <div class="modal-body bg-dark p-2">

                <!-- Area Video Kamera -->
                <div id="videoContainer">
                    <video id="videoStream" autoplay playsinline></video>
                    <!-- Garis bantu transparan -->
                    <div class="camera-guide" id="cameraGuide"></div>
                </div>
                
                <!-- Area Hasil Jepretan & Cropper -->
                <canvas id="canvasCapture" style="display: none;"></canvas>
                <div class="img-container d-none" id="imageResultContainer">
                    <img id="imageResult" src="">
                </div>
                
                <!-- Toolbar Cropper -->
                <div id="cropperTools" class="d-none text-center mt-3 mb-2">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="setCropRatio(215/330)">F4 (Folio)</button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="setCropRatio(210/297)">A4</button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="setCropRatio(NaN)">Bebas</button>
                    </div>
                </div>

            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-warning fw-bold d-none" id="btnUlangi"><i class="fas fa-redo me-1"></i> Ulangi Jepret</button>
                <button type="button" class="btn btn-primary fw-bold" id="btnJepret"><i class="fas fa-camera text-white me-1"></i> Jepret Halaman <span id="pageCounter">1</span></button>
                <button type="button" class="btn btn-success fw-bold d-none" id="btnGunakan"><i class="fas fa-cut me-1"></i> Potong & Simpan</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Cropper.js Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<script>
    let globalDataTransfer = new DataTransfer(); 

    $(document).ready(function() {
        $('#tabelSurat').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json', search: "_INPUT_", searchPlaceholder: "Cari nama santri/ortu..." },
            "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "pageLength": 10
        });

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        <?php if ($status_pesan == 'sukses'): ?>
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($pesan_final) ?>', confirmButtonColor: '#0da15b', timer: 3000, showConfirmButton: false });
        <?php elseif ($status_pesan == 'sukses_dengan_catatan'): ?>
            Swal.fire({ icon: 'warning', title: 'Tersimpan (Efek Scanner Dinonaktifkan)', text: '<?= addslashes($pesan_final) ?>', confirmButtonColor: '#f59e0b' });
        <?php elseif ($status_pesan == 'format_salah'): ?>
            Swal.fire({ icon: 'warning', title: 'Format Tidak Valid!', text: 'Harap hanya unggah file berformat PDF, JPG, atau PNG.', confirmButtonColor: '#f59e0b' });
        <?php elseif ($status_pesan == 'gagal_upload'): ?>
            Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan saat memproses/menggabungkan file Anda.', confirmButtonColor: '#ef4444' });
        <?php endif; ?>
    });

    // Menampilkan Loader Saat Form Submit (Karena Python Membutuhkan Waktu Sedikit)
    function showLoading(e) {
        if(globalDataTransfer.files.length > 0) {
            let btn = document.getElementById('btnSimpanArsip');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Menyimpan Arsip & Dokumen...';
            btn.setAttribute('disabled', 'disabled');
        }
    }

    function bukaModalUpload(id, nama) {
        document.getElementById('id_pendaftaran_upload').value = id;
        document.getElementById('nama_siswa_upload').innerText = nama;
        
        globalDataTransfer = new DataTransfer(); 
        document.getElementById('file_arsip').value = ''; 
        renderPreviews();
        
        var modal = new bootstrap.Modal(document.getElementById('modalUpload'));
        modal.show();
    }

    document.getElementById('file_arsip').addEventListener('change', function() {
        for(let i=0; i<this.files.length; i++){
            globalDataTransfer.items.add(this.files[i]);
        }
        this.files = globalDataTransfer.files; 
        renderPreviews();
    });

    // FUNGSI RENDER PREVIEWS 
    function renderPreviews() {
        const container = document.getElementById('previewContainer');
        const files = globalDataTransfer.files;
        
        container.innerHTML = '';
        
        if (files.length === 0) {
            container.innerHTML = '<div class="text-muted w-100 text-center mt-4" id="emptyPreviewText" style="font-size: 0.8rem;">Belum ada dokumen yang dipilih.</div>';
            return;
        }

        for(let i=0; i<files.length; i++) {
            const file = files[i];
            const div = document.createElement('div');
            div.className = 'position-relative border rounded p-1 shadow-sm';
            div.style.width = '120px';
            div.style.backgroundColor = '#f8fafc';
            
            if(file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'rounded';
                img.style.height = '150px'; 
                img.style.width = '100%'; 
                img.style.objectFit = 'contain'; 
                div.appendChild(img);
            } else {
                div.innerHTML = '<i class="fas fa-file-pdf fa-3x text-danger d-block mt-4 mb-2 text-center"></i><div class="text-center fw-bold text-muted" style="font-size:0.7rem;">PDF FILE</div>';
            }
            
            const btnDel = document.createElement('button');
            btnDel.className = 'btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0 rounded-circle';
            btnDel.style.width = '22px'; btnDel.style.height = '22px'; btnDel.innerHTML = '&times;';
            btnDel.onclick = function(e) { e.preventDefault(); removeFile(i); };
            div.appendChild(btnDel);
            
            container.appendChild(div);
        }
        
        document.getElementById('file_arsip').files = globalDataTransfer.files;
    }

    function removeFile(index) {
        const dt = new DataTransfer();
        for (let i = 0; i < globalDataTransfer.files.length; i++) {
            if (i !== index) dt.items.add(globalDataTransfer.files[i]);
        }
        globalDataTransfer = dt;
        renderPreviews();
    }

    // ==========================================
    // CROPPER.JS
    // ==========================================
    const cameraModalEl = document.getElementById('cameraModal');
    const videoStream = document.getElementById('videoStream');
    const videoContainer = document.getElementById('videoContainer');
    const canvasCapture = document.getElementById('canvasCapture');
    const imageResult = document.getElementById('imageResult');
    const imageResultContainer = document.getElementById('imageResultContainer');
    const cameraGuide = document.getElementById('cameraGuide');
    const pageCounter = document.getElementById('pageCounter');
    
    const btnJepret = document.getElementById('btnJepret');
    const btnUlangi = document.getElementById('btnUlangi');
    const btnGunakan = document.getElementById('btnGunakan');
    const cropperTools = document.getElementById('cropperTools');
    
    let stream = null;
    let cropper = null;

    function dataURLtoFile(dataurl, filename) {
        var arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
        while(n--){
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new File([u8arr], filename, {type:mime});
    }

    function openCameraModal() {
        let uploadModal = bootstrap.Modal.getInstance(document.getElementById('modalUpload'));
        if (uploadModal) uploadModal.hide();

        setTimeout(() => {
            pageCounter.innerText = globalDataTransfer.files.length + 1;
            let camModal = bootstrap.Modal.getInstance(cameraModalEl) || new bootstrap.Modal(cameraModalEl);
            camModal.show();
        }, 400);
    }

    function closeCameraModal() {
        let camModal = bootstrap.Modal.getInstance(cameraModalEl);
        if (camModal) camModal.hide();
        
        if(cropper) {
            cropper.destroy();
            cropper = null;
        }

        setTimeout(() => {
            let uploadModal = bootstrap.Modal.getInstance(document.getElementById('modalUpload')) || new bootstrap.Modal(document.getElementById('modalUpload'));
            uploadModal.show();
        }, 400);
    }

    cameraModalEl.addEventListener('shown.bs.modal', async function () {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } } });
            videoStream.srcObject = stream;
            
            resetToCameraView();
        } catch (err) {
            closeCameraModal();
            Swal.fire({ icon: 'error', title: 'Kamera Tidak Tersedia', text: 'Gagal mengakses kamera.', confirmButtonColor: '#0da15b' });
        }
    });

    cameraModalEl.addEventListener('hidden.bs.modal', function() {
        if (stream) stream.getTracks().forEach(track => track.stop());
    });

    function resetToCameraView() {
        if(cropper) {
            cropper.destroy();
            cropper = null;
        }
        
        videoContainer.classList.remove('d-none');
        imageResultContainer.classList.add('d-none');
        cropperTools.classList.add('d-none');
        
        btnJepret.classList.remove('d-none');
        btnUlangi.classList.add('d-none');
        btnGunakan.classList.add('d-none');
    }

    // Aksi Saat Menjepret Gambar
    btnJepret.onclick = function() {
        canvasCapture.width = videoStream.videoWidth;
        canvasCapture.height = videoStream.videoHeight;
        
        const ctx = canvasCapture.getContext('2d');
        ctx.drawImage(videoStream, 0, 0, canvasCapture.width, canvasCapture.height);
        
        imageResult.src = canvasCapture.toDataURL('image/jpeg', 1.0);
        
        videoContainer.classList.add('d-none');
        imageResultContainer.classList.remove('d-none');
        cropperTools.classList.remove('d-none');
        
        btnJepret.classList.add('d-none');
        btnUlangi.classList.remove('d-none');
        btnGunakan.classList.remove('d-none');
        
        // Inisialisasi Cropper.js
        cropper = new Cropper(imageResult, {
            aspectRatio: 215 / 330,
            viewMode: 1,
            autoCropArea: 0.9, 
            background: false
        });
    };

    function setCropRatio(ratio) {
        if(cropper) cropper.setAspectRatio(ratio);
    }

    btnUlangi.onclick = function() {
        resetToCameraView();
    };

    // Eksekusi Potong & Simpan Sementara (Diteruskan ke PHP -> Python)
    btnGunakan.onclick = function() {
        if(!cropper) return;
        
        try {
            // Dapatkan canvas potongan gambar (Resolusi Tinggi)
            let croppedCanvas = cropper.getCroppedCanvas({
                maxWidth: 2000,
                maxHeight: 2500
            });

            // Simpan gambar mentah ke file JPG (Filter akan dilakukan oleh Python di Backend)
            const dataUrl = croppedCanvas.toDataURL('image/jpeg', 0.90); 
            const fileName = `Halaman_${globalDataTransfer.files.length + 1}_${new Date().getTime()}.jpg`;
            const file = dataURLtoFile(dataUrl, fileName);
            
            globalDataTransfer.items.add(file);
            renderPreviews(); // Panggil fungsi render
            
            let totalFiles = globalDataTransfer.files.length;
            
            if (totalFiles >= 3) {
                closeCameraModal();
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: '3 Halaman Terkumpul!', showConfirmButton: false, timer: 2500
                });
            } else {
                pageCounter.innerText = totalFiles + 1;
                resetToCameraView();
                
                const Toast = Swal.mixin({ toast: true, position: "top-end", showConfirmButton: false, timer: 2000, timerProgressBar: true });
                Toast.fire({ icon: "success", title: `Halaman ${totalFiles} dipotong & tersimpan! Lanjut potret halaman ${totalFiles + 1}.` });
            }
        } catch (err) {
            Swal.fire('Error', 'Gagal memproses gambar: ' + err.message, 'error');
        }
    };
</script>

</body>
</html>