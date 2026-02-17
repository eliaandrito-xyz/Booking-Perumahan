<?php
require_once 'config/database.php';
require_once 'config/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $db = new Database();
        
        try {
            $stmt = $db->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if ($user['status'] !== 'active') {
                    $error = 'Akun Anda tidak aktif. Silakan hubungi administrator.';
                } elseif (password_verify($password, $user['password'])) {

                    // Simpan session
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role']      = $user['role'];

                    // Redirect berdasarkan role
                    if ($user['role'] === 'admin') {
                        header('Location: admin/index.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit();

                } else {
                    $error = 'Username atau password salah';
                }
            } else {
                $error = 'Username atau password salah';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

// Jika sudah login, arahkan sesuai role
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistem Informasi Perumahan Kota Kupang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Anti autofill -->
    <meta http-equiv="Cache-Control" content="no-store">
    <meta http-equiv="Pragma" content="no-cache">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">

            <!-- LOGO BULAT DARI KAMU -->
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

            <h1>Selamat Datang</h1>
            <p>Masuk ke Sistem Informasi Perumahan Kupang</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">

            <div class="form-group">
                <label>Username atau Email</label>
                <input 
                    type="text"
                    name="username"
                    id="username"
                    class="form-control"
                    autocomplete="off"
                    placeholder="Masukkan username atau email"
                    required
                >
            </div>

            <div class="form-group" style="position:relative;">
                <label>Password</label>
                <input 
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    autocomplete="new-password"
                    placeholder="Masukkan password"
                    required
                >
                <span 
                    onclick="togglePassword()" 
                    style="position:absolute;right:12px;top:38px;cursor:pointer;"
                    title="Lihat password"
                >
                    👁️
                </span>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:1rem;">
                Masuk
            </button>
        </form>

        <!-- REGISTER -->
        <div style="text-align:center; margin-top:1.5rem; border-top:1px solid #e0e0e0; padding-top:1.5rem;">
            <p>Belum punya akun?</p>
            <a href="register.php" class="btn btn-secondary" style="width:100%; padding:1rem;">
                Daftar Sekarang
            </a>
        </div>

        <div style="text-align:center; margin-top:1rem;">
            <a href="index.php">← Kembali ke Beranda</a>
        </div>

    </div>
</div>

<script>
    // Kosongkan input saat load (hindari autofill)
    window.onload = () => {
        document.getElementById("username").value = "";
        document.getElementById("password").value = "";
    }

    function togglePassword() {
        const pass = document.getElementById("password");
        pass.type = pass.type === "password" ? "text" : "password";
    }
</script>

</body>
</html>
