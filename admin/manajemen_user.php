<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';
$conn->set_charset("utf8mb4");

$nama_lengkap_admin = $_SESSION['nama_lengkap'];
$role = $_SESSION['role'];
$current_user_id = $_SESSION['user_id'];

// Keamanan Ekstra: Hanya Developer dan Super Admin yang bisa mengelola User
if ($role != 'Developer' && $role != 'Super Admin') {
    die("Akses ditolak! Anda tidak memiliki izin untuk mengakses halaman Manajemen User.");
}

$status_pesan = '';

// --- PROSES HAPUS USER ---
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    
    // Mencegah user menghapus akunnya sendiri
    if ($id_hapus == $current_user_id) {
        $status_pesan = 'hapus_sendiri';
    } else {
        if ($conn->query("DELETE FROM users WHERE id = $id_hapus")) {
            $status_pesan = 'hapus_sukses';
        } else {
            $status_pesan = 'gagal';
        }
    }
}

// --- PROSES TAMBAH / EDIT USER JIKA FORM DISUBMIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_type'])) {
    $user_id = intval($_POST['user_id']);
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $uname = mysqli_real_escape_string($conn, trim($_POST['username']));
    $role_input = mysqli_real_escape_string($conn, trim($_POST['role_admin']));
    $password_input = trim($_POST['password']); // Bisa kosong jika sedang edit dan tidak ingin ganti password

    // Cek apakah username sudah dipakai oleh orang lain
    $cek_uname = $conn->query("SELECT id FROM users WHERE username = '$uname' AND id != $user_id");
    if ($cek_uname && $cek_uname->num_rows > 0) {
        $status_pesan = 'username_terpakai';
    } else {
        if ($user_id > 0) {
            // PROSES UPDATE
            if (!empty($password_input)) {
                // Update dengan ganti password
                $pass_hash = password_hash($password_input, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET nama_lengkap='$nama', username='$uname', role='$role_input', password='$pass_hash' WHERE id=$user_id";
            } else {
                // Update tanpa ganti password
                $sql = "UPDATE users SET nama_lengkap='$nama', username='$uname', role='$role_input' WHERE id=$user_id";
            }
        } else {
            // PROSES INSERT (TAMBAH BARU)
            // Jika tambah baru, password wajib diisi. Kita hash menggunakan Bcrypt.
            $pass_hash = password_hash($password_input, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (nama_lengkap, username, password, role) VALUES ('$nama', '$uname', '$pass_hash', '$role_input')";
        }

        if ($conn->query($sql)) {
            $status_pesan = 'sukses';
        } else {
            $status_pesan = 'gagal';
        }
    }
}

// --- AMBIL DATA SELURUH USER ---
$query = "SELECT * FROM users ORDER BY id DESC";
$result = $conn->query($query);

// --- STATISTIK ---
$q_total = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$q_super = $conn->query("SELECT COUNT(*) as total FROM users WHERE role IN ('Super Admin', 'Developer')")->fetch_assoc()['total'];
$q_keuangan = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'Admin Keuangan'")->fetch_assoc()['total'];
$q_lainnya = $conn->query("SELECT COUNT(*) as total FROM users WHERE role NOT IN ('Super Admin', 'Developer', 'Admin Keuangan')")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - PSB RM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --primary-green: #0da15b; 
            --dark-green: #087d46;
            --bg-body: #f4f7fa;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-body); color: var(--text-dark); font-size: 0.9rem; }
        
        .topbar-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; margin-bottom: 25px; }
        .card-custom { background: #ffffff; border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 20px 25px; }

        /* Stat Cards */
        .stat-card { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); padding: 20px; display: flex; align-items: center; gap: 15px; transition: transform 0.3s; border: 1px solid rgba(0,0,0,0.03); }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 55px; height: 55px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        /* Table */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; border-top: none; white-space: nowrap; }
        .table-custom thead th:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table-custom thead th:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        .table-custom tbody td { padding: 12px 15px; vertical-align: middle; color: #334155; font-size: 0.85rem; font-weight: 500; border-bottom: 1px solid #f1f5f9; }

        /* Badges */
        .badge-role { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; display: inline-block; white-space: nowrap; text-align: center; border: 1px solid transparent; }
        .bg-dev { background-color: #1e293b; color: #ffffff; }
        .bg-super { background-color: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
        .bg-admin { background-color: #f1f5f9; color: var(--text-dark); border-color: #e2e8f0; }

        /* Buttons */
        .btn-solid-custom { background-color: var(--primary-green); color: #ffffff !important; font-weight: 500; border-radius: 50px; padding: 8px 20px; font-size: 0.85rem; transition: all 0.3s; border: none;}
        .btn-solid-custom:hover { background-color: var(--dark-green); }
        .btn-action-edit { background-color: #fef08a; color: #ca8a04; border: none; border-radius: 8px; padding: 6px 10px; font-size: 0.8rem; transition: 0.2s; margin-right: 4px;}
        .btn-action-edit:hover { background-color: #fde047; color: #a16207; }
        .btn-action-delete { background-color: #fee2e2; color: #dc2626; border: none; border-radius: 8px; padding: 6px 10px; font-size: 0.8rem; transition: 0.2s;}
        .btn-action-delete:hover { background-color: #fca5a5; color: #b91c1c; }

        /* DT Fix */
        div.dataTables_wrapper div.dataTables_length label { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        div.dataTables_wrapper div.dataTables_length select { border-radius: 8px; padding: 4px 30px 4px 10px; font-size: 0.85rem; width: auto; border: 1px solid #cbd5e1; }
        div.dataTables_wrapper div.dataTables_filter input { border-radius: 50px; padding: 4px 15px; font-size: 0.85rem; border: 1px solid #cbd5e1; }
        div.dataTables_wrapper div.dataTables_filter input:focus { border-color: var(--primary-green); outline: none; box-shadow: 0 0 0 0.25rem rgba(13, 161, 91, 0.25); }
        .page-item.active .page-link { background-color: var(--primary-green); border-color: var(--primary-green); }
    </style>
</head>
<body>

<div class="admin-wrapper" style="display: flex;">
    <?php include 'sidebar.php'; ?>

    <div class="main-content" style="flex-grow: 1; margin-left: 260px; padding: 30px; width: calc(100% - 260px);">
        
        <div class="topbar-card">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3 shadow-sm" id="sidebarToggle" style="border-radius: 10px;">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="fw-bold text-dark m-0">Pengaturan Sistem & User</h5>
            </div>
            
            <div class="d-none d-md-flex text-end align-items-center">
                <div class="me-3">
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_lengkap_admin) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($role) ?></div>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_lengkap_admin) ?>&background=1e293b&color=fff&rounded=true" width="40">
            </div>
        </div>

        <!-- Cards Statistik -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #dcfce7; color: #16a34a;">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_total ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Total Akun</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_super ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Super Admin</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fef3c7; color: #d97706;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_keuangan ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Admin Keuangan</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f1f5f9; color: #64748b;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $q_lainnya ?></h4>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Admin Lainnya</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="card-custom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <h5 class="fw-bold text-dark m-0" style="font-size: 1.1rem;">Daftar Administrator Sistem</h5>
                <button class="btn btn-solid-custom" onclick="bukaModalTambah()"><i class="fas fa-plus-circle me-2"></i> Tambah User Baru</button>
            </div>

            <div class="table-responsive">
                <table id="tabelUser" class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%">NO</th>
                            <th width="30%">NAMA LENGKAP</th>
                            <th width="20%">USERNAME LOGIN</th>
                            <th width="20%" class="text-center">HAK AKSES / ROLE</th>
                            <th width="15%" class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result && $result->num_rows > 0) {
                            $no = 1;
                            while ($row = $result->fetch_assoc()) {
                                
                                $badge_class = 'bg-admin';
                                if ($row['role'] == 'Developer') $badge_class = 'bg-dev';
                                if ($row['role'] == 'Super Admin') $badge_class = 'bg-super';
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['nama_lengkap']) ?> 
                                    <?= ($row['id'] == $current_user_id) ? '<span class="badge bg-success ms-1" style="font-size:0.65rem;">Anda</span>' : '' ?>
                                </td>
                                <td><i class="fas fa-at text-muted me-1"></i> <?= htmlspecialchars($row['username']) ?></td>
                                <td class="text-center"><span class="badge-role <?= $badge_class ?>"><?= htmlspecialchars($row['role']) ?></span></td>
                                <td class="text-center">
                                    <?php 
                                    // Cegah Super Admin mengedit akun Developer
                                    if ($role == 'Super Admin' && $row['role'] == 'Developer' && $row['id'] != $current_user_id) {
                                        echo '<span class="text-muted" style="font-size:0.75rem;"><i class="fas fa-lock"></i> Terkunci</span>';
                                    } else {
                                    ?>
                                        <button class="btn-action-edit" title="Edit Akun" 
                                            onclick="bukaModalEdit(<?= $row['id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>', '<?= addslashes($row['username']) ?>', '<?= $row['role'] ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if ($row['id'] != $current_user_id): // Jangan munculkan tombol hapus di akun sendiri ?>
                                            <button class="btn-action-delete" title="Hapus Akun" onclick="hapusUser(<?= $row['id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php 
                            }
                        } 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Form User -->
<div class="modal fade" id="modalUser" tabindex="-1" aria-labelledby="modalUserLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 15px; border: none;">
      <form action="" method="POST" id="formUser">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold text-dark" id="modalUserLabel"><i class="fas fa-user-plus text-primary-green me-2"></i> Tambah User Baru</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body pb-2">
            
            <input type="hidden" name="form_type" value="submit_user">
            <input type="hidden" name="user_id" id="user_id" value="0">

            <div class="mb-3">
                <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Nama Lengkap</label>
                <input type="text" class="form-control" name="nama_lengkap" id="nama_lengkap" required placeholder="Masukkan nama lengkap">
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Username Login</label>
                <input type="text" class="form-control" name="username" id="username" required placeholder="Buat username tanpa spasi">
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Hak Akses / Role</label>
                <select class="form-select" name="role_admin" id="role_admin" required>
                    <option value="">-- Pilih Role --</option>
                    <?php if($role == 'Developer'): ?>
                        <option value="Developer">Developer (Akses Mutlak)</option>
                    <?php endif; ?>
                    <option value="Super Admin">Super Admin (Akses Penuh)</option>
                    <option value="Admin Pendaftaran">Admin Pendaftaran (Tata Usaha)</option>
                    <option value="Admin Keuangan">Admin Keuangan</option>
                    <option value="Admin Kesehatan">Admin Kesehatan</option>
                    <option value="Admin Keamanan">Admin Keamanan (Keamanan/Disiplin)</option>
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label fw-medium text-dark" style="font-size: 0.85rem;">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control border-end-0" name="password" id="password" placeholder="Masukkan password">
                    <span class="input-group-text bg-transparent border-start-0 cursor-pointer" id="togglePassword" style="cursor: pointer;">
                        <i class="fas fa-eye-slash text-muted"></i>
                    </span>
                </div>
                <small class="text-muted mt-1 d-block" id="password_hint" style="font-size: 0.75rem;">Biarkan kosong jika tidak ingin mengubah password saat ini.</small>
            </div>

          </div>
          <div class="modal-footer border-top-0 pt-0 mt-3">
            <button type="button" class="btn text-muted" data-bs-dismiss="modal" style="font-weight: 500;">Batal</button>
            <button type="submit" class="btn text-white px-4" style="background-color: var(--primary-green); border-radius: 50px; font-weight: 500;"><i class="fas fa-save me-2"></i> Simpan Data</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        $('#tabelUser').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json', search: "_INPUT_", searchPlaceholder: "Cari admin..." },
            "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-end'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "pageLength": 10
        });

        // Toggle Sidebar Mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Toggle Password Visibilitas
        document.getElementById('togglePassword').addEventListener('click', function () {
            const pwdInput = document.getElementById('password');
            const type = pwdInput.getAttribute('type') === 'password' ? 'text' : 'password';
            pwdInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Handle Notifikasi SweetAlert
        <?php if ($status_pesan == 'sukses'): ?>
            Swal.fire({ icon: 'success', title: 'Tersimpan!', text: 'Data user berhasil diperbarui.', confirmButtonColor: '#0da15b', timer: 2000, showConfirmButton: false });
        <?php elseif ($status_pesan == 'username_terpakai'): ?>
            Swal.fire({ icon: 'warning', title: 'Gagal Menyimpan!', text: 'Username tersebut sudah dipakai oleh akun lain. Silakan gunakan username yang berbeda.', confirmButtonColor: '#d97706' });
        <?php elseif ($status_pesan == 'gagal'): ?>
            Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan sistem saat menyimpan data.', confirmButtonColor: '#ef4444' });
        <?php elseif ($status_pesan == 'hapus_sukses'): ?>
            Swal.fire({ icon: 'success', title: 'Dihapus!', text: 'Akun user berhasil dihapus permanen.', confirmButtonColor: '#0da15b', timer: 2000, showConfirmButton: false });
        <?php elseif ($status_pesan == 'hapus_sendiri'): ?>
            Swal.fire({ icon: 'error', title: 'Akses Ditolak!', text: 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login.', confirmButtonColor: '#ef4444' });
        <?php endif; ?>

        // Hilangkan param ?hapus= dari URL agar tidak ter-refresh hapus terus menerus
        if(window.location.search.includes('hapus=')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

    // FUNGSI TAMBAH USER BARU
    function bukaModalTambah() {
        document.getElementById('formUser').reset();
        document.getElementById('user_id').value = '0';
        document.getElementById('modalUserLabel').innerHTML = '<i class="fas fa-user-plus text-primary-green me-2"></i> Tambah User Baru';
        
        // Password wajib diisi saat tambah baru
        document.getElementById('password').setAttribute('required', 'required');
        document.getElementById('password_hint').innerText = "Password wajib diisi untuk keamanan akun.";
        document.getElementById('password_hint').classList.replace('text-muted', 'text-danger');

        new bootstrap.Modal(document.getElementById('modalUser')).show();
    }

    // FUNGSI EDIT USER
    function bukaModalEdit(id, nama, username, role) {
        document.getElementById('formUser').reset();
        document.getElementById('user_id').value = id;
        document.getElementById('nama_lengkap').value = nama;
        document.getElementById('username').value = username;
        document.getElementById('role_admin').value = role;
        
        document.getElementById('modalUserLabel').innerHTML = '<i class="fas fa-user-edit text-primary-green me-2"></i> Edit Data User';
        
        // Password opsional saat edit
        document.getElementById('password').removeAttribute('required');
        document.getElementById('password_hint').innerText = "Biarkan kosong jika tidak ingin mengubah password saat ini.";
        document.getElementById('password_hint').classList.replace('text-danger', 'text-muted');

        new bootstrap.Modal(document.getElementById('modalUser')).show();
    }

    // FUNGSI HAPUS USER
    function hapusUser(id, nama) {
        Swal.fire({
            title: 'Hapus Akun Ini?',
            html: `Apakah Anda yakin ingin menghapus akses login untuk <b>${nama}</b>?<br>Aksi ini tidak dapat dibatalkan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#cbd5e1',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: '<span class="text-dark">Batal</span>',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `manajemen_user.php?hapus=${id}`;
            }
        });
    }
</script>

</body>
</html>