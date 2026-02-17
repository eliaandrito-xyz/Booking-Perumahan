<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

requireLogin();

$db = new Database();
$user_id = getUserId();
$unit_id = $_GET['unit'] ?? '';
$success = '';
$error = '';

// Get user details for auto-fill
$user_data = null;
try {
    $stmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
} catch (Exception $e) {
    // Handle error silently
}

// Get unit details
$unit = null;
$unit_image = null;
if ($unit_id) {
    try {
        $stmt = $db->prepare("
            SELECT hu.*, hp.name as project_name, hp.location, d.name as developer_name
            FROM house_units hu 
            LEFT JOIN housing_projects hp ON hu.project_id = hp.id
            LEFT JOIN developers d ON hp.developer_id = d.id
            WHERE hu.id = ? AND hu.status = 'available'
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $unit = $result->fetch_assoc();
        
        if (!$unit) {
            header('Location: units.php');
            exit();
        }
        
        // Get unit front image
        $stmt = $db->prepare("
            SELECT image_path FROM house_unit_images 
            WHERE unit_id = ? AND category = 'depan' 
            LIMIT 1
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $unit_image = $row['image_path'];
        }
        
    } catch (Exception $e) {
        $error = "Terjadi kesalahan saat mengambil data unit.";
    }
} else {
    header('Location: units.php');
    exit();
}

// Handle document upload
function uploadDocument($file, $booking_id, $type) {
    $upload_dir = 'uploads/bookings/';
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak didukung. Gunakan PDF, JPG, atau PNG'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'booking_' . $booking_id . '_' . $type . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Gagal mengupload file'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $booking_fee = floatval($_POST['booking_fee'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($customer_name) || empty($customer_phone) || empty($customer_email)) {
        $error = 'Nama, telepon, dan email harus diisi';
    } elseif (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif ($booking_fee < 1000000) {
        $error = 'Biaya booking minimal Rp 1.000.000';
    } else {
        try {
            // Check if unit is still available
            $stmt = $db->prepare("SELECT status FROM house_units WHERE id = ?");
            $stmt->bind_param("i", $unit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_unit = $result->fetch_assoc();
            
            if (!$current_unit || $current_unit['status'] !== 'available') {
                $error = 'Unit sudah tidak tersedia untuk booking';
            } else {
                // Insert booking
                $stmt = $db->prepare("
                    INSERT INTO bookings (user_id, unit_id, customer_name, customer_phone, customer_email, customer_address, booking_fee, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iissssds", $user_id, $unit_id, $customer_name, $customer_phone, $customer_email, $customer_address, $booking_fee, $notes);
                
                if ($stmt->execute()) {
                    $booking_id = $db->getConnection()->insert_id;
                    
                    // Handle document uploads
                    $upload_errors = [];
                    $upload_success = [];
                    
                    $doc_types = ['ktp', 'kk', 'npwp', 'slip_gaji', 'foto_selfie'];
                    
                    foreach ($doc_types as $doc_type) {
                        if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
                            $upload_result = uploadDocument($_FILES[$doc_type], $booking_id, $doc_type);
                            
                            if ($upload_result['success']) {
                                $upload_success[] = $doc_type;
                                
                                // Save to database (you'll need to create booking_documents table)
                                $stmt = $db->prepare("
                                    INSERT INTO booking_documents (booking_id, document_type, file_path) 
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->bind_param("iss", $booking_id, $doc_type, $upload_result['path']);
                                $stmt->execute();
                            } else {
                                $upload_errors[] = $doc_type . ': ' . $upload_result['message'];
                            }
                        }
                    }
                    
                    // Update unit status
                    $stmt = $db->prepare("UPDATE house_units SET status = 'booked' WHERE id = ?");
                    $stmt->bind_param("i", $unit_id);
                    $stmt->execute();
                    
                    $success_msg = "Booking berhasil! ID Booking: #$booking_id";
                    
                    if (!empty($upload_success)) {
                        $success_msg .= " Dokumen berhasil diupload: " . implode(', ', $upload_success) . ".";
                    }
                    
                    if (!empty($upload_errors)) {
                        $success_msg .= " Beberapa dokumen gagal diupload: " . implode(', ', $upload_errors) . ".";
                    }
                    
                    $success_msg .= " Tim kami akan segera menghubungi Anda.";
                    $success = $success_msg;
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $error = 'Terjadi kesalahan saat melakukan booking. Silakan coba lagi.';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Unit - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .booking-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: start;
        }
        
        .unit-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .unit-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7)),
                        url('https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg') center/cover;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
        
        .file-upload-box {
            border: 2px dashed var(--gray-light);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        
        .file-upload-box:hover {
            border-color: var(--accent-gold);
            background: var(--light-gold);
        }
        
        .file-upload-box input[type="file"] {
            display: none;
        }
        
        .file-upload-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .file-preview {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: var(--gray-light);
            border-radius: 5px;
            font-size: 0.9rem;
            display: none;
        }
        
        .file-preview.active {
            display: block;
        }
        
        .required-docs-info {
            background: var(--light-gold);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .booking-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .unit-image,
            .unit-placeholder {
                height: 250px;
            }
            
            .file-upload-box {
                padding: 1rem;
            }
            
            .file-upload-icon {
                font-size: 1.5rem;
            }
            
            .required-docs-info {
                padding: 1rem;
            }
            
            .required-docs-info ul {
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 480px) {
            .unit-image,
            .unit-placeholder {
                height: 200px;
                font-size: 1rem;
            }
            
            .file-upload-box {
                padding: 0.75rem;
            }
            
            .file-upload-icon {
                font-size: 1.2rem;
            }
            
            .file-preview {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--primary-black);">
                    📞 Booking Unit Rumah
                </h1>
                <p style="color: var(--gray-dark); font-size: 1.1rem;">
                    Lengkapi form booking untuk mereservasi unit pilihan Anda
                </p>
            </div>
        </div>

        <div class="booking-container">
            <!-- Unit Details -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Detail Unit</h2>
                </div>

                <?php if (!empty($unit_image)): ?>
                    <img src="<?= htmlspecialchars($unit_image) ?>" 
                         alt="Unit <?= htmlspecialchars($unit['unit_number']) ?>"
                         class="unit-image"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="unit-placeholder" style="display: none;">
                        Unit <?= htmlspecialchars($unit['unit_number']) ?>
                    </div>
                <?php else: ?>
                    <div class="unit-placeholder">
                        Unit <?= htmlspecialchars($unit['unit_number']) ?>
                    </div>
                <?php endif; ?>

                <div style="space-y: 1rem;">
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                        <span style="font-weight: bold;">Nomor Unit:</span>
                        <span><?= htmlspecialchars($unit['unit_number']) ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                        <span style="font-weight: bold;">Proyek:</span>
                        <span><?= htmlspecialchars($unit['project_name']) ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                        <span style="font-weight: bold;">Lokasi:</span>
                        <span><?= htmlspecialchars($unit['location']) ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                        <span style="font-weight: bold;">Tipe:</span>
                        <span><?= htmlspecialchars($unit['type']) ?></span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: var(--light-gold); border-radius: 10px;">
                            <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🛏️</div>
                            <div style="font-weight: bold;"><?= $unit['bedrooms'] ?> Kamar Tidur</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: var(--light-gold); border-radius: 10px;">
                            <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🚿</div>
                            <div style="font-weight: bold;"><?= $unit['bathrooms'] ?> Kamar Mandi</div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                            <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">📐</div>
                            <div style="font-weight: bold;">Luas Tanah</div>
                            <div><?= $unit['land_area'] ?> m²</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                            <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">🏠</div>
                            <div style="font-weight: bold;">Luas Bangunan</div>
                            <div><?= $unit['building_area'] ?> m²</div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 1.5rem; background: var(--gradient-gold); border-radius: 10px;">
                        <div style="font-size: 1rem; color: var(--primary-black); margin-bottom: 0.5rem;">Harga Unit</div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-black);">
                            Rp <?= number_format($unit['price'], 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Form Booking</h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                        <div style="margin-top: 1rem;">
                            <a href="dashboard.php" class="btn btn-primary">Lihat Dashboard</a>
                            <a href="units.php" class="btn btn-secondary" style="margin-left: 1rem;">Cari Unit Lain</a>
                        </div>
                    </div>
                <?php else: ?>

                <form method="POST" id="bookingForm" enctype="multipart/form-data">
                    <h3 style="margin-bottom: 1rem; color: var(--primary-black);">📋 Data Pribadi</h3>
                    
                    <div class="form-group">
                        <label for="customer_name" class="form-label">Nama Lengkap *</label>
                        <input 
                            type="text" 
                            id="customer_name" 
                            name="customer_name" 
                            class="form-control" 
                            required
                            value="<?= htmlspecialchars($user_data['name'] ?? '') ?>"
                            placeholder="Masukkan nama lengkap"
                        >
                    </div>

                    <div class="form-group">
                        <label for="customer_phone" class="form-label">Nomor Telepon *</label>
                        <input 
                            type="tel" 
                            id="customer_phone" 
                            name="customer_phone" 
                            class="form-control" 
                            required
                            value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>"
                            placeholder="Masukkan nomor telepon"
                        >
                    </div>

                    <div class="form-group">
                        <label for="customer_email" class="form-label">Email *</label>
                        <input 
                            type="email" 
                            id="customer_email" 
                            name="customer_email" 
                            class="form-control" 
                            required
                            value="<?= htmlspecialchars($user_data['email'] ?? '') ?>"
                            placeholder="Masukkan alamat email"
                        >
                    </div>

                    <div class="form-group">
                        <label for="customer_address" class="form-label">Alamat Lengkap</label>
                        <textarea 
                            id="customer_address" 
                            name="customer_address" 
                            class="form-control" 
                            rows="3"
                            placeholder="Masukkan alamat lengkap"
                        ><?= htmlspecialchars($_POST['customer_address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="booking_fee" class="form-label">Biaya Booking (Rp) *</label>
                        <input 
                            type="number" 
                            id="booking_fee" 
                            name="booking_fee" 
                            class="form-control" 
                            required
                            min="1000000"
                            value="5000000"
                            placeholder="Minimal Rp 1.000.000"
                        >
                        <small style="color: var(--gray-dark); font-size: 0.9rem;">
                            Biaya booking minimal Rp 1.000.000. Biaya ini akan dipotong dari total harga unit.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label">Catatan Tambahan</label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            class="form-control" 
                            rows="3"
                            placeholder="Catatan atau permintaan khusus"
                        ><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <hr style="margin: 2rem 0;">

                    <h3 style="margin-bottom: 1rem; color: var(--primary-black);">📄 Upload Dokumen Persyaratan</h3>
                    
                    <div class="required-docs-info">
                        <h4 style="margin-bottom: 0.5rem; color: var(--primary-black);">📌 Dokumen yang Diperlukan:</h4>
                        <ul style="margin: 0; padding-left: 1.5rem; color: var(--gray-dark); line-height: 1.6; font-size: 0.9rem;">
                            <li><strong>KTP</strong> - Kartu Tanda Penduduk (Wajib)</li>
                            <li><strong>KK</strong> - Kartu Keluarga (Wajib)</li>
                            <li><strong>NPWP</strong> - Nomor Pokok Wajib Pajak (Opsional)</li>
                            <li><strong>Slip Gaji</strong> - 3 bulan terakhir (Opsional)</li>
                            <li><strong>Foto Selfie dengan KTP</strong> - Untuk verifikasi (Wajib)</li>
                        </ul>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--gray-dark);">
                            Format: PDF, JPG, PNG | Maksimal 5MB per file
                        </p>
                    </div>

                    <!-- KTP Upload -->
                    <div class="form-group">
                        <label class="form-label">KTP (Wajib) *</label>
                        <div class="file-upload-box" onclick="document.getElementById('ktp').click()">
                            <div class="file-upload-icon">📄</div>
                            <div>Upload KTP</div>
                            <small style="color: var(--gray-dark);">Klik untuk memilih file</small>
                            <input type="file" id="ktp" name="ktp" accept=".pdf,.jpg,.jpeg,.png" onchange="previewFile(this, 'ktp-preview')">
                        </div>
                        <div id="ktp-preview" class="file-preview"></div>
                    </div>

                    <!-- KK Upload -->
                    <div class="form-group">
                        <label class="form-label">Kartu Keluarga (Wajib) *</label>
                        <div class="file-upload-box" onclick="document.getElementById('kk').click()">
                            <div class="file-upload-icon">📄</div>
                            <div>Upload Kartu Keluarga</div>
                            <small style="color: var(--gray-dark);">Klik untuk memilih file</small>
                            <input type="file" id="kk" name="kk" accept=".pdf,.jpg,.jpeg,.png" onchange="previewFile(this, 'kk-preview')">
                        </div>
                        <div id="kk-preview" class="file-preview"></div>
                    </div>

                    <!-- NPWP Upload -->
                    <div class="form-group">
                        <label class="form-label">NPWP (Opsional)</label>
                        <div class="file-upload-box" onclick="document.getElementById('npwp').click()">
                            <div class="file-upload-icon">📄</div>
                            <div>Upload NPWP</div>
                            <small style="color: var(--gray-dark);">Klik untuk memilih file</small>
                            <input type="file" id="npwp" name="npwp" accept=".pdf,.jpg,.jpeg,.png" onchange="previewFile(this, 'npwp-preview')">
                        </div>
                        <div id="npwp-preview" class="file-preview"></div>
                    </div>

                    <!-- Slip Gaji Upload -->
                    <div class="form-group">
                        <label class="form-label">Slip Gaji (Opsional)</label>
                        <div class="file-upload-box" onclick="document.getElementById('slip_gaji').click()">
                            <div class="file-upload-icon">📄</div>
                            <div>Upload Slip Gaji</div>
                            <small style="color: var(--gray-dark);">Klik untuk memilih file</small>
                            <input type="file" id="slip_gaji" name="slip_gaji" accept=".pdf,.jpg,.jpeg,.png" onchange="previewFile(this, 'slip-preview')">
                        </div>
                        <div id="slip-preview" class="file-preview"></div>
                    </div>

                    <!-- Foto Selfie Upload -->
                    <div class="form-group">
                        <label class="form-label">Foto Selfie dengan KTP (Wajib) *</label>
                        <div class="file-upload-box" onclick="document.getElementById('foto_selfie').click()">
                            <div class="file-upload-icon">📸</div>
                            <div>Upload Foto Selfie dengan KTP</div>
                            <small style="color: var(--gray-dark);">Klik untuk memilih file</small>
                            <input type="file" id="foto_selfie" name="foto_selfie" accept=".jpg,.jpeg,.png" onchange="previewFile(this, 'selfie-preview')">
                        </div>
                        <div id="selfie-preview" class="file-preview"></div>
                    </div>

                    <hr style="margin: 2rem 0;">

                    <div style="background: var(--light-gold); padding: 1.5rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--primary-black);">⚠️ Penting untuk Diketahui:</h4>
                        <ul style="margin: 0; padding-left: 1.5rem; color: var(--gray-dark); line-height: 1.6;">
                            <li>Biaya booking bersifat mengikat dan tidak dapat dibatalkan</li>
                            <li>Tim marketing akan menghubungi Anda dalam 1x24 jam</li>
                            <li>Proses verifikasi dokumen diperlukan untuk melanjutkan</li>
                            <li>Unit akan direservasi selama 7 hari untuk proses pembayaran</li>
                            <li>Pastikan semua dokumen yang diupload jelas dan terbaca</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                            📞 Konfirmasi Booking
                        </button>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        function previewFile(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB
                
                preview.innerHTML = `
                    <strong>File dipilih:</strong> ${fileName} (${fileSize} MB)
                    <button type="button" onclick="clearFile('${input.id}', '${previewId}')" style="margin-left: 1rem; padding: 0.25rem 0.75rem; background: var(--danger-color); color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Hapus
                    </button>
                `;
                preview.classList.add('active');
            }
        }
        
        function clearFile(inputId, previewId) {
            document.getElementById(inputId).value = '';
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            preview.classList.remove('active');
        }
        
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const bookingFee = parseInt(document.getElementById('booking_fee').value);
            
            if (bookingFee < 1000000) {
                e.preventDefault();
                showAlert('Biaya booking minimal Rp 1.000.000', 'error');
                return;
            }
            
            // Check required documents
            const ktpFile = document.getElementById('ktp').files.length;
            const kkFile = document.getElementById('kk').files.length;
            const selfieFile = document.getElementById('foto_selfie').files.length;
            
            if (ktpFile === 0 || kkFile === 0 || selfieFile === 0) {
                e.preventDefault();
                showAlert('Mohon upload dokumen wajib: KTP, KK, dan Foto Selfie dengan KTP', 'error');
                return;
            }
            
            if (!confirm('Apakah Anda yakin ingin melakukan booking unit ini? Biaya booking tidak dapat dibatalkan.')) {
                e.preventDefault();
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const hideLoading = showLoading(submitBtn);
        });
    </script>
</body>
</html>