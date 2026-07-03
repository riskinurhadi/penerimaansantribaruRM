<?php
require_once 'config.php';
// Memanggil library FPDF dari dalam folder admin
require('admin/fpdf/fpdf.php');

$conn->set_charset("utf8mb4");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Peringatan: ID Pendaftaran tidak valid!");
}
$id_pendaftar = intval($_GET['id']);

// Ambil Data Utama Calon Santri
$query = "
    SELECT p.no_pendaftaran, p.status_masuk, p.pilihan_sekolah, p.program_takhosush, p.created_at,
           d.nama_lengkap, d.nisn, d.nik, d.tempat_lahir, d.tanggal_lahir, d.jenis_kelamin
    FROM pendaftaran p
    LEFT JOIN data_diri d ON p.id = d.pendaftaran_id
    WHERE p.id = $id_pendaftar
";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    die("Peringatan: Data pendaftar tidak ditemukan di sistem.");
}
$data = $result->fetch_assoc();

// Fungsi Format Tanggal Indo
function tgl_indo($tanggal){
    if(empty($tanggal)) return '-';
	$bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
	$pecahkan = explode('-', $tanggal);
	return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

// ==========================================
// PEMBUATAN PDF DENGAN FPDF (Ukuran A4)
// ==========================================
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 20);

// --- KOP SURAT ---
$header_path = 'admin/header/header_keuangan.png';
if (file_exists($header_path)) {
    $pdf->Image($header_path, 15, 15, 180);
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

// --- JUDUL BUKTI PENDAFTARAN ---
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'TANDA BUKTI PENDAFTARAN SANTRI BARU', 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 5, 'Tahun Ajaran 2026 / 2027', 0, 1, 'C');
$pdf->Ln(5);

// --- A. IDENTITAS SANTRI ---
$pdf->SetFillColor(241, 245, 249); // Abu-abu Terang
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, ' A. IDENTITAS CALON SANTRI', 0, 1, 'L', true);
$pdf->Ln(3);

function printRow($pdf, $label, $value) {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 6, $label, 0, 0);
    $pdf->Cell(5, 6, ':', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, $value, 0, 1);
}

printRow($pdf, 'Nomor Pendaftaran', $data['no_pendaftaran']);
printRow($pdf, 'Nama Lengkap', strtoupper($data['nama_lengkap']));
printRow($pdf, 'Tempat, Tgl Lahir', $data['tempat_lahir'] . ', ' . tgl_indo($data['tanggal_lahir']));
printRow($pdf, 'Jenis Kelamin', $data['jenis_kelamin']);
printRow($pdf, 'Jenjang Tujuan', $data['pilihan_sekolah']);
printRow($pdf, 'Program Takhosush', $data['program_takhosush'] == 'Ya' ? 'Mengikuti' : 'Tidak (Reguler)');
printRow($pdf, 'Waktu Mendaftar', date('d/m/Y H:i', strtotime($data['created_at'])) . ' WIB');
$pdf->Ln(8);

// --- B. KARTU KENDALI ADMINISTRASI ---
$pdf->SetFillColor(241, 245, 249);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, ' B. KARTU KENDALI ADMINISTRASI PANITIA', 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 6, "Formulir ini merupakan lembar pengesahan. Wajib dibawa oleh calon santri dan dimintakan tanda tangan/stempel kepada masing-masing pos panitia pada saat kedatangan di Pondok Pesantren.");
$pdf->Ln(4);

// Header Tabel Kendali
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 200, 200); // Abu-abu gelap untuk Header
$pdf->Cell(10, 10, 'NO', 1, 0, 'C', true);
$pdf->Cell(45, 10, 'POS PANITIA', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'KETERANGAN', 1, 0, 'C', true);
$pdf->Cell(55, 10, 'PARAF PETUGAS', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$rowHeight = 18; // Tinggi kolom cukup besar untuk tanda tangan

// Pos 1: TU / Pendaftaran
$pdf->Cell(10, $rowHeight, '1', 1, 0, 'C');
$pdf->Cell(45, $rowHeight, ' Pos Pendaftaran', 1, 0, 'L');
$pdf->Cell(60, $rowHeight, ' Verifikasi Pendaftaran', 1, 0, 'L');
$pdf->Cell(55, $rowHeight, '', 1, 1, 'C');

// Pos 2: Kesehatan
$pdf->Cell(10, $rowHeight, '2', 1, 0, 'C');
$pdf->Cell(45, $rowHeight, ' Pos Kesehatan', 1, 0, 'L');
$pdf->Cell(60, $rowHeight, ' Cek Fisik & Rekam Medis', 1, 0, 'L');
$pdf->Cell(55, $rowHeight, '', 1, 1, 'C');

// Pos 3: Keuangan
$pdf->Cell(10, $rowHeight, '3', 1, 0, 'C');
$pdf->Cell(45, $rowHeight, ' Bagian Bendahara', 1, 0, 'L');
$pdf->Cell(60, $rowHeight, ' Pelunasan / Cicilan Biaya', 1, 0, 'L');
$pdf->Cell(55, $rowHeight, '', 1, 1, 'C');

// Pos 4: Keamanan / Kesantrian
$pdf->Cell(10, $rowHeight, '4', 1, 0, 'C');
$pdf->Cell(45, $rowHeight, ' Bagian Keamanan', 1, 0, 'L');
$pdf->Cell(60, $rowHeight, ' Pengesahan Surat Perjanjian', 1, 0, 'L');
$pdf->Cell(55, $rowHeight, '', 1, 1, 'C');

// --- CATATAN PERINGATAN BAWAH ---
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'Catatan Penting Calon Santri:', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(5, 6, '-', 0, 0); $pdf->Cell(0, 6, 'Harap simpan lembar Tanda Bukti Pendaftaran ini dengan baik (tidak boleh hilang/rusak).', 0, 1);
$pdf->Cell(5, 6, '-', 0, 0); $pdf->Cell(0, 6, 'Semua berkas asli (KK, Akta Kelahiran, dll) diharap dibawa saat menyerahkan lembar ini.', 0, 1);
$pdf->Cell(5, 6, '-', 0, 0); $pdf->Cell(0, 6, 'Bagi yang berkendala, silakan tunjukkan lembar ini ke petugas panitia untuk meminta arahan.', 0, 1);

// --- PENGINGAT DI BAWAH ---
$pdf->SetY(272);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, '*Kuitansi ini dicetak otomatis oleh Sistem Informasi PSB - Pondok Pesantren Raudlatul Muta\'allimin.', 0, 1, 'C');


// Output PDF (Langsung ditampilkan di browser / siap diprint)
$pdf->Output('I', 'Bukti_Pendaftaran_'.$data['no_pendaftaran'].'.pdf');
?>