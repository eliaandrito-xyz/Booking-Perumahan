<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();
$success = '';
$error = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $booking_id = $_POST['booking_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        if (!empty($booking_id) && !empty($new_status)) {
            try {
                $stmt = $db->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $new_status, $booking_id);
                
                if ($stmt->execute()) {
                    // If status is cancelled, update unit status back to available
                    if ($new_status === 'cancelled') {
                        $stmt = $db->prepare("
                            UPDATE house_units 
                            SET status = 'available' 
                            WHERE id = (SELECT unit_id FROM bookings WHERE id = ?)
                        ");
                        $stmt->bind_param("i", $booking_id);
                        $stmt->execute();
                    }
                    
                    $success = 'Status booking berhasil diperbarui';
                } else {
                    $error = 'Terjadi kesalahan saat memperbarui status';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $booking_id = $_POST['id'] ?? '';
        
        if (!empty($booking_id)) {
            try {
                // Get unit_id and documents before deleting
                $stmt = $db->prepare("SELECT unit_id FROM bookings WHERE id = ?");
                $stmt->bind_param("i", $booking_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $booking = $result->fetch_assoc();
                
                if ($booking) {
                    // Delete associated documents from filesystem
                    $stmt = $db->prepare("SELECT file_path FROM booking_documents WHERE booking_id = ?");
                    $stmt->bind_param("i", $booking_id);
                    $stmt->execute();
                    $docs_result = $stmt->get_result();
                    
                    while ($doc = $docs_result->fetch_assoc()) {
                        $filepath = '../' . $doc['file_path'];
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                    }
                    
                    // Delete booking
                    $stmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
                    $stmt->bind_param("i", $booking_id);
                    
                    if ($stmt->execute()) {
                        // Update unit status back to available
                        $stmt = $db->prepare("UPDATE house_units SET status = 'available' WHERE id = ?");
                        $stmt->bind_param("i", $booking['unit_id']);
                        $stmt->execute();
                        
                        $success = 'Booking berhasil dihapus';
                    } else {
                        $error = 'Terjadi kesalahan saat menghapus booking';
                    }
                } else {
                    $error = 'Booking tidak ditemukan';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}

// Get bookings with filters
$bookings = [];
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$project_filter = $_GET['project'] ?? '';

try {
    // First, try a simple query to test
    $test_query = "SELECT COUNT(*) as total FROM bookings";
    $test_result = $db->query($test_query);
    if ($test_result) {
        $test_row = $test_result->fetch_assoc();
        // Success - continue with full query
    }
    
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(b.customer_name LIKE ? OR b.customer_email LIKE ? OR hu.unit_number LIKE ? OR hp.name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= 'ssss';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "b.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if (!empty($project_filter)) {
        $where_conditions[] = "hp.id = ?";
        $params[] = $project_filter;
        $types .= 'i';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Updated query - using COALESCE for null safety
    $sql = "
        SELECT b.id, b.user_id, b.unit_id, b.booking_date, 
               b.customer_name, b.customer_phone, b.customer_email, 
               b.customer_address, b.booking_fee, b.notes, 
               b.status, b.created_at, b.updated_at,
               COALESCE(hu.unit_number, 'N/A') as unit_number, 
               COALESCE(hu.type, 'N/A') as type, 
               COALESCE(hu.price, 0) as unit_price, 
               COALESCE(hu.bedrooms, 0) as bedrooms, 
               COALESCE(hu.bathrooms, 0) as bathrooms, 
               COALESCE(hu.land_area, 0) as land_area, 
               COALESCE(hu.building_area, 0) as building_area,
               COALESCE(hp.name, 'N/A') as project_name, 
               COALESCE(hp.location, 'N/A') as location, 
               COALESCE(u.full_name, u.username, 'N/A') as user_name, 
               COALESCE(u.email, 'N/A') as user_email
        FROM bookings b
        LEFT JOIN house_units hu ON b.unit_id = hu.id
        LEFT JOIN housing_projects hp ON hu.project_id = hp.id
        LEFT JOIN users u ON b.user_id = u.id
        $where_clause
        ORDER BY b.created_at DESC
    ";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->getConnection()->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
    } else {
        $result = $db->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $db->getConnection()->error);
        }
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data booking: " . $e->getMessage();
}

// Get projects for filter
$projects = [];
try {
    $result = $db->query("SELECT id, name FROM housing_projects ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get statistics
$stats = [
    'total' => count($bookings),
    'pending' => count(array_filter($bookings, fn($b) => $b['status'] === 'pending')),
    'confirmed' => count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed')),
    'cancelled' => count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled')),
    'completed' => count(array_filter($bookings, fn($b) => $b['status'] === 'completed'))
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .status-select {
            padding: 0.5rem 0.75rem;
            border-radius: 15px;
            border: 2px solid transparent;
            font-size: 0.85rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .status-select:hover {
            border-color: var(--accent-gold);
            transform: scale(1.05);
        }
        
        .status-select:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div>
                <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                    📋 Kelola Booking
                </h1>
                <p style="margin: 0; color: var(--gray-dark);">
                    Kelola dan pantau semua booking unit rumah
                </p>
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

        <!-- Statistics -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 2rem;">
            <div class="stat-card hover-lift">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Booking</div>
            </div>
            <div class="stat-card hover-lift" style="background: #fff3cd;">
                <div class="stat-icon" style="background: #856404; color: white;">⏳</div>
                <div class="stat-number" style="color: #856404;"><?= $stats['pending'] ?></div>
                <div class="stat-label" style="color: #856404;">Pending</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d4edda;">
                <div class="stat-icon" style="background: #155724; color: white;">✅</div>
                <div class="stat-number" style="color: #155724;"><?= $stats['confirmed'] ?></div>
                <div class="stat-label" style="color: #155724;">Confirmed</div>
            </div>
            <div class="stat-card hover-lift" style="background: #f8d7da;">
                <div class="stat-icon" style="background: #721c24; color: white;">❌</div>
                <div class="stat-number" style="color: #721c24;"><?= $stats['cancelled'] ?></div>
                <div class="stat-label" style="color: #721c24;">Cancelled</div>
            </div>
            <div class="stat-card hover-lift" style="background: var(--light-gold);">
                <div class="stat-icon" style="background: var(--primary-black); color: var(--accent-gold);">🎉</div>
                <div class="stat-number" style="color: var(--primary-black);"><?= $stats['completed'] ?></div>
                <div class="stat-label" style="color: var(--primary-black);">Completed</div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="card">
            <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Cari Booking</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama customer, email, unit..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Proyek</label>
                    <select name="project" class="form-control">
                        <option value="">Semua Proyek</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= $project_filter == $proj['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <a href="bookings.php" class="btn btn-secondary" style="margin-left: 0.5rem;">🔄 Reset</a>
                </div>
            </form>
        </div>

        <!-- Bookings List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Booking</h2>
                <div style="color: var(--gray-dark);">
                    Total: <?= count($bookings) ?> booking
                </div>
            </div>
            
            <?php if (empty($bookings)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                    <h3>Belum Ada Booking</h3>
                    <p style="color: var(--gray-dark);">
                        Booking akan muncul di sini ketika user melakukan pemesanan unit.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Unit</th>
                                <th>Proyek</th>
                                <th>Booking Fee</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong>#<?= $booking['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($booking['customer_name']) ?></strong><br>
                                    <small style="color: var(--gray-dark);"><?= htmlspecialchars($booking['customer_email']) ?></small><br>
                                    <small style="color: var(--gray-dark);"><?= htmlspecialchars($booking['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($booking['unit_number']) ?></strong><br>
                                    <small style="color: var(--gray-dark);"><?= htmlspecialchars($booking['type']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($booking['project_name']) ?></strong><br>
                                    <small style="color: var(--gray-dark);"><?= htmlspecialchars($booking['location']) ?></small>
                                </td>
                                <td>
                                    <strong>Rp <?= number_format($booking['booking_fee'], 0, ',', '.') ?></strong>
                                </td>
                                <td>
                                    <?= date('d M Y', strtotime($booking['booking_date'])) ?><br>
                                    <small style="color: var(--gray-dark);"><?= date('H:i', strtotime($booking['booking_date'])) ?></small>
                                </td>
                                <td>
                                    <select class="status-select status-<?= $booking['status'] ?>" 
                                            data-booking-id="<?= $booking['id'] ?>" 
                                            data-current-status="<?= $booking['status'] ?>">
                                        <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        <option value="completed" <?= $booking['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="booking_detail.php?id=<?= $booking['id'] ?>" class="btn btn-primary" style="padding: 0.5rem; text-decoration: none;">
                                            👁️
                                        </a>
                                        <button onclick="deleteBooking(<?= $booking['id'] ?>, '<?= htmlspecialchars(addslashes($booking['customer_name'])) ?>')" 
                                                class="btn btn-danger" style="padding: 0.5rem;">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Update Status Form (Hidden) -->
    <form id="updateStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="booking_id" id="statusBookingId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="../assets/js/script.js"></script>
    <script>
        // Status change handler - FIXED VERSION
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('.status-select');
            
            statusSelects.forEach(function(select) {
                select.addEventListener('change', function() {
                    const bookingId = this.getAttribute('data-booking-id');
                    const currentStatus = this.getAttribute('data-current-status');
                    const newStatus = this.value;
                    
                    // If status hasn't changed, do nothing
                    if (newStatus === currentStatus) {
                        return;
                    }
                    
                    let message = `Apakah Anda yakin ingin mengubah status booking #${bookingId} menjadi "${newStatus}"?`;
                    
                    if (newStatus === 'cancelled') {
                        message += '\n\n⚠️ Unit akan kembali tersedia untuk booking.';
                    } else if (newStatus === 'confirmed') {
                        message += '\n\n✅ Customer akan dihubungi untuk proses selanjutnya.';
                    } else if (newStatus === 'completed') {
                        message += '\n\n🎉 Booking akan ditandai sebagai selesai.';
                    }
                    
                    const selectElement = this;
                    
                    if (confirm(message)) {
                        // Update form values
                        document.getElementById('statusBookingId').value = bookingId;
                        document.getElementById('newStatus').value = newStatus;
                        
                        // Submit form
                        document.getElementById('updateStatusForm').submit();
                    } else {
                        // Reset to previous value if cancelled
                        this.value = currentStatus;
                    }
                });
            });
        });

        function deleteBooking(id, customerName) {
            if (confirm(`Apakah Anda yakin ingin menghapus booking dari "${customerName}"?\n\n⚠️ Unit akan kembali tersedia untuk booking.\n❌ Tindakan ini tidak dapat dibatalkan.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>