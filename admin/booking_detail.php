<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();
$booking_id = $_GET['id'] ?? '';

// Get booking details
$booking = null;
$documents = [];

if ($booking_id) {
    try {
        $stmt = $db->prepare("
            SELECT b.*, 
                   hu.unit_number, hu.type, hu.price as unit_price, hu.land_area, hu.building_area,
                   hu.bedrooms, hu.bathrooms, hu.status as unit_status,
                   hp.name as project_name, hp.location, hp.address, hp.description as project_description,
                   d.name as developer_name, d.phone as developer_phone, d.email as developer_email,
                   u.full_name as user_name, u.email as user_email, u.phone as user_phone
            FROM bookings b
            LEFT JOIN house_units hu ON b.unit_id = hu.id
            LEFT JOIN housing_projects hp ON hu.project_id = hp.id
            LEFT JOIN developers d ON hp.developer_id = d.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.id = ?
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if (!$booking) {
            header('Location: bookings.php');
            exit();
        }
        
        // Get booking documents
        $stmt = $db->prepare("
            SELECT * FROM booking_documents 
            WHERE booking_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        
    } catch (Exception $e) {
        $error = "Terjadi kesalahan saat mengambil data booking.";
    }
} else {
    header('Location: bookings.php');
    exit();
}

// Handle document upload
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_document') {
        $document_type = $_POST['document_type'] ?? '';
        
        if (!empty($document_type) && isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file = $_FILES['document'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Tipe file tidak didukung. Gunakan PDF, JPG, atau PNG.';
            } elseif ($file['size'] > $max_size) {
                $error = 'Ukuran file terlalu besar. Maksimal 5MB.';
            } else {
                // Create upload directory if not exists
                $upload_dir = '../uploads/booking_documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'booking_' . $booking_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO booking_documents (booking_id, document_type, file_path) 
                            VALUES (?, ?, ?)
                        ");
                        $db_filepath = 'uploads/booking_documents/' . $filename;
                        $stmt->bind_param("iss", $booking_id, $document_type, $db_filepath);
                        
                        if ($stmt->execute()) {
                            $success = 'Dokumen berhasil diupload.';
                            // Reload documents
                            $stmt = $db->prepare("
                                SELECT * FROM booking_documents 
                                WHERE booking_id = ? 
                                ORDER BY created_at DESC
                            ");
                            $stmt->bind_param("i", $booking_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $documents = [];
                            while ($row = $result->fetch_assoc()) {
                                $documents[] = $row;
                            }
                        } else {
                            $error = 'Gagal menyimpan data dokumen ke database.';
                            unlink($filepath);
                        }
                    } catch (Exception $e) {
                        $error = 'Terjadi kesalahan: ' . $e->getMessage();
                        unlink($filepath);
                    }
                } else {
                    $error = 'Gagal mengupload file.';
                }
            }
        } else {
            $error = 'Pilih dokumen dan tipe dokumen terlebih dahulu.';
        }
    } elseif ($action === 'delete_document') {
        $document_id = $_POST['document_id'] ?? '';
        
        if (!empty($document_id)) {
            try {
                // Get document info
                $stmt = $db->prepare("SELECT file_path FROM booking_documents WHERE id = ? AND booking_id = ?");
                $stmt->bind_param("ii", $document_id, $booking_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $doc = $result->fetch_assoc();
                
                if ($doc) {
                    // Delete file
                    $filepath = '../' . $doc['file_path'];
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                    
                    // Delete from database
                    $stmt = $db->prepare("DELETE FROM booking_documents WHERE id = ?");
                    $stmt->bind_param("i", $document_id);
                    
                    if ($stmt->execute()) {
                        $success = 'Dokumen berhasil dihapus.';
                        // Reload documents
                        $stmt = $db->prepare("
                            SELECT * FROM booking_documents 
                            WHERE booking_id = ? 
                            ORDER BY created_at DESC
                        ");
                        $stmt->bind_param("i", $booking_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $documents = [];
                        while ($row = $result->fetch_assoc()) {
                            $documents[] = $row;
                        }
                    } else {
                        $error = 'Gagal menghapus dokumen dari database.';
                    }
                } else {
                    $error = 'Dokumen tidak ditemukan.';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_status') {
        $new_status = $_POST['new_status'] ?? '';
        
        if (!empty($new_status)) {
            try {
                $stmt = $db->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $new_status, $booking_id);
                
                if ($stmt->execute()) {
                    // If status is cancelled, update unit status back to available
                    if ($new_status === 'cancelled') {
                        $stmt = $db->prepare("
                            UPDATE house_units 
                            SET status = 'available' 
                            WHERE id = ?
                        ");
                        $stmt->bind_param("i", $booking['unit_id']);
                        $stmt->execute();
                    }
                    
                    $success = 'Status booking berhasil diperbarui.';
                    // Reload booking
                    $stmt = $db->prepare("
                        SELECT b.*, 
                               hu.unit_number, hu.type, hu.price as unit_price, hu.land_area, hu.building_area,
                               hu.bedrooms, hu.bathrooms, hu.status as unit_status,
                               hp.name as project_name, hp.location, hp.address, hp.description as project_description,
                               d.name as developer_name, d.phone as developer_phone, d.email as developer_email,
                               u.full_name as user_name, u.email as user_email, u.phone as user_phone
                        FROM bookings b
                        LEFT JOIN house_units hu ON b.unit_id = hu.id
                        LEFT JOIN housing_projects hp ON hu.project_id = hp.id
                        LEFT JOIN developers d ON hp.developer_id = d.id
                        LEFT JOIN users u ON b.user_id = u.id
                        WHERE b.id = ?
                    ");
                    $stmt->bind_param("i", $booking_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $booking = $result->fetch_assoc();
                } else {
                    $error = 'Gagal mengupdate status booking.';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'add_notes') {
        $notes = $_POST['notes'] ?? '';
        
        try {
            $stmt = $db->prepare("UPDATE bookings SET notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $notes, $booking_id);
            
            if ($stmt->execute()) {
                $success = 'Catatan berhasil diperbarui.';
                $booking['notes'] = $notes;
            } else {
                $error = 'Gagal mengupdate catatan.';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get document type display name
function getDocumentTypeName($type) {
    $types = [
        'ktp' => 'KTP',
        'kk' => 'Kartu Keluarga',
        'npwp' => 'NPWP',
        'slip_gaji' => 'Slip Gaji',
        'foto_selfie' => 'Foto Selfie',
        'rekening_koran' => 'Rekening Koran',
        'surat_nikah' => 'Surat Nikah',
        'bukti_transfer' => 'Bukti Transfer',
        'lainnya' => 'Lainnya'
    ];
    return $types[$type] ?? ucfirst($type);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Booking #<?= $booking['id'] ?> - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .document-card {
            padding: 1rem;
            background: var(--gray-light);
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .timeline-item {
            padding: 1rem;
            border-left: 4px solid var(--accent-gold);
            background: var(--gray-light);
            margin-bottom: 1rem;
            border-radius: 0 10px 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav style="color: var(--gray-dark); font-size: 0.9rem;">
                <a href="dashboard.php" style="color: var(--gray-dark); text-decoration: none;">Dashboard</a>
                <span style="margin: 0 0.5rem;">›</span>
                <a href="bookings.php" style="color: var(--gray-dark); text-decoration: none;">Kelola Booking</a>
                <span style="margin: 0 0.5rem;">›</span>
                <span style="color: var(--accent-gold); font-weight: bold;">Booking #<?= $booking['id'] ?></span>
            </nav>
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

        <!-- Booking Header -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; color: var(--primary-black);">
                        Booking #<?= $booking['id'] ?>
                    </h1>
                    <p style="color: var(--gray-dark); margin-bottom: 1rem;">
                        📅 Dibuat pada <?= date('d M Y H:i', strtotime($booking['created_at'])) ?> WITA
                    </p>
                </div>
                
                <div style="text-align: right;">
                    <form method="POST" id="statusForm" style="display: inline-block;">
                        <input type="hidden" name="action" value="update_status">
                        <select name="new_status" onchange="updateStatus(this.value, '<?= $booking['status'] ?>')" style="
                            padding: 1rem 2rem;
                            border-radius: 25px;
                            font-size: 1.1rem;
                            font-weight: bold;
                            border: none;
                            cursor: pointer;
                            background: <?= 
                                $booking['status'] === 'confirmed' ? '#d4edda' : 
                                ($booking['status'] === 'pending' ? '#fff3cd' : 
                                ($booking['status'] === 'cancelled' ? '#f8d7da' : '#d1ecf1')) 
                            ?>;
                            color: <?= 
                                $booking['status'] === 'confirmed' ? '#155724' : 
                                ($booking['status'] === 'pending' ? '#856404' : 
                                ($booking['status'] === 'cancelled' ? '#721c24' : '#0c5460')) 
                            ?>;
                        ">
                            <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="completed" <?= $booking['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="bookings.php" class="btn btn-secondary">
                    ← Kembali ke Daftar
                </a>
                <button onclick="printBooking()" class="btn btn-primary">
                    🖨️ Cetak Detail
                </button>
                <button onclick="showNotesModal()" class="btn btn-primary">
                    📝 Edit Catatan
                </button>
                <button onclick="showUploadModal()" class="btn btn-primary">
                    📎 Upload Dokumen
                </button>
            </div>
        </div>

        <!-- Booking Details Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Main Details -->
            <div>
                <!-- Customer Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">👤 Informasi Customer</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
                        <div>
                            <div style="margin-bottom: 1rem;">
                                <div style="font-size: 0.9rem; color: var(--gray-dark); margin-bottom: 0.25rem;">Nama Lengkap</div>
                                <div style="font-weight: bold; font-size: 1.1rem;"><?= htmlspecialchars($booking['customer_name']) ?></div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <div style="font-size: 0.9rem; color: var(--gray-dark); margin-bottom: 0.25rem;">Email</div>
                                <div style="font-weight: bold;"><?= htmlspecialchars($booking['customer_email']) ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark); margin-bottom: 0.25rem;">Telepon</div>
                                <div style="font-weight: bold;"><?= htmlspecialchars($booking['customer_phone']) ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <?php if ($booking['user_id']): ?>
                            <div style="padding: 1rem; background: var(--light-gold); border-radius: 10px;">
                                <div style="font-weight: bold; margin-bottom: 0.5rem;">👤 User Terdaftar</div>
                                <div style="font-size: 0.9rem;"><?= htmlspecialchars($booking['user_name']) ?></div>
                                <div style="font-size: 0.85rem; color: var(--gray-dark);"><?= htmlspecialchars($booking['user_email']) ?></div>
                                <?php if ($booking['user_phone']): ?>
                                <div style="font-size: 0.85rem; color: var(--gray-dark);"><?= htmlspecialchars($booking['user_phone']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div style="padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                                <div style="font-weight: bold; margin-bottom: 0.5rem;">ℹ️ Status</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Booking dari guest (tidak login)</div>
                            </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 1rem;">
                                <div style="font-size: 0.9rem; color: var(--gray-dark); margin-bottom: 0.25rem;">Tanggal Booking</div>
                                <div style="font-weight: bold;"><?= date('d M Y', strtotime($booking['booking_date'])) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($booking['customer_address'])): ?>
                    <div style="padding: 1rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                        <div style="font-weight: bold; margin-bottom: 0.5rem;">📍 Alamat Customer</div>
                        <div style="line-height: 1.5;"><?= nl2br(htmlspecialchars($booking['customer_address'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($booking['notes'])): ?>
                    <div style="padding: 1rem; background: #fff3cd; border-radius: 10px; border-left: 4px solid #ffc107;">
                        <div style="font-weight: bold; margin-bottom: 0.5rem;">📝 Catatan Booking</div>
                        <div style="line-height: 1.5;"><?= nl2br(htmlspecialchars($booking['notes'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Unit Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🏠 Informasi Unit</h2>
                    </div>
                    
                    <div style="
                        height: 200px;
                        background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7)),
                                    url('https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg') center/cover;
                        border-radius: 15px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 1.5rem;
                        font-weight: bold;
                        margin-bottom: 2rem;
                    ">
                        Unit <?= htmlspecialchars($booking['unit_number']) ?>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <h4 style="margin-bottom: 1rem; color: var(--primary-black);">Spesifikasi Unit</h4>
                            <div style="display: grid; gap: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <span style="color: var(--gray-dark);">Nomor Unit:</span>
                                    <span style="font-weight: bold;"><?= htmlspecialchars($booking['unit_number']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <span style="color: var(--gray-dark);">Tipe:</span>
                                    <span style="font-weight: bold;"><?= htmlspecialchars($booking['type']) ?></span>
                                </div>
                                <?php if ($booking['bedrooms']): ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <span style="color: var(--gray-dark);">Kamar Tidur:</span>
                                    <span style="font-weight: bold;"><?= $booking['bedrooms'] ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($booking['bathrooms']): ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <span style="color: var(--gray-dark);">Kamar Mandi:</span>
                                    <span style="font-weight: bold;"><?= $booking['bathrooms'] ?></span>
                                </div>
                                <?php endif; ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <span style="color: var(--gray-dark);">Luas Tanah:</span>
                                    <span style="font-weight: bold;"><?= $booking['land_area'] ?> m²</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <span style="color: var(--gray-dark);">Luas Bangunan:</span>
                                    <span style="font-weight: bold;"><?= $booking['building_area'] ?> m²</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--light-gold); border-radius: 10px;">
                                    <span style="color: var(--primary-black);">Status Unit:</span>
                                    <span style="font-weight: bold; color: var(--primary-black);"><?= ucfirst($booking['unit_status']) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="margin-bottom: 1rem; color: var(--primary-black);">Detail Proyek</h4>
                            <div style="display: grid; gap: 0.75rem;">
                                <div style="padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <div style="color: var(--gray-dark); font-size: 0.9rem; margin-bottom: 0.25rem;">Nama Proyek</div>
                                    <div style="font-weight: bold;"><?= htmlspecialchars($booking['project_name']) ?></div>
                                </div>
                                <div style="padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <div style="color: var(--gray-dark); font-size: 0.9rem; margin-bottom: 0.25rem;">Lokasi</div>
                                    <div style="font-weight: bold;"><?= htmlspecialchars($booking['location']) ?></div>
                                </div>
                                <div style="padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <div style="color: var(--gray-dark); font-size: 0.9rem; margin-bottom: 0.25rem;">Developer</div>
                                    <div style="font-weight: bold;"><?= htmlspecialchars($booking['developer_name']) ?></div>
                                </div>
                                <div style="padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <div style="color: var(--gray-dark); font-size: 0.9rem; margin-bottom: 0.25rem;">Alamat Lengkap</div>
                                    <div style="font-size: 0.9rem; line-height: 1.5;"><?= htmlspecialchars($booking['address']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; text-align: right;">
                        <a href="../unit_detail.php?id=<?= $booking['unit_id'] ?>" class="btn btn-secondary" target="_blank">
                            🏠 Lihat Detail Unit Lengkap
                        </a>
                    </div>
                </div>

                <!-- Documents -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title">📎 Dokumen Booking</h2>
                        <button onclick="showUploadModal()" class="btn btn-primary">
                            ➕ Upload Dokumen
                        </button>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                        <div style="text-align: center; padding: 3rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📄</div>
                            <h4 style="color: var(--gray-dark);">Belum Ada Dokumen</h4>
                            <p style="color: var(--gray-dark); font-size: 0.9rem;">
                                Upload dokumen pendukung untuk booking ini
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                        <div class="document-card">
                            <div class="document-info">
                                <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                    <?= getDocumentTypeName($doc['document_type']) ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--gray-dark);">
                                    Upload: <?= date('d M Y H:i', strtotime($doc['created_at'])) ?>
                                </div>
                            </div>
                            <div class="document-actions">
                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                                    👁️ Lihat
                                </a>
                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" download class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                                    💾 Download
                                </a>
                                <button onclick="deleteDocument(<?= $doc['id'] ?>, '<?= getDocumentTypeName($doc['document_type']) ?>')" class="btn btn-danger" style="padding: 0.5rem 1rem;">
                                    🗑️
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Financial Summary -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">💰 Ringkasan Finansial</h2>
                    </div>
                    
                    <div style="display: grid; gap: 1rem;">
                        <div style="padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                            <div style="color: var(--gray-dark); font-size: 0.9rem; margin-bottom: 0.25rem;">Harga Unit</div>
                            <div style="font-weight: bold; font-size: 1.2rem;">Rp <?= number_format($booking['unit_price'], 0, ',', '.') ?></div>
                        </div>
                        
                        <div style="padding: 1rem; background: var(--light-gold); border-radius: 10px;">
                            <div style="color: var(--primary-black); font-size: 0.9rem; margin-bottom: 0.25rem;">Biaya Booking</div>
                            <div style="font-weight: bold; font-size: 1.2rem; color: var(--primary-black);">Rp <?= number_format($booking['booking_fee'], 0, ',', '.') ?></div>
                        </div>
                        
                        <div style="padding: 1rem; background: var(--gradient-gold); border-radius: 10px;">
                            <div style="color: var(--primary-black); font-size: 0.9rem; margin-bottom: 0.25rem;">Sisa Pembayaran</div>
                            <div style="font-weight: bold; font-size: 1.2rem; color: var(--primary-black);">
                                Rp <?= number_format($booking['unit_price'] - $booking['booking_fee'], 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📞 Kontak Developer</h2>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="
                            width: 60px;
                            height: 60px;
                            background: var(--gradient-gold);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 1rem;
                            font-size: 1.5rem;
                        ">
                            🏢
                        </div>
                        <h4 style="margin-bottom: 1rem;"><?= htmlspecialchars($booking['developer_name']) ?></h4>
                        <div style="display: grid; gap: 0.5rem; margin-bottom: 1rem;">
                            <?php if ($booking['developer_phone']): ?>
                            <p style="margin: 0; color: var(--gray-dark);">📞 <?= htmlspecialchars($booking['developer_phone']) ?></p>
                            <?php endif; ?>
                            <?php if ($booking['developer_email']): ?>
                            <p style="margin: 0; color: var(--gray-dark);">✉️ <?= htmlspecialchars($booking['developer_email']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">⏱️ Timeline</h2>
                    </div>
                    
                    <div>
                        <div class="timeline-item">
                            <div style="font-weight: bold; margin-bottom: 0.25rem;">✅ Booking Dibuat</div>
                            <div style="font-size: 0.9rem; color: var(--gray-dark);">
                                <?= date('d M Y H:i', strtotime($booking['created_at'])) ?> WITA
                            </div>
                        </div>
                        
                        <?php if ($booking['status'] !== 'pending' && $booking['updated_at'] !== $booking['created_at']): ?>
                        <div class="timeline-item" style="border-left-color: <?= $booking['status'] === 'confirmed' ? '#28a745' : ($booking['status'] === 'cancelled' ? '#dc3545' : '#17a2b8') ?>">
                            <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                <?= $booking['status'] === 'confirmed' ? '✅ Dikonfirmasi' : ($booking['status'] === 'cancelled' ? '❌ Dibatalkan' : '🎉 Selesai') ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--gray-dark);">
                                <?= date('d M Y H:i', strtotime($booking['updated_at'])) ?> WITA
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Document Form (Hidden) -->
    <form id="deleteDocForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_document">
        <input type="hidden" name="document_id" id="deleteDocId">
    </form>

    <!-- Notes Form (Hidden) -->
    <form id="notesForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="add_notes">
        <textarea name="notes" id="notesTextarea" style="display: none;"></textarea>
    </form>

    <script src="../assets/js/script.js"></script>
    <script>
        function updateStatus(newStatus, currentStatus) {
            if (newStatus !== currentStatus) {
                let message = `Apakah Anda yakin ingin mengubah status booking menjadi "${newStatus}"?`;
                
                if (newStatus === 'cancelled') {
                    message += ' Unit akan kembali tersedia untuk booking.';
                } else if (newStatus === 'confirmed') {
                    message += ' Customer akan dihubungi untuk proses selanjutnya.';
                }
                
                confirmDialog(message, function() {
                    document.getElementById('statusForm').submit();
                }, function() {
                    // Reset select to previous value
                    document.querySelector('select[name="new_status"]').value = currentStatus;
                });
            }
        }

        function showUploadModal() {
            const modalHTML = `
                <div style="max-width: 500px;">
                    <h3 style="margin-bottom: 1.5rem;">Upload Dokumen Booking</h3>
                    
                    <form id="uploadDocForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_document">
                        
                        <div class="form-group">
                            <label class="form-label">Tipe Dokumen *</label>
                            <select name="document_type" class="form-control" required>
                                <option value="">-- Pilih Tipe Dokumen --</option>
                                <option value="ktp">KTP</option>
                                <option value="kk">Kartu Keluarga</option>
                                <option value="npwp">NPWP</option>
                                <option value="slip_gaji">Slip Gaji</option>
                                <option value="foto_selfie">Foto Selfie</option>
                                <option value="rekening_koran">Rekening Koran</option>
                                <option value="surat_nikah">Surat Nikah</option>
                                <option value="bukti_transfer">Bukti Transfer</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">File Dokumen *</label>
                            <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                            <small style="color: var(--gray-dark);">Format: PDF, JPG, PNG (Maks. 5MB)</small>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                📤 Upload Dokumen
                            </button>
                            <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="this.closest('.modal-overlay').remove()">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            createModal('Upload Dokumen', modalHTML, []);
        }

        function deleteDocument(docId, docType) {
            confirmDialog(
                `Apakah Anda yakin ingin menghapus dokumen "${docType}"? Tindakan ini tidak dapat dibatalkan.`,
                function() {
                    document.getElementById('deleteDocId').value = docId;
                    document.getElementById('deleteDocForm').submit();
                }
            );
        }

        function showNotesModal() {
            const currentNotes = <?= json_encode($booking['notes'] ?? '') ?>;
            
            const modalHTML = `
                <div style="max-width: 600px;">
                    <h3 style="margin-bottom: 1.5rem;">Edit Catatan Booking</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <textarea id="modalNotes" class="form-control" rows="6" placeholder="Tambahkan catatan untuk booking ini...">${currentNotes}</textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="button" class="btn btn-primary" style="flex: 1;" onclick="saveNotes()">
                            💾 Simpan Catatan
                        </button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="this.closest('.modal-overlay').remove()">
                            Batal
                        </button>
                    </div>
                </div>
            `;
            
            createModal('Edit Catatan', modalHTML, []);
        }

        function saveNotes() {
            const notes = document.getElementById('modalNotes').value;
            document.getElementById('notesTextarea').value = notes;
            document.getElementById('notesForm').submit();
        }

        function printBooking() {
            const printContent = `
                <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 40px 20px;">
                    <div style="text-align: center; margin-bottom: 40px; border-bottom: 3px solid #ffd700; padding-bottom: 20px;">
                        <h1 style="color: #1a1a1a; margin-bottom: 10px; font-size: 28px;">Sistem Informasi Perumahan Kota Kupang</h1>
                        <h2 style="color: #ffd700; margin: 10px 0; font-size: 22px;">Detail Booking #<?= $booking['id'] ?></h2>
                        <p style="color: #666; margin: 5px 0;">Status: <strong style="color: #1a1a1a;"><?= ucfirst($booking['status']) ?></strong></p>
                    </div>
                    
                    <div style="margin-bottom: 30px;">
                        <h3 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #ffd700; margin-bottom: 15px;">Informasi Customer</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; width: 40%;"><strong>Nama:</strong></td><td><?= htmlspecialchars($booking['customer_name']) ?></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Email:</strong></td><td><?= htmlspecialchars($booking['customer_email']) ?></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Telepon:</strong></td><td><?= htmlspecialchars($booking['customer_phone']) ?></td></tr>
                            <?php if ($booking['customer_address']): ?>
                            <tr><td style="padding: 8px 0;"><strong>Alamat:</strong></td><td><?= htmlspecialchars($booking['customer_address']) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <div style="margin-bottom: 30px;">
                        <h3 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #ffd700; margin-bottom: 15px;">Informasi Unit</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; width: 40%;"><strong>Nomor Unit:</strong></td><td><?= htmlspecialchars($booking['unit_number']) ?></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Tipe:</strong></td><td><?= htmlspecialchars($booking['type']) ?></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Proyek:</strong></td><td><?= htmlspecialchars($booking['project_name']) ?></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Lokasi:</strong></td><td><?= htmlspecialchars($booking['location']) ?></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Developer:</strong></td><td><?= htmlspecialchars($booking['developer_name']) ?></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Luas Tanah:</strong></td><td><?= $booking['land_area'] ?> m²</td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Luas Bangunan:</strong></td><td><?= $booking['building_area'] ?> m²</td></tr>
                        </table>
                    </div>
                    
                    <div style="margin-bottom: 30px;">
                        <h3 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #ffd700; margin-bottom: 15px;">Informasi Finansial</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 8px 0; width: 40%;"><strong>Harga Unit:</strong></td><td><strong>Rp <?= number_format($booking['unit_price'], 0, ',', '.') ?></strong></td></tr>
                            <tr><td style="padding: 8px 0;"><strong>Biaya Booking:</strong></td><td><strong style="color: #ffd700;">Rp <?= number_format($booking['booking_fee'], 0, ',', '.') ?></strong></td></tr>
                            <tr style="border-top: 2px solid #ddd;"><td style="padding: 8px 0;"><strong>Sisa Pembayaran:</strong></td><td><strong>Rp <?= number_format($booking['unit_price'] - $booking['booking_fee'], 0, ',', '.') ?></strong></td></tr>
                        </table>
                    </div>
                    
                    <?php if ($booking['notes']): ?>
                    <div style="margin-bottom: 30px;">
                        <h3 style="background: #f5f5f5; padding: 10px; border-left: 4px solid #ffd700; margin-bottom: 15px;">Catatan</h3>
                        <p style="line-height: 1.6;"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 50px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center; font-size: 12px; color: #666;">
                        <p>Dicetak pada: ${new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p style="margin-top: 10px;">Sistem Informasi Perumahan Kota Kupang</p>
                    </div>
                </div>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Booking #<?= $booking['id'] ?> - Detail</title>
                        <style>
                            body { margin: 0; padding: 0; }
                            @media print {
                                body { margin: 0; }
                                @page { margin: 2cm; }
                            }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }
    </script>
</body>
</html>