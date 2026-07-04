<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Akses ditolak.");
}

require_once '../config.php';
require('fpdf/fpdf.php'); // Panggil FPDF

$conn->set_charset("utf8mb4");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Pendaftaran tidak valid!");
}
$id_pendaftar = intval($_GET['id']);

// --- QUERY DATA LENGKAP ---
$query = "
    SELECT p.no_pendaftaran, p.created_at, 
           d.nama_lengkap, d.nisn, d.tempat_lahir, d.tanggal_lahir, 
           k.ayah_nama, k.wali_nama, 
           a.desa_kelurahan, a.kecamatan, a.kota_kabupaten, 
           b.pas_foto 
    FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    LEFT JOIN data_keluarga k ON p.id = k.pendaftaran_id 
    LEFT JOIN data_alamat a ON p.id = a.pendaftaran_id 
    LEFT JOIN data_berkas b ON p.id = b.pendaftaran_id 
    WHERE p.id = $id_pendaftar
";
$result = $conn->query($query);

if ($result->num_rows == 0) die("Data pendaftar tidak ditemukan.");
$data = $result->fetch_assoc();

// Fungsi Format Tanggal Indo
function tgl_indo($tanggal){
    if(empty($tanggal)) return '-';
    $bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

// Logika Data
$nama_lengkap = strtoupper($data['nama_lengkap']);
if(strlen($nama_lengkap) > 20) {
    $nama_lengkap = substr($nama_lengkap, 0, 20) . '...'; // Truncate jika terlalu panjang
}

$nisn = !empty($data['nisn']) ? $data['nisn'] : '-';
$ttl = $data['tempat_lahir'] . ', ' . tgl_indo($data['tanggal_lahir']);
$nama_wali = !empty($data['wali_nama']) ? $data['wali_nama'] : $data['ayah_nama'];
$alamat = $data['desa_kelurahan'] . ', Kec. ' . $data['kecamatan'] . ', ' . $data['kota_kabupaten'];

// Tahun masuk untuk masa berlaku
$tahun_masuk = date('Y', strtotime($data['created_at']));
$tanggal_cetak = tgl_indo(date('Y-m-d'));

// ==========================================
// PEMBUATAN PDF DENGAN FPDF (Ukuran ID Card 90x55 mm)
// L = Landscape, mm = milimeter, array(90, 55) = Lebar 90mm, Tinggi 55mm
// ==========================================
$pdf = new FPDF('L', 'mm', array(90, 55));
$pdf->AddPage();
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);

// 1. BACKGROUND BLANGKO
// Cek file PNG atau JPG
$blangko_path = 'blangko/blangko_kts.png';
if (!file_exists($blangko_path)) {
    $blangko_path = 'blangko/blangko_kts.jpg'; // Fallback jika ekstensi .jpg
}

if (file_exists($blangko_path)) {
    $pdf->Image($blangko_path, 0, 0, 90, 55);
} else {
    // Jika file blangko tidak ada, beri warna dasar
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Rect(0, 0, 90, 55, 'F');
}

// 2. FOTO SANTRI (Posisi Kiri)
// Lebar 21mm, Tinggi 28mm (Rasio 3x4)
$x_foto = 5;
$y_foto = 18;
$w_foto = 21;
$h_foto = 28;

$foto_tersedia = false;
if (!empty($data['pas_foto']) && file_exists('../' . $data['pas_foto'])) {
    $ext = strtolower(pathinfo($data['pas_foto'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $pdf->Image('../' . $data['pas_foto'], $x_foto, $y_foto, $w_foto, $h_foto);
        $foto_tersedia = true;
    }
}

if (!$foto_tersedia) {
    // Kotak placeholder jika foto tidak ada
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Rect($x_foto, $y_foto, $w_foto, $h_foto, 'F');
    $pdf->SetXY($x_foto, $y_foto + 12);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell($w_foto, 4, 'FOTO', 0, 1, 'C');
    $pdf->SetXY($x_foto, $y_foto + 16);
    $pdf->Cell($w_foto, 4, '3x4', 0, 1, 'C');
}

// 3. KONTEN TEKS (Posisi Kanan)
$x_text = 28; // Mulai teks setelah foto
$pdf->SetTextColor(11, 74, 105); // Warna Biru Gelap sesuai referensi

// Judul KTS
$pdf->SetXY($x_text, 17);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 5, 'KARTU TANDA SANTRI', 0, 1, 'L');

// Fungsi pembantu untuk baris data
function printRow($pdf, $x, $y, $label, $value) {
    $pdf->SetXY($x, $y);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(18, 3.5, $label, 0, 0, 'L');
    $pdf->Cell(2, 3.5, ':', 0, 0, 'C');
    
    // Khusus alamat kita gunakan MultiCell agar bisa turun baris jika panjang
    if ($label == 'Alamat') {
        $pdf->MultiCell(37, 3.5, $value, 0, 'L');
    } else {
        $pdf->Cell(37, 3.5, $value, 0, 1, 'L');
    }
}

// Baris-baris Data
$y_start = 23;
$jarak = 3.8;

printRow($pdf, $x_text, $y_start, 'Nama Lengkap', $nama_lengkap);
printRow($pdf, $x_text, $y_start + ($jarak * 1), 'NISN', $nisn);
printRow($pdf, $x_text, $y_start + ($jarak * 2), 'T.T.L', $ttl);
printRow($pdf, $x_text, $y_start + ($jarak * 3), 'Nama Wali', strtoupper($nama_wali));
printRow($pdf, $x_text, $y_start + ($jarak * 4), 'Alamat', $alamat);

// 4. TANDA TANGAN (Posisi Kanan Bawah)
$x_ttd = 55;
$w_ttd = 32;

$pdf->SetXY($x_ttd, 40);
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell($w_ttd, 3, 'Way Kanan, ' . $tanggal_cetak, 0, 1, 'C');

$pdf->SetX($x_ttd);
$pdf->Cell($w_ttd, 3, 'Pimpinan', 0, 1, 'C');

// Ruang kosong untuk stempel/paraf
$pdf->SetXY($x_ttd, 51);
$pdf->SetFont('Arial', 'B', 6.5);
$pdf->Cell($w_ttd, 3, 'Ust. Oktawidodo, S.Pd.I.', 0, 1, 'C');

// --- OUTPUT PDF ---
$pdf->Output('I', 'KTS_'.$data['no_pendaftaran'].'.pdf');
?>