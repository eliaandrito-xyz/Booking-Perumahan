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
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND status = 'confirmed') as confirmed_bookings,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND status = 'pending') as pending_bookings,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND status = 'cancelled') as cancelled_bookings
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            background: var(--gradient-gold);
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-black);
            font-size: 1.5rem;
        }

        .close {
            color: var(--primary-black);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            line-height: 1;
        }

        .close:hover {
            color: #721c24;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .user-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-detail-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--accent-gold);
        }

        .user-detail-label {
            font-size: 0.85rem;
            color: var(--gray-dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .user-detail-value {
            font-size: 1rem;
            color: var(--primary-black);
            font-weight: 500;
        }

        .user-avatar-large {
            width: 120px;
            height: 120px;
            background: var(--gradient-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .booking-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .booking-stat-card {
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .booking-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .booking-stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .booking-stat-label {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        @media (max-width: 768px) {
            .user-detail-grid {
                grid-template-columns: 1fr;
            }
            
            .booking-stats {
                grid-template-columns: repeat(2, 1fr);
            }
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
                                        <button onclick='showUserDetail(<?= json_encode($user) ?>)' 
                                                class="btn-view" 
                                                title="Lihat Detail">
                                            👁️
                                        </button>
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

    <!-- User Detail Modal -->
    <div id="userDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>👤 Detail User</h2>
                <span class="close" onclick="closeUserDetail()">&times;</span>
            </div>
            <div class="modal-body" id="userDetailContent">
                <!-- Content will be inserted by JavaScript -->
            </div>
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
        // Status change handler
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('.status-select-user');
            
            statusSelects.forEach(function(select) {
                select.addEventListener('change', function() {
                    const userId = this.getAttribute('data-user-id');
                    const currentStatus = this.getAttribute('data-current-status');
                    const newStatus = this.value;
                    
                    if (newStatus === currentStatus) {
                        return;
                    }
                    
                    let message = `Apakah Anda yakin ingin mengubah status user #${userId} menjadi "${newStatus}"?`;
                    
                    if (newStatus === 'inactive') {
                        message += '\n\n⚠️ User tidak akan bisa login ke sistem.';
                    } else {
                        message += '\n\n✅ User akan bisa login kembali ke sistem.';
                    }
                    
                    if (confirm(message)) {
                        document.getElementById('statusUserId').value = userId;
                        document.getElementById('newUserStatus').value = newStatus;
                        document.getElementById('updateStatusForm').submit();
                    } else {
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

        function showUserDetail(user) {
            const modal = document.getElementById('userDetailModal');
            const content = document.getElementById('userDetailContent');
            
            const statusBadge = user.status === 'active' 
                ? '<span style="padding: 0.5rem 1rem; background: #d4edda; color: #155724; border-radius: 20px; font-weight: bold;">✅ Active</span>'
                : '<span style="padding: 0.5rem 1rem; background: #f8d7da; color: #721c24; border-radius: 20px; font-weight: bold;">❌ Inactive</span>';
            
            const roleBadge = user.role === 'admin'
                ? '<span style="padding: 0.5rem 1rem; background: var(--gradient-gold); color: var(--primary-black); border-radius: 20px; font-weight: bold;">👨‍💼 Admin</span>'
                : '<span style="padding: 0.5rem 1rem; background: #d1ecf1; color: #0c5460; border-radius: 20px; font-weight: bold;">👤 User</span>';
            
            content.innerHTML = `
                <div style="text-align: center;">
                    <div class="user-avatar-large">
                        ${user.role === 'admin' ? '👨‍💼' : '👤'}
                    </div>
                    <h2 style="margin-bottom: 0.5rem;">${user.full_name}</h2>
                    <p style="color: var(--gray-dark); margin-bottom: 1rem;">@${user.username}</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; margin-bottom: 2rem;">
                        ${statusBadge}
                        ${roleBadge}
                    </div>
                </div>

                <div class="user-detail-grid">
                    <div class="user-detail-item">
                        <div class="user-detail-label">📧 Email</div>
                        <div class="user-detail-value">${user.email}</div>
                    </div>
                    <div class="user-detail-item">
                        <div class="user-detail-label">📱 Nomor Telepon</div>
                        <div class="user-detail-value">${user.phone || 'Tidak ada'}</div>
                    </div>
                    <div class="user-detail-item">
                        <div class="user-detail-label">🆔 User ID</div>
                        <div class="user-detail-value">#${user.id}</div>
                    </div>
                    <div class="user-detail-item">
                        <div class="user-detail-label">📅 Bergabung Sejak</div>
                        <div class="user-detail-value">${formatDate(user.created_at)}</div>
                    </div>
                    <div class="user-detail-item">
                        <div class="user-detail-label">🔄 Terakhir Diperbarui</div>
                        <div class="user-detail-value">${formatDate(user.updated_at)}</div>
                    </div>
                    <div class="user-detail-item">
                        <div class="user-detail-label">👤 Username</div>
                        <div class="user-detail-value">@${user.username}</div>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; color: white;">
                    <h3 style="margin: 0 0 1rem 0; color: white;">📊 Statistik Booking</h3>
                    <div class="booking-stats">
                        <div class="booking-stat-card" style="background: rgba(255,255,255,0.2);">
                            <div class="booking-stat-number" style="color: white;">${user.total_bookings}</div>
                            <div class="booking-stat-label" style="color: white;">Total</div>
                        </div>
                        <div class="booking-stat-card" style="background: rgba(46, 213, 115, 0.3);">
                            <div class="booking-stat-number" style="color: white;">${user.confirmed_bookings}</div>
                            <div class="booking-stat-label" style="color: white;">Confirmed</div>
                        </div>
                        <div class="booking-stat-card" style="background: rgba(255, 184, 0, 0.3);">
                            <div class="booking-stat-number" style="color: white;">${user.pending_bookings}</div>
                            <div class="booking-stat-label" style="color: white;">Pending</div>
                        </div>
                        <div class="booking-stat-card" style="background: rgba(255, 71, 87, 0.3);">
                            <div class="booking-stat-number" style="color: white;">${user.cancelled_bookings}</div>
                            <div class="booking-stat-label" style="color: white;">Cancelled</div>
                        </div>
                    </div>
                </div>
            `;
            
            modal.classList.add('show');
        }

        function closeUserDetail() {
            const modal = document.getElementById('userDetailModal');
            modal.classList.remove('show');
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString('id-ID', options);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('userDetailModal');
            if (event.target == modal) {
                closeUserDetail();
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeUserDetail();
            }
        });
    </script>
</body>
</html>