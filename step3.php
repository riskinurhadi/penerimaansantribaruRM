<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langkah 3 - Data Orang Tua PSB</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
            display: none; /* Disembunyikan dulu sebelum cek token keamanan */
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

        /* Styling saat input error / kosong */
        .is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8;
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
    </style>
</head>
<body>

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
                    <div class="step-item active">
                        <div class="step-circle">3</div>
                        <div class="step-title">Data Orang Tua</div>
                    </div>
                    <div class="step-item">
                        <div class="step-circle">4</div>
                        <div class="step-title">Sekolah Asal & Berkas</div>
                    </div>
                </div>

                <!-- Form Start (Ditambah novalidate) -->
                <form id="formStep3" action="step4.php" method="POST" novalidate>
                    
                    <!-- DATA AYAH -->
                    <h5 class="section-title"><i class="fas fa-user-tie me-2"></i>Data Ayah Kandung</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nama Lengkap Ayah <span class="text-danger">*</span></label>
                            <input type="text" class="form-control req-field" name="ayah_nama" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status Ayah <span class="text-danger">*</span></label>
                            <select class="form-select req-field" name="ayah_status" id="ayah_status" required onchange="toggleParentFields('ayah')">
                                <option value="Masih Hidup">Masih Hidup</option>
                                <option value="Sudah Meninggal">Sudah Meninggal</option>
                                <option value="Tidak Diketahui">Tidak Diketahui</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NIK Ayah <span class="req-indicator-ayah text-danger">*</span></label>
                            <input type="text" class="form-control" name="ayah_nik" id="ayah_nik" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tempat Lahir <span class="req-indicator-ayah text-danger">*</span></label>
                            <input type="text" class="form-control" name="ayah_tempat_lahir" id="ayah_tempat_lahir" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Lahir <span class="req-indicator-ayah text-danger">*</span></label>
                            <input type="date" class="form-control" name="ayah_tanggal_lahir" id="ayah_tanggal_lahir" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pendidikan Terakhir <span class="req-indicator-ayah text-danger">*</span></label>
                            <select class="form-select" name="ayah_pendidikan" id="ayah_pendidikan" required>
                                <option value="">Pilih...</option>
                                <option value="Tidak Sekolah">Tidak Sekolah</option>
                                <option value="SD/Sederajat">SD/Sederajat</option>
                                <option value="SMP/Sederajat">SMP/Sederajat</option>
                                <option value="SMA/Sederajat">SMA/Sederajat</option>
                                <option value="D1/D2/D3">D1/D2/D3</option>
                                <option value="S1/D4">S1/D4</option>
                                <option value="S2/S3">S2/S3</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pekerjaan <span class="req-indicator-ayah text-danger">*</span></label>
                            <select class="form-select" name="ayah_pekerjaan" id="ayah_pekerjaan" required>
                                <option value="">Pilih...</option>
                                <option value="Tidak Bekerja">Tidak Bekerja</option>
                                <option value="Pensiunan">Pensiunan</option>
                                <option value="PNS">PNS</option>
                                <option value="TNI/Polisi">TNI/Polisi</option>
                                <option value="Guru/Dosen">Guru/Dosen</option>
                                <option value="Pegawai Swasta">Pegawai Swasta</option>
                                <option value="Wiraswasta">Wiraswasta</option>
                                <option value="Pengacara/Jaksa/Hakim/Notaris">Pengacara/Jaksa/Hakim/Notaris</option>
                                <option value="Seniman/Pelukis/Artis/Sejenis">Seniman/Pelukis/Artis/Sejenis</option>
                                <option value="Dokter/Bidan/Perawat">Dokter/Bidan/Perawat</option>
                                <option value="Pilot/Pramugara">Pilot/Pramugara</option>
                                <option value="Pedagang">Pedagang</option>
                                <option value="Petani/Peternak">Petani/Peternak</option>
                                <option value="Nelayan">Nelayan</option>
                                <option value="Buruh (Tani/Pabrik/Bangunan)">Buruh (Tani/Pabrik/Bangunan)</option>
                                <option value="Sopir/Masinis/Kondektur">Sopir/Masinis/Kondektur</option>
                                <option value="Politikus">Politikus</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Penghasilan / Bulan <span class="req-indicator-ayah text-danger">*</span></label>
                            <select class="form-select" name="ayah_penghasilan" id="ayah_penghasilan" required>
                                <option value="">Pilih...</option>
                                <option value="Kurang dari Rp 1 Juta">< Rp 1.000.000</option>
                                <option value="Rp 1 Juta - Rp 3 Juta">Rp 1.000.000 - Rp 3.000.000</option>
                                <option value="Rp 3 Juta - Rp 5 Juta">Rp 3.000.000 - Rp 5.000.000</option>
                                <option value="Lebih dari Rp 5 Juta">> Rp 5.000.000</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No Handphone Ayah <span class="req-indicator-ayah text-danger">*</span></label>
                            <input type="text" class="form-control" name="ayah_no_hp" id="ayah_no_hp" placeholder="08xxxxxxxxxx" required>
                        </div>
                    </div>

                    <!-- DATA IBU -->
                    <h5 class="section-title"><i class="fas fa-user me-2"></i>Data Ibu Kandung</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nama Lengkap Ibu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control req-field" name="ibu_nama" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status Ibu <span class="text-danger">*</span></label>
                            <select class="form-select req-field" name="ibu_status" id="ibu_status" required onchange="toggleParentFields('ibu')">
                                <option value="Masih Hidup">Masih Hidup</option>
                                <option value="Sudah Meninggal">Sudah Meninggal</option>
                                <option value="Tidak Diketahui">Tidak Diketahui</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NIK Ibu <span class="req-indicator-ibu text-danger">*</span></label>
                            <input type="text" class="form-control" name="ibu_nik" id="ibu_nik" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tempat Lahir <span class="req-indicator-ibu text-danger">*</span></label>
                            <input type="text" class="form-control" name="ibu_tempat_lahir" id="ibu_tempat_lahir" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Lahir <span class="req-indicator-ibu text-danger">*</span></label>
                            <input type="date" class="form-control" name="ibu_tanggal_lahir" id="ibu_tanggal_lahir" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pendidikan Terakhir <span class="req-indicator-ibu text-danger">*</span></label>
                            <select class="form-select" name="ibu_pendidikan" id="ibu_pendidikan" required>
                                <option value="">Pilih...</option>
                                <option value="Tidak Sekolah">Tidak Sekolah</option>
                                <option value="SD/Sederajat">SD/Sederajat</option>
                                <option value="SMP/Sederajat">SMP/Sederajat</option>
                                <option value="SMA/Sederajat">SMA/Sederajat</option>
                                <option value="D1/D2/D3">D1/D2/D3</option>
                                <option value="S1/D4">S1/D4</option>
                                <option value="S2/S3">S2/S3</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pekerjaan <span class="req-indicator-ibu text-danger">*</span></label>
                            <select class="form-select" name="ibu_pekerjaan" id="ibu_pekerjaan" required>
                                <option value="">Pilih...</option>
                                <option value="Ibu Rumah Tangga">Ibu Rumah Tangga</option>
                                <option value="Tidak Bekerja">Tidak Bekerja</option>
                                <option value="Pensiunan">Pensiunan</option>
                                <option value="PNS">PNS</option>
                                <option value="TNI/Polisi">TNI/Polisi</option>
                                <option value="Guru/Dosen">Guru/Dosen</option>
                                <option value="Pegawai Swasta">Pegawai Swasta</option>
                                <option value="Wiraswasta">Wiraswasta</option>
                                <option value="Pengacara/Jaksa/Hakim/Notaris">Pengacara/Jaksa/Hakim/Notaris</option>
                                <option value="Seniman/Pelukis/Artis/Sejenis">Seniman/Pelukis/Artis/Sejenis</option>
                                <option value="Dokter/Bidan/Perawat">Dokter/Bidan/Perawat</option>
                                <option value="Pilot/Pramugara">Pilot/Pramugara</option>
                                <option value="Pedagang">Pedagang</option>
                                <option value="Petani/Peternak">Petani/Peternak</option>
                                <option value="Nelayan">Nelayan</option>
                                <option value="Buruh (Tani/Pabrik/Bangunan)">Buruh (Tani/Pabrik/Bangunan)</option>
                                <option value="Sopir/Masinis/Kondektur">Sopir/Masinis/Kondektur</option>
                                <option value="Politikus">Politikus</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Penghasilan / Bulan <span class="req-indicator-ibu text-danger">*</span></label>
                            <select class="form-select" name="ibu_penghasilan" id="ibu_penghasilan" required>
                                <option value="">Pilih...</option>
                                <option value="Tidak Berpenghasilan">Tidak Berpenghasilan</option>
                                <option value="Kurang dari Rp 1 Juta">< Rp 1.000.000</option>
                                <option value="Rp 1 Juta - Rp 3 Juta">Rp 1.000.000 - Rp 3.000.000</option>
                                <option value="Rp 3 Juta - Rp 5 Juta">Rp 3.000.000 - Rp 5.000.000</option>
                                <option value="Lebih dari Rp 5 Juta">> Rp 5.000.000</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No Handphone Ibu <span class="req-indicator-ibu text-danger">*</span></label>
                            <input type="text" class="form-control" name="ibu_no_hp" id="ibu_no_hp" placeholder="08xxxxxxxxxx" required>
                        </div>
                    </div>

                    <!-- DATA WALI (Toggle) -->
                    <div class="mt-4 p-3 bg-light rounded border">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="toggleWali" onchange="toggleWaliForm()">
                            <label class="form-check-label fw-bold" for="toggleWali">Calon Santri tinggal bersama Wali (Bukan Orang Tua Kandung)?</label>
                        </div>
                    </div>

                    <div id="formWali" style="display: none;">
                        <h5 class="section-title bg-secondary"><i class="fas fa-users me-2"></i>Data Wali</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Nama Wali <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="wali_nama" id="wali_nama">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hubungan dengan Santri <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="wali_hubungan" id="wali_hubungan" placeholder="Contoh: Paman, Kakek, Kakak">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NIK Wali <span class="opt-label">(Opsional)</span></label>
                                <input type="text" class="form-control" name="wali_nik">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pendidikan Terakhir <span class="text-danger">*</span></label>
                                <select class="form-select" name="wali_pendidikan" id="wali_pendidikan">
                                    <option value="">Pilih...</option>
                                    <option value="Tidak Sekolah">Tidak Sekolah</option>
                                    <option value="SD/Sederajat">SD/Sederajat</option>
                                    <option value="SMP/Sederajat">SMP/Sederajat</option>
                                    <option value="SMA/Sederajat">SMA/Sederajat</option>
                                    <option value="D1/D2/D3">D1/D2/D3</option>
                                    <option value="S1/D4">S1/D4</option>
                                    <option value="S2/S3">S2/S3</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pekerjaan <span class="text-danger">*</span></label>
                                <select class="form-select" name="wali_pekerjaan" id="wali_pekerjaan">
                                    <option value="">Pilih...</option>
                                    <option value="Tidak Bekerja">Tidak Bekerja</option>
                                    <option value="Pensiunan">Pensiunan</option>
                                    <option value="PNS">PNS</option>
                                    <option value="TNI/Polisi">TNI/Polisi</option>
                                    <option value="Guru/Dosen">Guru/Dosen</option>
                                    <option value="Pegawai Swasta">Pegawai Swasta</option>
                                    <option value="Wiraswasta">Wiraswasta</option>
                                    <option value="Pengacara/Jaksa/Hakim/Notaris">Pengacara/Jaksa/Hakim/Notaris</option>
                                    <option value="Seniman/Pelukis/Artis/Sejenis">Seniman/Pelukis/Artis/Sejenis</option>
                                    <option value="Dokter/Bidan/Perawat">Dokter/Bidan/Perawat</option>
                                    <option value="Pilot/Pramugara">Pilot/Pramugara</option>
                                    <option value="Pedagang">Pedagang</option>
                                    <option value="Petani/Peternak">Petani/Peternak</option>
                                    <option value="Nelayan">Nelayan</option>
                                    <option value="Buruh (Tani/Pabrik/Bangunan)">Buruh (Tani/Pabrik/Bangunan)</option>
                                    <option value="Sopir/Masinis/Kondektur">Sopir/Masinis/Kondektur</option>
                                    <option value="Politikus">Politikus</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Penghasilan / Bulan <span class="text-danger">*</span></label>
                                <select class="form-select" name="wali_penghasilan" id="wali_penghasilan">
                                    <option value="">Pilih...</option>
                                    <option value="Kurang dari Rp 1 Juta">< Rp 1.000.000</option>
                                    <option value="Rp 1 Juta - Rp 3 Juta">Rp 1.000.000 - Rp 3.000.000</option>
                                    <option value="Rp 3 Juta - Rp 5 Juta">Rp 3.000.000 - Rp 5.000.000</option>
                                    <option value="Lebih dari Rp 5 Juta">> Rp 5.000.000</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No Handphone Wali <span class="opt-label">(Opsional)</span></label>
                                <input type="text" class="form-control" name="wali_no_hp" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Navigasi -->
                    <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                        <a href="step2.php" class="btn btn-prev">
                            <i class="fas fa-arrow-left me-2"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-next">
                            Langkah Selanjutnya <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // --- SECURITY CHECK (PENJAGA PINTU) ---
    // Cek apakah step 2 sudah diselesaikan
    if (!localStorage.getItem('step2_completed')) {
        Swal.fire({
            icon: 'warning',
            title: 'Akses Ditolak!',
            text: 'Anda harus menyelesaikan Langkah 2 terlebih dahulu.',
            confirmButtonColor: '#0da15b',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            window.location.href = 'step2.php';
        });
    } else {
        // Jika aman, tampilkan halaman
        document.body.style.display = 'block';
    }

    // --- Fungsi Cerdas Toggle Status Ayah / Ibu ---
    function toggleParentFields(type) {
        const status = document.getElementById(`${type}_status`).value;
        const fields = ['nik', 'tempat_lahir', 'tanggal_lahir', 'pendidikan', 'pekerjaan', 'penghasilan', 'no_hp'];
        
        // Jika status "Tidak Diketahui", field jadi Opsional
        const isRequired = (status !== 'Tidak Diketahui');

        fields.forEach(f => {
            const inputName = `${type}_${f}`;
            const inputEl = document.getElementById(inputName);
            
            if(inputEl) {
                const label = inputEl.closest('div').querySelector('label');
                const indicator = label.querySelector(`.req-indicator-${type}`);

                if (isRequired) {
                    inputEl.setAttribute('required', 'required');
                    inputEl.classList.add('req-field');
                    if(indicator) {
                        indicator.className = `req-indicator-${type} text-danger`;
                        indicator.innerHTML = '*';
                    }
                } else {
                    inputEl.removeAttribute('required');
                    inputEl.classList.remove('req-field');
                    inputEl.classList.remove('is-invalid'); // Bersihkan error merah
                    if(indicator) {
                        indicator.className = `req-indicator-${type} opt-label`;
                        indicator.innerHTML = '(Opsional)';
                    }
                }
            }
        });
    }

    // --- Fungsi Toggle Form Wali ---
    function toggleWaliForm() {
        const isChecked = document.getElementById('toggleWali').checked;
        const formWali = document.getElementById('formWali');
        formWali.style.display = isChecked ? 'block' : 'none';
        
        // Setup required attributes dynamically based on toggle
        const waliInputs = ['wali_nama', 'wali_hubungan', 'wali_pendidikan', 'wali_pekerjaan', 'wali_penghasilan'];
        
        waliInputs.forEach(id => {
            const input = document.getElementById(id);
            if(input) {
                if (isChecked) {
                    input.setAttribute('required', 'required');
                    input.classList.add('req-field');
                } else {
                    input.removeAttribute('required');
                    input.classList.remove('req-field');
                    input.classList.remove('is-invalid');
                }
            }
        });
        
        // Simpan status toggle ke localstorage juga
        const savedData = JSON.parse(localStorage.getItem(formId) || "{}");
        savedData['is_wali'] = isChecked;
        localStorage.setItem(formId, JSON.stringify(savedData));
    }

    // --- FUNGSI AUTO-SAVE & VALIDASI ---
    const formId = "formStep3";
    
    document.addEventListener("DOMContentLoaded", function() {
        const formElements = document.querySelectorAll(`#${formId} input:not([type="checkbox"]), #${formId} select`);
        const savedData = JSON.parse(localStorage.getItem(formId) || "{}");
        
        // Restore Data & Event Hapus Merah
        formElements.forEach(el => {
            if (savedData[el.name]) {
                el.value = savedData[el.name];
            }
            
            // Hapus class invalid saat user mulai mengetik
            el.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            el.addEventListener('change', function() {
                this.classList.remove('is-invalid');
            });
        });

        // Terapkan toggle (Penting agar yang tak diketahui/wali tidak diminta required)
        toggleParentFields('ayah');
        toggleParentFields('ibu');

        // Restore Toggle Wali
        if(savedData['is_wali']) {
            document.getElementById('toggleWali').checked = true;
            toggleWaliForm();
        }

        // Simpan setiap perubahan
        document.getElementById(formId).addEventListener("input", function() {
            const dataObj = JSON.parse(localStorage.getItem(formId) || "{}");
            formElements.forEach(el => {
                dataObj[el.name] = el.value;
            });
            localStorage.setItem(formId, JSON.stringify(dataObj));
        });

        // VALIDASI SAAT SUBMIT
        const form = document.getElementById(formId);
        form.addEventListener("submit", function(event) {
            let isValid = true;
            let firstInvalidElement = null;
            
            // Cari semua elemen yang saat ini aktif memiliki class 'req-field'
            const requiredFields = this.querySelectorAll('.req-field');
            
            requiredFields.forEach(field => {
                if (!field.value || field.value.trim() === '') {
                    isValid = false;
                    field.classList.add('is-invalid');
                    if (!firstInvalidElement) firstInvalidElement = field;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
                
                Swal.fire({
                    icon: 'error',
                    title: 'Formulir Belum Lengkap',
                    text: 'Mohon isi semua kolom data orang tua/wali yang bertanda bintang merah (*).',
                    confirmButtonColor: '#0da15b'
                }).then(() => {
                    if (firstInvalidElement) firstInvalidElement.focus();
                });
            } else {
                // Form Valid! Beri kunci untuk masuk ke Step 4
                localStorage.setItem('step3_completed', 'true');
            }
        });
    });
</script>

</body>
</html>