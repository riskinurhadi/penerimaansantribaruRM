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
$q_utama = $conn->query("
    SELECT p.no_pendaftaran, d.nama_lengkap, d.jenis_kelamin, d.tempat_lahir, d.tanggal_lahir, k.* FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    JOIN data_kesehatan k ON p.id = k.pendaftaran_id 
    WHERE p.id = $id_pendaftar
");

if ($q_utama->num_rows == 0) die("Belum ada data pemeriksaan medis untuk siswa ini.");
$data = $q_utama->fetch_assoc();

if (empty($data['catatan_kesehatan'])) {
    die("Siswa belum diperiksa secara fisik. Silakan input rekam medis terlebih dahulu.");
}

// Format Tanggal Indo
function tgl_indo($tanggal){
	$bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
	$pecahkan = explode('-', $tanggal);
	return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}
$tanggal_cetak = tgl_indo(date('Y-m-d'));
$umur_santri = (new DateTime($data['tanggal_lahir']))->diff(new DateTime('today'))->y;

// ==============================================================================
// LOGIKA CERDAS PENENTUAN NAMA PETUGAS
// Jika petugas di database kosong (data lama), gunakan nama admin yang login
// ==============================================================================
$nama_pemeriksa = $_SESSION['nama_lengkap'];
$role_pemeriksa = $_SESSION['role'];

if (!empty($data['petugas_medis']) && $data['petugas_medis'] !== 'Admin Kesehatan') {
    // Memecah format "Nama Lengkap (Role)" dari database
    $pecah_petugas = explode(' (', $data['petugas_medis']);
    $nama_pemeriksa = $pecah_petugas[0];
    $role_pemeriksa = isset($pecah_petugas[1]) ? rtrim($pecah_petugas[1], ')') : 'Bag. Kesehatan';
}

// ==========================================
// PEMBUATAN PDF DENGAN FPDF (Ukuran A4)
// ==========================================
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 20);

// --- KOP SURAT ---
$header_path = 'header/header_keuangan.png';
if (file_exists($header_path)) {
    $pdf->Image($header_path, 5, 5, 195);
    $pdf->SetY(50); 
} else {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, 'PONDOK PESANTREN', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(0, 100, 0); 
    $pdf->Cell(0, 8, "RAUDLATUL MUTA'ALLIMIN", 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'JAYA TINGGI - KASUI - WAY KANAN - LAMPUNG', 0, 1, 'C');
    $pdf->SetLineWidth(0.5);
    $pdf->Line(20, $pdf->GetY() + 2, 190, $pdf->GetY() + 2);
    $pdf->Ln(10);
}

// --- JUDUL SURAT ---
$pdf->SetFont('Arial', 'BU', 14);
$pdf->Cell(0, 8, 'SURAT KETERANGAN PEMERIKSAAN KESEHATAN', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Nomor Rekam Medis: RM-' . $data['no_pendaftaran'], 0, 1, 'C');
$pdf->Ln(5);

// --- PEMBUKAAN ---
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 6, 'Berdasarkan hasil pemeriksaan fisik dan rekam medis yang telah dilakukan di Pos Kesehatan Pondok Pesantren Raudlatul Muta\'allimin, dengan ini menerangkan bahwa calon santri di bawah ini:', 0, 'J');
$pdf->Ln(5);

// --- IDENTITAS SISWA ---
$pdf->SetX(25);
$pdf->Cell(45, 6, 'Nama Lengkap', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); 
$pdf->SetFont('Arial', 'B', 11); $pdf->Cell(0, 6, strtoupper($data['nama_lengkap']), 0, 1);
$pdf->SetFont('Arial', '', 11);

$pdf->SetX(25);
$pdf->Cell(45, 6, 'No. Pendaftaran', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $data['no_pendaftaran'], 0, 1);
$pdf->SetX(25);
$pdf->Cell(45, 6, 'Jenis Kelamin', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $data['jenis_kelamin'], 0, 1);
$pdf->SetX(25);
$pdf->Cell(45, 6, 'Tempat, Tgl Lahir', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $data['tempat_lahir'] . ', ' . tgl_indo($data['tanggal_lahir']) . ' ('.$umur_santri.' Tahun)', 0, 1);
$pdf->Ln(5);

// --- HASIL PEMERIKSAAN ---
$pdf->MultiCell(0, 6, 'Telah menjalani serangkaian pemeriksaan administrasi kesehatan dan fisik dengan rincian sebagai berikut:', 0, 'J');
$pdf->Ln(3);

// Tabel Hasil Medis
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 230, 230); // Abu-abu terang
$pdf->Cell(60, 8, ' JENIS PEMERIKSAAN', 1, 0, 'L', true);
$pdf->Cell(110, 8, ' HASIL PEMERIKSAAN', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(60, 8, ' Tinggi Badan', 1, 0, 'L');
$pdf->Cell(110, 8, ($data['tinggi_badan'] > 0 ? $data['tinggi_badan'].' cm' : '-'), 1, 1, 'C');

$pdf->Cell(60, 8, ' Berat Badan', 1, 0, 'L');
$pdf->Cell(110, 8, ($data['berat_badan'] > 0 ? $data['berat_badan'].' kg' : '-'), 1, 1, 'C');

$pdf->Cell(60, 8, ' Golongan Darah', 1, 0, 'L');
$pdf->Cell(110, 8, (!empty($data['golongan_darah']) ? $data['golongan_darah'] : '-'), 1, 1, 'C');

// Row Riwayat Penyakit (Bisa MultiCell jika panjang)
$pdf->Cell(60, 12, ' Riwayat Penyakit Menahun', 1, 0, 'L');
$riwayat = !empty($data['riwayat_penyakit']) ? $data['riwayat_penyakit'] : 'Tidak ada riwayat penyakit serius';
$x_pos = $pdf->GetX(); $y_pos = $pdf->GetY();
$pdf->MultiCell(110, 6, $riwayat, 0, 'C');
$pdf->SetXY($x_pos, $y_pos); $pdf->Cell(110, 12, '', 1, 1, 'C'); // Buat border luar

// Row Kelainan Fisik
$pdf->Cell(60, 12, ' Kelainan Fisik', 1, 0, 'L');
$kelainan = !empty($data['kelainan_fisik']) ? $data['kelainan_fisik'] : 'Tidak ditemukan kelainan fisik';
$x_pos = $pdf->GetX(); $y_pos = $pdf->GetY();
$pdf->MultiCell(110, 6, $kelainan, 0, 'C');
$pdf->SetXY($x_pos, $y_pos); $pdf->Cell(110, 12, '', 1, 1, 'C');

$pdf->Ln(5);

// --- KESIMPULAN / CATATAN MEDIS ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'Kesimpulan & Catatan Medis:', 0, 1, 'L');
$pdf->SetFont('Arial', 'I', 11);
// Kotak catatan
$pdf->SetFillColor(250, 250, 250);
$pdf->MultiCell(0, 7, $data['catatan_kesehatan'], 1, 'J', true);
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 6, 'Demikian surat keterangan pemeriksaan ini dibuat dengan sebenar-benarnya untuk dapat dipergunakan sebagai salah satu syarat administrasi penerimaan santri baru.', 0, 'J');

// --- TANDA TANGAN ---
$pdf->Ln(5);
$pdf->SetX(120);
$pdf->Cell(70, 6, 'Kasui, ' . $tanggal_cetak, 0, 1, 'C');

$pdf->SetX(120);
$pdf->Cell(70, 6, 'Petugas Pemeriksa,', 0, 1, 'C');

$pdf->Ln(20); // Ruang Tanda Tangan

$pdf->SetX(120);
$pdf->SetFont('Arial', 'BU', 11);

// Menampilkan Nama Pemeriksa Hasil Ekstraksi
$pdf->Cell(70, 6, strtoupper($nama_pemeriksa), 0, 1, 'C');

$pdf->SetX(120);
$pdf->SetFont('Arial', '', 10);
// Menampilkan Role Petugas Hasil Ekstraksi
$pdf->Cell(70, 5, $role_pemeriksa, 0, 1, 'C');

// Output
$pdf->Output('I', 'Rekam_Medis_'.$data['no_pendaftaran'].'.pdf');
?>