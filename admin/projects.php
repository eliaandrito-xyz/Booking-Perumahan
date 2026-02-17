<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $developer_id = $_POST['developer_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $total_units = intval($_POST['total_units'] ?? 0);
        $available_units = intval($_POST['available_units'] ?? 0);
        $price_range_min = floatval($_POST['price_range_min'] ?? 0);
        $price_range_max = floatval($_POST['price_range_max'] ?? 0);
        $facilities = trim($_POST['facilities'] ?? '');
        $status = $_POST['status'] ?? 'planning';
        $completion_date = !empty($_POST['completion_date']) ? $_POST['completion_date'] : null;
        
        if (empty($name) || empty($location) || empty($address)) {
            $error = 'Nama proyek, lokasi, dan alamat harus diisi';
        } else {
            try {
                if ($completion_date) {
                    $stmt = $db->prepare("
                        INSERT INTO housing_projects (developer_id, name, description, location, address, total_units, available_units, price_range_min, price_range_max, facilities, status, completion_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issssiiddsss", $developer_id, $name, $description, $location, $address, $total_units, $available_units, $price_range_min, $price_range_max, $facilities, $status, $completion_date);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO housing_projects (developer_id, name, description, location, address, total_units, available_units, price_range_min, price_range_max, facilities, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issssiiддss", $developer_id, $name, $description, $location, $address, $total_units, $available_units, $price_range_min, $price_range_max, $facilities, $status);
                }
                
                if ($stmt->execute()) {
                    $success = 'Proyek berhasil ditambahkan';
                    $_POST = []; // Clear form
                } else {
                    $error = 'Terjadi kesalahan saat menambah proyek';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $developer_id = !empty($_POST['developer_id']) ? intval($_POST['developer_id']) : null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $total_units = intval($_POST['total_units'] ?? 0);
        $available_units = intval($_POST['available_units'] ?? 0);
        $price_range_min = floatval($_POST['price_range_min'] ?? 0);
        $price_range_max = floatval($_POST['price_range_max'] ?? 0);
        $facilities = trim($_POST['facilities'] ?? '');
        $status = $_POST['status'] ?? 'planning';
        $completion_date = !empty($_POST['completion_date']) ? $_POST['completion_date'] : null;
        
        if (empty($name) || empty($location) || empty($address)) {
            $error = 'Nama proyek, lokasi, dan alamat harus diisi';
        } else {
            try {
                if ($completion_date) {
                    $stmt = $db->prepare("
                        UPDATE housing_projects 
                        SET developer_id = ?, name = ?, description = ?, location = ?, address = ?, total_units = ?, available_units = ?, price_range_min = ?, price_range_max = ?, facilities = ?, status = ?, completion_date = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("issssiiddsssi", $developer_id, $name, $description, $location, $address, $total_units, $available_units, $price_range_min, $price_range_max, $facilities, $status, $completion_date, $id);
                } else {
                    $stmt = $db->prepare("
                        UPDATE housing_projects 
                        SET developer_id = ?, name = ?, description = ?, location = ?, address = ?, total_units = ?, available_units = ?, price_range_min = ?, price_range_max = ?, facilities = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("issssiiddssi", $developer_id, $name, $description, $location, $address, $total_units, $available_units, $price_range_min, $price_range_max, $facilities, $status, $id);
                }
                
                if ($stmt->execute()) {
                    $success = 'Proyek berhasil diperbarui';
                } else {
                    $error = 'Terjadi kesalahan saat memperbarui proyek';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        try {
            // Check if project has units
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM house_units WHERE project_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $unit_count = $result->fetch_assoc()['count'];
            
            if ($unit_count > 0) {
                $error = "Proyek tidak dapat dihapus karena memiliki {$unit_count} unit rumah. Hapus unit terlebih dahulu.";
            } else {
                $stmt = $db->prepare("DELETE FROM housing_projects WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $success = 'Proyek berhasil dihapus';
                } else {
                    $error = 'Terjadi kesalahan saat menghapus proyek';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Get projects
$projects = [];
try {
    $result = $db->query("
        SELECT hp.*, 
               d.name as developer_name,
               (SELECT COUNT(*) FROM house_units WHERE project_id = hp.id) as unit_count,
               (SELECT COUNT(*) FROM house_units WHERE project_id = hp.id AND status = 'available') as available_count
        FROM housing_projects hp 
        LEFT JOIN developers d ON hp.developer_id = d.id 
        ORDER BY hp.created_at DESC
    ");
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data proyek.";
}

// Get developers for dropdown
$developers = [];
try {
    $result = $db->query("SELECT id, name FROM developers WHERE status = 'active' ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $developers[] = $row;
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get project for editing
$edit_project = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    try {
        $stmt = $db->prepare("SELECT * FROM housing_projects WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_project = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = "Proyek tidak ditemukan.";
    }
}

// Get statistics
$stats = [
    'total' => count($projects),
    'planning' => count(array_filter($projects, fn($p) => $p['status'] === 'planning')),
    'construction' => count(array_filter($projects, fn($p) => $p['status'] === 'construction')),
    'ready' => count(array_filter($projects, fn($p) => $p['status'] === 'ready')),
    'sold_out' => count(array_filter($projects, fn($p) => $p['status'] === 'sold_out')),
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Proyek - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
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
            max-width: 900px;
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

        .project-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .project-detail-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--accent-gold);
        }

        .project-detail-label {
            font-size: 0.85rem;
            color: var(--gray-dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .project-detail-value {
            font-size: 1rem;
            color: var(--primary-black);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .project-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                        🏘️ Kelola Proyek Perumahan
                    </h1>
                    <p style="margin: 0; color: var(--gray-dark);">
                        Tambah, edit, dan kelola proyek perumahan
                    </p>
                </div>
                <button onclick="showAddForm()" class="btn btn-primary">
                    ➕ Tambah Proyek
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

        <!-- Statistics -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 2rem;">
            <div class="stat-card hover-lift">
                <div class="stat-icon">🏘️</div>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Proyek</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d1ecf1;">
                <div class="stat-icon" style="background: #0c5460; color: white;">📋</div>
                <div class="stat-number" style="color: #0c5460;"><?= $stats['planning'] ?></div>
                <div class="stat-label" style="color: #0c5460;">Planning</div>
            </div>
            <div class="stat-card hover-lift" style="background: #fff3cd;">
                <div class="stat-icon" style="background: #856404; color: white;">🏗️</div>
                <div class="stat-number" style="color: #856404;"><?= $stats['construction'] ?></div>
                <div class="stat-label" style="color: #856404;">Construction</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d4edda;">
                <div class="stat-icon" style="background: #155724; color: white;">✅</div>
                <div class="stat-number" style="color: #155724;"><?= $stats['ready'] ?></div>
                <div class="stat-label" style="color: #155724;">Ready</div>
            </div>
            <div class="stat-card hover-lift" style="background: #f8d7da;">
                <div class="stat-icon" style="background: #721c24; color: white;">🔒</div>
                <div class="stat-number" style="color: #721c24;"><?= $stats['sold_out'] ?></div>
                <div class="stat-label" style="color: #721c24;">Sold Out</div>
            </div>
        </div>

        <!-- Add/Edit Form -->
        <div class="card" id="projectForm" style="<?= isset($_GET['add']) || $edit_project ? 'display: block;' : 'display: none;' ?>">
            <div class="card-header">
                <h2 class="card-title"><?= $edit_project ? '✏️ Edit Proyek' : '➕ Tambah Proyek Baru' ?></h2>
                <button onclick="hideForm()" class="btn btn-secondary">❌ Batal</button>
            </div>
            
            <form method="POST" id="addProjectForm">
                <input type="hidden" name="action" value="<?= $edit_project ? 'edit' : 'add' ?>">
                <?php if ($edit_project): ?>
                    <input type="hidden" name="id" value="<?= $edit_project['id'] ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label for="developer_id" class="form-label">Developer</label>
                            <select id="developer_id" name="developer_id" class="form-control">
                                <option value="">Pilih Developer (Opsional)</option>
                                <?php foreach ($developers as $dev): ?>
                                    <option value="<?= $dev['id'] ?>" <?= ($edit_project && $edit_project['developer_id'] == $dev['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dev['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="name" class="form-label">Nama Proyek *</label>
                            <input type="text" id="name" name="name" class="form-control" required
                                   value="<?= $edit_project ? htmlspecialchars($edit_project['name']) : '' ?>"
                                   placeholder="Masukkan nama proyek">
                        </div>

                        <div class="form-group">
                            <label for="location" class="form-label">Lokasi *</label>
                            <input type="text" id="location" name="location" class="form-control" required
                                   value="<?= $edit_project ? htmlspecialchars($edit_project['location']) : '' ?>"
                                   placeholder="Contoh: Kelapa Lima">
                        </div>

                        <div class="form-group">
                            <label for="address" class="form-label">Alamat Lengkap *</label>
                            <textarea id="address" name="address" class="form-control" rows="3" required
                                      placeholder="Masukkan alamat lengkap proyek"><?= $edit_project ? htmlspecialchars($edit_project['address']) : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status Proyek</label>
                            <select id="status" name="status" class="form-control">
                                <option value="planning" <?= ($edit_project && $edit_project['status'] == 'planning') ? 'selected' : '' ?>>📋 Planning</option>
                                <option value="construction" <?= ($edit_project && $edit_project['status'] == 'construction') ? 'selected' : '' ?>>🏗️ Construction</option>
                                <option value="ready" <?= ($edit_project && $edit_project['status'] == 'ready') ? 'selected' : '' ?>>✅ Ready</option>
                                <option value="sold_out" <?= ($edit_project && $edit_project['status'] == 'sold_out') ? 'selected' : '' ?>>🔒 Sold Out</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="total_units" class="form-label">Total Unit</label>
                                <input type="number" id="total_units" name="total_units" class="form-control" min="0"
                                       value="<?= $edit_project ? $edit_project['total_units'] : '' ?>"
                                       placeholder="0">
                            </div>

                            <div class="form-group">
                                <label for="available_units" class="form-label">Unit Tersedia</label>
                                <input type="number" id="available_units" name="available_units" class="form-control" min="0"
                                       value="<?= $edit_project ? $edit_project['available_units'] : '' ?>"
                                       placeholder="0">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="price_range_min" class="form-label">Harga Minimum (Rp)</label>
                                <input type="number" id="price_range_min" name="price_range_min" class="form-control" min="0"
                                       value="<?= $edit_project ? $edit_project['price_range_min'] : '' ?>"
                                       placeholder="0">
                            </div>

                            <div class="form-group">
                                <label for="price_range_max" class="form-label">Harga Maximum (Rp)</label>
                                <input type="number" id="price_range_max" name="price_range_max" class="form-control" min="0"
                                       value="<?= $edit_project ? $edit_project['price_range_max'] : '' ?>"
                                       placeholder="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="completion_date" class="form-label">Target Selesai</label>
                            <input type="date" id="completion_date" name="completion_date" class="form-control"
                                   value="<?= $edit_project ? $edit_project['completion_date'] : '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="facilities" class="form-label">Fasilitas</label>
                            <textarea id="facilities" name="facilities" class="form-control" rows="3"
                                      placeholder="Pisahkan dengan koma. Contoh: Security 24 jam, Taman bermain, Kolam renang"><?= $edit_project ? htmlspecialchars($edit_project['facilities']) : '' ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Deskripsi Proyek</label>
                    <textarea id="description" name="description" class="form-control" rows="4"
                              placeholder="Masukkan deskripsi lengkap proyek"><?= $edit_project ? htmlspecialchars($edit_project['description']) : '' ?></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                        <?= $edit_project ? '💾 Perbarui Proyek' : '➕ Tambah Proyek' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Projects List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Proyek</h2>
                <div style="color: var(--gray-dark);">
                    Total: <?= count($projects) ?> proyek
                </div>
            </div>
            
            <?php if (empty($projects)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🏘️</div>
                    <h3>Belum Ada Proyek</h3>
                    <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                        Mulai tambahkan proyek perumahan pertama Anda.
                    </p>
                    <button onclick="showAddForm()" class="btn btn-primary">
                        ➕ Tambah Proyek Pertama
                    </button>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Proyek</th>
                                <th>Developer</th>
                                <th>Lokasi</th>
                                <th>Unit</th>
                                <th>Harga Range</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><strong>#<?= $project['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($project['name']) ?></strong><br>
                                    <small style="color: var(--gray-dark);">
                                        <?= $project['unit_count'] ?> unit total, <?= $project['available_count'] ?> tersedia
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($project['developer_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?= htmlspecialchars($project['location']) ?><br>
                                    <small style="color: var(--gray-dark);">
                                        <?= strlen($project['address']) > 30 ? substr(htmlspecialchars($project['address']), 0, 30) . '...' : htmlspecialchars($project['address']) ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?= $project['total_units'] ?></strong> total<br>
                                    <small style="color: var(--gray-dark);"><?= $project['available_units'] ?> tersedia</small>
                                </td>
                                <td>
                                    <?php if ($project['price_range_min'] && $project['price_range_max']): ?>
                                        <strong>Rp <?= number_format($project['price_range_min'] / 1000000, 0) ?>jt</strong><br>
                                        <small style="color: var(--gray-dark);">s/d Rp <?= number_format($project['price_range_max'] / 1000000, 0) ?>jt</small>
                                    <?php else: ?>
                                        <span style="color: var(--gray-dark);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="
                                        padding: 0.25rem 0.75rem;
                                        border-radius: 15px;
                                        font-size: 0.8rem;
                                        font-weight: bold;
                                        background: <?= 
                                            $project['status'] === 'ready' ? '#d4edda' : 
                                            ($project['status'] === 'construction' ? '#fff3cd' : 
                                            ($project['status'] === 'planning' ? '#d1ecf1' : '#f8d7da')) 
                                        ?>;
                                        color: <?= 
                                            $project['status'] === 'ready' ? '#155724' : 
                                            ($project['status'] === 'construction' ? '#856404' : 
                                            ($project['status'] === 'planning' ? '#0c5460' : '#721c24')) 
                                        ?>;
                                    ">
                                        <?php
                                        $statusIcons = [
                                            'planning' => '📋',
                                            'construction' => '🏗️',
                                            'ready' => '✅',
                                            'sold_out' => '🔒'
                                        ];
                                        echo $statusIcons[$project['status']] . ' ' . ucfirst(str_replace('_', ' ', $project['status']));
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button onclick='showProjectDetail(<?= json_encode($project) ?>)' 
                                                class="btn btn-primary" 
                                                style="padding: 0.5rem;"
                                                title="Lihat Detail">
                                            👁️
                                        </button>
                                        <a href="?edit=<?= $project['id'] ?>" class="btn btn-secondary" style="padding: 0.5rem;" title="Edit">
                                            ✏️
                                        </a>
                                        <button onclick="deleteProject(<?= $project['id'] ?>, '<?= htmlspecialchars(addslashes($project['name'])) ?>')" 
                                                class="btn btn-danger" style="padding: 0.5rem;" title="Hapus">
                                            🗑️
                                        </button>
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

    <!-- Project Detail Modal -->
    <div id="projectDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🏘️ Detail Proyek</h2>
                <span class="close" onclick="closeProjectDetail()">&times;</span>
            </div>
            <div class="modal-body" id="projectDetailContent">
                <!-- Content will be inserted by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="../assets/js/script.js"></script>
    <script>
        function showAddForm() {
            document.getElementById('projectForm').style.display = 'block';
            document.getElementById('projectForm').scrollIntoView({ behavior: 'smooth' });
        }

        function hideForm() {
            document.getElementById('projectForm').style.display = 'none';
            // Clear form if it's add form
            if (!window.location.href.includes('edit=')) {
                document.getElementById('addProjectForm').reset();
            }
            // Remove edit parameter from URL
            if (window.location.href.includes('edit=')) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        function deleteProject(id, name) {
            if (confirm(`Apakah Anda yakin ingin menghapus proyek "${name}"?\n\n⚠️ Tindakan ini tidak dapat dibatalkan.\n\nCatatan: Proyek yang memiliki unit tidak dapat dihapus.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function showProjectDetail(project) {
            const modal = document.getElementById('projectDetailModal');
            const content = document.getElementById('projectDetailContent');
            
            const statusColors = {
                'planning': { bg: '#d1ecf1', color: '#0c5460', icon: '📋' },
                'construction': { bg: '#fff3cd', color: '#856404', icon: '🏗️' },
                'ready': { bg: '#d4edda', color: '#155724', icon: '✅' },
                'sold_out': { bg: '#f8d7da', color: '#721c24', icon: '🔒' }
            };
            
            const statusStyle = statusColors[project.status] || statusColors['planning'];
            const statusBadge = `<span style="padding: 0.5rem 1rem; background: ${statusStyle.bg}; color: ${statusStyle.color}; border-radius: 20px; font-weight: bold;">${statusStyle.icon} ${project.status.replace('_', ' ').toUpperCase()}</span>`;
            
            const facilities = project.facilities ? project.facilities.split(',').map(f => `<li>✅ ${f.trim()}</li>`).join('') : '<li>Tidak ada fasilitas tercatat</li>';
            
            content.innerHTML = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🏘️</div>
                    <h2 style="margin-bottom: 0.5rem;">${project.name}</h2>
                    <p style="color: var(--gray-dark); margin-bottom: 1rem;">${project.developer_name || 'Developer tidak ditentukan'}</p>
                    ${statusBadge}
                </div>

                <div class="project-detail-grid">
                    <div class="project-detail-item">
                        <div class="project-detail-label">📍 Lokasi</div>
                        <div class="project-detail-value">${project.location}</div>
                    </div>
                    <div class="project-detail-item">
                        <div class="project-detail-label">🏢 Alamat</div>
                        <div class="project-detail-value">${project.address}</div>
                    </div>
                    <div class="project-detail-item">
                        <div class="project-detail-label">🏠 Total Unit</div>
                        <div class="project-detail-value">${project.total_units} unit</div>
                    </div>
                    <div class="project-detail-item">
                        <div class="project-detail-label">✅ Unit Tersedia</div>
                        <div class="project-detail-value">${project.available_units} unit</div>
                    </div>
                    <div class="project-detail-item">
                        <div class="project-detail-label">💰 Harga Minimum</div>
                        <div class="project-detail-value">Rp ${formatCurrency(project.price_range_min)}</div>
                    </div>
                    <div class="project-detail-item">
                        <div class="project-detail-label">💎 Harga Maximum</div>
                        <div class="project-detail-value">Rp ${formatCurrency(project.price_range_max)}</div>
                    </div>
                    <div class="project-detail-item">
                        <div class="project-detail-label">📅 Tanggal Selesai</div>
                        <div class="project-detail-value">${project.completion_date ? formatDate(project.completion_date) : 'Belum ditentukan'}</div>
                    </div>
                    <div class="project-detail-item">
                        <div class="project-detail-label">🆔 Project ID</div>
                        <div class="project-detail-value">#${project.id}</div>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; color: white;">
                    <h3 style="margin: 0 0 1rem 0; color: white;">📝 Deskripsi</h3>
                    <p style="margin: 0; line-height: 1.6; color: white;">${project.description || 'Tidak ada deskripsi'}</p>
                </div>

                <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 15px;">
                    <h3 style="margin: 0 0 1rem 0; color: var(--primary-black);">🎯 Fasilitas</h3>
                    <ul style="margin: 0; padding-left: 1.5rem; line-height: 2;">
                        ${facilities}
                    </ul>
                </div>

                <div style="margin-top: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 15px; color: white;">
                    <h3 style="margin: 0 0 1rem 0; color: white;">📊 Statistik Unit</h3>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <div style="background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 10px; text-align: center;">
                            <div style="font-size: 2rem; font-weight: bold; color: white;">${project.unit_count || 0}</div>
                            <div style="font-size: 0.9rem; color: white;">Unit Terdaftar</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 10px; text-align: center;">
                            <div style="font-size: 2rem; font-weight: bold; color: white;">${project.available_count || 0}</div>
                            <div style="font-size: 0.9rem; color: white;">Unit Available</div>
                        </div>
                    </div>
                </div>
            `;
            
            modal.classList.add('show');
        }

        function closeProjectDetail() {
            const modal = document.getElementById('projectDetailModal');
            modal.classList.remove('show');
        }

        function formatCurrency(amount) {
            if (!amount || amount == 0) return '-';
            return new Intl.NumberFormat('id-ID').format(amount);
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('id-ID', options);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('projectDetailModal');
            if (event.target == modal) {
                closeProjectDetail();
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeProjectDetail();
            }
        });

        // Auto-calculate available units
        document.getElementById('total_units').addEventListener('input', function() {
            const totalUnits = parseInt(this.value) || 0;
            const availableUnits = document.getElementById('available_units');
            if (availableUnits.value === '' || parseInt(availableUnits.value) > totalUnits) {
                availableUnits.value = totalUnits;
            }
        });

        // Validate price range
        document.getElementById('price_range_max').addEventListener('input', function() {
            const minPrice = parseFloat(document.getElementById('price_range_min').value) || 0;
            const maxPrice = parseFloat(this.value) || 0;
            
            if (maxPrice > 0 && maxPrice < minPrice) {
                alert('⚠️ Harga maksimum tidak boleh lebih kecil dari harga minimum');
                this.value = '';
            }
        });

        // Scroll to form if edit mode
        <?php if ($edit_project): ?>
            document.getElementById('projectForm').scrollIntoView({ behavior: 'smooth' });
        <?php endif; ?>
    </script>
</body>
</html>