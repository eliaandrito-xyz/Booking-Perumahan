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
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $category = $_POST['category'] ?? 'news';
        $status = $_POST['status'] ?? 'draft';
        $author_id = getUserId();
        
        if (empty($title) || empty($content)) {
            $error = 'Judul dan konten harus diisi';
        } else {
            try {
                // Auto-generate excerpt if not provided
                if (empty($excerpt)) {
                    $excerpt = substr(strip_tags($content), 0, 200) . '...';
                }
                
                $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
                
                $stmt = $db->prepare("
                    INSERT INTO news (title, content, excerpt, category, status, author_id, published_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssis", $title, $content, $excerpt, $category, $status, $author_id, $published_at);
                
                if ($stmt->execute()) {
                    $success = 'Berita berhasil ditambahkan';
                    $_POST = []; // Clear form
                } else {
                    $error = 'Terjadi kesalahan saat menambah berita';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $category = $_POST['category'] ?? 'news';
        $status = $_POST['status'] ?? 'draft';
        
        if (empty($title) || empty($content)) {
            $error = 'Judul dan konten harus diisi';
        } else {
            try {
                // Auto-generate excerpt if not provided
                if (empty($excerpt)) {
                    $excerpt = substr(strip_tags($content), 0, 200) . '...';
                }
                
                // Update published_at if status changed to published
                $published_at_update = '';
                if ($status === 'published') {
                    $published_at_update = ', published_at = NOW()';
                }
                
                $stmt = $db->prepare("
                    UPDATE news 
                    SET title = ?, content = ?, excerpt = ?, category = ?, status = ? $published_at_update
                    WHERE id = ?
                ");
                $stmt->bind_param("sssssi", $title, $content, $excerpt, $category, $status, $id);
                
                if ($stmt->execute()) {
                    $success = 'Berita berhasil diperbarui';
                } else {
                    $error = 'Terjadi kesalahan saat memperbarui berita';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        try {
            $stmt = $db->prepare("DELETE FROM news WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = 'Berita berhasil dihapus';
            } else {
                $error = 'Terjadi kesalahan saat menghapus berita';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Get news with filters
$news = [];
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

try {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(n.title LIKE ? OR n.content LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param]);
        $types .= 'ss';
    }
    
    if (!empty($category_filter)) {
        $where_conditions[] = "n.category = ?";
        $params[] = $category_filter;
        $types .= 's';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "n.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT n.*, u.full_name as author_name
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        $where_clause
        ORDER BY n.created_at DESC
    ";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query($sql);
    }
    
    while ($row = $result->fetch_assoc()) {
        $news[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data berita.";
}

// Get news for editing
$edit_news = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_news = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = "Berita tidak ditemukan.";
    }
}

// Get statistics
$stats = [
    'total' => count($news),
    'published' => count(array_filter($news, fn($n) => $n['status'] === 'published')),
    'draft' => count(array_filter($news, fn($n) => $n['status'] === 'draft')),
    'news' => count(array_filter($news, fn($n) => $n['category'] === 'news')),
    'tips' => count(array_filter($news, fn($n) => $n['category'] === 'tips'))
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Berita - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                        📰 Kelola Berita & Artikel
                    </h1>
                    <p style="margin: 0; color: var(--gray-dark);">
                        Tulis, edit, dan kelola berita untuk website
                    </p>
                </div>
                <button onclick="showAddForm()" class="btn btn-primary">
                    ➕ Tulis Berita
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
                <div class="stat-icon">📰</div>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Artikel</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d4edda;">
                <div class="stat-icon" style="background: #155724; color: white;">✅</div>
                <div class="stat-number" style="color: #155724;"><?= $stats['published'] ?></div>
                <div class="stat-label" style="color: #155724;">Published</div>
            </div>
            <div class="stat-card hover-lift" style="background: #fff3cd;">
                <div class="stat-icon" style="background: #856404; color: white;">📝</div>
                <div class="stat-number" style="color: #856404;"><?= $stats['draft'] ?></div>
                <div class="stat-label" style="color: #856404;">Draft</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d1ecf1;">
                <div class="stat-icon" style="background: #0c5460; color: white;">📢</div>
                <div class="stat-number" style="color: #0c5460;"><?= $stats['news'] ?></div>
                <div class="stat-label" style="color: #0c5460;">Berita</div>
            </div>
            <div class="stat-card hover-lift" style="background: var(--light-gold);">
                <div class="stat-icon" style="background: var(--primary-black); color: var(--accent-gold);">💡</div>
                <div class="stat-number" style="color: var(--primary-black);"><?= $stats['tips'] ?></div>
                <div class="stat-label" style="color: var(--primary-black);">Tips</div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="card">
            <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Cari Berita</label>
                    <input type="text" name="search" class="form-control" placeholder="Judul atau konten..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-control">
                        <option value="">Semua Kategori</option>
                        <option value="news" <?= $category_filter === 'news' ? 'selected' : '' ?>>Berita</option>
                        <option value="tips" <?= $category_filter === 'tips' ? 'selected' : '' ?>>Tips</option>
                        <option value="market_update" <?= $category_filter === 'market_update' ? 'selected' : '' ?>>Update Pasar</option>
                        <option value="regulation" <?= $category_filter === 'regulation' ? 'selected' : '' ?>>Regulasi</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="published" <?= $status_filter === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <a href="news.php" class="btn btn-secondary" style="margin-left: 0.5rem;">🔄 Reset</a>
                </div>
            </form>
        </div>

        <!-- Add/Edit Form -->
        <div class="card" id="newsForm" style="<?= isset($_GET['add']) || $edit_news ? 'display: block;' : 'display: none;' ?>">
            <div class="card-header">
                <h2 class="card-title"><?= $edit_news ? 'Edit Berita' : 'Tulis Berita Baru' ?></h2>
                <button onclick="hideForm()" class="btn btn-secondary">Batal</button>
            </div>
            
            <form method="POST" id="addNewsForm">
                <input type="hidden" name="action" value="<?= $edit_news ? 'edit' : 'add' ?>">
                <?php if ($edit_news): ?>
                    <input type="hidden" name="id" value="<?= $edit_news['id'] ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label for="title" class="form-label">Judul Berita *</label>
                            <input type="text" id="title" name="title" class="form-control" required
                                   value="<?= $edit_news ? htmlspecialchars($edit_news['title']) : '' ?>"
                                   placeholder="Masukkan judul berita">
                        </div>

                        <div class="form-group">
                            <label for="excerpt" class="form-label">Ringkasan</label>
                            <textarea id="excerpt" name="excerpt" class="form-control" rows="3"
                                      placeholder="Ringkasan singkat berita (opsional, akan dibuat otomatis jika kosong)"><?= $edit_news ? htmlspecialchars($edit_news['excerpt']) : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="content" class="form-label">Konten Berita *</label>
                            <textarea id="content" name="content" class="form-control" rows="15" required
                                      placeholder="Tulis konten berita lengkap di sini..."><?= $edit_news ? htmlspecialchars($edit_news['content']) : '' ?></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="category" class="form-label">Kategori</label>
                            <select id="category" name="category" class="form-control">
                                <option value="news" <?= ($edit_news && $edit_news['category'] == 'news') ? 'selected' : '' ?>>Berita</option>
                                <option value="tips" <?= ($edit_news && $edit_news['category'] == 'tips') ? 'selected' : '' ?>>Tips</option>
                                <option value="market_update" <?= ($edit_news && $edit_news['category'] == 'market_update') ? 'selected' : '' ?>>Update Pasar</option>
                                <option value="regulation" <?= ($edit_news && $edit_news['category'] == 'regulation') ? 'selected' : '' ?>>Regulasi</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="draft" <?= ($edit_news && $edit_news['status'] == 'draft') ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= ($edit_news && $edit_news['status'] == 'published') ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>

                        <div style="background: var(--light-gold); padding: 1.5rem; border-radius: 10px; margin-bottom: 1.5rem;">
                            <h4 style="margin-bottom: 1rem; color: var(--primary-black);">📝 Tips Menulis:</h4>
                            <ul style="margin: 0; padding-left: 1.5rem; color: var(--gray-dark); line-height: 1.6; font-size: 0.9rem;">
                                <li>Gunakan judul yang menarik dan informatif</li>
                                <li>Tulis konten yang mudah dipahami</li>
                                <li>Sertakan informasi yang akurat dan terkini</li>
                                <li>Gunakan paragraf pendek untuk kemudahan baca</li>
                            </ul>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                                <?= $edit_news ? '💾 Perbarui Berita' : '📝 Publikasikan Berita' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- News List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Berita</h2>
                <div style="color: var(--gray-dark);">
                    Total: <?= count($news) ?> artikel
                </div>
            </div>
            
            <?php if (empty($news)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📰</div>
                    <h3>Belum Ada Berita</h3>
                    <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                        Mulai tulis berita pertama untuk website Anda.
                    </p>
                    <button onclick="showAddForm()" class="btn btn-primary">
                        ➕ Tulis Berita Pertama
                    </button>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Penulis</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($news as $article): ?>
                            <tr>
                                <td>#<?= $article['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($article['title']) ?></strong><br>
                                    <small style="color: var(--gray-dark);">
                                        <?= htmlspecialchars(substr($article['excerpt'], 0, 80)) ?>...
                                    </small>
                                </td>
                                <td>
                                    <span style="
                                        padding: 0.25rem 0.75rem;
                                        border-radius: 15px;
                                        font-size: 0.8rem;
                                        font-weight: bold;
                                        background: <?= 
                                            $article['category'] === 'news' ? '#d1ecf1' : 
                                            ($article['category'] === 'tips' ? 'var(--light-gold)' : 
                                            ($article['category'] === 'market_update' ? '#d4edda' : '#f8d7da')) 
                                        ?>;
                                        color: <?= 
                                            $article['category'] === 'news' ? '#0c5460' : 
                                            ($article['category'] === 'tips' ? 'var(--primary-black)' : 
                                            ($article['category'] === 'market_update' ? '#155724' : '#721c24')) 
                                        ?>;
                                    ">
                                        <?= ucfirst(str_replace('_', ' ', $article['category'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($article['author_name'] ?? 'Admin') ?></td>
                                <td>
                                    <?= date('d M Y', strtotime($article['created_at'])) ?><br>
                                    <small style="color: var(--gray-dark);"><?= date('H:i', strtotime($article['created_at'])) ?></small>
                                </td>
                                <td>
                                    <span style="
                                        padding: 0.25rem 0.75rem;
                                        border-radius: 15px;
                                        font-size: 0.8rem;
                                        font-weight: bold;
                                        background: <?= $article['status'] === 'published' ? '#d4edda' : '#fff3cd' ?>;
                                        color: <?= $article['status'] === 'published' ? '#155724' : '#856404' ?>;
                                    ">
                                        <?= ucfirst($article['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="?edit=<?= $article['id'] ?>" class="btn btn-secondary" style="padding: 0.5rem;">
                                            ✏️
                                        </a>
                                        <button onclick="deleteNews(<?= $article['id'] ?>, '<?= htmlspecialchars($article['title']) ?>')" 
                                                class="btn btn-danger" style="padding: 0.5rem;">
                                            🗑️
                                        </button>
                                        <?php if ($article['status'] === 'published'): ?>
                                            <a href="../news_detail.php?id=<?= $article['id'] ?>" class="btn btn-primary" style="padding: 0.5rem;" target="_blank">
                                                👁️
                                            </a>
                                        <?php endif; ?>
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

    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="../assets/js/script.js"></script>
    <script>
        function showAddForm() {
            document.getElementById('newsForm').style.display = 'block';
            document.getElementById('newsForm').scrollIntoView({ behavior: 'smooth' });
        }

        function hideForm() {
            document.getElementById('newsForm').style.display = 'none';
            // Clear form if it's add form
            if (!window.location.href.includes('edit=')) {
                document.getElementById('addNewsForm').reset();
            }
            // Remove edit parameter from URL
            if (window.location.href.includes('edit=')) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        function deleteNews(id, title) {
            confirmDialog(
                `Apakah Anda yakin ingin menghapus berita "${title}"? Tindakan ini tidak dapat dibatalkan.`,
                function() {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            );
        }

        // Auto-generate excerpt from content
        document.getElementById('content').addEventListener('input', function() {
            const excerptField = document.getElementById('excerpt');
            if (excerptField.value === '') {
                const content = this.value.replace(/<[^>]*>/g, ''); // Remove HTML tags
                const excerpt = content.substring(0, 200) + (content.length > 200 ? '...' : '');
                excerptField.value = excerpt;
            }
        });

        // Character counter for title
        document.getElementById('title').addEventListener('input', function() {
            const maxLength = 200;
            const currentLength = this.value.length;
            
            if (currentLength > maxLength) {
                this.value = this.value.substring(0, maxLength);
            }
        });

        // Form submission
        document.getElementById('addNewsForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const hideLoading = showLoading(submitBtn);
        });
    </script>
</body>
</html>