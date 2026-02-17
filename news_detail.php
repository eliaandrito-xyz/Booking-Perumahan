<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db = new Database();
$news_id = $_GET['id'] ?? '';

// Get news details
$news = null;
if ($news_id) {
    try {
        $stmt = $db->prepare("
            SELECT n.*, u.full_name as author_name
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id
            WHERE n.id = ? AND n.status = 'published'
        ");
        $stmt->bind_param("i", $news_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $news = $result->fetch_assoc();
        
        if (!$news) {
            header('Location: news.php');
            exit();
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan saat mengambil data berita.";
    }
} else {
    header('Location: news.php');
    exit();
}

// Get related news
$related_news = [];
try {
    $stmt = $db->prepare("
        SELECT n.*, u.full_name as author_name
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id
        WHERE n.category = ? AND n.id != ? AND n.status = 'published'
        ORDER BY n.published_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("si", $news['category'], $news_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $related_news[] = $row;
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
    <title><?= htmlspecialchars($news['title']) ?> - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <meta name="description" content="<?= htmlspecialchars($news['excerpt']) ?>">
</head>
<body>
  

    <div class="container">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav style="color: var(--gray-dark); font-size: 0.9rem;">
                <a href="index.php" style="color: var(--gray-dark); text-decoration: none;">Beranda</a>
                <span style="margin: 0 0.5rem;">›</span>
                <a href="news.php" style="color: var(--gray-dark); text-decoration: none;">Berita</a>
                <span style="margin: 0 0.5rem;">›</span>
                <span style="color: var(--accent-gold); font-weight: bold;"><?= htmlspecialchars($news['title']) ?></span>
            </nav>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
            <!-- Main Content -->
            <article class="card">
                <!-- Article Header -->
                <div style="margin-bottom: 2rem;">
                    <div style="margin-bottom: 1rem;">
                        <span style="
                            background: var(--gradient-gold);
                            color: var(--primary-black);
                            padding: 0.5rem 1rem;
                            border-radius: 20px;
                            font-size: 0.9rem;
                            font-weight: bold;
                            text-transform: uppercase;
                        ">
                            <?= ucfirst(str_replace('_', ' ', $news['category'])) ?>
                        </span>
                    </div>
                    
                    <h1 style="font-size: 2.5rem; line-height: 1.2; margin-bottom: 1rem; color: var(--primary-black);">
                        <?= htmlspecialchars($news['title']) ?>
                    </h1>
                    
                    <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
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
                                ✍️
                            </div>
                            <div>
                                <div style="font-weight: bold; color: var(--primary-black);">
                                    <?= $news['author_name'] ? htmlspecialchars($news['author_name']) : 'Admin' ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">Penulis</div>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="
                                width: 40px;
                                height: 40px;
                                background: var(--gradient-black);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 1.2rem;
                                color: white;
                            ">
                                📅
                            </div>
                            <div>
                                <div style="font-weight: bold; color: var(--primary-black);">
                                    <?= date('d M Y', strtotime($news['published_at'])) ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--gray-dark);">
                                    <?= date('H:i', strtotime($news['published_at'])) ?> WITA
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Featured Image -->
                <div style="
                    height: 400px;
                    background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(45, 45, 45, 0.8)),
                                url('https://images.pexels.com/photos/210617/pexels-photo-210617.jpeg') center/cover;
                    border-radius: 15px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 2rem;
                    font-weight: bold;
                    margin-bottom: 2rem;
                ">
                    📰 <?= ucfirst(str_replace('_', ' ', $news['category'])) ?>
                </div>

                <!-- Article Content -->
                <div style="line-height: 1.8; font-size: 1.1rem; color: var(--gray-dark);">
                    <?= nl2br(htmlspecialchars($news['content'])) ?>
                </div>

                <!-- Article Footer -->
                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid var(--gray-light);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h4 style="margin-bottom: 0.5rem; color: var(--primary-black);">Bagikan Artikel</h4>
                            <div style="display: flex; gap: 1rem;">
                                <button onclick="shareArticle('facebook')" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                                    📘 Facebook
                                </button>
                                <button onclick="shareArticle('twitter')" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                                    🐦 Twitter
                                </button>
                                <button onclick="shareArticle('whatsapp')" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                                    💬 WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <a href="news.php" class="btn btn-primary">
                                ← Kembali ke Berita
                            </a>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Sidebar -->
            <div>
                <!-- Related News -->
                <?php if (!empty($related_news)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Berita Terkait</h2>
                    </div>
                    
                    <div style="space-y: 1.5rem;">
                        <?php foreach ($related_news as $related): ?>
                        <article style="
                            border-bottom: 1px solid #e0e0e0;
                            padding-bottom: 1.5rem;
                            margin-bottom: 1.5rem;
                        ">
                            <div style="
                                height: 120px;
                                background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(45, 45, 45, 0.8)),
                                            url('https://images.pexels.com/photos/210617/pexels-photo-210617.jpeg') center/cover;
                                border-radius: 10px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: white;
                                font-weight: bold;
                                margin-bottom: 1rem;
                                font-size: 0.9rem;
                            ">
                                📰 Berita
                            </div>
                            
                            <h4 style="margin-bottom: 0.5rem; line-height: 1.3;">
                                <a href="news_detail.php?id=<?= $related['id'] ?>" style="color: var(--primary-black); text-decoration: none;">
                                    <?= htmlspecialchars($related['title']) ?>
                                </a>
                            </h4>
                            
                            <p style="color: var(--gray-dark); font-size: 0.9rem; line-height: 1.5; margin-bottom: 0.5rem;">
                                <?= htmlspecialchars(substr($related['excerpt'], 0, 100)) ?>...
                            </p>
                            
                            <div style="font-size: 0.8rem; color: var(--gray-dark);">
                                📅 <?= date('d M Y', strtotime($related['published_at'])) ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="news.php?category=<?= $news['category'] ?>" class="btn btn-primary" style="width: 100%;">
                            Lihat Berita Lainnya
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Newsletter -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Newsletter</h2>
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
                            📧
                        </div>
                        <h4 style="margin-bottom: 1rem;">Dapatkan Update Terbaru</h4>
                        <p style="color: var(--gray-dark); margin-bottom: 1.5rem; line-height: 1.6;">
                            Berlangganan newsletter kami untuk mendapatkan informasi terbaru seputar properti dan perumahan di Kupang.
                        </p>
                        
                        <form style="space-y: 1rem;">
                            <input type="email" placeholder="Masukkan email Anda" class="form-control" style="margin-bottom: 1rem;">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                📧 Berlangganan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Tautan Cepat</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <a href="projects.php" class="btn btn-secondary" style="width: 100%; text-align: left; padding: 1rem;">
                            🏘️ Lihat Proyek Terbaru
                        </a>
                        <a href="units.php" class="btn btn-secondary" style="width: 100%; text-align: left; padding: 1rem;">
                            🏠 Cari Unit Tersedia
                        </a>
                        <a href="contact.php" class="btn btn-secondary" style="width: 100%; text-align: left; padding: 1rem;">
                            📞 Hubungi Marketing
                        </a>
                        <?php if (!isLoggedIn()): ?>
                        <a href="register.php" class="btn btn-primary" style="width: 100%; text-align: left; padding: 1rem;">
                            📝 Daftar Akun
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        function shareArticle(platform) {
            const title = <?= json_encode($news['title']) ?>;
            const url = window.location.href;
            const text = <?= json_encode($news['excerpt']) ?>;
            
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodeURIComponent(title + ' - ' + url)}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        // Newsletter form
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            if (email) {
                showAlert('Terima kasih! Anda telah berlangganan newsletter kami.', 'success');
                this.reset();
            }
        });
    </script>
</body>
</html>