<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langkah 4 - Sekolah Asal & Berkas PSB</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0da15b; 
            --dark-green: #087d46;
            --light-green: #eafbf3;
            --text-dark: #2d3748;
            --text-muted: #718096;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-dark);
            background-color: #f4f9f6;
            padding-top: 40px;
            padding-bottom: 60px;
        }

        .form-container {
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 40px;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--border-color);
            z-index: 1;
            transform: translateY(-50%);
        }

        .step-item {
            position: relative;
            z-index: 2;
            background-color: #ffffff;
            padding: 0 15px;
            text-align: center;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--border-color);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 auto 10px auto;
            transition: all 0.3s;
        }

        .step-item.active .step-circle, .step-item.completed .step-circle {
            background-color: var(--primary-green);
            color: white;
            box-shadow: 0 0 0 5px var(--light-green);
            border-color: var(--primary-green);
        }

        .step-item.completed .step-circle {
            background-color: var(--dark-green);
            box-shadow: none;
        }

        .step-title {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .step-item.active .step-title {
            color: var(--primary-green);
            font-weight: 600;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffffff;
            background-color: var(--dark-green);
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(13, 161, 91, 0.25);
        }

        .form-control[type="file"] {
            padding: 7px 15px;
        }

        .btn-next {
            background-color: var(--primary-green);
            color: white;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }

        .btn-next:hover {
            background-color: var(--dark-green);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 161, 91, 0.3);
        }

        .btn-prev {
            background-color: #ffffff;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-prev:hover {
            background-color: #f8f9fa;
            color: var(--text-dark);
        }
        
        .opt-label {
            color: var(--text-muted);
            font-weight: 400;
            font-size: 0.85rem;
        }

        .upload-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: block;
            margin-top: 4px;
        }

        /* Camera Guide Overlay */
        .camera-guide {
            position: absolute;
            border: 2px dashed rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6);
            pointer-events: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        /* Cropper Settings */
        .img-container {
            max-height: 60vh;
            width: 100%;
        }
        .img-container img {
            display: block;
            max-width: 100%;
        }

        /* FULLSCREEN LOADER OVERLAY */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .loader-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid var(--light-green);
            border-top: 6px solid var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loader-text {
            font-weight: 600;
            color: var(--dark-green);
            font-size: 1.2rem;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>

<!-- OVERLAY LOADER (D-NONE BY DEFAULT) -->
<div id="loadingOverlay" class="d-none">
    <div class="loader-spinner"></div>
    <div class="loader-text">Memproses Pendaftaran Anda...</div>
    <p class="text-muted mt-2" style="font-size: 0.9rem;">Mohon jangan tutup atau refresh halaman ini.</p>
</div>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="form-container">
                
                <div class="text-center mb-4">
                    <h4 class="fw-bold" style="color: var(--dark-green);">Formulir Pendaftaran Santri Baru</h4>
                    <p class="text-muted">Pondok Pesantren Raudlatul Mutaallimin</p>
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator d-none d-md-flex">
                    <div class="step-item completed">
                        <div class="step-circle"><i class="fas fa-check"></i></div>
                        <div class="step-title">Data Diri & Sekolah</div>
                    </div>
                    <div class="step-item completed">
                        <div class="step-circle"><i class="fas fa-check"></i></div>
                        <div class="step-title">Alamat Domisili</div>
                    </div>
                    <div class="step-item completed">
                        <div class="step-circle"><i class="fas fa-check"></i></div>
                        <div class="step-title">Data Orang Tua</div>
                    </div>
                    <div class="step-item active">
                        <div class="step-circle">4</div>
                        <div class="step-title">Sekolah Asal & Berkas</div>
                    </div>
                </div>

                <!-- Form Start -->
                <form id="formStep4" action="proses.php" method="POST" enctype="multipart/form-data">
                    
                    <!-- DATA SEKOLAH SEBELUMNYA -->
                    <h5 class="section-title"><i class="fas fa-school me-2"></i>Data Sekolah Asal</h5>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nama Sekolah Asal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_sekolah" placeholder="Contoh: SD Negeri 1 Kasui / MTs Al-Hidayah" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tahun Lulus <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="tahun_lulus" min="2010" max="2030" placeholder="Contoh: 2024" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Alamat Sekolah Asal <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alamat_sekolah" rows="2" placeholder="Nama jalan, desa, kecamatan, kota/kab asal sekolah" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NPSN Sekolah <span class="opt-label">(Opsional)</span></label>
                            <input type="text" class="form-control" name="npsn_sekolah" placeholder="Nomor Pokok Sekolah Nasional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Ijazah / SKHU <span class="opt-label">(Opsional)</span></label>
                            <input type="text" class="form-control" name="no_ijazah_skhu" placeholder="Kosongkan jika belum ada">
                        </div>
                    </div>

                    <!-- UNGGAH BERKAS -->
                    <h5 class="section-title mt-5"><i class="fas fa-file-upload me-2"></i>Unggah Berkas Persyaratan</h5>
                    
                    <div class="alert alert-warning mb-4" role="alert">
                        <i class="fas fa-info-circle me-2"></i> Pastikan file yang diunggah jelas dan bisa dibaca. Format yang didukung: <strong>JPG, JPEG, PNG, atau PDF</strong> (Maksimal 2MB per file). Jika menggunakan kamera, foto akan tersimpan sebagai JPG.
                    </div>

                    <div class="row g-3">
                        
                        <!-- 1. PAS FOTO -->
                        <div class="col-md-6">
                            <label class="form-label">Pas Foto (3x4) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="file" class="form-control file-input" name="pas_foto" id="inputPasFoto" accept=".jpg, .jpeg, .png" required>
                                <button class="btn btn-outline-success btn-camera" type="button" data-bs-toggle="modal" data-bs-target="#cameraModal" data-target="PasFoto">
                                    <i class="fas fa-camera"></i> Kamera
                                </button>
                            </div>
                            <span class="upload-hint">Foto resmi berwarna (Latar belakang merah/biru).</span>
                            <div id="previewPasFoto" class="mt-3 d-none p-2 border rounded text-center bg-light" style="max-width: 250px;">
                                <img id="imgPasFoto" src="" alt="Preview" class="img-fluid rounded shadow-sm mb-2" style="max-height: 180px;">
                                <button type="button" class="btn btn-sm btn-danger w-100 btn-hapus" data-target="PasFoto"><i class="fas fa-trash me-1"></i> Hapus Foto</button>
                            </div>
                        </div>

                        <!-- 2. KARTU KELUARGA -->
                        <div class="col-md-6">
                            <label class="form-label">Kartu Keluarga (KK) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="file" class="form-control file-input" name="kartu_keluarga" id="inputKK" accept=".jpg, .jpeg, .png, .pdf" required>
                                <button class="btn btn-outline-success btn-camera" type="button" data-bs-toggle="modal" data-bs-target="#cameraModal" data-target="KK">
                                    <i class="fas fa-camera"></i> Kamera
                                </button>
                            </div>
                            <span class="upload-hint">Scan / Foto asli KK seluruh halaman.</span>
                            <div id="previewKK" class="mt-3 d-none p-2 border rounded text-center bg-light" style="max-width: 250px;">
                                <img id="imgKK" src="" alt="Preview" class="img-fluid rounded shadow-sm mb-2" style="max-height: 180px;">
                                <button type="button" class="btn btn-sm btn-danger w-100 btn-hapus" data-target="KK"><i class="fas fa-trash me-1"></i> Hapus Foto</button>
                            </div>
                        </div>

                        <!-- 3. KTP ORTU -->
                        <div class="col-md-6">
                            <label class="form-label">KTP Orang Tua / Wali <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="file" class="form-control file-input" name="ktp_ortu" id="inputKtp" accept=".jpg, .jpeg, .png, .pdf" required>
                                <button class="btn btn-outline-success btn-camera" type="button" data-bs-toggle="modal" data-bs-target="#cameraModal" data-target="Ktp">
                                    <i class="fas fa-camera"></i> Kamera
                                </button>
                            </div>
                            <span class="upload-hint">KTP Ayah/Ibu/Wali dijadikan satu file (jika difoto berjejer).</span>
                            <div id="previewKtp" class="mt-3 d-none p-2 border rounded text-center bg-light" style="max-width: 250px;">
                                <img id="imgKtp" src="" alt="Preview" class="img-fluid rounded shadow-sm mb-2" style="max-height: 180px;">
                                <button type="button" class="btn btn-sm btn-danger w-100 btn-hapus" data-target="Ktp"><i class="fas fa-trash me-1"></i> Hapus Foto</button>
                            </div>
                        </div>

                        <!-- 4. AKTA KELAHIRAN -->
                        <div class="col-md-6">
                            <label class="form-label">Akta Kelahiran <span class="opt-label">(Opsional)</span></label>
                            <div class="input-group">
                                <input type="file" class="form-control file-input" name="akta_kelahiran" id="inputAkta" accept=".jpg, .jpeg, .png, .pdf">
                                <button class="btn btn-outline-success btn-camera" type="button" data-bs-toggle="modal" data-bs-target="#cameraModal" data-target="Akta">
                                    <i class="fas fa-camera"></i> Kamera
                                </button>
                            </div>
                            <span class="upload-hint">Scan / Foto asli Akta Kelahiran.</span>
                            <div id="previewAkta" class="mt-3 d-none p-2 border rounded text-center bg-light" style="max-width: 250px;">
                                <img id="imgAkta" src="" alt="Preview" class="img-fluid rounded shadow-sm mb-2" style="max-height: 180px;">
                                <button type="button" class="btn btn-sm btn-danger w-100 btn-hapus" data-target="Akta"><i class="fas fa-trash me-1"></i> Hapus Foto</button>
                            </div>
                        </div>

                        <!-- 5. IJAZAH / SKL -->
                        <div class="col-md-6">
                            <label class="form-label">Ijazah / SKL / SKHU <span class="opt-label">(Opsional)</span></label>
                            <div class="input-group">
                                <input type="file" class="form-control file-input" name="ijazah_skhu" id="inputIjazah" accept=".jpg, .jpeg, .png, .pdf">
                                <button class="btn btn-outline-success btn-camera" type="button" data-bs-toggle="modal" data-bs-target="#cameraModal" data-target="Ijazah">
                                    <i class="fas fa-camera"></i> Kamera
                                </button>
                            </div>
                            <span class="upload-hint">Jika ijazah belum terbit, dapat diganti SKL.</span>
                            <div id="previewIjazah" class="mt-3 d-none p-2 border rounded text-center bg-light" style="max-width: 250px;">
                                <img id="imgIjazah" src="" alt="Preview" class="img-fluid rounded shadow-sm mb-2" style="max-height: 180px;">
                                <button type="button" class="btn btn-sm btn-danger w-100 btn-hapus" data-target="Ijazah"><i class="fas fa-trash me-1"></i> Hapus Foto</button>
                            </div>
                        </div>

                        <!-- 6. PIAGAM PRESTASI -->
                        <div class="col-md-6">
                            <label class="form-label">Piagam Prestasi <span class="opt-label">(Opsional)</span></label>
                            <div class="input-group">
                                <input type="file" class="form-control file-input" name="piagam_prestasi" id="inputPiagam" accept=".jpg, .jpeg, .png, .pdf">
                                <button class="btn btn-outline-success btn-camera" type="button" data-bs-toggle="modal" data-bs-target="#cameraModal" data-target="Piagam">
                                    <i class="fas fa-camera"></i> Kamera
                                </button>
                            </div>
                            <span class="upload-hint">Sertifikat lomba akademik/non-akademik (jika ada).</span>
                            <div id="previewPiagam" class="mt-3 d-none p-2 border rounded text-center bg-light" style="max-width: 250px;">
                                <img id="imgPiagam" src="" alt="Preview" class="img-fluid rounded shadow-sm mb-2" style="max-height: 180px;">
                                <button type="button" class="btn btn-sm btn-danger w-100 btn-hapus" data-target="Piagam"><i class="fas fa-trash me-1"></i> Hapus Foto</button>
                            </div>
                        </div>

                    </div>

                    <!-- Term & Condition Checkbox -->
                    <div class="mt-5 p-3 bg-light rounded border">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="persetujuan" name="persetujuan" required>
                            <label class="form-check-label text-dark" for="persetujuan" style="font-size: 0.9rem;">
                                Saya menyatakan bahwa seluruh data diri dan berkas yang saya lampirkan adalah <strong>BENAR</strong> dan dapat dipertanggungjawabkan. Jika dikemudian hari ditemukan ketidakbenaran data, saya bersedia menerima sanksi yang ditetapkan oleh Pondok Pesantren Raudlatul Mutaallimin.
                            </label>
                        </div>
                    </div>

                    <!-- Tombol Navigasi -->
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <a href="step3.php" class="btn btn-prev">
                            <i class="fas fa-arrow-left me-2"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-next bg-success" id="btnSubmitForm">
                            <i class="fas fa-paper-plane me-2"></i> Selesai & Kirim Formulir
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL KAMERA & CROPPER -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: var(--primary-green);">
                <h5 class="modal-title" id="cameraModalLabel"><i class="fas fa-camera me-2"></i> Ambil Foto Dokumen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-dark p-2">
                
                <!-- Area Video Kamera -->
                <div id="videoContainer" style="position: relative; overflow: hidden; border-radius: 8px; display: flex; justify-content: center; align-items: center; min-height: 400px;">
                    <video id="videoStream" autoplay playsinline style="width: 100%; max-height: 60vh; object-fit: cover;"></video>
                    <!-- Garis Panduan Dinamis -->
                    <div class="camera-guide" id="cameraGuide"></div>
                </div>
                
                <!-- Area Hasil Jepretan & Cropper -->
                <canvas id="canvasCapture" style="display: none;"></canvas>
                <div class="img-container d-none" id="imageResultContainer">
                    <img id="imageResult" src="">
                </div>

                <!-- Toolbar Cropper (Rasio, Rotasi, Flip) -->
                <div id="cropperTools" class="d-none mt-3 mb-2 px-3">
                    <div class="row g-2 justify-content-center">
                        <div class="col-auto text-center">
                            <span class="text-white d-block mb-1" style="font-size: 0.75rem;">Potong (Crop)</span>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="setCropRatio(215/330)">F4 (Folio)</button>
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="setCropRatio(210/297)">A4</button>
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="setCropRatio(3/4)" id="btnRatioPasFoto">3x4 (Foto)</button>
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="setCropRatio(NaN)">Bebas</button>
                            </div>
                        </div>
                        <div class="col-auto text-center">
                            <span class="text-white d-block mb-1" style="font-size: 0.75rem;">Posisi & Arah</span>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="rotateImage(-90)" title="Putar Kiri"><i class="fas fa-undo"></i></button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="rotateImage(90)" title="Putar Kanan"><i class="fas fa-redo"></i></button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="flipImage(true)" title="Balik Horizontal (Kiri/Kanan)"><i class="fas fa-arrows-alt-h"></i></button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="flipImage(false)" title="Balik Vertikal (Atas/Bawah)"><i class="fas fa-arrows-alt-v"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-warning text-dark fw-bold d-none" id="btnUlangi"><i class="fas fa-redo me-1"></i> Ulangi</button>
                <button type="button" class="btn btn-primary fw-bold" id="btnJepret"><i class="fas fa-circle text-danger me-1"></i> Jepret Foto</button>
                <button type="button" class="btn btn-success fw-bold d-none" id="btnGunakan"><i class="fas fa-check me-1"></i> Potong & Gunakan</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Cropper.js Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<script>
    // --- SCRIPT UNTUK FITUR KAMERA & CROPPER ---
    const cameraModal = document.getElementById('cameraModal');
    const videoStream = document.getElementById('videoStream');
    const videoContainer = document.getElementById('videoContainer');
    const canvasCapture = document.getElementById('canvasCapture');
    const imageResult = document.getElementById('imageResult');
    const imageResultContainer = document.getElementById('imageResultContainer');
    const cameraGuide = document.getElementById('cameraGuide');
    const cropperTools = document.getElementById('cropperTools');
    
    const btnJepret = document.getElementById('btnJepret');
    const btnUlangi = document.getElementById('btnUlangi');
    const btnGunakan = document.getElementById('btnGunakan');
    
    let stream = null;
    let currentTarget = null; 
    let cropper = null;
    let scaleX = 1;
    let scaleY = 1;

    document.querySelectorAll('.btn-camera').forEach(btn => {
        btn.addEventListener('click', function() {
            currentTarget = this.getAttribute('data-target');
        });
    });

    cameraModal.addEventListener('shown.bs.modal', async function () {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'environment' } 
            });
            videoStream.srcObject = stream;
            
            // Penyesuaian Garis Panduan Kamera awal
            if (currentTarget === 'PasFoto') {
                cameraGuide.style.top = '15%'; cameraGuide.style.bottom = '15%'; 
                cameraGuide.style.left = '30%'; cameraGuide.style.right = '30%';
            } else {
                cameraGuide.style.top = '20%'; cameraGuide.style.bottom = '20%'; 
                cameraGuide.style.left = '10%'; cameraGuide.style.right = '10%';
            }

            resetToCameraView();

        } catch (err) {
            const modalInstance = bootstrap.Modal.getInstance(cameraModal);
            modalInstance.hide();
            Swal.fire({
                icon: 'error',
                title: 'Kamera Tidak Tersedia',
                text: 'Gagal mengakses kamera. Pastikan browser memiliki izin untuk menggunakan kamera Anda atau perangkat Anda memiliki kamera yang berfungsi.',
                confirmButtonColor: '#0da15b'
            });
        }
    });

    cameraModal.addEventListener('hidden.bs.modal', function () {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        if(cropper) {
            cropper.destroy();
            cropper = null;
        }
    });

    function resetToCameraView() {
        if(cropper) {
            cropper.destroy();
            cropper = null;
        }
        scaleX = 1;
        scaleY = 1;
        
        videoContainer.style.display = 'flex';
        imageResultContainer.classList.add('d-none');
        cropperTools.classList.add('d-none');
        
        btnJepret.classList.remove('d-none');
        btnUlangi.classList.add('d-none');
        btnGunakan.classList.add('d-none');
    }

    btnJepret.addEventListener('click', function() {
        canvasCapture.width = videoStream.videoWidth;
        canvasCapture.height = videoStream.videoHeight;
        
        const ctx = canvasCapture.getContext('2d');
        ctx.drawImage(videoStream, 0, 0, canvasCapture.width, canvasCapture.height);
        
        imageResult.src = canvasCapture.toDataURL('image/jpeg', 1.0);
        
        videoContainer.style.display = 'none';
        imageResultContainer.classList.remove('d-none');
        cropperTools.classList.remove('d-none');
        
        btnJepret.classList.add('d-none');
        btnUlangi.classList.remove('d-none');
        btnGunakan.classList.remove('d-none');

        // Setup rasio default berdasarkan jenis dokumen
        let defaultRatio = NaN; // Bebas
        if (currentTarget === 'PasFoto') {
            defaultRatio = 3/4;
            // Highlight tombol pas foto
            document.getElementById('btnRatioPasFoto').focus();
        }

        // Inisialisasi Cropper.js
        cropper = new Cropper(imageResult, {
            aspectRatio: defaultRatio,
            viewMode: 1,
            autoCropArea: 0.9, 
            background: false
        });
    });

    // --- FUNGSI TOOLBAR CROPPER ---
    function setCropRatio(ratio) {
        if(cropper) cropper.setAspectRatio(ratio);
    }

    function rotateImage(degree) {
        if(cropper) cropper.rotate(degree);
    }

    function flipImage(horizontal) {
        if(!cropper) return;
        if(horizontal) {
            scaleX = scaleX === 1 ? -1 : 1;
            cropper.scaleX(scaleX);
        } else {
            scaleY = scaleY === 1 ? -1 : 1;
            cropper.scaleY(scaleY);
        }
    }

    btnUlangi.addEventListener('click', function() {
        resetToCameraView();
    });

    // Proses Akhir: Menyimpan hasil Cropping
    btnGunakan.addEventListener('click', function() {
        if(!cropper) return;

        // Dapatkan gambar hasil pemotongan
        let croppedCanvas = cropper.getCroppedCanvas({
            maxWidth: 2000,
            maxHeight: 2000
        });

        croppedCanvas.toBlob(function(blob) {
            const fileName = `scan_${currentTarget}_${new Date().getTime()}.jpg`;
            const file = new File([blob], fileName, { type: "image/jpeg", lastModified: new Date().getTime() });
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            
            const inputTarget = document.getElementById(`input${currentTarget}`);
            inputTarget.files = dataTransfer.files;
            
            // Buat URL Object untuk Preview agar ringan di browser
            tampilkanPreviewFoto(URL.createObjectURL(blob), currentTarget);
            
            const modalInstance = bootstrap.Modal.getInstance(cameraModal);
            modalInstance.hide();
        }, 'image/jpeg', 0.9);
    });

    // --- PREVIEW SAAT UNGGAH DARI FILE MANAGER ---
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function() {
            const target = this.getAttribute('id').replace('input', '');
            if (this.files && this.files[0]) {
                const fileType = this.files[0].type;
                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        tampilkanPreviewFoto(e.target.result, target);
                    }
                    reader.readAsDataURL(this.files[0]);
                } else {
                    document.getElementById(`preview${target}`).classList.add('d-none');
                }
            }
        });
    });

    function tampilkanPreviewFoto(src, target) {
        document.getElementById(`img${target}`).src = src;
        document.getElementById(`preview${target}`).classList.remove('d-none');
        document.getElementById(`input${target}`).classList.remove('is-invalid');
    }

    document.querySelectorAll('.btn-hapus').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            document.getElementById(`input${target}`).value = ''; 
            document.getElementById(`preview${target}`).classList.add('d-none');
            document.getElementById(`img${target}`).src = '';
        });
    });


    // --- FUNGSI AUTO-SAVE TEKS & FORM SUBMISSION (SWAL + LOADER) ---
    const formId = "formStep4";
    const formElement = document.getElementById(formId);
    
    document.addEventListener("DOMContentLoaded", function() {
        const formInputs = document.querySelectorAll(`#${formId} input:not([type="file"]):not([type="checkbox"]), #${formId} textarea`);
        const savedData = JSON.parse(localStorage.getItem(formId) || "{}");
        
        formInputs.forEach(el => {
            if (savedData[el.name]) {
                el.value = savedData[el.name];
            }
        });

        formElement.addEventListener("input", function(e) {
            if(e.target.type !== 'file' && e.target.type !== 'checkbox') {
                const dataObj = JSON.parse(localStorage.getItem(formId) || "{}");
                dataObj[e.target.name] = e.target.value;
                localStorage.setItem(formId, JSON.stringify(dataObj));
            }
        });

        // Intercept Form Submit menggunakan SweetAlert2
        formElement.addEventListener("submit", function(e) {
            e.preventDefault(); // Cegah submit bawaan

            Swal.fire({
                title: 'Kirim Formulir Pendaftaran?',
                text: "Pastikan seluruh data dan berkas yang Anda masukkan sudah benar.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0da15b',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Kirim Sekarang!',
                cancelButtonText: 'Cek Kembali',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    
                    // 1. Munculkan Animasi Loader
                    document.getElementById('loadingOverlay').classList.remove('d-none');

                    // 2. Gabungkan Data dari Langkah 1 - 3
                    const step1Data = JSON.parse(localStorage.getItem('formStep1') || "{}");
                    const step2Data = JSON.parse(localStorage.getItem('formStep2') || "{}");
                    const step3Data = JSON.parse(localStorage.getItem('formStep3') || "{}");
                    
                    const allData = {...step1Data, ...step2Data, ...step3Data};
                    
                    for (const key in allData) {
                        if (allData.hasOwnProperty(key)) {
                            // Hindari duplikasi input hidden jika tombol diklik dua kali
                            if(!document.querySelector(`input[name="${key}"][type="hidden"]`)) {
                                const hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = key;
                                hiddenInput.value = allData[key];
                                formElement.appendChild(hiddenInput);
                            }
                        }
                    }

                    // 3. Submit form secara programatik (bypass event listener submit ini)
                    HTMLFormElement.prototype.submit.call(formElement);
                }
            });
        });
    });
</script>

</body>
</html>