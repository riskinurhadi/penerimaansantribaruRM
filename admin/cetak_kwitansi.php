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

// Ambil Nama dan Role Kasir (Orang yang mencetak ini)
$nama_kasir = $_SESSION['nama_lengkap'];
$role_kasir = $_SESSION['role'];

// --- QUERY DATA LENGKAP ---
$q_utama = $conn->query("
    SELECT p.no_pendaftaran, d.nama_lengkap, d.jenis_kelamin, p.pilihan_sekolah, b.* FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    JOIN data_pembayaran b ON p.id = b.pendaftaran_id 
    WHERE p.id = $id_pendaftar
");

if ($q_utama->num_rows == 0) die("Belum ada data pembayaran yang diinput untuk siswa ini.");
$data = $q_utama->fetch_assoc();

// Hitung Target (Berdasarkan ketentuan)
$target_pendaftaran = 150000;
$target_bangunan = 2000000;
$target_fasilitas = 500000;
$target_tahunan = 1200000;
$target_bulanan = 550000;
$target_seragam = ($data['jenis_kelamin'] == 'Laki-laki') ? 950000 : 1000000;

// Tanggal Cetak
function tgl_indo($tanggal){
	$bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
	$pecahkan = explode('-', $tanggal);
	return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}
$tanggal_cetak = tgl_indo(date('Y-m-d'));

// ==========================================
// PEMBUATAN PDF DENGAN FPDF (Ukuran A4)
// ==========================================
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// --- KOP SURAT (Disesuaikan dengan header jika ada, atau teks statis) ---
$header_path = 'header/header_keuangan.png';
if (file_exists($header_path)) {
    $pdf->Image($header_path, 8, 8, 193);
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
    $pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);
    $pdf->Ln(5);
}

// --- JUDUL KUITANSI ---
$pdf->SetFont('Arial', 'BU', 14);
$pdf->Cell(0, 8, 'BUKTI PEMBAYARAN SANTRI BARU', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'No. Ref: INV-' . $data['no_pendaftaran'] . '-' . date('my'), 0, 1, 'C');
$pdf->Ln(8);

// --- INFO SISWA ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(35, 6, 'No. Pendaftaran', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $data['no_pendaftaran'], 0, 1);
$pdf->Cell(35, 6, 'Nama Santri', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, strtoupper($data['nama_lengkap']) . ' ('.$data['jenis_kelamin'].')', 0, 1);
$pdf->Cell(35, 6, 'Jenjang', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $data['pilihan_sekolah'], 0, 1);
$pdf->Ln(5);

// --- TABEL RINCIAN BIAYA ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(13, 161, 91); // Primary Green
$pdf->SetTextColor(255, 255, 255);
// Header Tabel
$pdf->Cell(10, 8, 'NO', 1, 0, 'C', true);
$pdf->Cell(70, 8, 'RINCIAN PEMBAYARAN', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'TAGIHAN (Rp)', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'DIBAYAR (Rp)', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'SISA (Rp)', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);

// Helper Fungsi Cetak Baris
function printRow($pdf, $no, $nama, $target, $bayar) {
    $sisa = $target - $bayar;
    if ($sisa < 0) $sisa = 0;
    
    $pdf->Cell(10, 8, $no, 1, 0, 'C');
    $pdf->Cell(70, 8, ' ' . $nama, 1, 0, 'L');
    $pdf->Cell(35, 8, number_format($target, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(35, 8, number_format($bayar, 0, ',', '.'), 1, 0, 'R');
    
    // Warnai sisa jika belum lunas
    if ($sisa > 0) $pdf->SetTextColor(220, 38, 38); // Merah
    $pdf->Cell(30, 8, number_format($sisa, 0, ',', '.'), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0); // Kembalikan Hitam
}

// Data Baris
printRow($pdf, 1, 'Pendaftaran', $target_pendaftaran, $data['bayar_pendaftaran']);
printRow($pdf, 2, 'Infaq Bangunan', $target_bangunan, $data['bayar_bangunan']);
printRow($pdf, 3, 'Infaq Kursi & Meja', $target_fasilitas, $data['bayar_fasilitas']);
printRow($pdf, 4, 'Kegiatan Tahunan', $target_tahunan, $data['bayar_tahunan']);
printRow($pdf, 5, 'Uang Bulanan (Asrama & Makan)', $target_bulanan, $data['bayar_bulanan']);
printRow($pdf, 6, 'Seragam Santri', $target_seragam, $data['bayar_seragam']);

// Footer Tabel (Total)
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(241, 245, 249); // Light Gray
$pdf->Cell(80, 8, 'TOTAL KESELURUHAN', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Rp ' . number_format($data['total_biaya'], 0, ',', '.'), 1, 0, 'R', true);
$pdf->SetTextColor(13, 161, 91); // Hijau untuk yang disetor
$pdf->Cell(35, 8, 'Rp ' . number_format($data['jumlah_dibayar'], 0, ',', '.'), 1, 0, 'R', true);

// Warna sisa tagihan akhir
if ($data['sisa_tagihan'] > 0) {
    $pdf->SetTextColor(220, 38, 38);
} else {
    $pdf->SetTextColor(0, 0, 0);
}
$pdf->Cell(30, 8, 'Rp ' . number_format($data['sisa_tagihan'], 0, ',', '.'), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0); // Reset

$pdf->Ln(5);

// --- STATUS & CATATAN ---
$pdf->SetFont('Arial', '', 10);
if ($data['status_pembayaran'] == 'Lunas') {
    $pdf->SetTextColor(22, 163, 74); // Hijau
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, 'STATUS: LUNAS', 0, 1, 'L');
} else {
    $pdf->SetTextColor(220, 38, 38); // Merah
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, 'STATUS: BELUM LUNAS', 0, 1, 'L');
}
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'I', 10);
if (!empty($data['catatan_kesepakatan'])) {
    $pdf->MultiCell(0, 6, 'Catatan: ' . $data['catatan_kesepakatan'], 0, 'L');
}

// --- TANDA TANGAN PENERIMA (KASIR) ---
$pdf->Ln(15);
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(130);
$pdf->Cell(60, 6, 'Kasui, ' . $tanggal_cetak, 0, 1, 'C');

$pdf->SetX(130);
$pdf->Cell(60, 6, 'Diterima Oleh,', 0, 1, 'C');

$pdf->Ln(20); // Ruang Tanda Tangan

$pdf->SetX(130);
$pdf->SetFont('Arial', 'BU', 11);
$pdf->Cell(60, 6, strtoupper($nama_kasir), 0, 1, 'C');
$pdf->SetX(130);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 5, 'Petugas / ' . $role_kasir, 0, 1, 'C');

// --- PENGINGAT DI BAWAH ---
$pdf->SetY(265);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, '*Kuitansi ini dicetak otomatis oleh Sistem Informasi PSB - Pondok Pesantren Raudlatul Muta\'allimin.', 0, 1, 'C');
$pdf->Cell(0, 5, '*Jika terjadi kesalahan, Harap konfirmasi ke petugas, 0821-7586-7914 (Ust. Kuswara., M.Pd.I).', 0, 1, 'C');
$pdf->Cell(0, 5, '*Harap simpan bukti pembayaran ini dengan baik sebagai dokumen yang sah, .', 0, 1, 'C');

// Output
$pdf->Output('I', 'Kuitansi_'.$data['no_pendaftaran'].'.pdf');
?>