<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();

// Get comprehensive statistics
$stats = [
    'total_users' => 0,
    'total_projects' => 0,
    'total_units' => 0,
    'total_bookings' => 0,
    'pending_bookings' => 0,
    'confirmed_bookings' => 0,
    'total_developers' => 0,
    'total_news' => 0,
    'unread_messages' => 0,
    'revenue_this_month' => 0
];

try {
    // Users
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Projects
    $result = $db->query("SELECT COUNT(*) as count FROM housing_projects");
    $stats['total_projects'] = $result->fetch_assoc()['count'];
    
    // Units
    $result = $db->query("SELECT COUNT(*) as count FROM house_units");
    $stats['total_units'] = $result->fetch_assoc()['count'];
    
    // Bookings
    $result = $db->query("SELECT COUNT(*) as count FROM bookings");
    $stats['total_bookings'] = $result->fetch_assoc()['count'];
    
    $result = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
    $stats['pending_bookings'] = $result->fetch_assoc()['count'];
    
    $result = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'");
    $stats['confirmed_bookings'] = $result->fetch_assoc()['count'];
    
    // Developers
    $result = $db->query("SELECT COUNT(*) as count FROM developers WHERE status = 'active'");
    $stats['total_developers'] = $result->fetch_assoc()['count'];
    
    // News
    $result = $db->query("SELECT COUNT(*) as count FROM news");
    $stats['total_news'] = $result->fetch_assoc()['count'];
    
    // Messages
    $result = $db->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread'");
    $stats['unread_messages'] = $result->fetch_assoc()['count'];
    
    // Revenue this month
    $result = $db->query("SELECT SUM(booking_fee) as total FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $revenue = $result->fetch_assoc()['total'];
    $stats['revenue_this_month'] = $revenue ? $revenue : 0;
    
} catch (Exception $e) {
    // Handle error silently
}

// Get recent activities
$recent_bookings = [];
$recent_users = [];
$recent_messages = [];

try {
    // Recent bookings
    $result = $db->query("
        SELECT b.*, hu.unit_number, hp.name as project_name, u.full_name as user_name
        FROM bookings b
        LEFT JOIN house_units hu ON b.unit_id = hu.id
        LEFT JOIN housing_projects hp ON hu.project_id = hp.id
        LEFT JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }
    
    // Recent users
    $result = $db->query("
        SELECT * FROM users 
        WHERE role = 'user' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
    
    // Recent messages
    $result = $db->query("
        SELECT * FROM contact_messages 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) {
        $recent_messages[] = $row;
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
    <title>Admin Dashboard - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
    <!-- Header -->
   

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
                    👨‍💼
                </div>
                <div>
                    <h1 style="margin: 0; color: var(--primary-black);">
                        Dashboard Administrator
                    </h1>
                    <p style="margin: 0; color: var(--gray-dark);">
                        Selamat datang, <?= htmlspecialchars(getUserName()) ?>! - Kelola sistem perumahan Kota Kupang
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Statistics -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 3rem;">
            <div class="stat-card hover-lift">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏘️</div>
                <div class="stat-number"><?= $stats['total_projects'] ?></div>
                <div class="stat-label">Proyek</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏠</div>
                <div class="stat-number"><?= $stats['total_units'] ?></div>
                <div class="stat-label">Unit Rumah</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?= $stats['total_bookings'] ?></div>
                <div class="stat-label">Total Booking</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?= $stats['pending_bookings'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?= $stats['confirmed_bookings'] ?></div>
                <div class="stat-label">Terkonfirmasi</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏢</div>
                <div class="stat-number"><?= $stats['total_developers'] ?></div>
                <div class="stat-label">Developer</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">📰</div>
                <div class="stat-number"><?= $stats['total_news'] ?></div>
                <div class="stat-label">Berita</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">💬</div>
                <div class="stat-number"><?= $stats['unread_messages'] ?></div>
                <div class="stat-label">Pesan Baru</div>
            </div>
           <div class="stat-card hover-lift" style="background: var(--gradient-gold); color: var(--primary-black);">
    <div class="stat-icon" style="background: var(--primary-black); color: var(--accent-gold);">💰</div>

    <div class="stat-number" style="font-weight: 700; line-height: 1;">
        <span style="font-size: 0.9rem; vertical-align: top;">Rp</span>
        <span style="font-size: 1.4rem;">
            <?= number_format($stats['revenue_this_month'], 0, ',', '.') ?>
        </span>
    </div>

    <div class="stat-label">Revenue Bulan Ini</div>
</div>

        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Aksi Cepat</h2>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="projects.php?action=add" class="btn btn-primary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">➕</div>
                    <div>Tambah Proyek</div>
                </a>
                <a href="units.php?action=add" class="btn btn-secondary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏠</div>
                    <div>Tambah Unit</div>
                </a>
                <a href="bookings.php?status=pending" class="btn btn-primary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏳</div>
                    <div>Review Booking</div>
                </a>
                <a href="news.php?action=add" class="btn btn-secondary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📝</div>
                    <div>Tulis Berita</div>
                </a>
                <a href="developers.php" class="btn btn-primary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏢</div>
                    <div>Kelola Developer</div>
                </a>
                <a href="messages.php" class="btn btn-secondary hover-lift" style="padding: 1.5rem; text-align: center; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">💬</div>
                    <div>Pesan Kontak</div>
                </a>
            </div>
        </div>

        <!-- Recent Activities Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
            <!-- Recent Bookings -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Booking Terbaru</h2>
                    <a href="bookings.php" class="btn btn-primary">Lihat Semua</a>
                </div>
                
                <?php if (empty($recent_bookings)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--gray-dark);">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">📋</div>
                        <p>Belum ada booking terbaru</p>
                    </div>
                <?php else: ?>
                    <div style="space-y: 1rem;">
                        <?php foreach ($recent_bookings as $booking): ?>
                        <div style="
                            padding: 1rem;
                            border: 1px solid #e0e0e0;
                            border-radius: 10px;
                            margin-bottom: 1rem;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.borderColor='var(--accent-gold)'"
                           onmouseout="this.style.borderColor='#e0e0e0'">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                <div>
                                    <h4 style="margin: 0; color: var(--primary-black);">
                                        Booking #<?= $booking['id'] ?>
                                    </h4>
                                    <p style="margin: 0; color: var(--gray-dark); font-size: 0.9rem;">
                                        <?= htmlspecialchars($booking['user_name']) ?>
                                    </p>
                                </div>
                                <span style="
                                    padding: 0.25rem 0.75rem;
                                    border-radius: 15px;
                                    font-size: 0.8rem;
                                    font-weight: bold;
                                    background: <?= 
                                        $booking['status'] === 'confirmed' ? '#d4edda' : 
                                        ($booking['status'] === 'pending' ? '#fff3cd' : '#f8d7da') 
                                    ?>;
                                    color: <?= 
                                        $booking['status'] === 'confirmed' ? '#155724' : 
                                        ($booking['status'] === 'pending' ? '#856404' : '#721c24') 
                                    ?>;
                                ">
                                    <?= ucfirst($booking['status']) ?>
                                </span>
                            </div>
                            <p style="margin: 0; color: var(--gray-dark); font-size: 0.9rem;">
                                Unit <?= htmlspecialchars($booking['unit_number']) ?> - <?= htmlspecialchars($booking['project_name']) ?>
                            </p>
                            <p style="margin: 0; color: var(--gray-dark); font-size: 0.8rem;">
                                <?= date('d M Y H:i', strtotime($booking['created_at'])) ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Users -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">User Terbaru</h2>
                    <a href="users.php" class="btn btn-primary">Lihat Semua</a>
                </div>
                
                <?php if (empty($recent_users)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--gray-dark);">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">👥</div>
                        <p>Belum ada user terbaru</p>
                    </div>
                <?php else: ?>
                    <div style="space-y: 1rem;">
                        <?php foreach ($recent_users as $user): ?>
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 1rem;
                            padding: 1rem;
                            border: 1px solid #e0e0e0;
                            border-radius: 10px;
                            margin-bottom: 1rem;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.borderColor='var(--accent-gold)'"
                           onmouseout="this.style.borderColor='#e0e0e0'">
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
                                👤
                            </div>
                            <div style="flex: 1;">
                                <h4 style="margin: 0; color: var(--primary-black);">
                                    <?= htmlspecialchars($user['full_name']) ?>
                                </h4>
                                <p style="margin: 0; color: var(--gray-dark); font-size: 0.9rem;">
                                    <?= htmlspecialchars($user['email']) ?>
                                </p>
                                <p style="margin: 0; color: var(--gray-dark); font-size: 0.8rem;">
                                    <?= date('d M Y', strtotime($user['created_at'])) ?>
                                </p>
                            </div>
                            <span style="
                                padding: 0.25rem 0.75rem;
                                border-radius: 15px;
                                font-size: 0.8rem;
                                font-weight: bold;
                                background: <?= $user['status'] === 'active' ? '#d4edda' : '#f8d7da' ?>;
                                color: <?= $user['status'] === 'active' ? '#155724' : '#721c24' ?>;
                            ">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Messages -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Pesan Kontak Terbaru</h2>
                    <a href="messages.php" class="btn btn-primary">Lihat Semua</a>
                </div>
                
                <?php if (empty($recent_messages)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--gray-dark);">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">💬</div>
                        <p>Belum ada pesan terbaru</p>
                    </div>
                <?php else: ?>
                    <div style="space-y: 1rem;">
                        <?php foreach ($recent_messages as $message): ?>
                        <div style="
                            padding: 1rem;
                            border: 1px solid #e0e0e0;
                            border-radius: 10px;
                            margin-bottom: 1rem;
                            transition: all 0.3s ease;
                            <?= $message['status'] === 'unread' ? 'border-left: 4px solid var(--accent-gold);' : '' ?>
                        " onmouseover="this.style.borderColor='var(--accent-gold)'"
                           onmouseout="this.style.borderColor='#e0e0e0'">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                <div>
                                    <h4 style="margin: 0; color: var(--primary-black);">
                                        <?= htmlspecialchars($message['name']) ?>
                                    </h4>
                                    <p style="margin: 0; color: var(--gray-dark); font-size: 0.9rem;">
                                        <?= htmlspecialchars($message['subject']) ?>
                                    </p>
                                </div>
                                <span style="
                                    padding: 0.25rem 0.75rem;
                                    border-radius: 15px;
                                    font-size: 0.8rem;
                                    font-weight: bold;
                                    background: <?= 
                                        $message['status'] === 'unread' ? '#fff3cd' : 
                                        ($message['status'] === 'read' ? '#d1ecf1' : '#d4edda') 
                                    ?>;
                                    color: <?= 
                                        $message['status'] === 'unread' ? '#856404' : 
                                        ($message['status'] === 'read' ? '#0c5460' : '#155724') 
                                    ?>;
                                ">
                                    <?= ucfirst($message['status']) ?>
                                </span>
                            </div>
                            <p style="margin: 0; color: var(--gray-dark); font-size: 0.9rem; line-height: 1.4;">
                                <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...
                            </p>
                            <p style="margin: 0; color: var(--gray-dark); font-size: 0.8rem; margin-top: 0.5rem;">
                                <?= date('d M Y H:i', strtotime($message['created_at'])) ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>