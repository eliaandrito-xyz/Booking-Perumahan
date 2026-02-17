<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

requireLogin();

$db = new Database();
$user_id = getUserId();
$is_admin = isAdmin();

// Get user statistics
$stats = [
    'total_bookings' => 0,
    'pending_bookings' => 0,
    'confirmed_bookings' => 0,
    'total_projects' => 0
];

try {
    if ($is_admin) {
        // Admin statistics
        $result = $db->query("SELECT COUNT(*) as count FROM bookings");
        $stats['total_bookings'] = $result->fetch_assoc()['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
        $stats['pending_bookings'] = $result->fetch_assoc()['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'");
        $stats['confirmed_bookings'] = $result->fetch_assoc()['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM housing_projects");
        $stats['total_projects'] = $result->fetch_assoc()['count'];
    } else {
        // User statistics
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats['total_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats['pending_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status = 'confirmed'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats['confirmed_bookings'] = $stmt->get_result()->fetch_assoc()['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM housing_projects WHERE status IN ('ready', 'construction')");
        $stats['total_projects'] = $result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get recent bookings
$recent_bookings = [];
try {
    if ($is_admin) {
        $result = $db->query("
            SELECT b.*, hu.unit_number, hp.name as project_name, u.full_name as user_name
            FROM bookings b
            LEFT JOIN house_units hu ON b.unit_id = hu.id
            LEFT JOIN housing_projects hp ON hu.project_id = hp.id
            LEFT JOIN users u ON b.user_id = u.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
    } else {
        $stmt = $db->prepare("
            SELECT b.*, hu.unit_number, hp.name as project_name
            FROM bookings b
            LEFT JOIN house_units hu ON b.unit_id = hu.id
            LEFT JOIN housing_projects hp ON hu.project_id = hp.id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    while ($row = $result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }
} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>


    <div class="container">
        <!-- Welcome Section -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                <div style="
                    width: 60px;
                    height: 60px;
                    background: var(--gradient-gold);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.5rem;
                    animation: pulse 2s infinite;
                ">
                    👋
                </div>
                <div>
                    <h1 style="margin: 0; color: var(--primary-black);">
                        Selamat Datang, <?= htmlspecialchars(getUserName()) ?>!
                    </h1>
                    <p style="margin: 0; color: var(--gray-dark);">
                        <?= $is_admin ? 'Administrator' : 'User' ?> - Dashboard Sistem Informasi Perumahan Kupang
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card hover-lift">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?= $stats['total_bookings'] ?></div>
                <div class="stat-label"><?= $is_admin ? 'Total Booking' : 'Booking Saya' ?></div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?= $stats['pending_bookings'] ?></div>
                <div class="stat-label">Booking Pending</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?= $stats['confirmed_bookings'] ?></div>
                <div class="stat-label">Booking Terkonfirmasi</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏘️</div>
                <div class="stat-number"><?= $stats['total_projects'] ?></div>
                <div class="stat-label">Proyek Tersedia</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Aksi Cepat</h2>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <?php if ($is_admin): ?>
                    <a href="admin/projects.php" class="btn btn-primary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏘️</div>
                        <div>Kelola Proyek</div>
                    </a>
                    <a href="admin/units.php" class="btn btn-secondary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏠</div>
                        <div>Kelola Unit</div>
                    </a>
                    <a href="admin/bookings.php" class="btn btn-primary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                        <div>Kelola Booking</div>
                    </a>
                    <a href="admin/users.php" class="btn btn-secondary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">👥</div>
                        <div>Kelola User</div>
                    </a>
                <?php else: ?>
                    <a href="projects.php" class="btn btn-primary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏘️</div>
                        <div>Lihat Proyek</div>
                    </a>
                    <a href="units.php" class="btn btn-secondary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏠</div>
                        <div>Cari Unit</div>
                    </a>
                    <a href="my_bookings.php" class="btn btn-primary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                        <div>Booking Saya</div>
                    </a>
                    <a href="profile.php" class="btn btn-secondary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">👤</div>
                        <div>Profil Saya</div>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $is_admin ? 'Booking Terbaru' : 'Booking Saya' ?></h2>
                <a href="<?= $is_admin ? 'admin/bookings.php' : 'my_bookings.php' ?>" class="btn btn-primary">
                    Lihat Semua
                </a>
            </div>
            
            <?php if (empty($recent_bookings)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--gray-dark);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                    <h3>Belum Ada Booking</h3>
                    <p>Belum ada data booking yang tersedia.</p>
                    <?php if (!$is_admin): ?>
                        <a href="units.php" class="btn btn-primary" style="margin-top: 1rem;">
                            Mulai Booking
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Proyek</th>
                                <th>Unit</th>
                                <?php if ($is_admin): ?>
                                    <th>Customer</th>
                                <?php endif; ?>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                            <tr>
                                <td>#<?= $booking['id'] ?></td>
                                <td><?= htmlspecialchars($booking['project_name']) ?></td>
                                <td><?= htmlspecialchars($booking['unit_number']) ?></td>
                                <?php if ($is_admin): ?>
                                    <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                <?php endif; ?>
                                <td><?= date('d/m/Y', strtotime($booking['booking_date'])) ?></td>
                                <td>
                                    <span style="
                                        padding: 0.25rem 0.75rem;
                                        border-radius: 15px;
                                        font-size: 0.8rem;
                                        font-weight: bold;
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
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= $is_admin ? 'admin/booking_detail.php' : 'booking_detail.php' ?>?id=<?= $booking['id'] ?>" 
                                       class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>