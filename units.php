<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db = new Database();

// Get search parameters
$search = $_GET['search'] ?? '';
$project_id = $_GET['project'] ?? '';
$type = $_GET['type'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$bedrooms = $_GET['bedrooms'] ?? '';
$status = $_GET['status'] ?? 'available';

// Build query
$where_conditions = ["hu.status = 'available'"];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(hu.unit_number LIKE ? OR hu.type LIKE ? OR hp.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if (!empty($project_id)) {
    $where_conditions[] = "hu.project_id = ?";
    $params[] = $project_id;
    $types .= 'i';
}

if (!empty($type)) {
    $where_conditions[] = "hu.type = ?";
    $params[] = $type;
    $types .= 's';
}

if (!empty($min_price)) {
    $where_conditions[] = "hu.price >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if (!empty($max_price)) {
    $where_conditions[] = "hu.price <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

if (!empty($bedrooms)) {
    $where_conditions[] = "hu.bedrooms >= ?";
    $params[] = $bedrooms;
    $types .= 'i';
}

$where_clause = implode(' AND ', $where_conditions);

$sql = "
    SELECT hu.*, hp.name as project_name, hp.location, d.name as developer_name,
           (SELECT image_path FROM house_unit_images WHERE unit_id = hu.id AND category = 'depan' LIMIT 1) as front_image,
           (SELECT COUNT(*) FROM house_unit_images WHERE unit_id = hu.id) as image_count
    FROM house_units hu 
    LEFT JOIN housing_projects hp ON hu.project_id = hp.id
    LEFT JOIN developers d ON hp.developer_id = d.id
    WHERE $where_clause
    ORDER BY hu.price ASC
";

$units = [];
try {
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query($sql);
    }
    
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data unit.";
}

// Get filter options
$projects = [];
$types_available = [];
try {
    $result = $db->query("SELECT id, name FROM housing_projects WHERE status IN ('ready', 'construction') ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    
    $result = $db->query("SELECT DISTINCT type FROM house_units WHERE status = 'available' ORDER BY type");
    while ($row = $result->fetch_assoc()) {
        $types_available[] = $row['type'];
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
    <title>Unit Rumah - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .unit-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        
        .unit-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7)),
                        url('https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg') center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            position: relative;
        }
        
        .unit-type-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--gradient-gold);
            color: var(--primary-black);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            z-index: 1;
        }
        
        .image-count-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--primary-black);">
                    🏠 Unit Rumah Tersedia
                </h1>
                <p style="color: var(--gray-dark); font-size: 1.1rem;">
                    Temukan unit rumah impian Anda di Kota Kupang
                </p>
            </div>

            <!-- Search & Filter -->
            <form method="GET" style="background: var(--gray-light); padding: 2rem; border-radius: 15px; margin-bottom: 2rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Cari Unit</label>
                        <input type="text" name="search" class="form-control" placeholder="Nomor unit, tipe..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Proyek</label>
                        <select name="project" class="form-control">
                            <option value="">Semua Proyek</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= $project_id == $proj['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proj['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Tipe</label>
                        <select name="type" class="form-control">
                            <option value="">Semua Tipe</option>
                            <?php foreach ($types_available as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $type === $t ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Kamar Tidur</label>
                        <select name="bedrooms" class="form-control">
                            <option value="">Semua</option>
                            <option value="1" <?= $bedrooms === '1' ? 'selected' : '' ?>>1 Kamar</option>
                            <option value="2" <?= $bedrooms === '2' ? 'selected' : '' ?>>2 Kamar</option>
                            <option value="3" <?= $bedrooms === '3' ? 'selected' : '' ?>>3 Kamar</option>
                            <option value="4" <?= $bedrooms === '4' ? 'selected' : '' ?>>4+ Kamar</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Harga Min (Rp)</label>
                        <input type="number" name="min_price" class="form-control" placeholder="0" value="<?= htmlspecialchars($min_price) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Harga Max (Rp)</label>
                        <input type="number" name="max_price" class="form-control" placeholder="1000000000" value="<?= htmlspecialchars($max_price) ?>">
                    </div>
                </div>
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-primary" style="margin-right: 1rem;">
                        🔍 Cari Unit
                    </button>
                    <a href="units.php" class="btn btn-secondary">
                        🔄 Reset Filter
                    </a>
                </div>
            </form>
        </div>

        <!-- Units Grid -->
        <?php if (empty($units)): ?>
            <div class="card" style="text-align: center; padding: 4rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🏠</div>
                <h3>Tidak Ada Unit Ditemukan</h3>
                <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                    Coba ubah kriteria pencarian Anda atau lihat semua unit yang tersedia.
                </p>
                <a href="units.php" class="btn btn-primary">Lihat Semua Unit</a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
                <?php foreach ($units as $unit): ?>
                <div class="card hover-lift" style="padding: 0; overflow: hidden;">
                    <!-- Unit Image -->
                    <div style="position: relative;">
                        <?php if (!empty($unit['front_image'])): ?>
                            <img src="<?= htmlspecialchars($unit['front_image']) ?>" 
                                 alt="Unit <?= htmlspecialchars($unit['unit_number']) ?>"
                                 class="unit-image"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="unit-image-placeholder" style="display: none;">
                                Unit <?= htmlspecialchars($unit['unit_number']) ?>
                            </div>
                        <?php else: ?>
                            <div class="unit-image-placeholder">
                                Unit <?= htmlspecialchars($unit['unit_number']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Type Badge -->
                        <div class="unit-type-badge">
                            <?= htmlspecialchars($unit['type']) ?>
                        </div>
                        
                        <!-- Image Count Badge -->
                        <?php if ($unit['image_count'] > 0): ?>
                        <div class="image-count-badge">
                            📷 <?= $unit['image_count'] ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="padding: 1.5rem;">
                        <h3 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                            Unit <?= htmlspecialchars($unit['unit_number']) ?>
                        </h3>
                        <p style="color: var(--gray-dark); margin-bottom: 0.5rem;">
                            <strong><?= htmlspecialchars($unit['project_name']) ?></strong>
                        </p>
                        <p style="color: var(--gray-dark); margin-bottom: 1rem; font-size: 0.9rem;">
                            📍 <?= htmlspecialchars($unit['location']) ?>
                        </p>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div style="text-align: center; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">🛏️</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Kamar Tidur</div>
                                <div style="font-weight: bold;"><?= $unit['bedrooms'] ?></div>
                            </div>
                            <div style="text-align: center; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">🚿</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Kamar Mandi</div>
                                <div style="font-weight: bold;"><?= $unit['bathrooms'] ?></div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div style="text-align: center; padding: 0.75rem; background: var(--light-gold); border-radius: 10px;">
                                <div style="font-size: 1.2rem; margin-bottom: 0.25rem;">📐</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Luas Tanah</div>
                                <div style="font-weight: bold;"><?= $unit['land_area'] ?> m²</div>
                            </div>
                            <div style="text-align: center; padding: 0.75rem; background: var(--light-gold); border-radius: 10px;">
                                <div style="font-size: 1.2rem; margin-bottom: 0.25rem;">🏠</div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Luas Bangunan</div>
                                <div style="font-weight: bold;"><?= $unit['building_area'] ?> m²</div>
                            </div>
                        </div>

                        <div style="text-align: center; margin-bottom: 1.5rem; padding: 1rem; background: var(--gradient-gold); border-radius: 10px;">
                            <div style="font-size: 0.9rem; color: var(--primary-black); margin-bottom: 0.25rem;">Harga</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-black);">
                                Rp <?= number_format($unit['price'], 0, ',', '.') ?>
                            </div>
                        </div>

                        <?php if (!empty($unit['description'])): ?>
                        <p style="margin-bottom: 1.5rem; line-height: 1.5; color: var(--gray-dark); font-size: 0.9rem;">
                            <?= htmlspecialchars(substr($unit['description'], 0, 100)) ?><?= strlen($unit['description']) > 100 ? '...' : '' ?>
                        </p>
                        <?php endif; ?>

                        <div style="display: flex; gap: 1rem;">
                            <a href="unit_detail.php?id=<?= $unit['id'] ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">
                                📋 Detail
                            </a>
                            <?php if (isLoggedIn()): ?>
                                <a href="booking.php?unit=<?= $unit['id'] ?>" class="btn btn-primary" style="flex: 1; text-align: center;">
                                    📞 Booking
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary" style="flex: 1; text-align: center;">
                                    🔑 Login
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Results Summary -->
            <div style="text-align: center; margin-top: 3rem; padding: 2rem; background: var(--gray-light); border-radius: 15px;">
                <p style="color: var(--primary-black); font-size: 1.1rem; margin: 0;">
                    <strong>Menampilkan <?= count($units) ?> unit rumah tersedia</strong>
                </p>
                <?php if (!empty($search) || !empty($project_id) || !empty($type) || !empty($min_price) || !empty($max_price) || !empty($bedrooms)): ?>
                <p style="color: var(--gray-dark); margin: 0.5rem 0 0 0;">
                    Hasil pencarian dengan filter yang diterapkan
                </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
     <?php
  require_once 'includes/footer.php';
  ?>

    <script src="assets/js/script.js"></script>
    <script>
        // Format price inputs with thousand separators
        const priceInputs = document.querySelectorAll('input[name="min_price"], input[name="max_price"]');
        priceInputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    const value = parseInt(this.value.replace(/\D/g, ''));
                    if (!isNaN(value)) {
                        this.value = value;
                    }
                }
            });
        });
    </script>
</body>
</html>