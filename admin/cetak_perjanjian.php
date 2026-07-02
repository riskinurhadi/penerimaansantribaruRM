<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Akses ditolak.");
}

require_once '../config.php';

// MEMANGGIL LIBRARY FPDF
// Pastikan folder fpdf sudah ada di dalam folder admin
require('fpdf/fpdf.php'); 

$conn->set_charset("utf8mb4");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Pendaftaran tidak valid!");
}
$id_pendaftar = intval($_GET['id']);

// --- QUERY DATA LENGKAP ---
$q_utama = $conn->query("
    SELECT p.*, d.*, k.*, a.* FROM pendaftaran p 
    JOIN data_diri d ON p.id = d.pendaftaran_id 
    JOIN data_keluarga k ON p.id = k.pendaftaran_id 
    JOIN data_alamat a ON p.id = a.pendaftaran_id 
    WHERE p.id = $id_pendaftar
");

if ($q_utama->num_rows == 0) die("Data tidak ditemukan.");
$data = $q_utama->fetch_assoc();

// --- LOGIKA CERDAS PENGISIAN FORM ---
function tgl_indo($tanggal){
    if(empty($tanggal)) return '-';
	$bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
	$pecahkan = explode('-', $tanggal);
	return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

// Menghitung Umur Santri
$tgl_lahir_santri = new DateTime($data['tanggal_lahir']);
$sekarang = new DateTime('today');
$umur_santri = $tgl_lahir_santri->diff($sekarang)->y;

// Menentukan Identitas Orang Tua / Wali
$ortu_nama = ''; $ortu_ttl = ''; $ortu_umur = ''; $ortu_pekerjaan = '';

if (!empty($data['wali_nama'])) {
    $ortu_nama = $data['wali_nama'] . ' (Wali)';
    $ortu_ttl = '-';
    $ortu_umur = '-';
    $ortu_pekerjaan = $data['wali_pekerjaan'];
} elseif ($data['ayah_status'] == 'Masih Hidup') {
    $ortu_nama = $data['ayah_nama'];
    $ortu_ttl = $data['ayah_tempat_lahir'] . ', ' . tgl_indo($data['ayah_tanggal_lahir']);
    $ortu_umur = !empty($data['ayah_tanggal_lahir']) ? (new DateTime($data['ayah_tanggal_lahir']))->diff($sekarang)->y . ' Tahun' : '-';
    $ortu_pekerjaan = $data['ayah_pekerjaan'];
} else {
    $ortu_nama = $data['ibu_nama'];
    $ortu_ttl = $data['ibu_tempat_lahir'] . ', ' . tgl_indo($data['ibu_tanggal_lahir']);
    $ortu_umur = !empty($data['ibu_tanggal_lahir']) ? (new DateTime($data['ibu_tanggal_lahir']))->diff($sekarang)->y . ' Tahun' : '-';
    $ortu_pekerjaan = $data['ibu_pekerjaan'];
}

$alamat_lengkap = $data['alamat_lengkap'] . ' RT ' . $data['rt'] . '/RW ' . $data['rw'] . ', Ds. ' . $data['desa_kelurahan'] . ', Kec. ' . $data['kecamatan'] . ', Kab. ' . $data['kota_kabupaten'] . ', ' . $data['provinsi'];

// ==========================================
// PEMBUATAN PDF DENGAN FPDF
// ==========================================
class PDF extends FPDF {
    // REVISI: Menggunakan Indentasi Gantung (Hanging Indent) Mutlak untuk Poin List
    function printList($number, $text) {
        // Jika sisa halaman kurang dari ruang baris, tambah halaman baru otomatis
        if($this->GetY() > 300) { $this->AddPage(); }
        
        $num_width = 8; // Lebar jarak indentasi angka
        $original_lMargin = $this->lMargin; // Menyimpan margin kiri asli dokumen (25)
        $current_y = $this->GetY();
        
        // 1. Cetak Angka (Contoh: "1.")
        $this->SetXY($original_lMargin, $current_y);
        $this->Cell($num_width, 6, $number . '.', 0, 0, 'L');
        
        // 2. Set Margin Kiri Baru (Agar teks baris ke-2 dan seterusnya tidak menjorok melipat ke kiri)
        $this->SetLeftMargin($original_lMargin + $num_width);
        
        // 3. Set Posisi Kursor untuk Teks Baris Pertama
        $this->SetXY($original_lMargin + $num_width, $current_y);
        
        // 4. Cetak Paragraf (Format rata Kanan-Kiri)
        $this->MultiCell(0, 6, $text, 0, 'J');
        
        // 5. Kembalikan Margin Kiri ke awal (25)
        $this->SetLeftMargin($original_lMargin);
    }
}

// Inisialisasi Ukuran Kertas F4 / Folio (215 x 330 mm)
$pdf = new PDF('P', 'mm', array(215, 330));
$pdf->AddPage();

// Margin Diperbesar (Kiri: 25, Atas: 20, Kanan: 25)
$pdf->SetMargins(25, 20, 25);
$pdf->SetAutoPageBreak(true, 20);

// --- REVISI 1: KOP SURAT MENGGUNAKAN GAMBAR HEADER ---
$header_path = 'header/header.png';
if (file_exists($header_path)) {
    // Memasukkan gambar, (X=0, Y=0, Lebar=215mm agar full margin ke margin kertas)
    $pdf->Image($header_path,  0,  0, 215);
    // Menggeser posisi Y ke bawah gambar agar teks tidak bertumpuk
    $pdf->SetY(60); 
} else {
    // Teks Fallback jika gambar header tidak ditemukan di direktori
    $pdf->SetFont('Times', 'B', 14);
    $pdf->Cell(0, 6, 'PONDOK PESANTREN', 0, 1, 'C');
    $pdf->SetFont('Times', 'B', 18);
    $pdf->SetTextColor(0, 100, 0); 
    $pdf->Cell(0, 8, "RAUDLATUL MUTA'ALLIMIN", 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell(0, 5, 'JAYA TINGGI - KASUI - WAY KANAN - LAMPUNG', 0, 1, 'C');
    $pdf->SetLineWidth(0.8);
    // Garis disesuaikan dengan margin 25 ke 190
    $pdf->Line(25, $pdf->GetY() + 2, 190, $pdf->GetY() + 2);
    $pdf->Ln(20);
}

// --- IDENTITAS ORANG TUA ---
$pdf->Ln(20);
$pdf->SetFont('Times', '', 12);
$pdf->Cell(0, 6, 'Yang bertanda tangan di bawah ini adalah saya :', 0, 1, 'L');

$pdf->Cell(45, 6, 'Nama', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); 
$pdf->SetFont('Times', 'B', 12); $pdf->Cell(0, 6, strtoupper($ortu_nama), 0, 1); 
$pdf->SetFont('Times', '', 12);

$pdf->Cell(45, 6, 'Tempat, Tgl Lahir', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $ortu_ttl, 0, 1);
$pdf->Cell(45, 6, 'Umur', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $ortu_umur, 0, 1);
$pdf->Cell(45, 6, 'Pekerjaan', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $ortu_pekerjaan, 0, 1);

$pdf->Cell(45, 6, 'Alamat', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); 
$left_margin = $pdf->GetX(); $pdf->SetLeftMargin($left_margin);
$pdf->MultiCell(0, 6, $alamat_lengkap, 0, 'J');
$pdf->SetLeftMargin(25); // Kembalikan ke margin kiri baru (25)
$pdf->Ln(3);

// --- IDENTITAS SANTRI ---
$pdf->Cell(0, 6, 'Adalah Orang tua/Wali dari santri sebagai berikut:', 0, 1, 'L');

$pdf->Cell(45, 6, 'Nama', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); 
$pdf->SetFont('Times', 'B', 12); $pdf->Cell(0, 6, strtoupper($data['nama_lengkap']), 0, 1); 
$pdf->SetFont('Times', '', 12);

$pdf->Cell(45, 6, 'Tempat, Tgl Lahir', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $data['tempat_lahir'] . ', ' . tgl_indo($data['tanggal_lahir']), 0, 1);
$pdf->Cell(45, 6, 'Umur', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); $pdf->Cell(0, 6, $umur_santri . ' Tahun', 0, 1);

$pdf->Cell(45, 6, 'Alamat', 0, 0); $pdf->Cell(5, 6, ':', 0, 0); 
$left_margin = $pdf->GetX(); $pdf->SetLeftMargin($left_margin);
$pdf->MultiCell(0, 6, $alamat_lengkap, 0, 'J');
$pdf->SetLeftMargin(25); // Kembalikan ke margin kiri baru (25)
$pdf->Ln(5);

// --- PEMBUKAAN ---
$pdf->MultiCell(0, 6, "Sehubungan dengan diterimanya anak saya tersebut sebagai Santri di Pondok Pesantren Raudlatul Muta'allimin Kasui, maka dengan ini saya, untuk kepentingan anak saya tersebut di atas, menyatakan persetujuan, perjanjian dan kesanggupan saya untuk memenuhi ketentuan - ketentuan sebagai berikut ini:", 0, 'J');

// --- PASAL 1 ---
$pdf->Ln(2);
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 10, 'PASAL I (SATU)', 0, 1, 'C');
$pdf->SetFont('Times', '', 12);
$pdf->printList('1', "Saya percaya dan menyerahkan anak saya secara penuh kepada pihak Pondok Pesantren Raudlatul Muta'allimin Kasui untuk dididik sesuai dengan system pendidikan, kurikulum, dan tata cara apapun bentuknya yang berlaku di dan/atau dianut oleh pihak Pesantren.");
$pdf->printList('2', "Saya dan/atau anak saya akan menjunjung tinggi, mematuhi dan mengikuti segala macam disiplin, ketentuan, peraturan dan kebijakan yang berlaku di Pondok - Pesantren Raudlatul Mutaallimin Kasui, apapun bentuk dan jenisnya.");
$pdf->printList('3', "Tidak akan mencampuri sistem Pendidikan dan Pengajaran maupun urusan manajemen dan administrasi yang telah ditetapkan oleh Pimpinan Pondok Pesantren Raudlatul Muta'allimin.");

// --- PASAL 2 ---
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 10, 'PASAL II (DUA)', 0, 1, 'C');
$pdf->SetFont('Times', '', 12);
$pdf->printList('1', "Saya akan memenuhi kewajiban saya untuk membayar dan/atau melunasi pada waktunya segala komponen biaya pendidikan, baik biaya rutin bulanan (Syahriyyah) maupun yang tidak rutin yaitu biaya tahunan ('Amiyah), yang ditetapkan oleh pihak Pon-Pes. Raudlatul Muta'allimin Kasui termasuk di dalamnya penyesuai dan/atau kenaikan biaya yang ditetapkan antar waktunya.");
$pdf->printList('2', "Jika saya dalam jangka waktu yang diberikan pihak pesantren saya tidak dapat menyelesaikanya, maka saya siap menerima resiko apapun terhadap ketetapan yang diputuskan oleh pihak Pesantren.");

// --- PASAL 3 ---
$pdf->Ln(3);
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 10, 'PASAL III (TIGA)', 0, 1, 'C');
$pdf->SetFont('Times', '', 12);
$pdf->MultiCell(0, 6, "Saya akan menerima dengan ikhlas dan tidak akan melakukan tuntutan dalam bentuk apapun, jika anak saya diberikan peringatan dan perjanjian, diberikan sanksi dan/atau diberhentikan langsung dari statusnya sebagai santri di Pon.Pes Raudlatul Muta'allimin Kasui, dan/atau dilaporkan kepada pihak yang berwajib untuk diproses sesuai dengan ketentuan hukum yang berlaku di Negara kita NKRI ini, karena terlibat atau melakukan perbuatan serta tindakan sebagai berikut:", 0, 'J');

$pdf->printList('1', "Menyimpan, mengkonsumsi, memperjualbelikan, dan/atau mengedarkan benda-benda memabukkan, minuman keras, obat-obatan terlarang, dan/atau benda-benda lainya yang termasuk dalam kategori NARKOTIKA dan ZAT ADIKTIF.");
$pdf->printList('2', "Menyimpan, memperlihatkan, memperjualbelikan, dan/atau mengedarkan benda-benda atau segala hal yang mengandung unsur-unsur PORNOGRAFI.");
$pdf->printList('3', "Mengambil atau merampas dengan maksud untuk memiliki barang-barang pihak lain yang bukan hak miliknya, baik dengan tindakan kekerasan, pemaksaan, ancaman ataupun tidak (bersembunyi-sembunyi/mencuri).");
$pdf->printList('4', "Berzina dan berbuat tidak senonoh/cabul dengan lawan jenis atau sesama jenis, termasuk menzinai, mencabuli, melihat aurat orang lain apa lagi lawan jenisnya.");
$pdf->printList('5', "Berjudi dan mengundi nasib.");
$pdf->printList('6', "Berkelahi langsung, terlibat perkelahian atau melakukan tindakan menyakiti orang lain yang membahayakan dan menyebabkan terluka atau bahkan meninggalnya orang lain.");
$pdf->printList('7', "Menunjukkan sikap permusuhan atau penghinaan dalam bentuk apapun juga terhadap orang lain, terlebih lagi terhadap institusi guru, Ustadz dan Ustadzah Pengasuh Pon-Pes. Raudlatul Muta'allimin Kasui.");
$pdf->printList('8', "Membuat keonaran, keributan atau perbuatan apapun juga yang menimbulkan ketidak nyamanan dan ketidaktenangan di lingkungan Pon-Pes. Raudlatul Muta'allimin dan lingkungan masyarakat sekitar Pesantren.");
$pdf->printList('9', "Melakukan pelanggaran disiplin atau peraturan lainya, baik sama ataupun tidak, secara berulang kali dalam rentang waktu yang berdekatan sesuai waktu yang ditetapkan pihak Pesantren.");
$pdf->printList('10', "Tidak melibatkan pihak luar Pondok (Aparat Kepolisian, Aparat Hukum, dsb.) dalam menyelesaikan urusan dengan Pondok Pesantren Raudlatul Muta'allimin.");
$pdf->printList('11', "Keluar Komplek atau minggat tanpa izin kepada pihak pesantren. Apabila hal itu terjadi, maka pihak pesantren tidak bertanggung jawab.");

// --- PASAL 4 ---
$pdf->Ln(5);
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 10, 'PASAL IV (EMPAT)', 0, 1, 'C');
$pdf->SetFont('Times', '', 12);
$pdf->MultiCell(0, 6, "Saya memberikan kuasa penuh kepada pihak Pondok - Pesantren Raudlatul Muta'allimin Kasui untuk menyita sekaligus mengikhlaskan dan menghibahkanya kepada pihak Pesantren, dan selanjutnya dimanfaatkan untuk kepentingan Pesantren dan umum, jika anak saya diketahui memiliki atau menyimpan dilingkungan Pesantren, barang-barang sebagai berikut:", 0, 'J');
$pdf->printList('1', "Alat-alat elektronik dan/atau alat-alat komunikasi dalam bentuk apapun dan macamnya.");
$pdf->printList('2', "Membawa, menyimpan, dan memperjualbelikan atau mengedarkan SENJATA TAJAM, apapun bentuk dan jenisnya.");
$pdf->printList('3', "Perhiasan emas, perak, intan dan berlian atau barang-barang berharga lainya yang berlebihan dan barang yang dilarang untuk dibawa masuk ke dalam lingkungan Pesantren atau yang dibatasi jumlah maksimal kepemilikannya.");

// --- PENUTUP ---
$pdf->Ln(3);
$pdf->MultiCell(0, 6, "Demikian surat pernyataan dan perjanjian ini, saya buat dengan sesungguh hati dan sebenar-benarnya, dalam keadaan sadar dan tidak ada paksaan dari pihak manapun. Semoga dapat dijadikan sebagai pedoman bersama dan untuk kebaikan bersama pula.", 0, 'J');


// =========================================================
// AREA TANDA TANGAN DIBUAT IDENTIK DENGAN GAMBAR
// =========================================================
$pdf->Ln(10);

// Jika sisa ruang terlalu sempit untuk kotak tanda tangan, geser ke halaman baru
if($pdf->GetY() > 240) {
    $pdf->AddPage();
}

// 1. Teks Tengah (Tanggal & "Yang Menyatakan")
$pdf->Cell(0, 6, 'Kasui, ' . tgl_indo(date('Y-m-d')), 0, 1, 'C');
$pdf->Cell(0, 6, 'Yang menyatakan,', 0, 1, 'C');

// 2. Kotak Materai 10.000 di Tengah
$y_materai = $pdf->GetY() + 5;
$x_center = (215 - 26) / 2; // (Lebar kertas F4 215mm - Lebar Kotak 26mm) / 2
$pdf->Rect($x_center, $y_materai, 26, 14); 

$pdf->SetXY($x_center, $y_materai + 2);
$pdf->SetFont('Times', '', 8);
$pdf->Cell(26, 4, 'Materai', 0, 1, 'C');
$pdf->SetX($x_center);
$pdf->Cell(26, 4, '10.000', 0, 1, 'C');

// 3. Nama Santri & Orang Tua (Sejajar Kiri - Kanan di bawah Materai)
$pdf->SetY($y_materai + 20);
$pdf->SetFont('Times', 'BU', 12); // Bold Underline untuk Nama
$pdf->SetX(25); // Menyesuaikan dengan margin baru
$pdf->Cell(70, 6, strtoupper($data['nama_lengkap']), 0, 0, 'C');
$pdf->SetX(120); // Menyesuaikan dengan margin baru (25 + 70 + spacing)
$pdf->Cell(70, 6, strtoupper($ortu_nama), 0, 1, 'C');

// 4. Jabatan (Santri/Wali)
$pdf->SetFont('Times', '', 12);
$pdf->SetX(25); // Menyesuaikan dengan margin baru
$pdf->Cell(70, 6, 'Santri/Calon santri', 0, 0, 'C');
$pdf->SetX(120); // Menyesuaikan dengan margin baru
$pdf->Cell(70, 6, 'Orang tua/wali', 0, 1, 'C');

// 5. Teks Pengesahan di Tengah
$pdf->Ln(12);
$pdf->Cell(0, 6, 'Menyetujui dan mengesahkan,', 0, 1, 'C');

// 6. Nama KH. Marsudi
$pdf->Ln(20);
$pdf->SetFont('Times', 'BU', 12);
$pdf->Cell(0, 6, 'KH. M A R S U D I', 0, 1, 'C');
$pdf->SetFont('Times', '', 12);
$pdf->Cell(0, 6, 'Pembina Pon.Pes. RM', 0, 1, 'C');

// --- OUTPUT PDF ---
// Tipe 'I' untuk langsung menampilkan preview PDF di Browser
$pdf->Output('I', 'Surat_Perjanjian_'.$data['nama_lengkap'].'.pdf');
?>