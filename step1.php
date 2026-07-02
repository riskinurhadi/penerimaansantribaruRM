<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langkah 1 - Data Diri PSB</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 CSS (Baru ditambahkan) -->
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

        .step-item.active .step-circle {
            background-color: var(--primary-green);
            color: white;
            box-shadow: 0 0 0 5px var(--light-green);
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
            color: var(--dark-green);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-green);
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-dark);
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
            transform: translateY(-2px);
            color: white;
        }
        
        /* Checkbox Takhosush */
        .takhosush-box {
            background-color: var(--light-green);
            border: 1px solid var(--primary-green);
            border-radius: 8px;
            padding: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="form-container">
                
                <!-- Header / Logo -->
                <div class="text-center mb-4">
                    <h4 class="fw-bold" style="color: var(--dark-green);">Formulir Pendaftaran Santri Baru</h4>
                    <p class="text-muted">Pondok Pesantren Raudlatul Mutaallimin</p>
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator d-none d-md-flex">
                    <div class="step-item active">
                        <div class="step-circle">1</div>
                        <div class="step-title">Data Diri & Sekolah</div>
                    </div>
                    <div class="step-item">
                        <div class="step-circle">2</div>
                        <div class="step-title">Alamat Domisili</div>
                    </div>
                    <div class="step-item">
                        <div class="step-circle">3</div>
                        <div class="step-title">Data Orang Tua</div>
                    </div>
                    <div class="step-item">
                        <div class="step-circle">4</div>
                        <div class="step-title">Sekolah Asal & Berkas</div>
                    </div>
                </div>

                <!-- Form Start (novalidate ditambahkan untuk menonaktifkan tooltip bawaan browser) -->
                <form id="formStep1" action="step2.php" method="POST" novalidate>
                    
                    <!-- BAGIAN 1: Pilihan Pendaftaran -->
                    <h5 class="section-title"><i class="fas fa-school me-2"></i>Informasi Pendaftaran</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Status Masuk <span class="text-danger">*</span></label>
                            <select class="form-select req-field" name="status_masuk" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="Santri Baru">Santri Baru</option>
                                <option value="Pindahan">Pindahan</option>
                                <option value="Drop Out">Drop Out (Melanjutkan)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sekolah Formal Yang Dipilih <span class="text-danger">*</span></label>
                            <select class="form-select req-field" name="pilihan_sekolah" required>
                                <option value="">-- Pilih Jenjang Sekolah --</option>
                                <option value="RA">RA</option>
                                <option value="MI">MI</option>
                                <option value="MTs">MTs</option>
                                <option value="MA">MA</option>
                                <option value="SMK">SMK</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="takhosush-box form-check d-flex align-items-center">
                                <input class="form-check-input mt-0 me-3" style="width: 20px; height: 20px;" type="checkbox" name="program_takhosush" value="Ya" id="takhosush">
                                <label class="form-check-label mb-0 fw-bold" for="takhosush">
                                    Ikut Program Takhosush (Kelas Khusus Penghafal Al-Qur'an)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- BAGIAN 2: Data Diri Siswa -->
                    <h5 class="section-title"><i class="fas fa-user me-2"></i>Data Diri Calon Santri</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nama Lengkap (Sesuai Ijazah/KK) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control req-field" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NISN <span class="text-danger">*</span></label>
                            <input type="number" class="form-control req-field" name="nisn" placeholder="Nomor Induk Siswa Nasional" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NIK Siswa (Sesuai KK) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control req-field" name="nik" placeholder="Nomor Induk Kependudukan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tempat Lahir <span class="text-danger">*</span></label>
                            <input type="text" class="form-control req-field" name="tempat_lahir" placeholder="Kota/Kabupaten kelahiran" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                            <input type="date" class="form-control req-field" name="tanggal_lahir" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select class="form-select req-field" name="jenis_kelamin" required>
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Agama <span class="text-danger">*</span></label>
                            <select class="form-select req-field" name="agama" required>
                                <option value="Islam" selected>Islam</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Hobi</label>
                            <input type="text" class="form-control" name="hobi" placeholder="Contoh: Membaca, Olahraga, Menggambar">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Anak Ke- <span class="text-danger">*</span></label>
                            <input type="number" class="form-control req-field" name="anak_ke" min="1" placeholder="Contoh: 1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah Saudara Kandung <span class="text-danger">*</span></label>
                            <input type="number" class="form-control req-field" name="jumlah_saudara" min="0" placeholder="Contoh: 2" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Nomor KIP (Kartu Indonesia Pintar)</label>
                            <input type="text" class="form-control" name="no_kip" placeholder="Isi jika memiliki, kosongkan jika tidak ada">
                            <small class="text-muted">*Opsional</small>
                        </div>
                    </div>

                    <!-- Tombol Navigasi -->
                    <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                        <button type="submit" class="btn btn-next" id="btnSubmit">
                            Langkah Selanjutnya <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 JS (Baru ditambahkan) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const formId = "formStep1";
        const form = document.getElementById(formId);
        const formElements = document.querySelectorAll(`#${formId} input, #${formId} select`);
        
        // 1. Load Data dari LocalStorage saat halaman dibuka (AUTO-SAVE)
        const savedData = JSON.parse(localStorage.getItem(formId) || "{}");
        formElements.forEach(el => {
            if (savedData[el.name]) {
                if (el.type === 'checkbox') {
                    el.checked = (savedData[el.name] === 'Ya');
                } else {
                    el.value = savedData[el.name];
                }
            }
            
            // Hapus class invalid jika user mulai mengetik ulang
            el.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });

        // 2. Simpan Data ke LocalStorage setiap kali pengguna mengetik (AUTO-SAVE)
        form.addEventListener("input", function() {
            const formData = new FormData(this);
            const dataObj = {};
            
            for (let [key, value] of formData.entries()) {
                dataObj[key] = value;
            }
            
            formElements.forEach(el => {
                if (el.type === 'checkbox') {
                    dataObj[el.name] = el.checked ? el.value : '';
                }
            });

            localStorage.setItem(formId, JSON.stringify(dataObj));
        });

        // 3. VALIDASI FORM & SECURITY TOKEN SEBELUM PINDAH STEP
        form.addEventListener("submit", function(event) {
            // Cek apakah form valid (apakah semua yang 'required' sudah diisi)
            if (!this.checkValidity()) {
                event.preventDefault(); // Hentikan perpindahan halaman
                event.stopPropagation();
                
                // Cari semua input yang kosong dan wajib diisi
                let firstInvalidElement = null;
                const requiredFields = this.querySelectorAll('.req-field');
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid'); // Tambahkan kotak merah
                        if (!firstInvalidElement) firstInvalidElement = field;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                // Tampilkan Popup Alert
                Swal.fire({
                    icon: 'error',
                    title: 'Formulir Belum Lengkap',
                    text: 'Mohon isi semua kolom yang bertanda bintang merah (*).',
                    confirmButtonColor: '#0da15b'
                }).then(() => {
                    // Fokuskan layar ke input pertama yang kosong
                    if (firstInvalidElement) {
                        firstInvalidElement.focus();
                    }
                });
                
            } else {
                // Jika validasi sukses: 
                // Buat token keamanan untuk membuka pintu step 2
                localStorage.setItem('step1_completed', 'true');
                
                // Form akan otomatis ter-submit dan berpindah ke step2.php
            }
        });
    });
</script>

</body>
</html>