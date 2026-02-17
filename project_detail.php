<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db = new Database();
$project_id = $_GET['id'] ?? '';

// Get project details
$project = null;
$units = [];
$project_images = [];

if ($project_id) {
    try {
        $stmt = $db->prepare("
            SELECT hp.*, d.name as developer_name, d.description as developer_description, 
                   d.phone as developer_phone, d.email as developer_email
            FROM housing_projects hp 
            LEFT JOIN developers d ON hp.developer_id = d.id 
            WHERE hp.id = ?
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        
        if (!$project) {
            header('Location: projects.php');
            exit();
        }
        
        // Get project images from units
        $stmt = $db->prepare("
            SELECT DISTINCT hui.image_path, hui.category
            FROM house_unit_images hui
            INNER JOIN house_units hu ON hui.unit_id = hu.id
            WHERE hu.project_id = ?
            ORDER BY 
                CASE hui.category
                    WHEN 'depan' THEN 1
                    WHEN 'siteplan' THEN 2
                    WHEN 'denah_rumah' THEN 3
                    WHEN 'belakang' THEN 4
                    WHEN 'samping_kiri' THEN 5
                    WHEN 'samping_kanan' THEN 6
                    WHEN 'denah_kamar' THEN 7
                    WHEN 'denah_kamar_mandi' THEN 8
                    ELSE 9
                END
            LIMIT 10
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $project_images[] = $row;
        }
        
        // Get hero image (prioritize 'depan' category)
        $hero_image = null;
        foreach ($project_images as $img) {
            if ($img['category'] === 'depan') {
                $hero_image = $img['image_path'];
                break;
            }
        }
        if (!$hero_image && !empty($project_images)) {
            $hero_image = $project_images[0]['image_path'];
        }
        
        // Get project units with images
        $stmt = $db->prepare("
            SELECT hu.*,
                   (SELECT image_path FROM house_unit_images WHERE unit_id = hu.id AND category = 'depan' LIMIT 1) as front_image,
                   (SELECT COUNT(*) FROM house_unit_images WHERE unit_id = hu.id) as image_count
            FROM house_units hu
            WHERE hu.project_id = ? 
            ORDER BY hu.unit_number ASC
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        
    } catch (Exception $e) {
        $error = "Terjadi kesalahan saat mengambil data proyek.";
    }
} else {
    header('Location: projects.php');
    exit();
}

// Get unit statistics
$unit_stats = [
    'total' => count($units),
    'available' => count(array_filter($units, fn($u) => $u['status'] === 'available')),
    'booked' => count(array_filter($units, fn($u) => $u['status'] === 'booked')),
    'sold' => count(array_filter($units, fn($u) => $u['status'] === 'sold'))
];

// Category labels
$category_labels = [
    'depan' => 'Tampak Depan',
    'belakang' => 'Tampak Belakang',
    'samping_kiri' => 'Tampak Samping Kiri',
    'samping_kanan' => 'Tampak Samping Kanan',
    'denah_rumah' => 'Denah Rumah',
    'denah_kamar' => 'Denah Kamar',
    'denah_kamar_mandi' => 'Denah Kamar Mandi',
    'siteplan' => 'Siteplan'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['name']) ?> - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
            box-shadow: var(--shadow);
        }
        
        .gallery-item:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-gold);
        }
        
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        
        .gallery-item-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            padding: 1rem 0.75rem 0.5rem;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .lightbox.active {
            display: flex;
        }
        
        .lightbox-content {
            max-width: 90%;
            max-height: 90vh;
            position: relative;
        }
        
        .lightbox-content img {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
        }
        
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 40px;
            color: white;
            cursor: pointer;
            z-index: 10001;
            background: rgba(0,0,0,0.5);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .lightbox-close:hover {
            background: var(--accent-gold);
            color: var(--primary-black);
        }
        
        .lightbox-caption {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: bold;
        }
        
        .unit-card {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            background: white;
            overflow: hidden;
        }
        
        .unit-card:hover {
            border-color: var(--accent-gold);
            transform: translateY(-5px);
            box-shadow: var(--shadow-gold);
        }
        
        .unit-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .unit-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7));
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav style="color: var(--gray-dark); font-size: 0.9rem;">
                <a href="index.php" style="color: var(--gray-dark); text-decoration: none;">Beranda</a>
                <span style="margin: 0 0.5rem;">›</span>
                <a href="projects.php" style="color: var(--gray-dark); text-decoration: none;">Proyek</a>
                <span style="margin: 0 0.5rem;">›</span>
                <span style="color: var(--accent-gold); font-weight: bold;"><?= htmlspecialchars($project['name']) ?></span>
            </nav>
        </div>

        <!-- Project Hero -->
        <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
            <div style="
                height: 400px;
                background: linear-gradient(135deg, rgba(26, 26, 26, 0.6), rgba(45, 45, 45, 0.6)),
                            url('<?= $hero_image ? htmlspecialchars($hero_image) : 'https://images.pexels.com/photos/280222/pexels-photo-280222.jpeg' ?>') center/cover;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                text-align: center;
                position: relative;
            ">
                <div style="
                    position: absolute;
                    top: 2rem;
                    right: 2rem;
                    background: var(--gradient-gold);
                    color: var(--primary-black);
                    padding: 1rem 2rem;
                    border-radius: 25px;
                    font-weight: bold;
                    font-size: 1.1rem;
                ">
                    <?= ucfirst($project['status']) ?>
                </div>
                
                <div>
                    <h1 style="font-size: 3rem; margin-bottom: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                        <?= htmlspecialchars($project['name']) ?>
                    </h1>
                    <p style="font-size: 1.2rem; margin-bottom: 2rem;">
                        📍 <?= htmlspecialchars($project['location']) ?>
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="#units" class="btn btn-primary" style="padding: 1rem 2rem;">
                            🏠 Lihat Unit
                        </a>
                        <?php if (isLoggedIn()): ?>
                        <a href="contact.php" class="btn btn-secondary" style="padding: 1rem 2rem;">
                            📞 Hubungi Marketing
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Info Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; margin-bottom: 3rem;">
            <!-- Main Info -->
            <div>
                <!-- Description -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Deskripsi Proyek</h2>
                    </div>
                    <p style="line-height: 1.8; color: var(--gray-dark); font-size: 1.1rem;">
                        <?= nl2br(htmlspecialchars($project['description'])) ?>
                    </p>
                </div>

                <!-- Image Gallery -->
                <?php if (!empty($project_images)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📷 Galeri Foto Proyek</h2>
                    </div>
                    
                    <div class="image-gallery">
                        <?php foreach ($project_images as $image): ?>
                        <div class="gallery-item" onclick="openLightbox('<?= htmlspecialchars($image['image_path']) ?>', '<?= htmlspecialchars($category_labels[$image['category']] ?? $image['category']) ?>')">
                            <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($category_labels[$image['category']] ?? $image['category']) ?>"
                                 onerror="this.src='https://via.placeholder.com/250x200?text=No+Image'">
                            <div class="gallery-item-label">
                                <?= htmlspecialchars($category_labels[$image['category']] ?? $image['category']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Facilities -->
                <?php if (!empty($project['facilities'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🏆 Fasilitas</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <?php 
                        $facilities = explode(',', $project['facilities']);
                        foreach ($facilities as $facility): 
                        ?>
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 1rem;
                            padding: 1rem;
                            background: var(--gray-light);
                            border-radius: 10px;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.background='var(--light-gold)'"
                           onmouseout="this.style.background='var(--gray-light)'">
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
                                ✓
                            </div>
                            <span style="font-weight: 500;"><?= htmlspecialchars(trim($facility)) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar Info -->
            <div>
                <!-- Project Stats -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informasi Proyek</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Total Unit:</span>
                            <span><?= $project['total_units'] ?> unit</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Unit Tersedia:</span>
                            <span style="color: var(--accent-gold); font-weight: bold;"><?= $project['available_units'] ?> unit</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Harga Mulai:</span>
                            <span style="color: var(--accent-gold); font-weight: bold;">
                                Rp <?= number_format($project['price_range_min'], 0, ',', '.') ?>
                            </span>
                        </div>
                        
                        <?php if ($project['completion_date']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Target Selesai:</span>
                            <span><?= date('M Y', strtotime($project['completion_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Developer Info -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Developer</h2>
                    </div>
                    
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <div style="
                            width: 80px;
                            height: 80px;
                            background: var(--gradient-gold);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 1rem;
                            font-size: 2rem;
                        ">
                            🏢
                        </div>
                        <h3 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($project['developer_name']) ?></h3>
                    </div>
                    
                    <?php if ($project['developer_description']): ?>
                    <p style="margin-bottom: 1rem; line-height: 1.6; color: var(--gray-dark); font-size: 0.9rem;">
                        <?= htmlspecialchars($project['developer_description']) ?>
                    </p>
                    <?php endif; ?>
                    
                    <div style="space-y: 0.5rem;">
                        <?php if ($project['developer_phone']): ?>
                        <p style="margin: 0.25rem 0; color: var(--gray-dark); font-size: 0.9rem;">
                            📞 <?= htmlspecialchars($project['developer_phone']) ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($project['developer_email']): ?>
                        <p style="margin: 0.25rem 0; color: var(--gray-dark); font-size: 0.9rem;">
                            ✉️ <?= htmlspecialchars($project['developer_email']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Unit Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Statistik Unit</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: #d4edda; border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #155724;"><?= $unit_stats['available'] ?></div>
                            <div style="font-size: 0.9rem; color: #155724;">Tersedia</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #fff3cd; border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #856404;"><?= $unit_stats['booked'] ?></div>
                            <div style="font-size: 0.9rem; color: #856404;">Booking</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #f8d7da; border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: #721c24;"><?= $unit_stats['sold'] ?></div>
                            <div style="font-size: 0.9rem; color: #721c24;">Terjual</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: var(--light-gold); border-radius: 10px;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-black);"><?= $unit_stats['total'] ?></div>
                            <div style="font-size: 0.9rem; color: var(--primary-black);">Total</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Units Section -->
        <div class="card" id="units">
            <div class="card-header">
                <h2 class="card-title">🏠 Unit Tersedia</h2>
                <a href="units.php?project=<?= $project['id'] ?>" class="btn btn-primary">
                    Lihat Semua Unit
                </a>
            </div>
            
            <?php if (empty($units)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--gray-dark);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🏠</div>
                    <h3>Belum Ada Unit</h3>
                    <p>Unit untuk proyek ini sedang dalam tahap perencanaan.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <?php 
                    $displayed_units = array_slice($units, 0, 6); // Show only first 6 units
                    foreach ($displayed_units as $unit): 
                    ?>
                    <div class="unit-card">
                        <!-- Unit Image -->
                        <?php if (!empty($unit['front_image'])): ?>
                            <img src="<?= htmlspecialchars($unit['front_image']) ?>" 
                                 alt="Unit <?= htmlspecialchars($unit['unit_number']) ?>"
                                 class="unit-image"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="unit-image-placeholder" style="display: none;">
                                🏠 Unit <?= htmlspecialchars($unit['unit_number']) ?>
                            </div>
                        <?php else: ?>
                            <div class="unit-image-placeholder">
                                🏠 Unit <?= htmlspecialchars($unit['unit_number']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="margin: 0; color: var(--primary-black);">
                                Unit <?= htmlspecialchars($unit['unit_number']) ?>
                            </h3>
                            <span style="
                                padding: 0.25rem 0.75rem;
                                border-radius: 15px;
                                font-size: 0.8rem;
                                font-weight: bold;
                                background: <?= 
                                    $unit['status'] === 'available' ? '#d4edda' : 
                                    ($unit['status'] === 'booked' ? '#fff3cd' : '#f8d7da') 
                                ?>;
                                color: <?= 
                                    $unit['status'] === 'available' ? '#155724' : 
                                    ($unit['status'] === 'booked' ? '#856404' : '#721c24') 
                                ?>;
                            ">
                                <?= ucfirst($unit['status']) ?>
                            </span>
                        </div>
                        
                        <div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                            <span style="
                                background: var(--gradient-gold);
                                color: var(--primary-black);
                                padding: 0.5rem 1rem;
                                border-radius: 20px;
                                font-weight: bold;
                                font-size: 0.9rem;
                            ">
                                <?= htmlspecialchars($unit['type']) ?>
                            </span>
                            <?php if ($unit['image_count'] > 0): ?>
                            <span style="font-size: 0.85rem; color: var(--gray-dark);">
                                📷 <?= $unit['image_count'] ?> foto
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div style="text-align: center; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                <div style="font-size: 1.2rem; margin-bottom: 0.25rem;">🛏️</div>
                                <div style="font-weight: bold;"><?= $unit['bedrooms'] ?></div>
                                <div style="font-size: 0.8rem; color: var(--gray-dark);">Kamar</div>
                            </div>
                            <div style="text-align: center; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                <div style="font-size: 1.2rem; margin-bottom: 0.25rem;">🚿</div>
                                <div style="font-weight: bold;"><?= $unit['bathrooms'] ?></div>
                                <div style="font-size: 0.8rem; color: var(--gray-dark);">Kamar Mandi</div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div style="text-align: center; padding: 0.75rem; background: var(--light-gold); border-radius: 10px;">
                                <div style="font-size: 1rem; margin-bottom: 0.25rem;">📐</div>
                                <div style="font-weight: bold;"><?= $unit['land_area'] ?> m²</div>
                                <div style="font-size: 0.8rem; color: var(--gray-dark);">Luas Tanah</div>
                            </div>
                            <div style="text-align: center; padding: 0.75rem; background: var(--light-gold); border-radius: 10px;">
                                <div style="font-size: 1rem; margin-bottom: 0.25rem;">🏠</div>
                                <div style="font-weight: bold;"><?= $unit['building_area'] ?> m²</div>
                                <div style="font-size: 0.8rem; color: var(--gray-dark);">Luas Bangunan</div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-bottom: 1rem; padding: 1rem; background: var(--gradient-gold); border-radius: 10px;">
                            <div style="font-size: 1.2rem; font-weight: bold; color: var(--primary-black);">
                                Rp <?= number_format($unit['price'], 0, ',', '.') ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="unit_detail.php?id=<?= $unit['id'] ?>" class="btn btn-secondary" style="flex: 1; text-align: center; padding: 0.75rem;">
                                Detail
                            </a>
                            <?php if ($unit['status'] === 'available' && isLoggedIn()): ?>
                                <a href="booking.php?unit=<?= $unit['id'] ?>" class="btn btn-primary" style="flex: 1; text-align: center; padding: 0.75rem;">
                                    Booking
                                </a>
                            <?php elseif ($unit['status'] === 'available'): ?>
                                <a href="login.php" class="btn btn-primary" style="flex: 1; text-align: center; padding: 0.75rem;">
                                    Login
                                </a>
                            <?php else: ?>
                                <button class="btn" style="flex: 1; text-align: center; padding: 0.75rem; background: #ccc; color: #666; cursor: not-allowed;" disabled>
                                    Tidak Tersedia
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($units) > 6): ?>
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="units.php?project=<?= $project['id'] ?>" class="btn btn-primary" style="padding: 1rem 2rem;">
                        Lihat <?= count($units) - 6 ?> Unit Lainnya
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <img id="lightbox-image" src="" alt="">
            <div class="lightbox-caption" id="lightbox-caption"></div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        // Smooth scroll to units section
        document.addEventListener('DOMContentLoaded', function() {
            const unitsLink = document.querySelector('a[href="#units"]');
            if (unitsLink) {
                unitsLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('units').scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            }
        });
        
        // Lightbox functions
        function openLightbox(imagePath, caption) {
            const lightbox = document.getElementById('lightbox');
            const lightboxImage = document.getElementById('lightbox-image');
            const lightboxCaption = document.getElementById('lightbox-caption');
            
            lightboxImage.src = imagePath;
            lightboxCaption.textContent = caption;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close lightbox with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>
</body>
</html>