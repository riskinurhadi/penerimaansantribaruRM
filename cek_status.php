<?php
require_once 'config.php';

$search_performed = false;
$data_found = false;
$data = null;
$pembayaran = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['keyword'])) {
    $search_performed = true;
    
    // Bersihkan input untuk mencegah SQL Injection
    $keyword = mysqli_real_escape_string($conn, trim($_POST['keyword']));

    // Query untuk mencari data pendaftar berdasarkan No Daftar ATAU Nama Lengkap
    $query = "
        SELECT p.*, d.nama_lengkap, d.nik, d.jenis_kelamin 
        FROM pendaftaran p 
        JOIN data_diri d ON p.id = d.pendaftaran_id 
        WHERE p.no_pendaftaran = '$keyword' OR d.nama_lengkap LIKE '%$keyword%'
        LIMIT 1
    ";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $data_found = true;
        $data = $result->fetch_assoc();

        // Ambil data status pembayaran jika ada
        $q_bayar = $conn->query("SELECT status_pembayaran, sisa_tagihan FROM data_pembayaran WHERE pendaftaran_id = " . $data['id']);
        if ($q_bayar && $q_bayar->num_rows > 0) {
            $pembayaran = $q_bayar->fetch_assoc();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Status Pendaftaran - PSB RM</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --primary-green: #0da15b; 
            --dark-green: #087d46;
            --light-green: #eafbf3;
            --text-dark: #2d3748;
            --text-muted: #718096;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fcf9;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- Navbar Minimalis --- */
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-wrapper {
            flex-grow: 1;
            padding: 50px 0;
            display: flex;
            align-items: center;
        }

        .search-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(13, 161, 91, 0.08);
            padding: 40px;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(13, 161, 91, 0.25);
        }

        .btn-search {
            background-color: var(--primary-green);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background-color: var(--dark-green);
            transform: translateY(-2px);
        }

        /* --- Modal Custom --- */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .result-header {
            background-color: var(--light-green);
            padding: 25px;
            border-bottom: 2px solid var(--primary-green);
            position: relative;
        }

        .btn-close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            background: transparent;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            transition: 0.3s;
        }

        .btn-close-modal:hover {
            color: var(--text-dark);
            transform: scale(1.1);
        }

        .result-body {
            padding: 30px 25px;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
        }

        /* Badge Colors */
        .bg-menunggu { background-color: #fef3c7; color: #d97706; }

        .data-row { margin-bottom: 15px; }
        .data-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; margin-bottom: 3px; }
        .data-value { font-size: 1rem; color: var(--text-dark); font-weight: 600; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand text-decoration-none" href="index.html">
                <div style="width: 40px; height: 40px; background-color: var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="d-flex flex-column" style="line-height: 1.1;">
                    <span style="font-size: 1rem;">RAUDLATUL MUTA'ALLIMIN</span>
                    <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 400;">Pusat Informasi Pendaftaran</span>
                </div>
            </a>
            <a href="index.html" class="btn btn-outline-secondary btn-sm rounded-pill d-none d-md-block"><i class="fas fa-home me-1"></i> Beranda</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    
                    <div class="text-center mb-4">
                        <h2 class="fw-bold" style="color: var(--dark-green);">Cek Status Pendaftaran</h2>
                        <p class="text-muted">Pantau tahapan seleksi dan kelengkapan berkas Anda.</p>
                    </div>

                    <!-- Form Pencarian Tunggal -->
                    <div class="search-card">
                        <form action="" method="POST">
                            <div class="mb-4">
                                <label class="form-label text-dark fw-medium">Cari Data Pendaftar <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0 ps-0" name="keyword" placeholder="Masukkan No. Pendaftaran atau Nama Lengkap" required value="<?= isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : '' ?>">
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-info-circle me-1 text-primary"></i> Anda dapat mencari menggunakan No. Daftar (contoh: SB-001) atau Nama Lengkap.</small>
                            </div>

                            <button type="submit" class="btn btn-search">
                                <i class="fas fa-search me-2"></i> Cek Status Sekarang
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal Hasil Pencarian -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <?php if ($search_performed): ?>
                    <?php if ($data_found): ?>
                        
                        <?php 
                        // Status Pendaftaran (Dibuat Statis: Menunggu Verifikasi)
                        $status_daftar = 'Menunggu Verifikasi';
                        $badge_class = 'bg-menunggu';
                        $icon_class = 'fa-hourglass-half';

                        // Status Pembayaran (Tetap Dinamis)
                        $status_bayar = $pembayaran['status_pembayaran'] ?? 'Belum Diinput';
                        $warna_bayar = 'text-danger';
                        if ($status_bayar == 'Lunas') $warna_bayar = 'text-success';
                        if ($status_bayar == 'Belum Lunas') $warna_bayar = 'text-warning';
                        ?>

                        <div class="result-header text-center">
                            <button type="button" class="btn-close-modal" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
                            <div class="mb-2 text-muted" style="font-size: 0.85rem;">Status Pendaftaran Anda Saat Ini:</div>
                            <div class="status-badge <?= $badge_class ?>">
                                <i class="fas <?= $icon_class ?> me-1"></i> <?= $status_daftar ?>
                            </div>
                        </div>
                        
                        <div class="result-body">
                            <div class="row g-3">
                                <div class="col-6 data-row">
                                    <div class="data-label">Nama Santri</div>
                                    <div class="data-value"><?= htmlspecialchars($data['nama_lengkap']) ?></div>
                                </div>
                                <div class="col-6 data-row">
                                    <div class="data-label">Nomor Pendaftaran</div>
                                    <div class="data-value text-primary-green"><?= htmlspecialchars($data['no_pendaftaran']) ?></div>
                                </div>
                                <div class="col-6 data-row">
                                    <div class="data-label">Jenjang Sekolah</div>
                                    <div class="data-value"><?= htmlspecialchars($data['pilihan_sekolah']) ?></div>
                                </div>
                                <div class="col-6 data-row">
                                    <div class="data-label">Tgl Mendaftar</div>
                                    <div class="data-value"><?= date('d M Y', strtotime($data['created_at'])) ?></div>
                                </div>
                                <div class="col-12 data-row mb-0">
                                    <div class="data-label">Status Pembayaran</div>
                                    <div class="data-value <?= $warna_bayar ?>"><?= $status_bayar ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-light p-3 text-center border-top">
                            <p class="text-muted m-0" style="font-size: 0.8rem;">Silakan cek secara berkala atau hubungi pihak panitia jika ada kendala.</p>
                        </div>

                    <?php else: ?>
                        <!-- Jika Data Tidak Ditemukan -->
                        <div class="result-body text-center py-5" style="border-top: 4px solid #ef4444;">
                            <button type="button" class="btn-close-modal" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
                            <i class="fas fa-search-minus fa-4x text-muted mb-3 opacity-50"></i>
                            <h5 class="fw-bold text-dark">Data Tidak Ditemukan</h5>
                            <p class="text-muted mb-4" style="font-size: 0.9rem;">Pastikan Nomor Pendaftaran atau Nama Lengkap yang Anda masukkan sudah benar.</p>
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script to Auto Open Modal if Search is Performed -->
    <?php if ($search_performed): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var resultModal = new bootstrap.Modal(document.getElementById('resultModal'), {
                keyboard: true
            });
            resultModal.show();
        });
    </script>
    <?php endif; ?>

</body>
</html>