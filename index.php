<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db = new Database();

// Get statistics
$stats = [
    'total_projects' => 0,
    'total_units' => 0,
    'available_units' => 0,
    'total_developers' => 0
];

try {
    $result = $db->query("SELECT COUNT(*) as count FROM housing_projects WHERE status != 'planning'");
    $stats['total_projects'] = $result->fetch_assoc()['count'];
    
    $result = $db->query("SELECT COUNT(*) as count FROM house_units");
    $stats['total_units'] = $result->fetch_assoc()['count'];
    
    $result = $db->query("SELECT COUNT(*) as count FROM house_units WHERE status = 'available'");
    $stats['available_units'] = $result->fetch_assoc()['count'];
    
    $result = $db->query("SELECT COUNT(*) as count FROM developers WHERE status = 'active'");
    $stats['total_developers'] = $result->fetch_assoc()['count'];
} catch (Exception $e) {
    // Handle error silently for demo
}

// Get featured projects with their images
$featured_projects = [];
try {
    $result = $db->query("
        SELECT hp.*, d.name as developer_name,
               (SELECT COUNT(*) FROM house_units WHERE project_id = hp.id) as total_units,
               (SELECT COUNT(*) FROM house_units WHERE project_id = hp.id AND status = 'available') as available_units
        FROM housing_projects hp 
        LEFT JOIN developers d ON hp.developer_id = d.id 
        WHERE hp.status IN ('ready', 'construction') 
        ORDER BY hp.created_at DESC 
        LIMIT 6
    ");
    
    while ($row = $result->fetch_assoc()) {
        // Get first unit's front image from this project
        $img_stmt = $db->prepare("
            SELECT hui.image_path 
            FROM house_unit_images hui
            INNER JOIN house_units hu ON hui.unit_id = hu.id
            WHERE hu.project_id = ? AND hui.category = 'depan'
            LIMIT 1
        ");
        $img_stmt->bind_param("i", $row['id']);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        
        if ($img_row = $img_result->fetch_assoc()) {
            $row['image_path'] = $img_row['image_path'];
        } else {
            // Fallback: try any image from units in this project
            $img_stmt = $db->prepare("
                SELECT hui.image_path 
                FROM house_unit_images hui
                INNER JOIN house_units hu ON hui.unit_id = hu.id
                WHERE hu.project_id = ?
                LIMIT 1
            ");
            $img_stmt->bind_param("i", $row['id']);
            $img_stmt->execute();
            $img_result = $img_stmt->get_result();
            
            if ($img_row = $img_result->fetch_assoc()) {
                $row['image_path'] = $img_row['image_path'];
            } else {
                $row['image_path'] = null;
            }
        }
        
        $featured_projects[] = $row;
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get latest news
$latest_news = [];
try {
    $result = $db->query("
        SELECT * FROM news 
        WHERE status = 'published' 
        ORDER BY published_at DESC 
        LIMIT 3
    ");
    while ($row = $result->fetch_assoc()) {
        $latest_news[] = $row;
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get random featured unit images for hero section
$hero_image = 'https://cdn.antaranews.com/cache/800x533/2021/09/14/pembangunan-perumahan.jpg';
try {
    $result = $db->query("
        SELECT image_path 
        FROM house_unit_images 
        WHERE category = 'depan'
        ORDER BY RAND()
        LIMIT 1
    ");
    if ($row = $result->fetch_assoc()) {
        $hero_image = $row['image_path'];
    }
} catch (Exception $e) {
    // Use default image
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<style>
  .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    font-weight: 600;
    color: #ffd700;
}

.logo-img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

.project-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
}

.project-image-placeholder {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    font-weight: bold;
}
</style>
<body>
    <!-- Header -->
    <!-- Header -->

<!-- Mobile Menu Overlay -->
<div class="menu-overlay"></div>

    <!-- Hero Section -->
    <section class="hero" style="
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(45, 45, 45, 0.8)), 
                    url('https://cdn.antaranews.com/cache/800x533/2021/09/14/pembangunan-perumahan.jpg') center/cover;
        min-height: 70vh;
        display: flex;
        align-items: center;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    ">
        <div class="container" style="position: relative; z-index: 2;">
            <h1 style="
                font-size: 3.5rem;
                font-weight: bold;
                margin-bottom: 1rem;
                animation: fadeInUp 1s ease-out;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            ">
                Temukan Rumah Impian Anda
            </h1>
            <p style="
                font-size: 1.2rem;
                margin-bottom: 2rem;
                animation: fadeInUp 1s ease-out 0.2s both;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            ">
                Sistem Informasi Perumahan Kota Kupang - Portal terpercaya untuk menemukan hunian berkualitas di jantung Nusa Tenggara Timur
            </p>
            <div style="animation: fadeInUp 1s ease-out 0.4s both;">
                <a href="projects.php" class="btn btn-primary" style="margin-right: 1rem; font-size: 1.1rem; padding: 1rem 2rem;">
                    Jelajahi Proyek
                </a>
                <a href="units.php" class="btn btn-secondary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    Lihat Unit Tersedia
                </a>
            </div>
        </div>
        
        <!-- Animated background elements -->
        <div style="
            position: absolute;
            top: 10%;
            left: 10%;
            width: 100px;
            height: 100px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            animation: pulse 3s infinite;
        "></div>
        <div style="
            position: absolute;
            bottom: 20%;
            right: 15%;
            width: 150px;
            height: 150px;
            background: rgba(255, 215, 0, 0.05);
            border-radius: 50%;
            animation: pulse 4s infinite 1s;
        "></div>
    </section>

    <!-- Statistics Section -->
    <section class="container">
        <div class="stats-grid">
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏘️</div>
                <div class="stat-number"><?= $stats['total_projects'] ?></div>
                <div class="stat-label">Proyek Perumahan</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏠</div>
                <div class="stat-number"><?= $stats['total_units'] ?></div>
                <div class="stat-label">Total Unit</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?= $stats['available_units'] ?></div>
                <div class="stat-label">Unit Tersedia</div>
            </div>
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏢</div>
                <div class="stat-number"><?= $stats['total_developers'] ?></div>
                <div class="stat-label">Developer</div>
            </div>
        </div>
    </section>

    <!-- Featured Projects -->
    <section class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Proyek Unggulan</h2>
                <a href="projects.php" class="btn btn-primary">Lihat Semua</a>
            </div>
            
            <?php if (empty($featured_projects)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🏘️</div>
                    <h3>Belum Ada Proyek</h3>
                    <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                        Proyek perumahan akan segera hadir.
                    </p>
                </div>
            <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
                <?php foreach ($featured_projects as $project): ?>
                <div class="project-card" style="
                    background: white;
                    border-radius: 15px;
                    overflow: hidden;
                    box-shadow: var(--shadow);
                    transition: all 0.3s ease;
                    animation: fadeInUp 0.6s ease-out;
                " onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='var(--shadow-gold)'"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'">
                    
                    <?php if (!empty($project['image_path'])): ?>
                        <img src="<?= htmlspecialchars($project['image_path']) ?>" 
                             alt="<?= htmlspecialchars($project['name']) ?>"
                             class="project-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="project-image-placeholder" style="display: none;">
                            🏘️ <?= htmlspecialchars($project['name']) ?>
                        </div>
                    <?php else: ?>
                        <div class="project-image-placeholder">
                            🏘️ <?= htmlspecialchars($project['name']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="padding: 1.5rem;">
                        <h3 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                            <?= htmlspecialchars($project['name']) ?>
                        </h3>
                        <p style="color: var(--gray-dark); margin-bottom: 0.5rem;">
                            📍 <?= htmlspecialchars($project['location']) ?>
                        </p>
                        <p style="color: var(--gray-dark); margin-bottom: 1rem; font-size: 0.9rem;">
                            Developer: <?= htmlspecialchars($project['developer_name']) ?>
                        </p>
                        <p style="margin-bottom: 1rem; line-height: 1.5;">
                            <?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...
                        </p>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-weight: bold; color: var(--accent-gold); font-size: 1.1rem;">
                                Rp <?= number_format($project['price_range_min'], 0, ',', '.') ?>
                            </span>
                            <span style="
                                background: var(--gradient-gold);
                                color: var(--primary-black);
                                padding: 0.25rem 0.75rem;
                                border-radius: 15px;
                                font-size: 0.8rem;
                                font-weight: bold;
                            ">
                                <?= ucfirst($project['status']) ?>
                            </span>
                        </div>
                        
                        <div style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--gray-dark);">
                            📊 <?= $project['total_units'] ?> unit | ✅ <?= $project['available_units'] ?> tersedia
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="project_detail.php?id=<?= $project['id'] ?>" class="btn btn-primary" style="flex: 1; text-align: center;">
                                Detail
                            </a>
                            <a href="units.php?project=<?= $project['id'] ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">
                                Lihat Unit
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Latest News -->
    <section class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Berita Terbaru</h2>
                <a href="news.php" class="btn btn-primary">Lihat Semua</a>
            </div>
            
            <?php if (empty($latest_news)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📰</div>
                    <h3>Belum Ada Berita</h3>
                    <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                        Berita dan informasi terbaru akan segera hadir.
                    </p>
                </div>
            <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <?php foreach ($latest_news as $news): ?>
                <article style="
                    border: 1px solid #e0e0e0;
                    border-radius: 10px;
                    overflow: hidden;
                    transition: all 0.3s ease;
                    animation: fadeInUp 0.6s ease-out;
                " onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='var(--shadow)'"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    
                    <div style="
                        height: 150px;
                        background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(45, 45, 45, 0.8)),
                                    url('https://images.pexels.com/photos/210617/pexels-photo-210617.jpeg') center/cover;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-weight: bold;
                    ">
                        📰 Berita
                    </div>
                    
                    <div style="padding: 1.5rem;">
                        <h3 style="margin-bottom: 0.5rem; color: var(--primary-black); line-height: 1.3;">
                            <?= htmlspecialchars($news['title']) ?>
                        </h3>
                        <p style="color: var(--gray-dark); margin-bottom: 1rem; line-height: 1.5; font-size: 0.9rem;">
                            <?= htmlspecialchars($news['excerpt']) ?>
                        </p>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.8rem; color: var(--gray-dark);">
                                <?= date('d M Y', strtotime($news['published_at'])) ?>
                            </span>
                            <a href="news_detail.php?id=<?= $news['id'] ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                Baca Selengkapnya
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Call to Action -->
    <section style="
        background: var(--gradient-black);
        color: white;
        padding: 4rem 0;
        text-align: center;
        margin-top: 3rem;
    ">
        <div class="container">
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem; animation: fadeInUp 0.8s ease-out;">
                Siap Menemukan Rumah Impian?
            </h2>
            <p style="font-size: 1.1rem; margin-bottom: 2rem; animation: fadeInUp 0.8s ease-out 0.2s both; max-width: 600px; margin-left: auto; margin-right: auto;">
                Bergabunglah dengan ribuan keluarga yang telah menemukan hunian berkualitas melalui platform kami
            </p>
            <div style="animation: fadeInUp 0.8s ease-out 0.4s both;">
                <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn btn-primary" style="margin-right: 1rem; font-size: 1.1rem; padding: 1rem 2rem;">
                    Daftar Sekarang
                </a>
                <?php endif; ?>
                <a href="contact.php" class="btn btn-secondary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    Hubungi Kami
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
     <?php
  require_once 'includes/footer.php';
  ?>

    <script src="assets/js/script.js"></script>
</body>
</html>
