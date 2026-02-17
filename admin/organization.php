<?php
// Start output buffering at the very beginning
ob_start();

require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $photo_path = null;
                    
                    // Handle photo upload
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/organization/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = 'org_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                            $photo_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                                $photo_path = 'uploads/organization/' . $new_filename;
                            } else {
                                $photo_path = null;
                            }
                        }
                    }
                    
                    // Get the minimum order_number and subtract 1 to place new item at top
                    $min_order_result = $db->query("SELECT MIN(order_number) as min_order FROM organization_structure");
                    $min_order_row = $min_order_result->fetch_assoc();
                    $new_order = ($min_order_row['min_order'] !== null) ? $min_order_row['min_order'] - 1 : 0;
                    
                    // Override order_number if user specified one
                    if (isset($_POST['order_number']) && $_POST['order_number'] !== '') {
                        $new_order = (int)$_POST['order_number'];
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO organization_structure 
                        (name, position, level, parent_id, photo, email, phone, description, order_number) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
                    
                    $stmt->bind_param(
                        "sssissssi",
                        $_POST['name'],
                        $_POST['position'],
                        $_POST['level'],
                        $parent_id,
                        $photo_path,
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['description'],
                        $new_order
                    );
                    
                    $stmt->execute();
                    
                    // Clean output buffer before redirect
                    ob_end_clean();
                    header("Location: organization.php?success=" . urlencode("✅ Data berhasil ditambahkan!"));
                    exit();
                } catch (Exception $e) {
                    $error = "❌ Gagal menambahkan data: " . $e->getMessage();
                }
                break;
                
            case 'edit':
                try {
                    $photo_path = $_POST['existing_photo'];
                    
                    // Handle photo upload
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/organization/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            // Delete old photo
                            if (!empty($_POST['existing_photo']) && file_exists('../' . $_POST['existing_photo'])) {
                                unlink('../' . $_POST['existing_photo']);
                            }
                            
                            $new_filename = 'org_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                            $new_photo_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['photo']['tmp_name'], $new_photo_path)) {
                                $photo_path = 'uploads/organization/' . $new_filename;
                            }
                        }
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE organization_structure 
                        SET name = ?, position = ?, level = ?, parent_id = ?, 
                            photo = ?, email = ?, phone = ?, description = ?, order_number = ?
                        WHERE id = ?
                    ");
                    
                    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
                    
                    $stmt->bind_param(
                        "sssissssii",
                        $_POST['name'],
                        $_POST['position'],
                        $_POST['level'],
                        $parent_id,
                        $photo_path,
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['description'],
                        $_POST['order_number'],
                        $_POST['id']
                    );
                    
                    $stmt->execute();
                    
                    // Clean output buffer before redirect
                    ob_end_clean();
                    header("Location: organization.php?success=" . urlencode("✅ Data berhasil diupdate!"));
                    exit();
                } catch (Exception $e) {
                    $error = "❌ Gagal mengupdate data: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    // Get photo path before delete
                    $stmt = $db->prepare("SELECT photo FROM organization_structure WHERE id = ?");
                    $stmt->bind_param("i", $_POST['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    // Delete photo file
                    if (!empty($row['photo']) && file_exists('../' . $row['photo'])) {
                        unlink('../' . $row['photo']);
                    }
                    
                    // Delete record
                    $stmt = $db->prepare("DELETE FROM organization_structure WHERE id = ?");
                    $stmt->bind_param("i", $_POST['id']);
                    $stmt->execute();
                    
                    // Clean output buffer before redirect
                    ob_end_clean();
                    header("Location: organization.php?success=" . urlencode("✅ Data berhasil dihapus!"));
                    exit();
                } catch (Exception $e) {
                    $error = "❌ Gagal menghapus data: " . $e->getMessage();
                }
                break;
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Get all organization members - ORDER BY order_number ASC (smallest first = newest at top)
$members = [];
try {
    $result = $db->query("SELECT * FROM organization_structure ORDER BY order_number ASC, id DESC");
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
} catch (Exception $e) {
    $error = "❌ Gagal mengambil data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Organisasi - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        /* Container & Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }

        .admin-content {
            margin-top: 2rem;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .admin-header h1 {
            font-size: 1.8rem;
            color: var(--primary-black);
            margin: 0;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: slideInDown 0.5s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Card */
        .card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table thead {
            background: var(--gradient-gold);
        }

        .table thead th {
            padding: 1rem;
            text-align: left;
            font-weight: bold;
            color: var(--primary-black);
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--light-gold);
        }

        /* Badge */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            white-space: nowrap;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--gradient-gold);
            color: var(--primary-black);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-gold);
        }

        .btn-secondary {
            background: var(--gradient-black);
            color: white;
        }

        .btn-secondary:hover {
            opacity: 0.9;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-warning {
            background: #ffc107;
            color: var(--primary-black);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning:hover,
        .btn-danger:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Modal Styles - CRITICAL: High z-index */
        .modal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
            animation: slideIn 0.3s ease;
            position: relative;
            z-index: 100000;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 2px solid #e0e0e0;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--primary-black);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }

        .form-text {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--gray-dark);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                align-items: stretch;
            }

            .admin-header .btn {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 1rem;
            }

            .modal-header {
                padding: 1rem 1.5rem;
            }

            .modal-body {
                padding: 1.5rem;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer .btn {
                width: 100%;
            }

            .table {
                font-size: 0.85rem;
            }

            .table thead th,
            .table tbody td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Auto-hide alert after 5 seconds */
        .alert.auto-hide {
            animation: slideInDown 0.5s ease, slideOutUp 0.5s ease 4.5s forwards;
        }

        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-content">
            <div class="admin-header">
                <h1>👥 Kelola Struktur Organisasi</h1>
                <button class="btn btn-primary" onclick="showAddModal()">
                    ➕ Tambah Anggota
                </button>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success auto-hide"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger auto-hide"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>Jabatan</th>
                                <th>Level</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Urutan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem; color: var(--gray-dark);">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                                    <p>Belum ada data anggota organisasi.</p>
                                    <button class="btn btn-primary" onclick="showAddModal()">
                                        ➕ Tambah Anggota Pertama
                                    </button>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($members as $index => $member): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <?php if (!empty($member['photo'])): ?>
                                            <img src="../<?= htmlspecialchars($member['photo']) ?>" 
                                                 style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-gold);"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div style="display: none; width: 50px; height: 50px; border-radius: 50%; background: var(--gradient-gold); align-items: center; justify-content: center; font-size: 1.5rem;">
                                                👤
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--gradient-gold); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                                👤
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($member['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($member['position']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $member['level'] === 'top' ? 'success' : ($member['level'] === 'middle' ? 'warning' : 'info') ?>">
                                            <?= $member['level'] === 'top' ? '⭐ Top' : ($member['level'] === 'middle' ? '💼 Middle' : '👨‍💼 Staff') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($member['email']) ?: '-' ?></td>
                                    <td><?= htmlspecialchars($member['phone']) ?: '-' ?></td>
                                    <td><?= $member['order_number'] ?></td>
                                    <td style="white-space: nowrap;">
                                        <button class="btn btn-sm btn-warning" onclick='editMember(<?= json_encode($member, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            ✏️
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Yakin ingin menghapus anggota ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="memberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Tambah Anggota</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="memberForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="memberId">
                    <input type="hidden" name="existing_photo" id="existingPhoto">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" name="name" id="memberName" class="form-control" required placeholder="Masukkan nama lengkap">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Jabatan *</label>
                            <input type="text" name="position" id="memberPosition" class="form-control" required placeholder="Masukkan jabatan">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Level *</label>
                            <select name="level" id="memberLevel" class="form-control" required>
                                <option value="">-- Pilih Level --</option>
                                <option value="top">⭐ Pimpinan (Top)</option>
                                <option value="middle">💼 Manajer (Middle)</option>
                                <option value="bottom">👨‍💼 Staff (Bottom)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="memberEmail" class="form-control" placeholder="email@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Telepon</label>
                            <input type="text" name="phone" id="memberPhone" class="form-control" placeholder="08xx-xxxx-xxxx">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Urutan Tampilan</label>
                            <input type="number" name="order_number" id="memberOrder" class="form-control" value="" placeholder="Kosongkan untuk urutan teratas">
                            <small class="form-text">Biarkan kosong agar tampil di urutan teratas</small>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label class="form-label">Foto Profil</label>
                            <input type="file" name="photo" id="memberPhoto" class="form-control" accept="image/jpeg,image/jpg,image/png">
                            <small class="form-text">📷 Format: JPG, PNG | Ukuran maksimal: 2MB</small>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label class="form-label">Deskripsi (Opsional)</label>
                            <textarea name="description" id="memberDescription" class="form-control" rows="3" placeholder="Tambahkan deskripsi atau informasi tambahan..."></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">❌ Batal</button>
                        <button type="submit" class="btn btn-primary">💾 Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show Add Modal
        function showAddModal() {
            document.getElementById('modalTitle').textContent = '➕ Tambah Anggota Baru';
            document.getElementById('formAction').value = 'add';
            document.getElementById('memberForm').reset();
            document.getElementById('memberId').value = '';
            document.getElementById('existingPhoto').value = '';
            document.getElementById('memberOrder').value = '';
            
            const modal = document.getElementById('memberModal');
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Edit Member
        function editMember(member) {
            document.getElementById('modalTitle').textContent = '✏️ Edit Anggota';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('memberId').value = member.id;
            document.getElementById('memberName').value = member.name;
            document.getElementById('memberPosition').value = member.position;
            document.getElementById('memberLevel').value = member.level;
            document.getElementById('memberEmail').value = member.email || '';
            document.getElementById('memberPhone').value = member.phone || '';
            document.getElementById('memberOrder').value = member.order_number;
            document.getElementById('memberDescription').value = member.description || '';
            document.getElementById('existingPhoto').value = member.photo || '';
            
            const modal = document.getElementById('memberModal');
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close Modal
        function closeModal() {
            const modal = document.getElementById('memberModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('memberModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert.auto-hide');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);

        // Form validation
        document.getElementById('memberForm').addEventListener('submit', function(e) {
            const level = document.getElementById('memberLevel').value;
            if (!level) {
                e.preventDefault();
                alert('⚠️ Mohon pilih level jabatan!');
                return false;
            }
        });
    </script>
</body>
</html>
<?php
// Flush output buffer at the end
ob_end_flush();
?>