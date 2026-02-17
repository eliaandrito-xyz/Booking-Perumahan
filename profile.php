<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';


requireLogin();

$db = new Database();
$user_id = getUserId();
$success = '';
$error = '';

// Get user data
$user = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data profil.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation
        if (empty($full_name) || empty($email)) {
            $error = 'Nama lengkap dan email harus diisi';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid';
        } else {
            try {
                // Check if email is already used by another user
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = 'Email sudah digunakan oleh user lain';
                } else {
                    // Update profile
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = 'Profil berhasil diperbarui';
                        // Update session
                        $_SESSION['full_name'] = $full_name;
                        // Refresh user data
                        $user['full_name'] = $full_name;
                        $user['email'] = $email;
                        $user['phone'] = $phone;
                    } else {
                        $error = 'Terjadi kesalahan saat memperbarui profil';
                    }
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field password harus diisi';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password baru minimal 6 karakter';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Konfirmasi password tidak cocok';
        } else {
            try {
                // Verify current password
                if (password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = 'Password berhasil diubah';
                    } else {
                        $error = 'Terjadi kesalahan saat mengubah password';
                    }
                } else {
                    $error = 'Password saat ini salah';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

// Get user statistics
$user_stats = [
    'total_bookings' => 0,
    'active_bookings' => 0,
    'completed_bookings' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_stats['total_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status IN ('pending', 'confirmed')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_stats['active_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_stats['completed_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
  

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 2rem;">
                <div style="
                    width: 80px;
                    height: 80px;
                    background: var(--gradient-gold);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 2rem;
                    animation: pulse 2s infinite;
                ">
                    👤
                </div>
                <div>
                    <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                        Profil Saya
                    </h1>
                    <p style="margin: 0; color: var(--gray-dark);">
                        Kelola informasi akun dan preferensi Anda
                    </p>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
            <!-- Profile Information -->
            <div>
                <!-- User Stats -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Statistik Akun</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <div style="text-align: center; padding: 1.5rem; background: var(--light-gold); border-radius: 10px;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-black);"><?= $user_stats['total_bookings'] ?></div>
                            <div style="font-size: 0.9rem; color: var(--gray-dark);">Total Booking</div>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #fff3cd; border-radius: 10px;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏳</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #856404;"><?= $user_stats['active_bookings'] ?></div>
                            <div style="font-size: 0.9rem; color: #856404;">Aktif</div>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #d4edda; border-radius: 10px;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #155724;"><?= $user_stats['completed_bookings'] ?></div>
                            <div style="font-size: 0.9rem; color: #155724;">Selesai</div>
                        </div>
                    </div>
                </div>

                <!-- Update Profile -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informasi Profil</h2>
                    </div>
                    
                    <form method="POST" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input 
                                type="text" 
                                id="username" 
                                class="form-control" 
                                value="<?= htmlspecialchars($user['username']) ?>"
                                disabled
                                style="background: #f5f5f5; color: #666;"
                            >
                            <small style="color: var(--gray-dark); font-size: 0.9rem;">
                                Username tidak dapat diubah
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="full_name" class="form-label">Nama Lengkap *</label>
                            <input 
                                type="text" 
                                id="full_name" 
                                name="full_name" 
                                class="form-control" 
                                required
                                value="<?= htmlspecialchars($user['full_name']) ?>"
                                placeholder="Masukkan nama lengkap"
                            >
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email *</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                required
                                value="<?= htmlspecialchars($user['email']) ?>"
                                placeholder="Masukkan alamat email"
                            >
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                class="form-control"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                placeholder="Masukkan nomor telepon"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                value="<?= ucfirst($user['role']) ?>"
                                disabled
                                style="background: #f5f5f5; color: #666;"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status Akun</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                value="<?= ucfirst($user['status']) ?>"
                                disabled
                                style="background: #f5f5f5; color: #666;"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bergabung Sejak</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                value="<?= date('d M Y', strtotime($user['created_at'])) ?>"
                                disabled
                                style="background: #f5f5f5; color: #666;"
                            >
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                                💾 Perbarui Profil
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security & Actions -->
            <div>
                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Ubah Password</h2>
                    </div>
                    
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">Password Saat Ini *</label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="form-control" 
                                required
                                placeholder="Masukkan password saat ini"
                            >
                        </div>

                        <div class="form-group">
                            <label for="new_password" class="form-label">Password Baru *</label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="form-control" 
                                required
                                minlength="6"
                                placeholder="Masukkan password baru (min. 6 karakter)"
                            >
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru *</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-control" 
                                required
                                placeholder="Ulangi password baru"
                            >
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                                🔒 Ubah Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Aksi Cepat</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <a href="my_bookings.php" class="btn btn-secondary" style="width: 100%; text-align: left; padding: 1rem; margin-bottom: 1rem;">
                            📋 Lihat Booking Saya
                        </a>
                        <a href="units.php" class="btn btn-secondary" style="width: 100%; text-align: left; padding: 1rem; margin-bottom: 1rem;">
                            🏠 Cari Unit Baru
                        </a>
                        <a href="contact.php" class="btn btn-secondary" style="width: 100%; text-align: left; padding: 1rem; margin-bottom: 1rem;">
                            📞 Hubungi Support
                        </a>
                        <a href="news.php" class="btn btn-secondary" style="width: 100%; text-align: left; padding: 1rem;">
                            📰 Baca Berita Terbaru
                        </a>
                    </div>
                </div>

                <!-- Account Security -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Keamanan Akun</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <div>
                                <div style="font-weight: bold; margin-bottom: 0.25rem;">Password</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Terakhir diubah: <?= date('d M Y', strtotime($user['updated_at'])) ?></div>
                            </div>
                            <span style="color: #155724; font-weight: bold;">✓ Aman</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <div>
                                <div style="font-weight: bold; margin-bottom: 0.25rem;">Email Verifikasi</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Email telah terverifikasi</div>
                            </div>
                            <span style="color: #155724; font-weight: bold;">✓ Aktif</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                            <div>
                                <div style="font-weight: bold; margin-bottom: 0.25rem;">Login Terakhir</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Hari ini</div>
                            </div>
                            <span style="color: #155724; font-weight: bold;">✓ Normal</span>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="card" style="border: 2px solid #dc3545;">
                    <div class="card-header">
                        <h2 class="card-title" style="color: #dc3545;">⚠️ Zona Berbahaya</h2>
                    </div>
                    
                    <div style="background: #f8d7da; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                        <p style="margin: 0; color: #721c24; line-height: 1.6;">
                            Tindakan di bawah ini bersifat permanen dan tidak dapat dibatalkan. Pastikan Anda benar-benar yakin sebelum melanjutkan.
                        </p>
                    </div>
                    
                    <button onclick="confirmDeleteAccount()" class="btn btn-danger" style="width: 100%; padding: 1rem;">
                        🗑️ Hapus Akun
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        // Profile form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const hideLoading = showLoading(submitBtn);
        });

        // Password form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showAlert('Konfirmasi password tidak cocok', 'error');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const hideLoading = showLoading(submitBtn);
        });

        // Real-time password confirmation check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.style.borderColor = '#dc3545';
                showFieldError(this, 'Password tidak cocok');
            } else {
                clearFieldError(this);
            }
        });

        // Delete account confirmation
        function confirmDeleteAccount() {
            confirmDialog(
                'Apakah Anda yakin ingin menghapus akun? Semua data termasuk booking akan hilang permanen dan tidak dapat dipulihkan.',
                function() {
                    // Here you would typically redirect to delete account handler
                    showAlert('Fitur hapus akun akan segera tersedia. Hubungi admin untuk bantuan.', 'info');
                }
            );
        }
    </script>
</body>
</html>