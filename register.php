<?php
require_once 'config/database.php';
require_once 'config/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Semua field wajib diisi';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok';
    } else {
        $db = new Database();
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Username atau email sudah digunakan';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'user', 'active')");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $phone);
                
                if ($stmt->execute()) {
                    $success = 'Pendaftaran berhasil! Silakan login dengan akun Anda.';
                    $_POST = [];
                } else {
                    $error = 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar - Sistem Informasi Perumahan Kota Kupang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Anti autofill -->
    <meta http-equiv="Cache-Control" content="no-store">
    <meta http-equiv="Pragma" content="no-cache">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-container">
    <div class="login-card" style="max-width: 520px;">
        <div class="login-header">

            <!-- LOGO BULAT -->
            <div style="
                width: 90px;
                height: 90px;
                margin: 0 auto 1rem;
                border-radius: 50%;
                overflow: hidden;
                border: 3px solid #f4c430;
                box-shadow: 0 5px 15px rgba(0,0,0,.15);
            ">
                <img 
                    src="assets/img/logo.png" 
                    alt="Logo Gracia Jaya Permai"
                    style="width:100%;height:100%;object-fit:cover;"
                >
            </div>

            <h1>Daftar Akun</h1>
            <p>Bergabung dengan Sistem Informasi Perumahan Kupang</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <div style="margin-top:1rem;">
                    <a href="login.php" class="btn btn-primary">Login Sekarang</a>
                </div>
            </div>
        <?php else: ?>

        <form method="POST" id="registerForm" autocomplete="off">

            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" class="form-control" required placeholder="Masukkan nama lengkap">
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required minlength="3" placeholder="Min. 3 karakter">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required placeholder="Masukkan email">
            </div>

            <div class="form-group">
                <label>No. Telepon</label>
                <input type="tel" name="phone" class="form-control" placeholder="Opsional">
            </div>

            <!-- PASSWORD -->
            <div class="form-group" style="position:relative;">
                <label>Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    autocomplete="new-password"
                    minlength="6"
                    required
                    placeholder="Min. 6 karakter"
                >
                <span onclick="togglePass('password')" style="position:absolute;right:12px;top:38px;cursor:pointer;">👁️</span>
            </div>

            <!-- CONFIRM PASSWORD -->
            <div class="form-group" style="position:relative;">
                <label>Konfirmasi Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-control" 
                    autocomplete="new-password"
                    required
                    placeholder="Ulangi password"
                >
                <span onclick="togglePass('confirm_password')" style="position:absolute;right:12px;top:38px;cursor:pointer;">👁️</span>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:1rem;">
                Daftar Sekarang
            </button>
        </form>

        <?php endif; ?>

        <div style="text-align:center;margin-top:1.5rem;border-top:1px solid #e0e0e0;padding-top:1.5rem;">
            <p>Sudah punya akun?</p>
            <a href="login.php" class="btn btn-secondary" style="width:100%;">Login</a>
        </div>

        <div style="text-align:center;margin-top:1rem;">
            <a href="index.php">← Kembali ke Beranda</a>
        </div>
    </div>
</div>

<script>
    function togglePass(id) {
        const el = document.getElementById(id);
        el.type = el.type === "password" ? "text" : "password";
    }
</script>

</body>
</html>
