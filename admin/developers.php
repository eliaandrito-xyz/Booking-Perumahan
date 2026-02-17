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
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($name)) {
            $error = 'Nama developer harus diisi';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO developers (name, description, address, phone, email, website, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssss", $name, $description, $address, $phone, $email, $website, $status);
                
                if ($stmt->execute()) {
                    $success = 'Developer berhasil ditambahkan';
                    $_POST = []; // Clear form
                } else {
                    $error = 'Terjadi kesalahan saat menambah developer';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($name)) {
            $error = 'Nama developer harus diisi';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE developers 
                    SET name = ?, description = ?, address = ?, phone = ?, email = ?, website = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("sssssssi", $name, $description, $address, $phone, $email, $website, $status, $id);
                
                if ($stmt->execute()) {
                    $success = 'Developer berhasil diperbarui';
                } else {
                    $error = 'Terjadi kesalahan saat memperbarui developer';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        try {
            // Check if developer has projects
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM housing_projects WHERE developer_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $project_count = $result->fetch_assoc()['count'];
            
            if ($project_count > 0) {
                $error = 'Developer tidak dapat dihapus karena memiliki proyek';
            } else {
                $stmt = $db->prepare("DELETE FROM developers WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $success = 'Developer berhasil dihapus';
                } else {
                    $error = 'Terjadi kesalahan saat menghapus developer';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Get developers
$developers = [];
try {
    $result = $db->query("
        SELECT d.*, 
               (SELECT COUNT(*) FROM housing_projects WHERE developer_id = d.id) as total_projects
        FROM developers d 
        ORDER BY d.created_at DESC
    ");
    while ($row = $result->fetch_assoc()) {
        $developers[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data developer.";
}

// Get developer for editing
$edit_developer = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $db->prepare("SELECT * FROM developers WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_developer = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = "Developer tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Developer - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
    <!-- Header -->

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                        🏢 Kelola Developer
                    </h1>
                    <p style="margin: 0; color: var(--gray-dark);">
                        Tambah, edit, dan kelola developer perumahan
                    </p>
                </div>
                <button onclick="showAddForm()" class="btn btn-primary">
                    ➕ Tambah Developer
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

        <!-- Add/Edit Form -->
        <div class="card" id="developerForm" style="<?= isset($_GET['add']) || $edit_developer ? 'display: block;' : 'display: none;' ?>">
            <div class="card-header">
                <h2 class="card-title"><?= $edit_developer ? 'Edit Developer' : 'Tambah Developer Baru' ?></h2>
                <button onclick="hideForm()" class="btn btn-secondary">Batal</button>
            </div>
            
            <form method="POST" id="addDeveloperForm">
                <input type="hidden" name="action" value="<?= $edit_developer ? 'edit' : 'add' ?>">
                <?php if ($edit_developer): ?>
                    <input type="hidden" name="id" value="<?= $edit_developer['id'] ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label for="name" class="form-label">Nama Developer *</label>
                            <input type="text" id="name" name="name" class="form-control" required
                                   value="<?= $edit_developer ? htmlspecialchars($edit_developer['name']) : '' ?>"
                                   placeholder="PT. Developer Name">
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea id="description" name="description" class="form-control" rows="4"
                                      placeholder="Deskripsi singkat tentang developer"><?= $edit_developer ? htmlspecialchars($edit_developer['description']) : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="address" class="form-label">Alamat</label>
                            <textarea id="address" name="address" class="form-control" rows="3"
                                      placeholder="Alamat lengkap kantor developer"><?= $edit_developer ? htmlspecialchars($edit_developer['address']) : '' ?></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   value="<?= $edit_developer ? htmlspecialchars($edit_developer['phone']) : '' ?>"
                                   placeholder="(0380) 123-456">
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= $edit_developer ? htmlspecialchars($edit_developer['email']) : '' ?>"
                                   placeholder="info@developer.com">
                        </div>

                        <div class="form-group">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" id="website" name="website" class="form-control"
                                   value="<?= $edit_developer ? htmlspecialchars($edit_developer['website']) : '' ?>"
                                   placeholder="https://www.developer.com">
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?= ($edit_developer && $edit_developer['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_developer && $edit_developer['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                        <?= $edit_developer ? '💾 Perbarui Developer' : '➕ Tambah Developer' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Developers List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Developer</h2>
                <div style="color: var(--gray-dark);">
                    Total: <?= count($developers) ?> developer
                </div>
            </div>
            
            <?php if (empty($developers)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🏢</div>
                    <h3>Belum Ada Developer</h3>
                    <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                        Mulai tambahkan developer perumahan pertama Anda.
                    </p>
                    <button onclick="showAddForm()" class="btn btn-primary">
                        ➕ Tambah Developer Pertama
                    </button>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                    <?php foreach ($developers as $developer): ?>
                    <div class="hover-lift" style="
                        border: 2px solid #e0e0e0;
                        border-radius: 15px;
                        padding: 2rem;
                        transition: all 0.3s ease;
                        background: white;
                    " onmouseover="this.style.borderColor='var(--accent-gold)'; this.style.transform='translateY(-5px)'"
                       onmouseout="this.style.borderColor='#e0e0e0'; this.style.transform='translateY(0)'">
                        
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div style="
                                width: 60px;
                                height: 60px;
                                background: var(--gradient-gold);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 1.5rem;
                            ">
                                🏢
                            </div>
                            <span style="
                                padding: 0.25rem 0.75rem;
                                border-radius: 15px;
                                font-size: 0.8rem;
                                font-weight: bold;
                                background: <?= $developer['status'] === 'active' ? '#d4edda' : '#f8d7da' ?>;
                                color: <?= $developer['status'] === 'active' ? '#155724' : '#721c24' ?>;
                            ">
                                <?= ucfirst($developer['status']) ?>
                            </span>
                        </div>
                        
                        <h3 style="margin-bottom: 1rem; color: var(--primary-black);">
                            <?= htmlspecialchars($developer['name']) ?>
                        </h3>
                        
                        <?php if ($developer['description']): ?>
                        <p style="margin-bottom: 1rem; line-height: 1.5; color: var(--gray-dark);">
                            <?= htmlspecialchars(substr($developer['description'], 0, 100)) ?>...
                        </p>
                        <?php endif; ?>
                        
                        <div style="space-y: 0.5rem; margin-bottom: 1.5rem;">
                            <?php if ($developer['phone']): ?>
                            <p style="margin: 0.25rem 0; color: var(--gray-dark); font-size: 0.9rem;">
                                📞 <?= htmlspecialchars($developer['phone']) ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if ($developer['email']): ?>
                            <p style="margin: 0.25rem 0; color: var(--gray-dark); font-size: 0.9rem;">
                                ✉️ <?= htmlspecialchars($developer['email']) ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if ($developer['website']): ?>
                            <p style="margin: 0.25rem 0; color: var(--gray-dark); font-size: 0.9rem;">
                                🌐 <a href="<?= htmlspecialchars($developer['website']) ?>" target="_blank" style="color: var(--accent-gold);">Website</a>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                            <span style="font-weight: bold; color: var(--primary-black);">Total Proyek:</span>
                            <span style="font-size: 1.2rem; font-weight: bold; color: var(--accent-gold);">
                                <?= $developer['total_projects'] ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <a href="?edit=<?= $developer['id'] ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">
                                ✏️ Edit
                            </a>
                            <button onclick="deleteDeveloper(<?= $developer['id'] ?>, '<?= htmlspecialchars($developer['name']) ?>', <?= $developer['total_projects'] ?>)" 
                                    class="btn btn-danger" style="flex: 1;">
                                🗑️ Hapus
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
            document.getElementById('developerForm').style.display = 'block';
            document.getElementById('developerForm').scrollIntoView({ behavior: 'smooth' });
        }

        function hideForm() {
            document.getElementById('developerForm').style.display = 'none';
            // Clear form if it's add form
            if (!window.location.href.includes('edit=')) {
                document.getElementById('addDeveloperForm').reset();
            }
            // Remove edit parameter from URL
            if (window.location.href.includes('edit=')) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        function deleteDeveloper(id, name, projectCount) {
            if (projectCount > 0) {
                showAlert(`Developer "${name}" tidak dapat dihapus karena memiliki ${projectCount} proyek.`, 'error');
                return;
            }
            
            confirmDialog(
                `Apakah Anda yakin ingin menghapus developer "${name}"? Tindakan ini tidak dapat dibatalkan.`,
                function() {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            );
        }

        // Form submission
        document.getElementById('addDeveloperForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const hideLoading = showLoading(submitBtn);
        });

        // Validate email format
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            if (email && !email.includes('@')) {
                showFieldError(this, 'Format email tidak valid');
            } else {
                clearFieldError(this);
            }
        });

        // Validate website URL
        document.getElementById('website').addEventListener('input', function() {
            const website = this.value;
            if (website && !website.startsWith('http')) {
                this.value = 'https://' + website;
            }
        });
    </script>
</body>
</html>