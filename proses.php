<?php
// Include file koneksi database
require_once 'config.php';

// Set character set ke utf8mb4 untuk mendukung karakter khusus dan emoji dengan sempurna
$conn->set_charset("utf8mb4");

// Memastikan request datang dari form method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses ditolak.");
}

// FUNGSI BANTUAN UNTUK MEMBERSIHKAN INPUT (Anti SQL Injection Basic)
function bersihkan($koneksi, $data) {
    return mysqli_real_escape_string($koneksi, trim($data ?? ''));
}

// FUNGSI BANTUAN UNTUK UPLOAD FILE
function uploadFile($input_name, $target_dir, $no_pendaftaran) {
    if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == 0) {
        // Buat folder jika belum ada
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_info = pathinfo($_FILES[$input_name]['name']);
        $ext = strtolower($file_info['extension']);
        // Rename file agar rapi dan unik (Contoh: SB-001_pas_foto_168000000.jpg)
        $new_filename = $no_pendaftaran . "_" . $input_name . "_" . time() . "." . $ext;
        $target_file = $target_dir . $new_filename;

        // Validasi Ekstensi
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($ext, $allowed_ext)) {
            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_file)) {
                return $target_file; // Return path file jika berhasil
            }
        }
    }
    return NULL; // Return null jika tidak ada file / gagal
}

// ==============================================================================
// MULAI PROSES PENYIMPANAN
// ==============================================================================

// Mulai Transaksi Database (Agar jika salah satu query gagal, semua dibatalkan/Rollback)
$conn->begin_transaction();

try {
    // --- 1. GENERATE NOMOR PENDAFTARAN ---
    $status_masuk = bersihkan($conn, $_POST['status_masuk']);
    
    // Tentukan Prefix berdasarkan status masuk
    $prefix = "SB"; // Default Santri Baru
    if ($status_masuk == "Pindahan") $prefix = "PD";
    if ($status_masuk == "Drop Out") $prefix = "DO";

    // Cari nomor terakhir di database dengan prefix tersebut
    $query_cek = "SELECT no_pendaftaran FROM pendaftaran WHERE no_pendaftaran LIKE '$prefix-%' ORDER BY id DESC LIMIT 1";
    $result_cek = $conn->query($query_cek);

    if ($result_cek && $result_cek->num_rows > 0) {
        $row = $result_cek->fetch_assoc();
        $last_number = (int) substr($row['no_pendaftaran'], 3); // Ambil angka setelah 'SB-'
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    // Format menjadi SB-001, SB-002, dst
    $no_pendaftaran = $prefix . "-" . str_pad($new_number, 3, "0", STR_PAD_LEFT);


    // --- 2. INSERT KE TABEL PENDAFTARAN ---
    $pilihan_sekolah = bersihkan($conn, $_POST['pilihan_sekolah']);
    $program_takhosush = bersihkan($conn, $_POST['program_takhosush']) === 'Ya' ? 'Ya' : 'Tidak';

    $sql_pendaftaran = "INSERT INTO pendaftaran (no_pendaftaran, status_masuk, pilihan_sekolah, program_takhosush, status_pendaftaran) 
                        VALUES ('$no_pendaftaran', '$status_masuk', '$pilihan_sekolah', '$program_takhosush', 'Menunggu Verifikasi')";
    $conn->query($sql_pendaftaran);
    $pendaftaran_id = $conn->insert_id; // Dapatkan ID pendaftaran yang baru saja dibuat


    // --- 3. INSERT KE TABEL DATA DIRI ---
    $nama_lengkap = bersihkan($conn, $_POST['nama_lengkap']);
    $nisn = bersihkan($conn, $_POST['nisn']);
    $nik = bersihkan($conn, $_POST['nik']);
    $tempat_lahir = bersihkan($conn, $_POST['tempat_lahir']);
    $tanggal_lahir = bersihkan($conn, $_POST['tanggal_lahir']);
    $jenis_kelamin = bersihkan($conn, $_POST['jenis_kelamin']);
    $agama = bersihkan($conn, $_POST['agama']);
    $hobi = bersihkan($conn, $_POST['hobi']);
    $anak_ke = (int) bersihkan($conn, $_POST['anak_ke']);
    $jumlah_saudara = (int) bersihkan($conn, $_POST['jumlah_saudara']);
    $no_kip = bersihkan($conn, $_POST['no_kip']);

    $sql_diri = "INSERT INTO data_diri (pendaftaran_id, nama_lengkap, nisn, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, agama, hobi, anak_ke, jumlah_saudara, no_kip) 
                 VALUES ($pendaftaran_id, '$nama_lengkap', '$nisn', '$nik', '$tempat_lahir', '$tanggal_lahir', '$jenis_kelamin', '$agama', '$hobi', $anak_ke, $jumlah_saudara, '$no_kip')";
    $conn->query($sql_diri);


    // --- 4. INSERT KE TABEL DATA ALAMAT ---
    $alamat_lengkap = bersihkan($conn, $_POST['alamat_lengkap']);
    $rt = bersihkan($conn, $_POST['rt']);
    $rw = bersihkan($conn, $_POST['rw']);
    $provinsi = bersihkan($conn, $_POST['provinsi']);
    $kota_kabupaten = bersihkan($conn, $_POST['kota_kabupaten']);
    $kecamatan = bersihkan($conn, $_POST['kecamatan']);
    $desa_kelurahan = bersihkan($conn, $_POST['desa_kelurahan']);
    $kode_pos = bersihkan($conn, $_POST['kode_pos']);
    $no_whatsapp = bersihkan($conn, $_POST['no_whatsapp']);
    $email = bersihkan($conn, $_POST['email']);

    $sql_alamat = "INSERT INTO data_alamat (pendaftaran_id, alamat_lengkap, rt, rw, provinsi, kota_kabupaten, kecamatan, desa_kelurahan, kode_pos, no_whatsapp, email) 
                   VALUES ($pendaftaran_id, '$alamat_lengkap', '$rt', '$rw', '$provinsi', '$kota_kabupaten', '$kecamatan', '$desa_kelurahan', '$kode_pos', '$no_whatsapp', '$email')";
    $conn->query($sql_alamat);


    // --- 5. INSERT KE TABEL SEKOLAH ASAL ---
    $nama_sekolah = bersihkan($conn, $_POST['nama_sekolah']);
    $npsn_sekolah = bersihkan($conn, $_POST['npsn_sekolah']);
    $alamat_sekolah = bersihkan($conn, $_POST['alamat_sekolah']);
    $tahun_lulus = bersihkan($conn, $_POST['tahun_lulus']);
    $no_ijazah_skhu = bersihkan($conn, $_POST['no_ijazah_skhu']);

    $sql_sekolah = "INSERT INTO sekolah_asal (pendaftaran_id, nama_sekolah, npsn_sekolah, alamat_sekolah, tahun_lulus, no_ijazah_skhu) 
                    VALUES ($pendaftaran_id, '$nama_sekolah', '$npsn_sekolah', '$alamat_sekolah', '$tahun_lulus', '$no_ijazah_skhu')";
    $conn->query($sql_sekolah);


    // --- 6. INSERT KE TABEL DATA KELUARGA ---
    // Ayah
    $ayah_nama = bersihkan($conn, $_POST['ayah_nama']);
    $ayah_status = bersihkan($conn, $_POST['ayah_status']);
    $ayah_nik = bersihkan($conn, $_POST['ayah_nik']);
    $ayah_tempat_lahir = bersihkan($conn, $_POST['ayah_tempat_lahir']);
    $ayah_pendidikan = bersihkan($conn, $_POST['ayah_pendidikan']);
    $ayah_pekerjaan = bersihkan($conn, $_POST['ayah_pekerjaan']);
    $ayah_penghasilan = bersihkan($conn, $_POST['ayah_penghasilan']);
    $ayah_no_hp = bersihkan($conn, $_POST['ayah_no_hp']);
    $ayah_tanggal_lahir = !empty($_POST['ayah_tanggal_lahir']) ? "'" . bersihkan($conn, $_POST['ayah_tanggal_lahir']) . "'" : "NULL";

    // Ibu
    $ibu_nama = bersihkan($conn, $_POST['ibu_nama']);
    $ibu_status = bersihkan($conn, $_POST['ibu_status']);
    $ibu_nik = bersihkan($conn, $_POST['ibu_nik']);
    $ibu_tempat_lahir = bersihkan($conn, $_POST['ibu_tempat_lahir']);
    $ibu_pendidikan = bersihkan($conn, $_POST['ibu_pendidikan']);
    $ibu_pekerjaan = bersihkan($conn, $_POST['ibu_pekerjaan']);
    $ibu_penghasilan = bersihkan($conn, $_POST['ibu_penghasilan']);
    $ibu_no_hp = bersihkan($conn, $_POST['ibu_no_hp']);
    $ibu_tanggal_lahir = !empty($_POST['ibu_tanggal_lahir']) ? "'" . bersihkan($conn, $_POST['ibu_tanggal_lahir']) . "'" : "NULL";

    // Wali
    $wali_nama = bersihkan($conn, $_POST['wali_nama']);
    $wali_nik = bersihkan($conn, $_POST['wali_nik']);
    $wali_pekerjaan = bersihkan($conn, $_POST['wali_pekerjaan']);
    $wali_penghasilan = bersihkan($conn, $_POST['wali_penghasilan']);
    $wali_pendidikan = bersihkan($conn, $_POST['wali_pendidikan']);
    $wali_no_hp = bersihkan($conn, $_POST['wali_no_hp']);
    $wali_hubungan = bersihkan($conn, $_POST['wali_hubungan']);

    $sql_keluarga = "INSERT INTO data_keluarga (
                        pendaftaran_id, ayah_nama, ayah_status, ayah_nik, ayah_tempat_lahir, ayah_tanggal_lahir, ayah_pendidikan, ayah_pekerjaan, ayah_penghasilan, ayah_no_hp,
                        ibu_nama, ibu_status, ibu_nik, ibu_tempat_lahir, ibu_tanggal_lahir, ibu_pendidikan, ibu_pekerjaan, ibu_penghasilan, ibu_no_hp,
                        wali_nama, wali_nik, wali_pekerjaan, wali_penghasilan, wali_pendidikan, wali_no_hp, wali_hubungan
                     ) VALUES (
                        $pendaftaran_id, '$ayah_nama', '$ayah_status', '$ayah_nik', '$ayah_tempat_lahir', $ayah_tanggal_lahir, '$ayah_pendidikan', '$ayah_pekerjaan', '$ayah_penghasilan', '$ayah_no_hp',
                        '$ibu_nama', '$ibu_status', '$ibu_nik', '$ibu_tempat_lahir', $ibu_tanggal_lahir, '$ibu_pendidikan', '$ibu_pekerjaan', '$ibu_penghasilan', '$ibu_no_hp',
                        '$wali_nama', '$wali_nik', '$wali_pekerjaan', '$wali_penghasilan', '$wali_pendidikan', '$wali_no_hp', '$wali_hubungan'
                     )";
    $conn->query($sql_keluarga);


    // --- 7. UPLOAD FILE & INSERT KE TABEL DATA BERKAS ---
    $dir_berkas = "uploads/berkas/";
    
    // Upload File satu-satu memanggil fungsi bantuan di atas
    $path_pas_foto = uploadFile('pas_foto', $dir_berkas, $no_pendaftaran);
    $path_kk = uploadFile('kartu_keluarga', $dir_berkas, $no_pendaftaran);
    $path_ktp = uploadFile('ktp_ortu', $dir_berkas, $no_pendaftaran);
    $path_akta = uploadFile('akta_kelahiran', $dir_berkas, $no_pendaftaran);
    $path_ijazah = uploadFile('ijazah_skhu', $dir_berkas, $no_pendaftaran);
    $path_piagam = uploadFile('piagam_prestasi', $dir_berkas, $no_pendaftaran);

    // Mencegah error jika wajib tapi gagal upload
    if (!$path_pas_foto || !$path_kk || !$path_ktp) {
        throw new Exception("File wajib (Foto, KK, atau KTP) gagal diunggah atau ukurannya terlalu besar.");
    }

    $sql_berkas = "INSERT INTO data_berkas (pendaftaran_id, pas_foto, ijazah_skhu, akta_kelahiran, ktp_ortu, kartu_keluarga, piagam_prestasi) 
                   VALUES ($pendaftaran_id, '$path_pas_foto', '$path_ijazah', '$path_akta', '$path_ktp', '$path_kk', '$path_piagam')";
    $conn->query($sql_berkas);

    
    // --- 8. PREPARE TABEL PENDUKUNG (Kesehatan, Keuangan, Seragam) DENGAN NILAI DEFAULT ---
    $conn->query("INSERT INTO data_kesehatan (pendaftaran_id) VALUES ($pendaftaran_id)");
    $conn->query("INSERT INTO data_pembayaran (pendaftaran_id) VALUES ($pendaftaran_id)");
    $conn->query("INSERT INTO data_seragam (pendaftaran_id) VALUES ($pendaftaran_id)");


    // Jika semua eksekusi di atas berhasil tanpa error, simpan permanen (Commit)
    $conn->commit();
    $status_akhir = "SUKSES";

} catch (Exception $e) {
    // Jika terjadi error di salah satu proses, batalkan semua perubahan (Rollback)
    $conn->rollback();
    $status_akhir = "GAGAL";
    $raw_error = $e->getMessage();

    $pesan_error = "";
    if (strpos($raw_error, 'Duplicate entry') !== false) {
        $pesan_error = "<b style='color:#dc3545;'>Data Ganda Ditemukan!</b><br>NISN atau NIK sudah pernah didaftarkan sebelumnya.";
    } elseif (strpos($raw_error, 'File wajib') !== false) {
        $pesan_error = "<b style='color:#dc3545;'>Berkas Gagal Diunggah!</b><br>Pastikan format foto benar (.jpg/.png) dan tidak lebih dari 2MB.";
    } else {
        $pesan_error = "<b style='color:#dc3545;'>Gagal Menyimpan Data!</b><br>Pastikan seluruh isian formulir yang wajib bertanda bintang (*) telah diisi dengan benar.<br><br><small class='text-muted' style='font-size: 0.75rem;'>Kode Sistem: " . htmlspecialchars($raw_error) . "</small>";
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memproses Pendaftaran...</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f9f6; }
    </style>
</head>
<body>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($status_akhir == "SUKSES") : ?>
            
            // Bersihkan riwayat Auto-Save dari browser
            localStorage.removeItem('formStep1');
            localStorage.removeItem('formStep2');
            localStorage.removeItem('formStep3');
            localStorage.removeItem('formStep4');

            // Tampilkan Notifikasi Sukses dengan Tombol Download
            Swal.fire({
                icon: 'success',
                title: 'Pendaftaran Berhasil!',
                html: 'Selamat! Data calon santri <b><?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8') ?></b> telah kami terima.<br><br>Nomor Pendaftaran Anda:<br><span style="display:inline-block; background:#eafbf3; color:#0da15b; font-size:1.8rem; font-weight:bold; padding:10px 20px; border-radius:10px; border:2px dashed #0da15b; margin-top:10px; margin-bottom:15px; letter-spacing:2px;"><?= $no_pendaftaran ?></span><br><span style="font-size:0.9rem;">Silakan <b>Unduh Bukti Pendaftaran</b> di bawah ini. Bukti ini merupakan <b class="text-danger">Kartu Kendali</b> yang wajib dicetak dan dibawa saat mendatangi pos panitia (TU, Kesehatan, Keuangan, Keamanan).</span>',
                showCancelButton: true,
                confirmButtonColor: '#0da15b',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-file-pdf me-2"></i> Unduh Bukti PDF',
                cancelButtonText: '<i class="fas fa-home me-1"></i> Beranda',
                allowOutsideClick: false,
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Buka PDF di tab baru
                    window.open('cetak_bukti.php?id=<?= $pendaftaran_id ?>', '_blank');
                    // Arahkan kembali ke Landing Page
                    window.location.href = 'index.html'; 
                } else {
                    window.location.href = 'index.html';
                }
            });

        <?php else : ?>
            
            // Tampilkan Notifikasi Gagal
            Swal.fire({
                icon: 'error',
                title: 'Mohon Maaf, Proses Gagal!',
                html: '<?= addslashes($pesan_error) ?>',
                confirmButtonColor: '#d33',
                confirmButtonText: 'Kembali & Perbaiki Data',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.history.back(); // Kembali ke halaman sebelumnya tanpa menghapus isi form
                }
            });

        <?php endif; ?>
    });
</script>

</body>
</html>