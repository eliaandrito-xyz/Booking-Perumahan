<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db = new Database();
$unit_id = $_GET['id'] ?? '';

// Get unit details
$unit = null;
$unit_images = [];
if ($unit_id) {
    try {
        $stmt = $db->prepare("
            SELECT hu.*, hp.name as project_name, hp.location, hp.description as project_description,
                   hp.facilities, d.name as developer_name
            FROM house_units hu 
            LEFT JOIN housing_projects hp ON hu.project_id = hp.id
            LEFT JOIN developers d ON hp.developer_id = d.id
            WHERE hu.id = ?
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $unit = $result->fetch_assoc();
        
        if (!$unit) {
            header('Location: units.php');
            exit();
        }
        
        // Get unit images
        $stmt = $db->prepare("
            SELECT * FROM house_unit_images 
            WHERE unit_id = ? 
            ORDER BY 
                CASE category
                    WHEN 'depan' THEN 1
                    WHEN 'belakang' THEN 2
                    WHEN 'samping_kiri' THEN 3
                    WHEN 'samping_kanan' THEN 4
                    WHEN 'denah_rumah' THEN 5
                    WHEN 'denah_kamar' THEN 6
                    WHEN 'denah_kamar_mandi' THEN 7
                    WHEN 'siteplan' THEN 8
                    ELSE 9
                END
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $unit_images[] = $row;
        }
        
        // Get hero image (prioritize 'depan' category)
        $hero_image = null;
        foreach ($unit_images as $img) {
            if ($img['category'] === 'depan') {
                $hero_image = $img['image_path'];
                break;
            }
        }
        if (!$hero_image && !empty($unit_images)) {
            $hero_image = $unit_images[0]['image_path'];
        }
        
    } catch (Exception $e) {
        $error = "Terjadi kesalahan saat mengambil data unit.";
    }
} else {
    header('Location: units.php');
    exit();
}

// Get similar units
$similar_units = [];
try {
    $stmt = $db->prepare("
        SELECT hu.*, hp.name as project_name, hp.location,
               (SELECT image_path FROM house_unit_images WHERE unit_id = hu.id AND category = 'depan' LIMIT 1) as front_image
        FROM house_units hu 
        LEFT JOIN housing_projects hp ON hu.project_id = hp.id
        WHERE hu.project_id = ? AND hu.id != ? AND hu.status = 'available'
        ORDER BY ABS(hu.price - ?) ASC
        LIMIT 3
    ");
    $stmt->bind_param("iid", $unit['project_id'], $unit_id, $unit['price']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $similar_units[] = $row;
    }
} catch (Exception $e) {
    // Handle error silently
}

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
    <title>Unit <?= htmlspecialchars($unit['unit_number']) ?> - <?= htmlspecialchars($unit['project_name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            height: 150px;
            object-fit: cover;
            display: block;
        }
        
        .gallery-item-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            color: white;
            padding: 1rem 0.5rem 0.5rem;
            font-size: 0.75rem;
            font-weight: bold;
            text-align: center;
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
        
        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 24px;
            transition: all 0.3s ease;
        }
        
        .lightbox-nav:hover {
            background: var(--accent-gold);
            color: var(--primary-black);
        }
        
        .lightbox-prev {
            left: 30px;
        }
        
        .lightbox-next {
            right: 30px;
        }
        
        .similar-unit-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .similar-unit-placeholder {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7));
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
                <a href="units.php" style="color: var(--gray-dark); text-decoration: none;">Unit Rumah</a>
                <span style="margin: 0 0.5rem;">›</span>
                <a href="project_detail.php?id=<?= $unit['project_id'] ?>" style="color: var(--gray-dark); text-decoration: none;"><?= htmlspecialchars($unit['project_name']) ?></a>
                <span style="margin: 0 0.5rem;">›</span>
                <span style="color: var(--accent-gold); font-weight: bold;">Unit <?= htmlspecialchars($unit['unit_number']) ?></span>
            </nav>
        </div>

        <!-- Unit Hero -->
        <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
            <div style="
                height: 350px;
                background: linear-gradient(135deg, rgba(26, 26, 26, 0.6), rgba(45, 45, 45, 0.6)),
                            url('<?= $hero_image ? htmlspecialchars($hero_image) : 'https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg' ?>') center/cover;
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
                    background: <?= 
                        $unit['status'] === 'available' ? 'var(--gradient-gold)' : 
                        ($unit['status'] === 'booked' ? '#fff3cd' : '#f8d7da') 
                    ?>;
                    color: <?= 
                        $unit['status'] === 'available' ? 'var(--primary-black)' : 
                        ($unit['status'] === 'booked' ? '#856404' : '#721c24') 
                    ?>;
                    padding: 1rem 2rem;
                    border-radius: 25px;
                    font-weight: bold;
                    font-size: 1.1rem;
                ">
                    <?= ucfirst($unit['status']) ?>
                </div>
                
                <?php if (count($unit_images) > 0): ?>
                <div style="
                    position: absolute;
                    top: 2rem;
                    left: 2rem;
                    background: rgba(0, 0, 0, 0.7);
                    color: white;
                    padding: 0.75rem 1.5rem;
                    border-radius: 25px;
                    font-weight: bold;
                    font-size: 0.9rem;
                ">
                    📷 <?= count($unit_images) ?> Foto
                </div>
                <?php endif; ?>
                
                <div>
                    <h1 style="font-size: 3rem; margin-bottom: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                        Unit <?= htmlspecialchars($unit['unit_number']) ?>
                    </h1>
                    <p style="font-size: 1.2rem; margin-bottom: 0.5rem;">
                        <?= htmlspecialchars($unit['type']) ?>
                    </p>
                    <p style="font-size: 1.1rem; margin-bottom: 2rem;">
                        📍 <?= htmlspecialchars($unit['project_name']) ?>, <?= htmlspecialchars($unit['location']) ?>
                    </p>
                    <div style="font-size: 2rem; font-weight: bold; color: var(--accent-gold);">
                        Rp <?= number_format($unit['price'], 0, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit Details Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; margin-bottom: 3rem;">
            <!-- Main Content -->
            <div>
                <!-- Image Gallery -->
                <?php if (!empty($unit_images)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📷 Galeri Foto Unit</h2>
                    </div>
                    
                    <div class="image-gallery">
                        <?php foreach ($unit_images as $index => $image): ?>
                        <div class="gallery-item" onclick="openLightbox(<?= $index ?>)">
                            <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($category_labels[$image['category']] ?? $image['category']) ?>"
                                 onerror="this.src='https://via.placeholder.com/200x150?text=No+Image'">
                            <div class="gallery-item-label">
                                <?= htmlspecialchars($category_labels[$image['category']] ?? $image['category']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Specifications -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📐 Spesifikasi Unit</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                        <div style="text-align: center; padding: 2rem; background: var(--gray-light); border-radius: 15px;">
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
                                🛏️
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;"><?= $unit['bedrooms'] ?></div>
                            <div style="color: var(--gray-dark);">Kamar Tidur</div>
                        </div>
                        
                        <div style="text-align: center; padding: 2rem; background: var(--gray-light); border-radius: 15px;">
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
                                🚿
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;"><?= $unit['bathrooms'] ?></div>
                            <div style="color: var(--gray-dark);">Kamar Mandi</div>
                        </div>
                        
                        <div style="text-align: center; padding: 2rem; background: var(--light-gold); border-radius: 15px;">
                            <div style="
                                width: 60px;
                                height: 60px;
                                background: var(--gradient-black);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 1rem;
                                font-size: 1.5rem;
                                color: white;
                            ">
                                📐
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;"><?= $unit['land_area'] ?> m²</div>
                            <div style="color: var(--gray-dark);">Luas Tanah</div>
                        </div>
                        
                        <div style="text-align: center; padding: 2rem; background: var(--light-gold); border-radius: 15px;">
                            <div style="
                                width: 60px;
                                height: 60px;
                                background: var(--gradient-black);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 1rem;
                                font-size: 1.5rem;
                                color: white;
                            ">
                                🏠
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;"><?= $unit['building_area'] ?> m²</div>
                            <div style="color: var(--gray-dark);">Luas Bangunan</div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($unit['description'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📝 Deskripsi Unit</h2>
                    </div>
                    <p style="line-height: 1.8; color: var(--gray-dark); font-size: 1.1rem;">
                        <?= nl2br(htmlspecialchars($unit['description'])) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Project Facilities -->
                <?php if (!empty($unit['facilities'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🏆 Fasilitas Proyek</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <?php 
                        $facilities = explode(',', $unit['facilities']);
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

            <!-- Sidebar -->
            <div>
                <!-- Price & Action -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">💰 Informasi Harga</h2>
                    </div>
                    
                    <div style="text-align: center; padding: 2rem; background: var(--gradient-gold); border-radius: 15px; margin-bottom: 2rem;">
                        <div style="font-size: 1.2rem; color: var(--primary-black); margin-bottom: 0.5rem;">Harga Unit</div>
                        <div style="font-size: 2.5rem; font-weight: bold; color: var(--primary-black); margin-bottom: 1rem;">
                            Rp <?= number_format($unit['price'], 0, ',', '.') ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--primary-black);">
                            Harga sudah termasuk sertifikat dan fasilitas
                        </div>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <?php if ($unit['status'] === 'available'): ?>
                            <?php if (isLoggedIn()): ?>
                                <a href="booking.php?unit=<?= $unit['id'] ?>" class="btn btn-primary" style="width: 100%; padding: 1rem; text-align: center; margin-bottom: 1rem;">
                                    📞 Booking Sekarang
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary" style="width: 100%; padding: 1rem; text-align: center; margin-bottom: 1rem;">
                                    🔑 Login untuk Booking
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn" style="width: 100%; padding: 1rem; text-align: center; margin-bottom: 1rem; background: #ccc; color: #666; cursor: not-allowed;" disabled>
                                Unit Tidak Tersedia
                            </button>
                        <?php endif; ?>
                        
                        <a href="contact.php" class="btn btn-secondary" style="width: 100%; padding: 1rem; text-align: center; margin-bottom: 1rem;">
                            💬 Hubungi Marketing
                        </a>
                        
                        <a href="project_detail.php?id=<?= $unit['project_id'] ?>" class="btn btn-secondary" style="width: 100%; padding: 1rem; text-align: center;">
                            🏘️ Lihat Proyek
                        </a>
                    </div>
                </div>

                <!-- Unit Info -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">ℹ️ Detail Unit</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Nomor Unit:</span>
                            <span><?= htmlspecialchars($unit['unit_number']) ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Tipe:</span>
                            <span><?= htmlspecialchars($unit['type']) ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Proyek:</span>
                            <span><?= htmlspecialchars($unit['project_name']) ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Lokasi:</span>
                            <span><?= htmlspecialchars($unit['location']) ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                            <span style="font-weight: bold;">Developer:</span>
                            <span><?= htmlspecialchars($unit['developer_name']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">❓ Butuh Bantuan?</h2>
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
                            📞
                        </div>
                        <h4 style="margin-bottom: 0.5rem;">Tim Marketing</h4>
                        <p style="color: var(--gray-dark); margin-bottom: 1rem; line-height: 1.6;">
                            Hubungi tim marketing kami untuk konsultasi dan informasi lebih lanjut
                        </p>
                        <div style="space-y: 0.5rem; margin-bottom: 1rem;">
                            <p style="margin: 0.25rem 0; color: var(--gray-dark);">📞 (0380) 123-456</p>
                            <p style="margin: 0.25rem 0; color: var(--gray-dark);">📱 +62 812-3456-7890</p>
                            <p style="margin: 0.25rem 0; color: var(--gray-dark);">✉️ marketing@perumahankupang.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Similar Units -->
        <?php if (!empty($similar_units)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🏠 Unit Serupa</h2>
                <a href="units.php?project=<?= $unit['project_id'] ?>" class="btn btn-primary">
                    Lihat Semua
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <?php foreach ($similar_units as $similar): ?>
                <div class="hover-lift" style="
                    border: 2px solid #e0e0e0;
                    border-radius: 15px;
                    padding: 1.5rem;
                    transition: all 0.3s ease;
                    background: white;
                " onmouseover="this.style.borderColor='var(--accent-gold)'; this.style.transform='translateY(-5px)'"
                   onmouseout="this.style.borderColor='#e0e0e0'; this.style.transform='translateY(0)'">
                    
                    <!-- Similar Unit Image -->
                    <?php if (!empty($similar['front_image'])): ?>
                        <img src="<?= htmlspecialchars($similar['front_image']) ?>" 
                             alt="Unit <?= htmlspecialchars($similar['unit_number']) ?>"
                             class="similar-unit-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="similar-unit-placeholder" style="display: none;">
                            Unit <?= htmlspecialchars($similar['unit_number']) ?>
                        </div>
                    <?php else: ?>
                        <div class="similar-unit-placeholder">
                            Unit <?= htmlspecialchars($similar['unit_number']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 style="margin-bottom: 1rem; color: var(--primary-black);">
                        Unit <?= htmlspecialchars($similar['unit_number']) ?>
                    </h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <span style="
                            background: var(--gradient-gold);
                            color: var(--primary-black);
                            padding: 0.5rem 1rem;
                            border-radius: 20px;
                            font-weight: bold;
                            font-size: 0.9rem;
                        ">
                            <?= htmlspecialchars($similar['type']) ?>
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div style="text-align: center; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                            <div style="font-weight: bold;"><?= $similar['bedrooms'] ?> / <?= $similar['bathrooms'] ?></div>
                            <div style="font-size: 0.8rem; color: var(--gray-dark);">KT / KM</div>
                        </div>
                        <div style="text-align: center; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                            <div style="font-weight: bold;"><?= $similar['land_area'] ?> m²</div>
                            <div style="font-size: 0.8rem; color: var(--gray-dark);">Luas Tanah</div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-bottom: 1rem; padding: 1rem; background: var(--light-gold); border-radius: 10px;">
                        <div style="font-size: 1.1rem; font-weight: bold; color: var(--primary-black);">
                            Rp <?= number_format($similar['price'], 0, ',', '.') ?>
                        </div>
                    </div>
                    
                    <a href="unit_detail.php?id=<?= $similar['id'] ?>" class="btn btn-primary" style="width: 100%; text-align: center;">
                        Lihat Detail
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <span class="lightbox-nav lightbox-prev" onclick="event.stopPropagation(); changeImage(-1)">‹</span>
        <span class="lightbox-nav lightbox-next" onclick="event.stopPropagation(); changeImage(1)">›</span>
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <img id="lightbox-image" src="" alt="">
            <div class="lightbox-caption" id="lightbox-caption"></div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        // Image gallery data
        const images = <?= json_encode($unit_images) ?>;
        const categoryLabels = <?= json_encode($category_labels) ?>;
        let currentImageIndex = 0;
        
        // Lightbox functions
        function openLightbox(index) {
            currentImageIndex = index;
            updateLightboxImage();
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        function changeImage(direction) {
            currentImageIndex += direction;
            if (currentImageIndex < 0) {
                currentImageIndex = images.length - 1;
            } else if (currentImageIndex >= images.length) {
                currentImageIndex = 0;
            }
            updateLightboxImage();
        }
        
        function updateLightboxImage() {
            const image = images[currentImageIndex];
            document.getElementById('lightbox-image').src = image.image_path;
            document.getElementById('lightbox-caption').textContent = categoryLabels[image.category] || image.category;
        }
        
        // Close lightbox with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft') {
                changeImage(-1);
            } else if (e.key === 'ArrowRight') {
                changeImage(1);
            }
        });
    </script>
</body>
</html>