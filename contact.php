<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Nama, email, subjek, dan pesan harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        $db = new Database();
        
        try {
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
            
            if ($stmt->execute()) {
                $success = 'Pesan Anda telah terkirim. Kami akan segera menghubungi Anda.';
                // Clear form data
                $_POST = [];
            } else {
                $error = 'Terjadi kesalahan saat mengirim pesan. Silakan coba lagi.';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
    <!-- Header -->
   

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--primary-black);">
                    📞 Hubungi Kami
                </h1>
                <p style="color: var(--gray-dark); font-size: 1.1rem;">
                    Kami siap membantu Anda menemukan rumah impian di Kota Kupang
                </p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: start;">
            <!-- Contact Form -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Kirim Pesan</h2>
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
                <?php else: ?>

                <form method="POST" id="contactForm">
                    <div class="form-group">
                        <label for="name" class="form-label">Nama Lengkap *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
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
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
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
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                            placeholder="Masukkan nomor telepon"
                        >
                    </div>

                    <div class="form-group">
                        <label for="subject" class="form-label">Subjek *</label>
                        <select id="subject" name="subject" class="form-control" required>
                            <option value="">Pilih subjek</option>
                            <option value="Informasi Proyek" <?= ($_POST['subject'] ?? '') === 'Informasi Proyek' ? 'selected' : '' ?>>Informasi Proyek</option>
                            <option value="Booking Unit" <?= ($_POST['subject'] ?? '') === 'Booking Unit' ? 'selected' : '' ?>>Booking Unit</option>
                            <option value="Konsultasi" <?= ($_POST['subject'] ?? '') === 'Konsultasi' ? 'selected' : '' ?>>Konsultasi</option>
                            <option value="Keluhan" <?= ($_POST['subject'] ?? '') === 'Keluhan' ? 'selected' : '' ?>>Keluhan</option>
                            <option value="Lainnya" <?= ($_POST['subject'] ?? '') === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label">Pesan *</label>
                        <textarea 
                            id="message" 
                            name="message" 
                            class="form-control" 
                            rows="5" 
                            required
                            placeholder="Tulis pesan Anda di sini..."
                        ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                            📤 Kirim Pesan
                        </button>
                    </div>
                </form>

                <?php endif; ?>
            </div>

            <!-- Contact Information -->
            <div>
                <!-- Office Info -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informasi Kontak</h2>
                    </div>

                    <div style="space-y: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="
                                width: 50px;
                                height: 50px;
                                background: var(--gradient-gold);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 1.2rem;
                            ">
                                📍
                            </div>
                            <div>
                                <h4 style="margin-bottom: 0.25rem; color: var(--primary-black);">Alamat Kantor</h4>
                                <p style="margin: 0; color: var(--gray-dark);">
                                    Jl. Timor Raya No. 123<br>
                                    Kelapa Lima, Kupang, NTT 85228
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="
                                width: 50px;
                                height: 50px;
                                background: var(--gradient-gold);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 1.2rem;
                            ">
                                📞
                            </div>
                            <div>
                                <h4 style="margin-bottom: 0.25rem; color: var(--primary-black);">Telepon</h4>
                                <p style="margin: 0; color: var(--gray-dark);">
                                    (0380) 123-456<br>
                                    +62 812-3456-7890
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="
                                width: 50px;
                                height: 50px;
                                background: var(--gradient-gold);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 1.2rem;
                            ">
                                ✉️
                            </div>
                            <div>
                                <h4 style="margin-bottom: 0.25rem; color: var(--primary-black);">Email</h4>
                                <p style="margin: 0; color: var(--gray-dark);">
                                    info@perumahankupang.com<br>
                                    marketing@perumahankupang.com
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="
                                width: 50px;
                                height: 50px;
                                background: var(--gradient-gold);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 1.2rem;
                            ">
                                🕒
                            </div>
                            <div>
                                <h4 style="margin-bottom: 0.25rem; color: var(--primary-black);">Jam Operasional</h4>
                                <p style="margin: 0; color: var(--gray-dark);">
                                    Senin - Jumat: 08:00 - 17:00<br>
                                    Sabtu: 08:00 - 15:00<br>
                                    Minggu: Tutup
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Tautan Cepat</h2>
                    </div>

                    <div style="display: grid; gap: 1rem;">
                        <a href="projects.php" class="btn btn-secondary" style="text-align: left; padding: 1rem;">
                            🏘️ Lihat Semua Proyek
                        </a>
                        <a href="units.php" class="btn btn-secondary" style="text-align: left; padding: 1rem;">
                            🏠 Cari Unit Tersedia
                        </a>
                        <a href="news.php" class="btn btn-secondary" style="text-align: left; padding: 1rem;">
                            📰 Baca Berita Terbaru
                        </a>
                        <?php if (!isLoggedIn()): ?>
                        <a href="register.php" class="btn btn-primary" style="text-align: left; padding: 1rem;">
                            📝 Daftar Akun Baru
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Pertanyaan yang Sering Diajukan</h2>
            </div>

            <div style="display: grid; gap: 1rem;">
                <details style="border: 1px solid #e0e0e0; border-radius: 10px; padding: 1rem;">
                    <summary style="font-weight: bold; cursor: pointer; margin-bottom: 0.5rem;">
                        Bagaimana cara melakukan booking unit rumah?
                    </summary>
                    <p style="color: var(--gray-dark); line-height: 1.6;">
                        Anda dapat melakukan booking dengan mendaftar akun terlebih dahulu, kemudian memilih unit yang diinginkan dan mengisi form booking. Tim kami akan menghubungi Anda untuk proses selanjutnya.
                    </p>
                </details>

                <details style="border: 1px solid #e0e0e0; border-radius: 10px; padding: 1rem;">
                    <summary style="font-weight: bold; cursor: pointer; margin-bottom: 0.5rem;">
                        Apakah ada biaya booking?
                    </summary>
                    <p style="color: var(--gray-dark); line-height: 1.6;">
                        Ya, biasanya ada biaya booking yang besarnya bervariasi tergantung proyek. Informasi detail akan dijelaskan saat proses booking.
                    </p>
                </details>

                <details style="border: 1px solid #e0e0e0; border-radius: 10px; padding: 1rem;">
                    <summary style="font-weight: bold; cursor: pointer; margin-bottom: 0.5rem;">
                        Apakah bisa melakukan survey lokasi terlebih dahulu?
                    </summary>
                    <p style="color: var(--gray-dark); line-height: 1.6;">
                        Tentu saja! Kami sangat menyarankan untuk melakukan survey lokasi. Hubungi kami untuk mengatur jadwal kunjungan.
                    </p>
                </details>

                <details style="border: 1px solid #e0e0e0; border-radius: 10px; padding: 1rem;">
                    <summary style="font-weight: bold; cursor: pointer; margin-bottom: 0.5rem;">
                        Bagaimana sistem pembayaran yang tersedia?
                    </summary>
                    <p style="color: var(--gray-dark); line-height: 1.6;">
                        Kami menyediakan berbagai skema pembayaran mulai dari cash, KPR, hingga cicilan developer. Tim marketing akan membantu Anda memilih yang terbaik.
                    </p>
                </details>
            </div>
        </div>
    </div>
             <?php
  require_once 'includes/footer.php';
  ?>
    <script src="assets/js/script.js"></script>
    <script>
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const hideLoading = showLoading(submitBtn);
        });
    </script>
</body>
</html>