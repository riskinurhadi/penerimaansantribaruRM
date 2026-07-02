<?php
session_start();

// 1. Cek apakah user sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Akses ditolak.");
}

// 2. Cek Role (Hanya Developer dan Super Admin yang boleh mendownload semua data)
$role = $_SESSION['role'];
if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengunduh rekap data pendaftar.");
}

require_once '../config.php';
$conn->set_charset("utf8mb4");

// --- LOGIKA FILTER BERDASARKAN SEKOLAH ---
$filter_sekolah = "";
$judul_tambahan = "Semua Jenjang"; // Default
if (isset($_GET['filter']) && !empty($_GET['filter'])) {
    // Hindari SQL Injection
    $jenjang = mysqli_real_escape_string($conn, $_GET['filter']);
    // Pastikan filter valid sesuai pilihan yang ada
    if (in_array($jenjang, ['MI', 'MTs', 'MA', 'SMK'])) {
        $filter_sekolah = " WHERE p.pilihan_sekolah = '$jenjang'";
        $judul_tambahan = "Jenjang $jenjang";
    }
}

// 3. Nama file Excel yang akan diunduh (disesuaikan dengan filter)
$nama_file = "Rekap_Data_Pendaftar_PSB_" . str_replace(" ", "_", $judul_tambahan) . "_" . date('Y-m-d_H-i-s') . ".xls";

// 4. Header untuk memaksa browser mendownload file sebagai Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=$nama_file");
header("Pragma: no-cache");
header("Expires: 0");

// 5. Query Ambil Data Lengkap (Dengan tambahan WHERE jika ada filter)
$query = "
    SELECT 
        p.no_pendaftaran, p.status_masuk, p.pilihan_sekolah, p.program_takhosush, p.status_pendaftaran, p.created_at,
        d.nama_lengkap, d.nisn, d.nik, d.tempat_lahir, d.tanggal_lahir, d.jenis_kelamin, d.agama, d.hobi, d.anak_ke, d.jumlah_saudara, d.no_kip,
        a.alamat_lengkap, a.rt, a.rw, a.desa_kelurahan, a.kecamatan, a.kota_kabupaten, a.provinsi, a.kode_pos, a.no_whatsapp, a.email,
        k.ayah_nama, k.ayah_status, k.ayah_nik, k.ayah_pendidikan, k.ayah_pekerjaan, k.ayah_penghasilan, k.ayah_no_hp,
        k.ibu_nama, k.ibu_status, k.ibu_nik, k.ibu_pendidikan, k.ibu_pekerjaan, k.ibu_penghasilan, k.ibu_no_hp,
        k.wali_nama, k.wali_hubungan, k.wali_pekerjaan, k.wali_no_hp,
        s.nama_sekolah, s.tahun_lulus, s.npsn_sekolah, s.no_ijazah_skhu
    FROM pendaftaran p
    LEFT JOIN data_diri d ON p.id = d.pendaftaran_id
    LEFT JOIN data_alamat a ON p.id = a.pendaftaran_id
    LEFT JOIN data_keluarga k ON p.id = k.pendaftaran_id
    LEFT JOIN sekolah_asal s ON p.id = s.pendaftaran_id
    $filter_sekolah
    ORDER BY p.id ASC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Memaksa Excel membaca kolom sebagai Teks agar angka nol di depan tidak hilang */
        .text-format { mso-number-format: "\@"; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000000; padding: 5px; }
        th { background-color: #0da15b; color: #ffffff; font-weight: bold; text-align: center; }
        .bg-section { background-color: #e2e8f0; font-weight: bold; color: #000000;}
    </style>
</head>
<body>

    <center>
        <h2>REKAPITULASI DATA PENDAFTARAN SANTRI BARU (<?= strtoupper($judul_tambahan) ?>)</h2>
        <h3>PONDOK PESANTREN RAUDLATUL MUTA'ALLIMIN</h3>
        <p>Diunduh pada: <?= date('d-m-Y H:i:s') ?> WIB</p>
    </center>
    <br>

    <table>
        <thead>
            <tr>
                <th rowspan="2">NO</th>
                
                <!-- KATEGORI STATUS -->
                <th colspan="6">INFORMASI PENDAFTARAN</th>
                
                <!-- KATEGORI DATA DIRI -->
                <th colspan="10">DATA DIRI CALON SANTRI</th>
                
                <!-- KATEGORI ALAMAT -->
                <th colspan="10">ALAMAT DOMISILI</th>
                
                <!-- KATEGORI SEKOLAH -->
                <th colspan="4">SEKOLAH ASAL</th>

                <!-- KATEGORI KELUARGA -->
                <th colspan="7">DATA AYAH</th>
                <th colspan="7">DATA IBU</th>
                <th colspan="4">DATA WALI</th>
            </tr>
            <tr>
                <!-- Pendaftaran -->
                <th class="bg-section">No. Daftar</th>
                <th class="bg-section">Tanggal Daftar</th>
                <th class="bg-section">Status Masuk</th>
                <th class="bg-section">Pilihan Jenjang</th>
                <th class="bg-section">Program Takhosush</th>
                <th class="bg-section">Status Kelulusan</th>
                
                <!-- Data Diri -->
                <th class="bg-section">Nama Lengkap</th>
                <th class="bg-section">NISN</th>
                <th class="bg-section">NIK</th>
                <th class="bg-section">Tempat Lahir</th>
                <th class="bg-section">Tanggal Lahir</th>
                <th class="bg-section">Jenis Kelamin</th>
                <th class="bg-section">Agama</th>
                <th class="bg-section">Hobi</th>
                <th class="bg-section">Anak Ke</th>
                <th class="bg-section">No KIP</th>

                <!-- Alamat -->
                <th class="bg-section">Alamat Lengkap</th>
                <th class="bg-section">RT</th>
                <th class="bg-section">RW</th>
                <th class="bg-section">Desa / Kelurahan</th>
                <th class="bg-section">Kecamatan</th>
                <th class="bg-section">Kota / Kab</th>
                <th class="bg-section">Provinsi</th>
                <th class="bg-section">Kode Pos</th>
                <th class="bg-section">No. WhatsApp</th>
                <th class="bg-section">Email</th>

                <!-- Sekolah Asal -->
                <th class="bg-section">Nama Sekolah</th>
                <th class="bg-section">Tahun Lulus</th>
                <th class="bg-section">NPSN</th>
                <th class="bg-section">No Ijazah/SKHU</th>

                <!-- Ayah -->
                <th class="bg-section">Nama Ayah</th>
                <th class="bg-section">Status Ayah</th>
                <th class="bg-section">NIK Ayah</th>
                <th class="bg-section">Pendidikan</th>
                <th class="bg-section">Pekerjaan</th>
                <th class="bg-section">Penghasilan</th>
                <th class="bg-section">No HP Ayah</th>

                <!-- Ibu -->
                <th class="bg-section">Nama Ibu</th>
                <th class="bg-section">Status Ibu</th>
                <th class="bg-section">NIK Ibu</th>
                <th class="bg-section">Pendidikan</th>
                <th class="bg-section">Pekerjaan</th>
                <th class="bg-section">Penghasilan</th>
                <th class="bg-section">No HP Ibu</th>

                <!-- Wali -->
                <th class="bg-section">Nama Wali</th>
                <th class="bg-section">Hubungan</th>
                <th class="bg-section">Pekerjaan</th>
                <th class="bg-section">No HP Wali</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($result && $result->num_rows > 0) {
                $no = 1;
                while ($row = $result->fetch_assoc()) {
            ?>
                <tr>
                    <td><?= $no++ ?></td>
                    
                    <!-- Pendaftaran -->
                    <td class="text-format"><?= $row['no_pendaftaran'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td><?= $row['status_masuk'] ?></td>
                    <td><?= $row['pilihan_sekolah'] ?></td>
                    <td><?= $row['program_takhosush'] ?></td>
                    <td><?= $row['status_pendaftaran'] ?></td>

                    <!-- Data Diri -->
                    <td><?= $row['nama_lengkap'] ?></td>
                    <td class="text-format"><?= $row['nisn'] ?></td>
                    <td class="text-format"><?= $row['nik'] ?></td>
                    <td><?= $row['tempat_lahir'] ?></td>
                    <td><?= $row['tanggal_lahir'] ?></td>
                    <td><?= $row['jenis_kelamin'] ?></td>
                    <td><?= $row['agama'] ?></td>
                    <td><?= $row['hobi'] ?></td>
                    <td><?= $row['anak_ke'] ?> dari <?= $row['jumlah_saudara'] ?> bersaudara</td>
                    <td class="text-format"><?= !empty($row['no_kip']) ? $row['no_kip'] : '-' ?></td>

                    <!-- Alamat -->
                    <td><?= $row['alamat_lengkap'] ?></td>
                    <td class="text-format"><?= $row['rt'] ?></td>
                    <td class="text-format"><?= $row['rw'] ?></td>
                    <td><?= $row['desa_kelurahan'] ?></td>
                    <td><?= $row['kecamatan'] ?></td>
                    <td><?= $row['kota_kabupaten'] ?></td>
                    <td><?= $row['provinsi'] ?></td>
                    <td class="text-format"><?= $row['kode_pos'] ?></td>
                    <td class="text-format"><?= $row['no_whatsapp'] ?></td>
                    <td><?= $row['email'] ?></td>

                    <!-- Sekolah -->
                    <td><?= $row['nama_sekolah'] ?></td>
                    <td><?= $row['tahun_lulus'] ?></td>
                    <td class="text-format"><?= !empty($row['npsn_sekolah']) ? $row['npsn_sekolah'] : '-' ?></td>
                    <td class="text-format"><?= !empty($row['no_ijazah_skhu']) ? $row['no_ijazah_skhu'] : '-' ?></td>

                    <!-- Ayah -->
                    <td><?= $row['ayah_nama'] ?></td>
                    <td><?= $row['ayah_status'] ?></td>
                    <td class="text-format"><?= !empty($row['ayah_nik']) ? $row['ayah_nik'] : '-' ?></td>
                    <td><?= !empty($row['ayah_pendidikan']) ? $row['ayah_pendidikan'] : '-' ?></td>
                    <td><?= !empty($row['ayah_pekerjaan']) ? $row['ayah_pekerjaan'] : '-' ?></td>
                    <td><?= !empty($row['ayah_penghasilan']) ? $row['ayah_penghasilan'] : '-' ?></td>
                    <td class="text-format"><?= !empty($row['ayah_no_hp']) ? $row['ayah_no_hp'] : '-' ?></td>

                    <!-- Ibu -->
                    <td><?= $row['ibu_nama'] ?></td>
                    <td><?= $row['ibu_status'] ?></td>
                    <td class="text-format"><?= !empty($row['ibu_nik']) ? $row['ibu_nik'] : '-' ?></td>
                    <td><?= !empty($row['ibu_pendidikan']) ? $row['ibu_pendidikan'] : '-' ?></td>
                    <td><?= !empty($row['ibu_pekerjaan']) ? $row['ibu_pekerjaan'] : '-' ?></td>
                    <td><?= !empty($row['ibu_penghasilan']) ? $row['ibu_penghasilan'] : '-' ?></td>
                    <td class="text-format"><?= !empty($row['ibu_no_hp']) ? $row['ibu_no_hp'] : '-' ?></td>

                    <!-- Wali -->
                    <td><?= !empty($row['wali_nama']) ? $row['wali_nama'] : '-' ?></td>
                    <td><?= !empty($row['wali_hubungan']) ? $row['wali_hubungan'] : '-' ?></td>
                    <td><?= !empty($row['wali_pekerjaan']) ? $row['wali_pekerjaan'] : '-' ?></td>
                    <td class="text-format"><?= !empty($row['wali_no_hp']) ? $row['wali_no_hp'] : '-' ?></td>
                </tr>
            <?php 
                }
            } else {
            ?>
                <tr>
                    <td colspan="46" style="text-align: center;">Belum ada data pendaftar.</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

</body>
</html>