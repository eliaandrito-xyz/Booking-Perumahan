<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

$db = new Database();

// Get organization structure
$organization = [];
try {
    $result = $db->query("
        SELECT * FROM organization_structure 
        WHERE status = 'active'
        ORDER BY order_number ASC, level DESC
    ");
    
    while ($row = $result->fetch_assoc()) {
        $organization[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data.";
}

// Group by level
$top_level = array_filter($organization, fn($o) => $o['level'] === 'top');
$middle_level = array_filter($organization, fn($o) => $o['level'] === 'middle');
$bottom_level = array_filter($organization, fn($o) => $o['level'] === 'bottom');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struktur Organisasi - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <style>
        /* Responsive Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-black);
            animation: fadeInDown 0.6s ease-out;
        }

        .page-description {
            color: var(--gray-dark);
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        /* Organization Chart */
        .org-chart {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .org-level {
            animation: fadeInUp 0.8s ease-out;
        }

        .org-level-title {
            text-align: center;
            font-size: 1.3rem;
            color: var(--accent-gold);
            margin-bottom: 2rem;
            font-weight: bold;
            position: relative;
            padding-bottom: 1rem;
        }

        .org-level-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--gradient-gold);
            border-radius: 2px;
        }

        .org-members {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            justify-items: center;
        }

        /* Member Card */
        .member-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .member-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-gold);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .member-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(255, 215, 0, 0.3);
        }

        .member-card:hover::before {
            transform: scaleX(1);
        }

        .member-photo-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
        }

        .member-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--accent-gold);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .member-card:hover .member-photo {
            transform: scale(1.1) rotate(5deg);
            border-color: var(--primary-black);
        }

        .member-photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--gradient-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            border: 4px solid var(--accent-gold);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .member-card:hover .member-photo-placeholder {
            transform: scale(1.1) rotate(5deg);
        }

        .member-info {
            text-align: center;
        }

        .member-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-black);
            margin-bottom: 0.5rem;
        }

        .member-position {
            font-size: 1rem;
            color: var(--accent-gold);
            font-weight: 600;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            background: var(--light-gold);
            border-radius: 20px;
            display: inline-block;
        }

        .member-contact {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px dashed #e0e0e0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray-dark);
        }

        .contact-icon {
            color: var(--accent-gold);
            font-size: 1.1rem;
        }

        /* Connection Lines */
        .connection-line {
            width: 2px;
            height: 40px;
            background: var(--gradient-gold);
            margin: 0 auto;
            position: relative;
            animation: growLine 0.8s ease-out;
        }

        @keyframes growLine {
            from {
                height: 0;
            }
            to {
                height: 40px;
            }
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        /* Desktop Responsive */
        @media (min-width: 768px) {
            .container {
                padding: 2rem;
            }

            .page-title {
                font-size: 2.5rem;
            }

            .page-description {
                font-size: 1.1rem;
            }

            .org-level-title {
                font-size: 1.5rem;
            }

            .org-members {
                grid-template-columns: repeat(2, 1fr);
            }

            .member-photo-container {
                width: 140px;
                height: 140px;
            }

            .member-photo,
            .member-photo-placeholder {
                width: 140px;
                height: 140px;
            }
        }

        @media (min-width: 1024px) {
            .org-members.top-level {
                grid-template-columns: repeat(2, 1fr);
            }

            .org-members.middle-level {
                grid-template-columns: repeat(3, 1fr);
            }

            .org-members.bottom-level {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Mobile Optimizations */
        @media (max-width: 767px) {
            .member-card {
                padding: 1.5rem;
            }

            .member-name {
                font-size: 1.1rem;
            }

            .member-position {
                font-size: 0.9rem;
            }

            .contact-item {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                👥 Struktur Organisasi
            </h1>
            <p class="page-description">
                Tim profesional kami yang berpengalaman siap melayani kebutuhan perumahan Anda
            </p>
        </div>

        <!-- Organization Chart -->
        <div class="org-chart">
            <!-- Top Level -->
            <?php if (!empty($top_level)): ?>
            <div class="org-level">
                <h2 class="org-level-title">⭐ Pimpinan</h2>
                <div class="org-members top-level">
                    <?php foreach ($top_level as $member): ?>
                    <div class="member-card">
                        <div class="member-photo-container">
                            <?php if (!empty($member['photo'])): ?>
                                <img src="<?= htmlspecialchars($member['photo']) ?>" 
                                     alt="<?= htmlspecialchars($member['name']) ?>"
                                     class="member-photo"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="member-photo-placeholder" style="display: none;">
                                    👤
                                </div>
                            <?php else: ?>
                                <div class="member-photo-placeholder">
                                    👤
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="member-info">
                            <h3 class="member-name"><?= htmlspecialchars($member['name']) ?></h3>
                            <div class="member-position"><?= htmlspecialchars($member['position']) ?></div>
                            
                            <div class="member-contact">
                                <?php if (!empty($member['email'])): ?>
                                <div class="contact-item">
                                    <span class="contact-icon">✉️</span>
                                    <span><?= htmlspecialchars($member['email']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['phone'])): ?>
                                <div class="contact-item">
                                    <span class="contact-icon">📞</span>
                                    <span><?= htmlspecialchars($member['phone']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="connection-line"></div>
            <?php endif; ?>

            <!-- Middle Level -->
            <?php if (!empty($middle_level)): ?>
            <div class="org-level">
                <h2 class="org-level-title">💼 Manajer</h2>
                <div class="org-members middle-level">
                    <?php foreach ($middle_level as $member): ?>
                    <div class="member-card">
                        <div class="member-photo-container">
                            <?php if (!empty($member['photo'])): ?>
                                <img src="<?= htmlspecialchars($member['photo']) ?>" 
                                     alt="<?= htmlspecialchars($member['name']) ?>"
                                     class="member-photo"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="member-photo-placeholder" style="display: none;">
                                    👤
                                </div>
                            <?php else: ?>
                                <div class="member-photo-placeholder">
                                    👤
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="member-info">
                            <h3 class="member-name"><?= htmlspecialchars($member['name']) ?></h3>
                            <div class="member-position"><?= htmlspecialchars($member['position']) ?></div>
                            
                            <div class="member-contact">
                                <?php if (!empty($member['email'])): ?>
                                <div class="contact-item">
                                    <span class="contact-icon">✉️</span>
                                    <span><?= htmlspecialchars($member['email']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['phone'])): ?>
                                <div class="contact-item">
                                    <span class="contact-icon">📞</span>
                                    <span><?= htmlspecialchars($member['phone']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="connection-line"></div>
            <?php endif; ?>

            <!-- Bottom Level -->
            <?php if (!empty($bottom_level)): ?>
            <div class="org-level">
                <h2 class="org-level-title">👨‍💼 Staff</h2>
                <div class="org-members bottom-level">
                    <?php foreach ($bottom_level as $member): ?>
                    <div class="member-card">
                        <div class="member-photo-container">
                            <?php if (!empty($member['photo'])): ?>
                                <img src="<?= htmlspecialchars($member['photo']) ?>" 
                                     alt="<?= htmlspecialchars($member['name']) ?>"
                                     class="member-photo"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="member-photo-placeholder" style="display: none;">
                                    👤
                                </div>
                            <?php else: ?>
                                <div class="member-photo-placeholder">
                                    👤
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="member-info">
                            <h3 class="member-name"><?= htmlspecialchars($member['name']) ?></h3>
                            <div class="member-position"><?= htmlspecialchars($member['position']) ?></div>
                            
                            <div class="member-contact">
                                <?php if (!empty($member['email'])): ?>
                                <div class="contact-item">
                                    <span class="contact-icon">✉️</span>
                                    <span><?= htmlspecialchars($member['email']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['phone'])): ?>
                                <div class="contact-item">
                                    <span class="contact-icon">📞</span>
                                    <span><?= htmlspecialchars($member['phone']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script src="assets/js/script.js"></script>
    <script>
        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.member-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = `all 0.6s ease-out ${index * 0.1}s`;
            observer.observe(card);
        });
    </script>
</body>
</html>