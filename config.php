<?php
// Informasi koneksi database
$host = "localhost";
$username = "psb";
$password = "Aloevera21.";
$database = "psb";

// Membuat koneksi
$conn = new mysqli($host, $username, $password, $database);

// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
// Jika berhasil, tidak akan menampilkan apa-apa
?>