<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();
$success = '';
$error = '';

// Handle image upload
function uploadUnitImage($file, $unit_id, $category) {
    $upload_dir = '../uploads/units/';
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'unit_' . $unit_id . '_' . $category . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => 'uploads/units/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Gagal mengupload file'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $project_id = $_POST['project_id'] ?? '';
        $unit_number = trim($_POST['unit_number'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $bedrooms = intval($_POST['bedrooms'] ?? 0);
        $bathrooms = intval($_POST['bathrooms'] ?? 0);
        $land_area = floatval($_POST['land_area'] ?? 0);
        $building_area = floatval($_POST['building_area'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'available';
        
        if (empty($project_id) || empty($unit_number) || empty($type) || $price <= 0) {
            $error = 'Proyek, nomor unit, tipe, dan harga harus diisi';
        } else {
            try {
                // Check if unit number already exists in project
                $stmt = $db->prepare("SELECT id FROM house_units WHERE project_id = ? AND unit_number = ?");
                $stmt->bind_param("is", $project_id, $unit_number);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = 'Nomor unit sudah ada dalam proyek ini';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO house_units (project_id, unit_number, type, bedrooms, bathrooms, land_area, building_area, price, description, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issiidddss", $project_id, $unit_number, $type, $bedrooms, $bathrooms, $land_area, $building_area, $price, $description, $status);
                    
                    if ($stmt->execute()) {
                        $unit_id = $stmt->insert_id;
                        
                        // Handle image uploads
                        $image_categories = ['depan', 'belakang', 'samping_kiri', 'samping_kanan', 'denah_rumah', 'denah_kamar', 'denah_kamar_mandi', 'siteplan'];
                        $upload_errors = [];
                        
                        foreach ($image_categories as $category) {
                            if (isset($_FILES[$category]) && $_FILES[$category]['error'] === UPLOAD_ERR_OK) {
                                $upload_result = uploadUnitImage($_FILES[$category], $unit_id, $category);
                                
                                if ($upload_result['success']) {
                                    $stmt = $db->prepare("INSERT INTO house_unit_images (unit_id, image_path, category) VALUES (?, ?, ?)");
                                    $stmt->bind_param("iss", $unit_id, $upload_result['path'], $category);
                                    $stmt->execute();
                                } else {
                                    $upload_errors[] = $category . ': ' . $upload_result['message'];
                                }
                            }
                        }
                        
                        if (empty($upload_errors)) {
                            $success = 'Unit berhasil ditambahkan dengan gambar';
                        } else {
                            $success = 'Unit berhasil ditambahkan, tetapi beberapa gambar gagal diupload: ' . implode(', ', $upload_errors);
                        }
                        
                        $_POST = []; // Clear form
                    } else {
                        $error = 'Terjadi kesalahan saat menambah unit';
                    }
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $project_id = $_POST['project_id'] ?? '';
        $unit_number = trim($_POST['unit_number'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $bedrooms = intval($_POST['bedrooms'] ?? 0);
        $bathrooms = intval($_POST['bathrooms'] ?? 0);
        $land_area = floatval($_POST['land_area'] ?? 0);
        $building_area = floatval($_POST['building_area'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'available';
        
        if (empty($project_id) || empty($unit_number) || empty($type) || $price <= 0) {
            $error = 'Proyek, nomor unit, tipe, dan harga harus diisi';
        } else {
            try {
                // Check if unit number already exists in project (excluding current unit)
                $stmt = $db->prepare("SELECT id FROM house_units WHERE project_id = ? AND unit_number = ? AND id != ?");
                $stmt->bind_param("isi", $project_id, $unit_number, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = 'Nomor unit sudah ada dalam proyek ini';
                } else {
                    $stmt = $db->prepare("
                        UPDATE house_units 
                        SET project_id = ?, unit_number = ?, type = ?, bedrooms = ?, bathrooms = ?, land_area = ?, building_area = ?, price = ?, description = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("issiidddssi", $project_id, $unit_number, $type, $bedrooms, $bathrooms, $land_area, $building_area, $price, $description, $status, $id);
                    
                    if ($stmt->execute()) {
                        // Handle image uploads
                        $image_categories = ['depan', 'belakang', 'samping_kiri', 'samping_kanan', 'denah_rumah', 'denah_kamar', 'denah_kamar_mandi', 'siteplan'];
                        $upload_errors = [];
                        
                        foreach ($image_categories as $category) {
                            if (isset($_FILES[$category]) && $_FILES[$category]['error'] === UPLOAD_ERR_OK) {
                                // Delete old image if exists
                                $stmt = $db->prepare("SELECT image_path FROM house_unit_images WHERE unit_id = ? AND category = ?");
                                $stmt->bind_param("is", $id, $category);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($row = $result->fetch_assoc()) {
                                    $old_image = '../' . $row['image_path'];
                                    if (file_exists($old_image)) {
                                        unlink($old_image);
                                    }
                                    
                                    // Delete from database
                                    $stmt = $db->prepare("DELETE FROM house_unit_images WHERE unit_id = ? AND category = ?");
                                    $stmt->bind_param("is", $id, $category);
                                    $stmt->execute();
                                }
                                
                                // Upload new image
                                $upload_result = uploadUnitImage($_FILES[$category], $id, $category);
                                
                                if ($upload_result['success']) {
                                    $stmt = $db->prepare("INSERT INTO house_unit_images (unit_id, image_path, category) VALUES (?, ?, ?)");
                                    $stmt->bind_param("iss", $id, $upload_result['path'], $category);
                                    $stmt->execute();
                                } else {
                                    $upload_errors[] = $category . ': ' . $upload_result['message'];
                                }
                            }
                        }
                        
                        if (empty($upload_errors)) {
                            $success = 'Unit berhasil diperbarui';
                        } else {
                            $success = 'Unit berhasil diperbarui, tetapi beberapa gambar gagal diupload: ' . implode(', ', $upload_errors);
                        }
                    } else {
                        $error = 'Terjadi kesalahan saat memperbarui unit';
                    }
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        try {
            // Delete images from filesystem
            $stmt = $db->prepare("SELECT image_path FROM house_unit_images WHERE unit_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $image_file = '../' . $row['image_path'];
                if (file_exists($image_file)) {
                    unlink($image_file);
                }
            }
            
            // Delete unit (images will be deleted automatically via CASCADE)
            $stmt = $db->prepare("DELETE FROM house_units WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = 'Unit berhasil dihapus';
            } else {
                $error = 'Terjadi kesalahan saat menghapus unit';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_image') {
        $image_id = $_POST['image_id'] ?? '';
        
        try {
            // Get image path
            $stmt = $db->prepare("SELECT image_path FROM house_unit_images WHERE id = ?");
            $stmt->bind_param("i", $image_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $image_file = '../' . $row['image_path'];
                if (file_exists($image_file)) {
                    unlink($image_file);
                }
                
                // Delete from database
                $stmt = $db->prepare("DELETE FROM house_unit_images WHERE id = ?");
                $stmt->bind_param("i", $image_id);
                
                if ($stmt->execute()) {
                    $success = 'Gambar berhasil dihapus';
                } else {
                    $error = 'Terjadi kesalahan saat menghapus gambar';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get filters
$search = $_GET['search'] ?? '';
$project_filter = $_GET['project'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get total count for pagination
$total_units = 0;
try {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(hu.unit_number LIKE ? OR hu.type LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param]);
        $types .= 'ss';
    }
    
    if (!empty($project_filter)) {
        $where_conditions[] = "hu.project_id = ?";
        $params[] = $project_filter;
        $types .= 'i';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "hu.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $count_sql = "SELECT COUNT(*) as total FROM house_units hu LEFT JOIN housing_projects hp ON hu.project_id = hp.id $where_clause";
    
    if (!empty($params)) {
        $stmt = $db->prepare($count_sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query($count_sql);
    }
    
    if ($row = $result->fetch_assoc()) {
        $total_units = $row['total'];
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat menghitung data unit.";
}

// Calculate total pages
$total_pages = ceil($total_units / $items_per_page);

// Get units with pagination
$units = [];
try {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(hu.unit_number LIKE ? OR hu.type LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param]);
        $types .= 'ss';
    }
    
    if (!empty($project_filter)) {
        $where_conditions[] = "hu.project_id = ?";
        $params[] = $project_filter;
        $types .= 'i';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "hu.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT hu.*, hp.name as project_name, hp.location,
               (SELECT COUNT(*) FROM house_unit_images WHERE unit_id = hu.id) as image_count
        FROM house_units hu 
        LEFT JOIN housing_projects hp ON hu.project_id = hp.id 
        $where_clause
        ORDER BY hp.name, hu.unit_number
        LIMIT ? OFFSET ?
    ";
    
    // Add pagination params
    $params[] = $items_per_page;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data unit: " . $e->getMessage();
}

// Get projects for dropdown
$projects = [];
try {
    $result = $db->query("SELECT id, name FROM housing_projects ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get unit for editing
$edit_unit = null;
$unit_images = [];
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $db->prepare("SELECT * FROM house_units WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_unit = $result->fetch_assoc();
        
        // Get existing images
        if ($edit_unit) {
            $stmt = $db->prepare("SELECT * FROM house_unit_images WHERE unit_id = ? ORDER BY category");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $unit_images[$row['category']] = $row;
            }
        }
    } catch (Exception $e) {
        $error = "Unit tidak ditemukan.";
    }
}

// Function to build query string with current filters
function buildQueryString($exclude_params = []) {
    $params = [];
    
    if (!empty($_GET['search']) && !in_array('search', $exclude_params)) {
        $params['search'] = $_GET['search'];
    }
    if (!empty($_GET['project']) && !in_array('project', $exclude_params)) {
        $params['project'] = $_GET['project'];
    }
    if (!empty($_GET['status']) && !in_array('status', $exclude_params)) {
        $params['status'] = $_GET['status'];
    }
    
    return !empty($params) ? '&' . http_build_query($params) : '';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Unit - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        .image-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .image-upload-item {
            border: 2px dashed var(--gray-light);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
        }
        
        .image-upload-item:hover {
            border-color: var(--primary-color);
            background: var(--gray-lighter);
        }
        
        .image-upload-item label {
            display: block;
            cursor: pointer;
        }
        
        .image-upload-item input[type="file"] {
            display: none;
        }
        
        .image-preview {
            position: relative;
            margin-top: 0.5rem;
        }
        
        .image-preview img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .image-preview .delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-upload-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .image-category-label {
            font-size: 0.85rem;
            font-weight: bold;
            color: var(--primary-black);
            margin-bottom: 0.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 4px;
            text-decoration: none;
            color: var(--primary-black);
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination .active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            font-weight: bold;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--gray-dark);
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Header -->

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                        🏠 Kelola Unit Rumah
                    </h1>
                    <p style="margin: 0; color: var(--gray-dark);">
                        Tambah, edit, dan kelola unit rumah dalam proyek
                    </p>
                </div>
                <button onclick="showAddForm()" class="btn btn-primary">
                    ➕ Tambah Unit
                </button>
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

        <!-- Search & Filter -->
        <div class="card">
            <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Cari Unit</label>
                    <input type="text" name="search" class="form-control" placeholder="Nomor unit atau tipe..." value="<?= htmlspecialchars($search) ?>">
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
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="available" <?= $status_filter === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="booked" <?= $status_filter === 'booked' ? 'selected' : '' ?>>Booked</option>
                        <option value="sold" <?= $status_filter === 'sold' ? 'selected' : '' ?>>Sold</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <a href="units.php" class="btn btn-secondary" style="margin-left: 0.5rem;">🔄 Reset</a>
                </div>
            </form>
        </div>

        <!-- Add/Edit Form -->
        <div class="card" id="unitForm" style="<?= isset($_GET['add']) || $edit_unit ? 'display: block;' : 'display: none;' ?>">
            <div class="card-header">
                <h2 class="card-title"><?= $edit_unit ? 'Edit Unit' : 'Tambah Unit Baru' ?></h2>
                <button onclick="hideForm()" class="btn btn-secondary">Batal</button>
            </div>
            
            <form method="POST" id="addUnitForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $edit_unit ? 'edit' : 'add' ?>">
                <?php if ($edit_unit): ?>
                    <input type="hidden" name="id" value="<?= $edit_unit['id'] ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label for="project_id" class="form-label">Proyek *</label>
                            <select id="project_id" name="project_id" class="form-control" required>
                                <option value="">Pilih Proyek</option>
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?= $proj['id'] ?>" <?= ($edit_unit && $edit_unit['project_id'] == $proj['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($proj['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="unit_number" class="form-label">Nomor Unit *</label>
                                <input type="text" id="unit_number" name="unit_number" class="form-control" required
                                       value="<?= $edit_unit ? htmlspecialchars($edit_unit['unit_number']) : '' ?>"
                                       placeholder="A-01">
                            </div>

                            <div class="form-group">
                                <label for="type" class="form-label">Tipe *</label>
                                <input type="text" id="type" name="type" class="form-control" required
                                       value="<?= $edit_unit ? htmlspecialchars($edit_unit['type']) : '' ?>"
                                       placeholder="Type 45">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="bedrooms" class="form-label">Kamar Tidur</label>
                                <input type="number" id="bedrooms" name="bedrooms" class="form-control" min="0"
                                       value="<?= $edit_unit ? $edit_unit['bedrooms'] : '' ?>"
                                       placeholder="2">
                            </div>

                            <div class="form-group">
                                <label for="bathrooms" class="form-label">Kamar Mandi</label>
                                <input type="number" id="bathrooms" name="bathrooms" class="form-control" min="0"
                                       value="<?= $edit_unit ? $edit_unit['bathrooms'] : '' ?>"
                                       placeholder="1">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="available" <?= ($edit_unit && $edit_unit['status'] == 'available') ? 'selected' : '' ?>>Available</option>
                                <option value="booked" <?= ($edit_unit && $edit_unit['status'] == 'booked') ? 'selected' : '' ?>>Booked</option>
                                <option value="sold" <?= ($edit_unit && $edit_unit['status'] == 'sold') ? 'selected' : '' ?>>Sold</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="land_area" class="form-label">Luas Tanah (m²)</label>
                                <input type="number" id="land_area" name="land_area" class="form-control" min="0" step="0.01"
                                       value="<?= $edit_unit ? $edit_unit['land_area'] : '' ?>"
                                       placeholder="90.00">
                            </div>

                            <div class="form-group">
                                <label for="building_area" class="form-label">Luas Bangunan (m²)</label>
                                <input type="number" id="building_area" name="building_area" class="form-control" min="0" step="0.01"
                                       value="<?= $edit_unit ? $edit_unit['building_area'] : '' ?>"
                                       placeholder="45.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="price" class="form-label">Harga (Rp) *</label>
                            <input type="number" id="price" name="price" class="form-control" min="0" required
                                   value="<?= $edit_unit ? $edit_unit['price'] : '' ?>"
                                   placeholder="350000000">
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea id="description" name="description" class="form-control" rows="5"
                                      placeholder="Deskripsi unit rumah..."><?= $edit_unit ? htmlspecialchars($edit_unit['description']) : '' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Image Upload Section -->
                <div class="form-group">
                    <h3 style="margin-bottom: 1rem;">📸 Upload Gambar Unit</h3>
                    <p style="color: var(--gray-dark); margin-bottom: 1rem;">
                        Upload gambar untuk berbagai kategori (Maksimal 5MB per gambar, format: JPG, PNG, GIF, WEBP)
                    </p>
                    
                    <div class="image-upload-grid">
                        <?php 
                        $image_categories = [
                            'depan' => 'Tampak Depan',
                            'belakang' => 'Tampak Belakang',
                            'samping_kiri' => 'Tampak Samping Kiri',
                            'samping_kanan' => 'Tampak Samping Kanan',
                            'denah_rumah' => 'Denah Rumah',
                            'denah_kamar' => 'Denah Kamar',
                            'denah_kamar_mandi' => 'Denah Kamar Mandi',
                            'siteplan' => 'Siteplan'
                        ];
                        
                        foreach ($image_categories as $key => $label): 
                            $has_image = isset($unit_images[$key]);
                        ?>
                        <div class="image-upload-item">
                            <div class="image-category-label"><?= $label ?></div>
                            <label for="<?= $key ?>">
                                <div class="image-upload-icon">📷</div>
                                <div style="font-size: 0.85rem; color: var(--gray-dark);">
                                    <?= $has_image ? 'Ganti Gambar' : 'Upload Gambar' ?>
                                </div>
                                <input type="file" id="<?= $key ?>" name="<?= $key ?>" accept="image/*" onchange="previewImage(this, '<?= $key ?>')">
                            </label>
                            
                            <div id="preview_<?= $key ?>" class="image-preview" style="<?= $has_image ? 'display: block;' : 'display: none;' ?>">
                                <img src="<?= $has_image ? '../' . htmlspecialchars($unit_images[$key]['image_path']) : '' ?>" alt="Preview">
                                <?php if ($has_image): ?>
                                <button type="button" class="delete-btn" onclick="deleteImage(<?= $unit_images[$key]['id'] ?>, '<?= $key ?>')">
                                    🗑️
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                        <?= $edit_unit ? '💾 Perbarui Unit' : '➕ Tambah Unit' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Units List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Unit</h2>
                <div style="color: var(--gray-dark);">
                    Total: <?= $total_units ?> unit
                    <?php if ($total_units > 0): ?>
                        (Halaman <?= $current_page ?> dari <?= $total_pages ?>)
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($units)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🏠</div>
                    <h3>Belum Ada Unit</h3>
                    <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                        <?= !empty($search) || !empty($project_filter) || !empty($status_filter) 
                            ? 'Tidak ada unit yang sesuai dengan filter.' 
                            : 'Mulai tambahkan unit rumah untuk proyek Anda.' ?>
                    </p>
                    <?php if (empty($search) && empty($project_filter) && empty($status_filter)): ?>
                    <button onclick="showAddForm()" class="btn btn-primary">
                        ➕ Tambah Unit Pertama
                    </button>
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
                                <th>Tipe</th>
                                <th>KT/KM</th>
                                <th>Luas (m²)</th>
                                <th>Harga</th>
                                <th>Gambar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $unit): ?>
                            <tr>
                                <td>#<?= $unit['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($unit['project_name']) ?></strong><br>
                                    <small style="color: var(--gray-dark);"><?= htmlspecialchars($unit['location']) ?></small>
                                </td>
                                <td><strong><?= htmlspecialchars($unit['unit_number']) ?></strong></td>
                                <td><?= htmlspecialchars($unit['type']) ?></td>
                                <td><?= $unit['bedrooms'] ?>/<?= $unit['bathrooms'] ?></td>
                                <td>
                                    <?= $unit['land_area'] ?> / <?= $unit['building_area'] ?><br>
                                    <small style="color: var(--gray-dark);">Tanah / Bangunan</small>
                                </td>
                                <td>
                                    <strong>Rp <?= number_format($unit['price'], 0, ',', '.') ?></strong>
                                </td>
                                <td>
                                    <span style="
                                        padding: 0.25rem 0.75rem;
                                        border-radius: 15px;
                                        font-size: 0.8rem;
                                        background: <?= $unit['image_count'] > 0 ? '#d4edda' : '#f8d7da' ?>;
                                        color: <?= $unit['image_count'] > 0 ? '#155724' : '#721c24' ?>;
                                    ">
                                        📷 <?= $unit['image_count'] ?>
                                    </span>
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="?edit=<?= $unit['id'] ?><?= buildQueryString(['edit']) ?>&page=<?= $current_page ?>" class="btn btn-secondary" style="padding: 0.5rem;">
                                            ✏️
                                        </a>
                                        <button onclick="deleteUnit(<?= $unit['id'] ?>, '<?= htmlspecialchars($unit['unit_number']) ?>')" 
                                                class="btn btn-danger" style="padding: 0.5rem;">
                                            🗑️
                                        </button>
                                        <a href="../unit_detail.php?id=<?= $unit['id'] ?>" class="btn btn-primary" style="padding: 0.5rem;" target="_blank">
                                            👁️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1<?= buildQueryString() ?>">⏮️ Pertama</a>
                        <a href="?page=<?= $current_page - 1 ?><?= buildQueryString() ?>">◀️ Sebelumnya</a>
                    <?php else: ?>
                        <span class="disabled">⏮️ Pertama</span>
                        <span class="disabled">◀️ Sebelumnya</span>
                    <?php endif; ?>
                    
                    <?php
                    // Show page numbers with ellipsis for large number of pages
                    $range = 2; // Number of pages to show on each side of current page
                    $start = max(1, $current_page - $range);
                    $end = min($total_pages, $current_page + $range);
                    
                    // Show first page if not in range
                    if ($start > 1) {
                        echo '<a href="?page=1' . buildQueryString() . '">1</a>';
                        if ($start > 2) {
                            echo '<span>...</span>';
                        }
                    }
                    
                    // Show page numbers
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $current_page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . buildQueryString() . '">' . $i . '</a>';
                        }
                    }
                    
                    // Show last page if not in range
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) {
                            echo '<span>...</span>';
                        }
                        echo '<a href="?page=' . $total_pages . buildQueryString() . '">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?><?= buildQueryString() ?>">Selanjutnya ▶️</a>
                        <a href="?page=<?= $total_pages ?><?= buildQueryString() ?>">Terakhir ⏭️</a>
                    <?php else: ?>
                        <span class="disabled">Selanjutnya ▶️</span>
                        <span class="disabled">Terakhir ⏭️</span>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-info">
                    Menampilkan <?= ($offset + 1) ?> - <?= min($offset + $items_per_page, $total_units) ?> dari <?= $total_units ?> unit
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <!-- Delete Image Form (Hidden) -->
    <form id="deleteImageForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_image">
        <input type="hidden" name="image_id" id="deleteImageId">
    </form>

    <script src="../assets/js/script.js"></script>
    <script>
        function showAddForm() {
            document.getElementById('unitForm').style.display = 'block';
            document.getElementById('unitForm').scrollIntoView({ behavior: 'smooth' });
        }

        function hideForm() {
            document.getElementById('unitForm').style.display = 'none';
            // Clear form if it's add form
            if (!window.location.href.includes('edit=')) {
                document.getElementById('addUnitForm').reset();
                // Clear all preview images
                document.querySelectorAll('.image-preview').forEach(preview => {
                    preview.style.display = 'none';
                });
            }
            // Remove edit parameter from URL but keep other params
            if (window.location.href.includes('edit=')) {
                const url = new URL(window.location.href);
                url.searchParams.delete('edit');
                window.history.replaceState({}, document.title, url.toString());
            }
        }

        function deleteUnit(id, unitNumber) {
            confirmDialog(
                `Apakah Anda yakin ingin menghapus unit "${unitNumber}"? Semua booking dan gambar terkait unit ini juga akan terhapus.`,
                function() {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            );
        }

        function deleteImage(imageId, category) {
            confirmDialog(
                'Apakah Anda yakin ingin menghapus gambar ini?',
                function() {
                    document.getElementById('deleteImageId').value = imageId;
                    document.getElementById('deleteImageForm').submit();
                }
            );
        }

        function previewImage(input, category) {
            const preview = document.getElementById('preview_' + category);
            const img = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                    
                    // Remove existing delete button if any
                    const existingBtn = preview.querySelector('.delete-btn');
                    if (existingBtn) {
                        existingBtn.remove();
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Validate building area not larger than land area
        document.getElementById('building_area').addEventListener('input', function() {
            const landArea = parseFloat(document.getElementById('land_area').value) || 0;
            const buildingArea = parseFloat(this.value) || 0;
            
            if (landArea > 0 && buildingArea > landArea) {
                showAlert('Luas bangunan tidak boleh lebih besar dari luas tanah', 'error');
                this.value = '';
            }
        });

        // Format price input
        document.getElementById('price').addEventListener('input', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                this.value = value;
            }
        });

        // Form submission
        document.getElementById('addUnitForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const hideLoading = showLoading(submitBtn);
        });

        // Auto-scroll to form if edit parameter exists
        <?php if ($edit_unit): ?>
        window.addEventListener('load', function() {
            document.getElementById('unitForm').scrollIntoView({ behavior: 'smooth' });
        });
        <?php endif; ?>
    </script>
</body>
</html>