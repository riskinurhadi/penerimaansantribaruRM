<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langkah 2 - Alamat Domisili PSB</title>
    
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
                    <div class="step-item active">
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

                <!-- Form Start -->
                <form id="formStep2" action="step3.php" method="POST">
                    
                    <h5 class="section-title"><i class="fas fa-map-marker-alt me-2"></i>Alamat Lengkap (Sesuai KK)</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Alamat Jalan / Dusun <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alamat_lengkap" rows="2" placeholder="Contoh: Jl. Sudirman No. 12, Dusun Mawar" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RT <span class="opt-label">(Opsional)</span></label>
                            <input type="text" class="form-control" name="rt" placeholder="Contoh: 001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RW <span class="opt-label">(Opsional)</span></label>
                            <input type="text" class="form-control" name="rw" placeholder="Contoh: 002">
                        </div>
                        
                        <!-- Area Dropdown Berjenjang (Integrasi API se-Indonesia) -->
                        <div class="col-md-6">
                            <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                            <select class="form-select" name="provinsi" id="provinsi" required onchange="loadKota()">
                                <option value="">Loading data...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kota/Kabupaten <span class="text-danger">*</span></label>
                            <select class="form-select" name="kota_kabupaten" id="kota" required onchange="loadKecamatan()" disabled>
                                <option value="">-- Pilih Kota/Kabupaten --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kecamatan <span class="text-danger">*</span></label>
                            <select class="form-select" name="kecamatan" id="kecamatan" required onchange="loadDesa()" disabled>
                                <option value="">-- Pilih Kecamatan --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Desa/Kelurahan <span class="text-danger">*</span></label>
                            <select class="form-select" name="desa_kelurahan" id="desa" required disabled onchange="triggerSave()">
                                <option value="">-- Pilih Desa/Kelurahan --</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Kode Pos <span class="opt-label">(Opsional)</span></label>
                            <input type="number" class="form-control" name="kode_pos" placeholder="Masukkan 5 digit kode pos">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No WhatsApp / HP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="no_whatsapp" placeholder="08xxxxxxxxxx" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email Aktif</label>
                            <input type="email" class="form-control" name="email" placeholder="contoh@email.com">
                            <small class="text-muted">*Opsional</small>
                        </div>
                    </div>

                    <!-- Tombol Navigasi -->
                    <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                        <a href="step1.php" class="btn btn-prev">
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

<script>
    const apiBase = "https://www.emsifa.com/api-wilayah-indonesia/api";
    const formId = "formStep2";

    // Fungsi untuk mendapatkan ID dari dropdown yang dipilih (dibutuhkan API untuk level selanjutnya)
    function getSelectedId(selectElement) {
        if(selectElement.selectedIndex === -1) return null;
        return selectElement.options[selectElement.selectedIndex].getAttribute('data-id');
    }

    async function loadProvinsi() {
        try {
            const response = await fetch(`${apiBase}/provinces.json`);
            const provinces = await response.json();
            
            let options = '<option value="">-- Pilih Provinsi --</option>';
            provinces.forEach(p => {
                // value diisi nama wilayah agar di database tersimpan namanya, bukan kodenya
                options += `<option value="${p.name}" data-id="${p.id}">${p.name}</option>`;
            });
            document.getElementById('provinsi').innerHTML = options;
        } catch (error) {
            document.getElementById('provinsi').innerHTML = '<option value="">Gagal memuat data</option>';
        }
    }

    async function loadKota() {
        const provId = getSelectedId(document.getElementById('provinsi'));
        const kotaSelect = document.getElementById('kota');
        const kecSelect = document.getElementById('kecamatan');
        const desaSelect = document.getElementById('desa');

        kotaSelect.innerHTML = '<option value="">Loading...</option>';
        kecSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        desaSelect.innerHTML = '<option value="">-- Pilih Desa/Kelurahan --</option>';
        
        kotaSelect.disabled = true; kecSelect.disabled = true; desaSelect.disabled = true;

        if(!provId) {
            kotaSelect.innerHTML = '<option value="">-- Pilih Kota/Kabupaten --</option>';
            triggerSave();
            return;
        }

        try {
            const response = await fetch(`${apiBase}/regencies/${provId}.json`);
            const kotas = await response.json();
            let options = '<option value="">-- Pilih Kota/Kabupaten --</option>';
            kotas.forEach(k => {
                options += `<option value="${k.name}" data-id="${k.id}">${k.name}</option>`;
            });
            kotaSelect.innerHTML = options;
            kotaSelect.disabled = false;
        } catch (error) {
            kotaSelect.innerHTML = '<option value="">Gagal memuat data</option>';
        }
        triggerSave();
    }

    async function loadKecamatan() {
        const kotaId = getSelectedId(document.getElementById('kota'));
        const kecSelect = document.getElementById('kecamatan');
        const desaSelect = document.getElementById('desa');

        kecSelect.innerHTML = '<option value="">Loading...</option>';
        desaSelect.innerHTML = '<option value="">-- Pilih Desa/Kelurahan --</option>';
        
        kecSelect.disabled = true; desaSelect.disabled = true;

        if(!kotaId) {
            kecSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
            triggerSave();
            return;
        }

        try {
            const response = await fetch(`${apiBase}/districts/${kotaId}.json`);
            const kecamatans = await response.json();
            let options = '<option value="">-- Pilih Kecamatan --</option>';
            kecamatans.forEach(k => {
                options += `<option value="${k.name}" data-id="${k.id}">${k.name}</option>`;
            });
            kecSelect.innerHTML = options;
            kecSelect.disabled = false;
        } catch (error) {
            kecSelect.innerHTML = '<option value="">Gagal memuat data</option>';
        }
        triggerSave();
    }

    async function loadDesa() {
        const kecId = getSelectedId(document.getElementById('kecamatan'));
        const desaSelect = document.getElementById('desa');

        desaSelect.innerHTML = '<option value="">Loading...</option>';
        desaSelect.disabled = true;

        if(!kecId) {
            desaSelect.innerHTML = '<option value="">-- Pilih Desa/Kelurahan --</option>';
            triggerSave();
            return;
        }

        try {
            const response = await fetch(`${apiBase}/villages/${kecId}.json`);
            const desas = await response.json();
            let options = '<option value="">-- Pilih Desa/Kelurahan --</option>';
            desas.forEach(d => {
                options += `<option value="${d.name}" data-id="${d.id}">${d.name}</option>`;
            });
            desaSelect.innerHTML = options;
            desaSelect.disabled = false;
        } catch (error) {
            desaSelect.innerHTML = '<option value="">Gagal memuat data</option>';
        }
        triggerSave();
    }

    // --- FUNGSI AUTO-SAVE ---
    function triggerSave() {
        const formElements = document.querySelectorAll(`#${formId} input, #${formId} select, #${formId} textarea`);
        const dataObj = {};
        formElements.forEach(el => {
            dataObj[el.name] = el.value;
        });
        localStorage.setItem(formId, JSON.stringify(dataObj));
    }

    // --- INisialisasi & Restore Data ---
    document.addEventListener("DOMContentLoaded", async function() {
        // 1. Muat data Provinsi pertama kali
        await loadProvinsi();

        const formElements = document.querySelectorAll(`#${formId} input, #${formId} select, #${formId} textarea`);
        const savedData = JSON.parse(localStorage.getItem(formId) || "{}");
        
        // 2. Restore data input biasa (teks, dll)
        formElements.forEach(el => {
            if (savedData[el.name] && el.tagName !== 'SELECT') {
                el.value = savedData[el.name];
            }
        });

        // 3. Restore data Dropdown Berjenjang secara berurutan agar ID-nya valid
        if(savedData['provinsi']) {
            const provSelect = document.getElementById('provinsi');
            provSelect.value = savedData['provinsi'];
            
            // Cek apakah data benar-benar ada di option (validasi localstorage lama vs baru)
            if(provSelect.selectedIndex > 0) {
                await loadKota();
                
                if(savedData['kota_kabupaten']) {
                    const kotaSelect = document.getElementById('kota');
                    kotaSelect.value = savedData['kota_kabupaten'];
                    
                    if(kotaSelect.selectedIndex > 0) {
                        await loadKecamatan();
                        
                        if(savedData['kecamatan']) {
                            const kecSelect = document.getElementById('kecamatan');
                            kecSelect.value = savedData['kecamatan'];
                            
                            if(kecSelect.selectedIndex > 0) {
                                await loadDesa();
                                
                                if(savedData['desa_kelurahan']) {
                                    document.getElementById('desa').value = savedData['desa_kelurahan'];
                                }
                            }
                        }
                    }
                }
            }
        }

        // 4. Tambahkan event listener untuk simpan setiap ketikan
        document.getElementById(formId).addEventListener("input", triggerSave);
    });
</script>

</body>
</html>