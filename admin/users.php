<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();
$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $user_id = $_POST['user_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        if (!empty($user_id) && !empty($new_status)) {
            try {
                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'");
                $stmt->bind_param("si", $new_status, $user_id);
                
                if ($stmt->execute()) {
                    $success = 'Status user berhasil diperbarui';
                } else {
                    $error = 'Terjadi kesalahan saat memperbarui status';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $user_id = $_POST['user_id'] ?? '';
        
        try {
            // Check if user has bookings
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking_count = $result->fetch_assoc()['count'];
            
            if ($booking_count > 0) {
                $error = 'User tidak dapat dihapus karena memiliki riwayat booking';
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $success = 'User berhasil dihapus';
                } else {
                    $error = 'Terjadi kesalahan saat menghapus user';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Get users with filters
$users = [];
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';

try {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
        $types .= 'sss';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if (!empty($role_filter)) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as total_bookings,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND status = 'confirmed') as confirmed_bookings
        FROM users u 
        $where_clause
        ORDER BY u.created_at DESC
    ";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query($sql);
    }
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data user: " . $e->getMessage();
}

// Get statistics
$stats = [
    'total' => count($users),
    'active' => count(array_filter($users, fn($u) => $u['status'] === 'active')),
    'inactive' => count(array_filter($users, fn($u) => $u['status'] === 'inactive')),
    'admin' => count(array_filter($users, fn($u) => $u['role'] === 'admin')),
    'user' => count(array_filter($users, fn($u) => $u['role'] === 'user'))
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Users - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .status-select-user {
            padding: 0.5rem 0.75rem;
            border-radius: 15px;
            border: 2px solid transparent;
            font-size: 0.85rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .status-select-user:hover {
            border-color: var(--accent-gold);
            transform: scale(1.05);
        }
        
        .status-select-user:focus {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div>
                <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                    👥 Kelola Users
                </h1>
                <p style="margin: 0; color: var(--gray-dark);">
                    Kelola dan pantau semua pengguna sistem
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
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d4edda;">
                <div class="stat-icon" style="background: #155724; color: white;">✅</div>
                <div class="stat-number" style="color: #155724;"><?= $stats['active'] ?></div>
                <div class="stat-label" style="color: #155724;">Active</div>
            </div>
            <div class="stat-card hover-lift" style="background: #f8d7da;">
                <div class="stat-icon" style="background: #721c24; color: white;">❌</div>
                <div class="stat-number" style="color: #721c24;"><?= $stats['inactive'] ?></div>
                <div class="stat-label" style="color: #721c24;">Inactive</div>
            </div>
            <div class="stat-card hover-lift" style="background: var(--light-gold);">
                <div class="stat-icon" style="background: var(--primary-black); color: var(--accent-gold);">👨‍💼</div>
                <div class="stat-number" style="color: var(--primary-black);"><?= $stats['admin'] ?></div>
                <div class="stat-label" style="color: var(--primary-black);">Admin</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d1ecf1;">
                <div class="stat-icon" style="background: #0c5460; color: white;">👤</div>
                <div class="stat-number" style="color: #0c5460;"><?= $stats['user'] ?></div>
                <div class="stat-label" style="color: #0c5460;">Regular User</div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="card">
            <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Cari User</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama, username, email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="">Semua Role</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <a href="users.php" class="btn btn-secondary" style="margin-left: 0.5rem;">🔄 Reset</a>
                </div>
            </form>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Users</h2>
                <div style="color: var(--gray-dark);">
                    Total: <?= count($users) ?> users
                </div>
            </div>
            
            <?php if (empty($users)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">👥</div>
                    <h3>Tidak Ada User Ditemukan</h3>
                    <p style="color: var(--gray-dark);">
                        Coba ubah kriteria pencarian Anda.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User Info</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Booking</th>
                                <th>Bergabung</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong>#<?= $user['id'] ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="
                                            width: 40px;
                                            height: 40px;
                                            background: var(--gradient-gold);
                                            border-radius: 50%;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            font-size: 1.2rem;
                                        ">
                                            <?= $user['role'] === 'admin' ? '👨‍💼' : '👤' ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                            <small style="color: var(--gray-dark);">@<?= htmlspecialchars($user['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($user['email']) ?><br>
                                    <small style="color: var(--gray-dark);">
                                        <?= $user['phone'] ? htmlspecialchars($user['phone']) : 'No phone' ?>
                                    </small>
                                </td>
                                <td>
                                    <span style="
                                        padding: 0.25rem 0.75rem;
                                        border-radius: 15px;
                                        font-size: 0.8rem;
                                        font-weight: bold;
                                        background: <?= $user['role'] === 'admin' ? 'var(--gradient-gold)' : '#d1ecf1' ?>;
                                        color: <?= $user['role'] === 'admin' ? 'var(--primary-black)' : '#0c5460' ?>;
                                    ">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= $user['total_bookings'] ?></strong> total<br>
                                    <small style="color: var(--gray-dark);"><?= $user['confirmed_bookings'] ?> confirmed</small>
                                </td>
                                <td>
                                    <?= date('d M Y', strtotime($user['created_at'])) ?><br>
                                    <small style="color: var(--gray-dark);"><?= date('H:i', strtotime($user['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <select class="status-select-user status-<?= $user['status'] ?>" 
                                                data-user-id="<?= $user['id'] ?>" 
                                                data-current-status="<?= $user['status'] ?>">
                                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    <?php else: ?>
                                        <span style="
                                            padding: 0.25rem 0.75rem;
                                            border-radius: 15px;
                                            font-size: 0.8rem;
                                            font-weight: bold;
                                            background: var(--gradient-gold);
                                            color: var(--primary-black);
                                        ">
                                            Admin
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <?php if ($user['role'] !== 'admin' && $user['id'] != getUserId()): ?>
                                            <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['full_name'])) ?>')" 
                                                    class="btn btn-danger" style="padding: 0.5rem;">
                                                🗑️
                                            </button>
                                        <?php endif; ?>
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
        <input type="hidden" name="user_id" id="statusUserId">
        <input type="hidden" name="new_status" id="newUserStatus">
    </form>

    <!-- Delete Form (Hidden) -->
    <form id="deleteUserForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="deleteUserId">
    </form>

    <script src="../assets/js/script.js"></script>
    <script>
        // Status change handler - SIMPLE & WORKING VERSION
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('.status-select-user');
            
            statusSelects.forEach(function(select) {
                select.addEventListener('change', function() {
                    const userId = this.getAttribute('data-user-id');
                    const currentStatus = this.getAttribute('data-current-status');
                    const newStatus = this.value;
                    
                    // If status hasn't changed, do nothing
                    if (newStatus === currentStatus) {
                        return;
                    }
                    
                    let message = `Apakah Anda yakin ingin mengubah status user #${userId} menjadi "${newStatus}"?`;
                    
                    if (newStatus === 'inactive') {
                        message += '\n\n⚠️ User tidak akan bisa login ke sistem.';
                    } else {
                        message += '\n\n✅ User akan bisa login kembali ke sistem.';
                    }
                    
                    const selectElement = this;
                    
                    if (confirm(message)) {
                        // Update form values
                        document.getElementById('statusUserId').value = userId;
                        document.getElementById('newUserStatus').value = newStatus;
                        
                        // Submit form
                        document.getElementById('updateStatusForm').submit();
                    } else {
                        // Reset to previous value if cancelled
                        this.value = currentStatus;
                    }
                });
            });
        });

        function deleteUser(userId, userName) {
            if (confirm(`Apakah Anda yakin ingin menghapus user "${userName}"?\n\n❌ Tindakan ini tidak dapat dibatalkan.`)) {
                document.getElementById('deleteUserId').value = userId;
                document.getElementById('deleteUserForm').submit();
            }
        }
    </script>
</body>
</html>