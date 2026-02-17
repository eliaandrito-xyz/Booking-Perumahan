<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db   = new Database();
$conn = $db->getConnection(); // ✅ FIX UTAMA

$search   = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$where_conditions = ["n.status = 'published'"];
$params = [];
$types  = "";

if (!empty($search)) {
    $where_conditions[] = "(n.title LIKE ? OR n.content LIKE ? OR n.excerpt LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if (!empty($category)) {
    $where_conditions[] = "n.category = ?";
    $params[] = $category;
    $types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

$sql = "
    SELECT n.*, u.full_name AS author_name
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    WHERE $where_clause
    ORDER BY n.published_at DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$news = [];
while ($row = $result->fetch_assoc()) {
    $news[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
   

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--primary-black);">
                    📰 Berita & Artikel
                </h1>
                <p style="color: var(--gray-dark); font-size: 1.1rem;">
                    Informasi terkini seputar properti dan perumahan di Kota Kupang
                </p>
            </div>

            <!-- Search & Filter -->
            <form method="GET" style="background: var(--gray-light); padding: 2rem; border-radius: 15px; margin-bottom: 2rem;">
                <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Cari Berita</label>
                        <input type="text" name="search" class="form-control" placeholder="Judul, konten..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Kategori</label>
                        <select name="category" class="form-control">
                            <option value="">Semua Kategori</option>
                            <option value="news" <?= $category === 'news' ? 'selected' : '' ?>>Berita</option>
                            <option value="tips" <?= $category === 'tips' ? 'selected' : '' ?>>Tips</option>
                            <option value="market_update" <?= $category === 'market_update' ? 'selected' : '' ?>>Update Pasar</option>
                            <option value="regulation" <?= $category === 'regulation' ? 'selected' : '' ?>>Regulasi</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            🔍 Cari
                        </button>
                        <a href="news.php" class="btn btn-secondary" style="margin-left: 0.5rem;">
                            🔄 Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- News Grid -->
        <?php if (empty($news)): ?>
            <div class="card" style="text-align: center; padding: 4rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📰</div>
                <h3>Tidak Ada Berita Ditemukan</h3>
                <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                    Coba ubah kriteria pencarian Anda atau lihat semua berita yang tersedia.
                </p>
                <a href="news.php" class="btn btn-primary">Lihat Semua Berita</a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
                <?php foreach ($news as $article): ?>
                <article class="card hover-lift" style="padding: 0; overflow: hidden;">
                    <!-- Article Image -->
                    <div style="
                        height: 200px;
                        background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(45, 45, 45, 0.8)),
                                    url('https://images.pexels.com/photos/210617/pexels-photo-210617.jpeg') center/cover;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-weight: bold;
                        position: relative;
                    ">
                        <div style="
                            position: absolute;
                            top: 1rem;
                            left: 1rem;
                            background: var(--gradient-gold);
                            color: var(--primary-black);
                            padding: 0.5rem 1rem;
                            border-radius: 20px;
                            font-size: 0.8rem;
                            font-weight: bold;
                            text-transform: uppercase;
                        ">
                            <?= ucfirst(str_replace('_', ' ', $article['category'])) ?>
                        </div>
                        📰 Berita
                    </div>

                    <div style="padding: 1.5rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-black); line-height: 1.3;">
                            <?= htmlspecialchars($article['title']) ?>
                        </h3>
                        
                        <p style="margin-bottom: 1rem; line-height: 1.6; color: var(--gray-dark);">
                            <?= htmlspecialchars($article['excerpt']) ?>
                        </p>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-top: 1rem; border-top: 1px solid #e0e0e0;">
                            <div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">
                                    📅 <?= date('d M Y', strtotime($article['published_at'])) ?>
                                </div>
                                <?php if ($article['author_name']): ?>
                                <div style="font-size: 0.8rem; color: var(--gray-dark); margin-top: 0.25rem;">
                                    ✍️ <?= htmlspecialchars($article['author_name']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <a href="news_detail.php?id=<?= $article['id'] ?>" class="btn btn-primary" style="width: 100%; text-align: center;">
                            📖 Baca Selengkapnya
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Results Summary -->
            <div style="text-align: center; margin-top: 3rem;">
                <p style="color: var(--gray-dark);">
                    Menampilkan <?= count($news) ?> artikel
                </p>
            </div>
        <?php endif; ?>
    </div>
         <?php
  require_once 'includes/footer.php';
  ?>
    <script src="assets/js/script.js"></script>
</body>
</html>