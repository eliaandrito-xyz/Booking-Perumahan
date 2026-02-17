<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db = new Database();

// Get search parameters
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$where_conditions = ["hp.status != 'planning'"];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(hp.name LIKE ? OR hp.description LIKE ? OR hp.location LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if (!empty($location)) {
    $where_conditions[] = "hp.location LIKE ?";
    $params[] = "%$location%";
    $types .= 's';
}

if (!empty($min_price)) {
    $where_conditions[] = "hp.price_range_min >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if (!empty($max_price)) {
    $where_conditions[] = "hp.price_range_max <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

if (!empty($status)) {
    $where_conditions[] = "hp.status = ?";
    $params[] = $status;
    $types .= 's';
}

$where_clause = implode(' AND ', $where_conditions);

$sql = "
    SELECT hp.*, d.name as developer_name,
           (SELECT COUNT(*) FROM house_units hu WHERE hu.project_id = hp.id) as total_units,
           (SELECT COUNT(*) FROM house_units hu WHERE hu.project_id = hp.id AND hu.status = 'available') as available_units
    FROM housing_projects hp 
    LEFT JOIN developers d ON hp.developer_id = d.id 
    WHERE $where_clause
    ORDER BY hp.created_at DESC
";

$projects = [];
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
        // Get project image from first unit with 'depan' category
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
        
        // Get image count
        $count_stmt = $db->prepare("
            SELECT COUNT(DISTINCT hui.id) as image_count
            FROM house_unit_images hui
            INNER JOIN house_units hu ON hui.unit_id = hu.id
            WHERE hu.project_id = ?
        ");
        $count_stmt->bind_param("i", $row['id']);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $row['image_count'] = $count_row['image_count'];
        
        $projects[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data proyek.";
}

// Get locations for filter
$locations = [];
try {
    $result = $db->query("SELECT DISTINCT location FROM housing_projects WHERE status != 'planning' ORDER BY location");
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row['location'];
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
    <title>Proyek Perumahan - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .project-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }
        
        .project-image-placeholder {
            width: 100%;
            height: 250px;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7)),
                        url('https://images.pexels.com/photos/280222/pexels-photo-280222.jpeg') center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            position: relative;
        }
        
        .project-status-badge {
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
        
        .project-card {
            transition: all 0.3s ease;
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--primary-black);">
                    🏘️ Proyek Perumahan
                </h1>
                <p style="color: var(--gray-dark); font-size: 1.1rem;">
                    Temukan proyek perumahan terbaik di Kota Kupang
                </p>
            </div>

            <!-- Search & Filter -->
            <form method="GET" style="background: var(--gray-light); padding: 2rem; border-radius: 15px; margin-bottom: 2rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Cari Proyek</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama proyek, lokasi..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Lokasi</label>
                        <select name="location" class="form-control">
                            <option value="">Semua Lokasi</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                            <?php endforeach; ?>
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
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="ready" <?= $status === 'ready' ? 'selected' : '' ?>>Ready</option>
                            <option value="construction" <?= $status === 'construction' ? 'selected' : '' ?>>Construction</option>
                        </select>
                    </div>
                </div>
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-primary" style="margin-right: 1rem;">
                        🔍 Cari Proyek
                    </button>
                    <a href="projects.php" class="btn btn-secondary">
                        🔄 Reset Filter
                    </a>
                </div>
            </form>
        </div>

        <!-- Projects Grid -->
        <?php if (empty($projects)): ?>
            <div class="card" style="text-align: center; padding: 4rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🏘️</div>
                <h3>Tidak Ada Proyek Ditemukan</h3>
                <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                    Coba ubah kriteria pencarian Anda atau lihat semua proyek yang tersedia.
                </p>
                <a href="projects.php" class="btn btn-primary">Lihat Semua Proyek</a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                <?php foreach ($projects as $project): ?>
                <div class="card project-card" style="padding: 0; overflow: hidden;">
                    <!-- Project Image -->
                    <div style="position: relative;">
                        <?php if (!empty($project['image_path'])): ?>
                            <img src="<?= htmlspecialchars($project['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($project['name']) ?>"
                                 class="project-image"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="project-image-placeholder" style="display: none;">
                                <?= htmlspecialchars($project['name']) ?>
                            </div>
                        <?php else: ?>
                            <div class="project-image-placeholder">
                                <?= htmlspecialchars($project['name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <div class="project-status-badge">
                            <?= ucfirst($project['status']) ?>
                        </div>
                        
                        <!-- Image Count Badge -->
                        <?php if ($project['image_count'] > 0): ?>
                        <div class="image-count-badge">
                            📷 <?= $project['image_count'] ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="padding: 2rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-black);">
                            <?= htmlspecialchars($project['name']) ?>
                        </h3>
                        
                        <div style="margin-bottom: 1rem;">
                            <p style="color: var(--gray-dark); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                📍 <strong>Lokasi:</strong> <?= htmlspecialchars($project['location']) ?>
                            </p>
                            <p style="color: var(--gray-dark); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                🏢 <strong>Developer:</strong> <?= htmlspecialchars($project['developer_name']) ?>
                            </p>
                            <p style="color: var(--gray-dark); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                🏠 <strong>Total Unit:</strong> <?= $project['total_units'] ?> unit
                            </p>
                            <p style="color: var(--accent-gold); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; font-weight: bold;">
                                ✅ <strong>Unit Tersedia:</strong> <?= $project['available_units'] ?> unit
                            </p>
                        </div>

                        <p style="margin-bottom: 1.5rem; line-height: 1.6; color: var(--gray-dark);">
                            <?= htmlspecialchars(substr($project['description'], 0, 150)) ?><?= strlen($project['description']) > 150 ? '...' : '' ?>
                        </p>

                        <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <span style="font-size: 0.9rem; color: var(--gray-dark);">Harga Mulai</span>
                                <span style="font-size: 1.3rem; font-weight: bold; color: var(--accent-gold);">
                                    Rp <?= number_format($project['price_range_min'], 0, ',', '.') ?>
                                </span>
                            </div>
                            <?php if ($project['price_range_max'] > $project['price_range_min']): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.85rem; color: var(--gray-dark);">Harga Maksimal</span>
                                <span style="font-size: 1rem; color: var(--gray-dark);">
                                    Rp <?= number_format($project['price_range_max'], 0, ',', '.') ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($project['facilities'])): ?>
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--primary-black);">🏆 Fasilitas:</h4>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                <?php 
                                $facilities = explode(',', $project['facilities']);
                                foreach (array_slice($facilities, 0, 3) as $facility): 
                                ?>
                                <span style="
                                    background: var(--light-gold);
                                    color: var(--primary-black);
                                    padding: 0.25rem 0.75rem;
                                    border-radius: 15px;
                                    font-size: 0.8rem;
                                ">
                                    ✓ <?= htmlspecialchars(trim($facility)) ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($facilities) > 3): ?>
                                <span style="
                                    background: var(--gray-light);
                                    color: var(--gray-dark);
                                    padding: 0.25rem 0.75rem;
                                    border-radius: 15px;
                                    font-size: 0.8rem;
                                    font-weight: bold;
                                ">
                                    +<?= count($facilities) - 3 ?> lainnya
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div style="display: flex; gap: 1rem;">
                            <a href="project_detail.php?id=<?= $project['id'] ?>" class="btn btn-primary" style="flex: 1; text-align: center;">
                                📋 Detail Proyek
                            </a>
                            <a href="units.php?project=<?= $project['id'] ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">
                                🏠 Lihat Unit
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Results Summary -->
            <div style="text-align: center; margin-top: 3rem; padding: 2rem; background: var(--gray-light); border-radius: 15px;">
                <p style="color: var(--primary-black); font-size: 1.1rem; margin: 0;">
                    <strong>Menampilkan <?= count($projects) ?> proyek perumahan</strong>
                </p>
                <?php if (!empty($search) || !empty($location) || !empty($min_price) || !empty($max_price) || !empty($status)): ?>
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
        // Format price inputs
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