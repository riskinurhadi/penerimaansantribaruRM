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
    SELECT p.no_pendaftaran, p.status_masuk, p.pilihan_sekolah, p.program_takhosush, p.created_at,
           d.nama_lengkap, d.nisn, d.nik, d.tempat_lahir, d.tanggal_lahir, d.jenis_kelamin, d.agama, d.hobi, d.anak_ke, d.jumlah_saudara, d.no_kip,
           a.alamat_lengkap, a.rt, a.rw, a.desa_kelurahan, a.kecamatan, a.kota_kabupaten, a.provinsi, a.kode_pos, a.no_whatsapp, a.email,
           k.ayah_nama, k.ayah_status, k.ayah_pekerjaan, k.ayah_no_hp,
           k.ibu_nama, k.ibu_status, k.ibu_pekerjaan, k.ibu_no_hp,
           k.wali_nama, k.wali_hubungan, k.wali_pekerjaan, k.wali_no_hp,
           s.nama_sekolah, s.tahun_lulus, s.npsn_sekolah
    FROM pendaftaran p
    LEFT JOIN data_diri d ON p.id = d.pendaftaran_id
    LEFT JOIN data_alamat a ON p.id = a.pendaftaran_id
    LEFT JOIN data_keluarga k ON p.id = k.pendaftaran_id
    LEFT JOIN sekolah_asal s ON p.id = s.pendaftaran_id
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

$tanggal_cetak = tgl_indo(date('Y-m-d'));
$admin_pencetak = $_SESSION['nama_lengkap'];

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
    $pdf->Image($header_path, 10, 5, 190);
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
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 8, 'BIODATA CALON SANTRI BARU', 0, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Nomor Pendaftaran: ' . $data['no_pendaftaran'], 0, 1, 'C');
$pdf->Ln(5);

// Fungsi Bantu untuk Cetak Baris
function printRow($pdf, $label, $value) {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, $label, 0, 0);
    $pdf->Cell(5, 6, ':', 0, 0);
    $pdf->MultiCell(0, 6, empty($value) ? '-' : $value, 0, 'L');
}

// Fungsi Bantu untuk Header Seksi
function printSection($pdf, $title) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 100, 0); // Warna Hijau Gelap
    $pdf->Cell(0, 7, $title, 'B', 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);
}

// --- A. INFORMASI PENDAFTARAN ---
printSection($pdf, 'A. INFORMASI PENDAFTARAN');
printRow($pdf, 'Tanggal Daftar', date('d/m/Y H:i', strtotime($data['created_at'])) . ' WIB');
printRow($pdf, 'Status Masuk', $data['status_masuk']);
printRow($pdf, 'Jenjang Pilihan', $data['pilihan_sekolah']);
printRow($pdf, 'Program Takhosush', $data['program_takhosush']);
$pdf->Ln(5);

// --- B. DATA DIRI SANTRI ---
printSection($pdf, 'B. DATA DIRI SANTRI');
printRow($pdf, 'Nama Lengkap', strtoupper($data['nama_lengkap']));
printRow($pdf, 'Nomor Induk (NIK/NISN)', $data['nik'] . ' / ' . $data['nisn']);
printRow($pdf, 'Tempat, Tgl Lahir', $data['tempat_lahir'] . ', ' . tgl_indo($data['tanggal_lahir']));
printRow($pdf, 'Jenis Kelamin', $data['jenis_kelamin']);
printRow($pdf, 'Agama', $data['agama']);
printRow($pdf, 'Anak Ke', $data['anak_ke'] . ' dari ' . $data['jumlah_saudara'] . ' bersaudara');
if (!empty($data['no_kip'])) { printRow($pdf, 'Nomor KIP', $data['no_kip']); }
$pdf->Ln(5);

// --- C. ALAMAT DOMISILI ---
printSection($pdf, 'C. ALAMAT DOMISILI LENGKAP');
$alamat = $data['alamat_lengkap'] . " RT " . $data['rt'] . "/RW " . $data['rw'] . "\nDs. " . $data['desa_kelurahan'] . ", Kec. " . $data['kecamatan'] . "\nKab. " . $data['kota_kabupaten'] . ", " . $data['provinsi'] . " " . $data['kode_pos'];
printRow($pdf, 'Alamat Tinggal', $alamat);
printRow($pdf, 'No. WhatsApp / HP', $data['no_whatsapp']);
printRow($pdf, 'Email', $data['email']);
$pdf->Ln(5);

// --- D. DATA SEKOLAH ASAL ---
printSection($pdf, 'D. SEKOLAH ASAL');
printRow($pdf, 'Nama Sekolah', $data['nama_sekolah']);
printRow($pdf, 'Tahun Lulus', $data['tahun_lulus']);
printRow($pdf, 'NPSN', $data['npsn_sekolah']);
$pdf->Ln(20);

// --- E. DATA ORANG TUA / WALI ---
printSection($pdf, 'E. DATA ORANG TUA / WALI');
// Ayah
$info_ayah = $data['ayah_nama'] . ' (' . $data['ayah_status'] . ')';
if($data['ayah_status'] == 'Masih Hidup') $info_ayah .= "\n" . $data['ayah_pekerjaan'] . " | HP: " . $data['ayah_no_hp'];
printRow($pdf, 'Identitas Ayah', $info_ayah);
$pdf->Ln(5);
// Ibu
$info_ibu = $data['ibu_nama'] . ' (' . $data['ibu_status'] . ')';
if($data['ibu_status'] == 'Masih Hidup') $info_ibu .= "\n" . $data['ibu_pekerjaan'] . " | HP: " . $data['ibu_no_hp'];
printRow($pdf, 'Identitas Ibu', $info_ibu);

// Wali (Jika Ada)
if (!empty($data['wali_nama'])) {
    $pdf->Ln(2);
    $info_wali = $data['wali_nama'] . ' (Hubungan: ' . $data['wali_hubungan'] . ')' . "\n" . $data['wali_pekerjaan'] . " | HP: " . $data['wali_no_hp'];
    printRow($pdf, 'Identitas Wali', $info_wali);
}

// --- TANDA TANGAN / PENGESAHAN ---
$pdf->Ln(10);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Data ini dicetak otomatis oleh sistem sebagai arsip sah Pendaftaran Santri Baru.', 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetX(120);
$pdf->Cell(70, 6, 'Kasui, ' . $tanggal_cetak, 0, 1, 'C');
$pdf->SetX(120);
$pdf->Cell(70, 6, 'Petugas / Admin Pendaftaran,', 0, 1, 'C');

$pdf->Ln(15); 

$pdf->SetX(120);
$pdf->SetFont('Arial', 'BU', 10);
$pdf->Cell(70, 6, strtoupper($admin_pencetak), 0, 1, 'C');
$pdf->SetX(120);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(70, 4, 'Panitia PSB', 0, 1, 'C');

// Output PDF
$pdf->Output('I', 'Biodata_'.$data['no_pendaftaran'].'.pdf');
?>