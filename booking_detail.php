<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

requireLogin();

$db = new Database();
$user_id = getUserId();
$booking_id = $_GET['id'] ?? '';

// Get booking details
$booking = null;
if ($booking_id) {
    try {
        $stmt = $db->prepare("
            SELECT b.*, hu.unit_number, hu.type, hu.price as unit_price, hu.land_area, hu.building_area,
                   hp.name as project_name, hp.location, hp.address, d.name as developer_name,
                   u.full_name as user_name
            FROM bookings b
            LEFT JOIN house_units hu ON b.unit_id = hu.id
            LEFT JOIN housing_projects hp ON hu.project_id = hp.id
            LEFT JOIN developers d ON hp.developer_id = d.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND b.user_id = ?
        ");
        $stmt->bind_param("ii", $booking_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if (!$booking) {
            header('Location: my_bookings.php');
            exit();
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan saat mengambil data booking.";
    }
} else {
    header('Location: my_bookings.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Booking #<?= $booking['id'] ?> - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
    

    <div class="container">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <nav style="color: var(--gray-dark); font-size: 0.9rem;">
                <a href="dashboard.php" style="color: var(--gray-dark); text-decoration: none;">Dashboard</a>
                <span style="margin: 0 0.5rem;">›</span>
                <a href="my_bookings.php" style="color: var(--gray-dark); text-decoration: none;">Booking Saya</a>
                <span style="margin: 0 0.5rem;">›</span>
                <span style="color: var(--accent-gold); font-weight: bold;">Booking #<?= $booking['id'] ?></span>
            </nav>
        </div>

        <!-- Booking Header -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; color: var(--primary-black);">
                        Booking #<?= $booking['id'] ?>
                    </h1>
                    <p style="color: var(--gray-dark); margin-bottom: 1rem;">
                        📅 Dibuat pada <?= date('d M Y H:i', strtotime($booking['booking_date'])) ?> WITA
                    </p>
                </div>
                
                <div style="text-align: right;">
                    <span style="
                        padding: 1rem 2rem;
                        border-radius: 25px;
                        font-size: 1.1rem;
                        font-weight: bold;
                        background: <?= 
                            $booking['status'] === 'confirmed' ? '#d4edda' : 
                            ($booking['status'] === 'pending' ? '#fff3cd' : 
                            ($booking['status'] === 'cancelled' ? '#f8d7da' : '#d1ecf1')) 
                        ?>;
                        color: <?= 
                            $booking['status'] === 'confirmed' ? '#155724' : 
                            ($booking['status'] === 'pending' ? '#856404' : 
                            ($booking['status'] === 'cancelled' ? '#721c24' : '#0c5460')) 
                        ?>;
                    ">
                        <?= ucfirst($booking['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Status Timeline -->
            <div style="background: var(--gray-light); padding: 2rem; border-radius: 15px;">
                <h3 style="margin-bottom: 1.5rem; color: var(--primary-black);">Status Booking</h3>
                <div style="display: flex; justify-content: space-between; align-items: center; position: relative;">
                    <!-- Timeline Line -->
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 0;
                        right: 0;
                        height: 4px;
                        background: #e0e0e0;
                        z-index: 1;
                    "></div>
                    
                    <!-- Timeline Steps -->
                    <div style="display: flex; justify-content: space-between; width: 100%; position: relative; z-index: 2;">
                        <div style="text-align: center;">
                            <div style="
                                width: 40px;
                                height: 40px;
                                background: var(--gradient-gold);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 0.5rem;
                                font-weight: bold;
                                color: var(--primary-black);
                            ">
                                1
                            </div>
                            <div style="font-size: 0.9rem; font-weight: bold;">Booking Dibuat</div>
                            <div style="font-size: 0.8rem; color: var(--gray-dark);">✓ Selesai</div>
                        </div>
                        
                        <div style="text-align: center;">
                            <div style="
                                width: 40px;
                                height: 40px;
                                background: <?= in_array($booking['status'], ['confirmed', 'completed']) ? 'var(--gradient-gold)' : '#e0e0e0' ?>;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 0.5rem;
                                font-weight: bold;
                                color: <?= in_array($booking['status'], ['confirmed', 'completed']) ? 'var(--primary-black)' : '#666' ?>;
                            ">
                                2
                            </div>
                            <div style="font-size: 0.9rem; font-weight: bold;">Verifikasi</div>
                            <div style="font-size: 0.8rem; color: var(--gray-dark);">
                                <?= in_array($booking['status'], ['confirmed', 'completed']) ? '✓ Selesai' : ($booking['status'] === 'pending' ? '⏳ Proses' : '❌ Dibatalkan') ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center;">
                            <div style="
                                width: 40px;
                                height: 40px;
                                background: <?= $booking['status'] === 'completed' ? 'var(--gradient-gold)' : '#e0e0e0' ?>;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 0.5rem;
                                font-weight: bold;
                                color: <?= $booking['status'] === 'completed' ? 'var(--primary-black)' : '#666' ?>;
                            ">
                                3
                            </div>
                            <div style="font-size: 0.9rem; font-weight: bold;">Pembayaran</div>
                            <div style="font-size: 0.8rem; color: var(--gray-dark);">
                                <?= $booking['status'] === 'completed' ? '✓ Selesai' : '⏳ Menunggu' ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center;">
                            <div style="
                                width: 40px;
                                height: 40px;
                                background: <?= $booking['status'] === 'completed' ? 'var(--gradient-gold)' : '#e0e0e0' ?>;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 0.5rem;
                                font-weight: bold;
                                color: <?= $booking['status'] === 'completed' ? 'var(--primary-black)' : '#666' ?>;
                            ">
                                4
                            </div>
                            <div style="font-size: 0.9rem; font-weight: bold;">Serah Terima</div>
                            <div style="font-size: 0.8rem; color: var(--gray-dark);">
                                <?= $booking['status'] === 'completed' ? '✓ Selesai' : '⏳ Menunggu' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Details Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
            <!-- Main Details -->
            <div>
                <!-- Unit Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informasi Unit</h2>
                    </div>
                    
                    <div style="
                        height: 200px;
                        background: linear-gradient(135deg, rgba(26, 26, 26, 0.7), rgba(45, 45, 45, 0.7)),
                                    url('https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg') center/cover;
                        border-radius: 15px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 1.5rem;
                        font-weight: bold;
                        margin-bottom: 2rem;
                    ">
                        Unit <?= htmlspecialchars($booking['unit_number']) ?>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                        <div>
                            <h4 style="margin-bottom: 1rem; color: var(--primary-black);">Detail Unit</h4>
                            <div style="space-y: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                    <span style="font-weight: bold;">Nomor Unit:</span>
                                    <span><?= htmlspecialchars($booking['unit_number']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                    <span style="font-weight: bold;">Tipe:</span>
                                    <span><?= htmlspecialchars($booking['type']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                    <span style="font-weight: bold;">Luas Tanah:</span>
                                    <span><?= $booking['land_area'] ?> m²</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <span style="font-weight: bold;">Luas Bangunan:</span>
                                    <span><?= $booking['building_area'] ?> m²</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="margin-bottom: 1rem; color: var(--primary-black);">Lokasi Proyek</h4>
                            <div style="space-y: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                    <span style="font-weight: bold;">Proyek:</span>
                                    <span><?= htmlspecialchars($booking['project_name']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                    <span style="font-weight: bold;">Lokasi:</span>
                                    <span><?= htmlspecialchars($booking['location']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                    <span style="font-weight: bold;">Developer:</span>
                                    <span><?= htmlspecialchars($booking['developer_name']) ?></span>
                                </div>
                                <div style="padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                    <div style="font-weight: bold; margin-bottom: 0.5rem;">Alamat Lengkap:</div>
                                    <div style="font-size: 0.9rem; line-height: 1.5;"><?= htmlspecialchars($booking['address']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informasi Customer</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                <span style="font-weight: bold;">Nama Lengkap:</span>
                                <span><?= htmlspecialchars($booking['customer_name']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                <span style="font-weight: bold;">Email:</span>
                                <span><?= htmlspecialchars($booking['customer_email']) ?></span>
                            </div>
                        </div>
                        
                        <div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 0.75rem;">
                                <span style="font-weight: bold;">Telepon:</span>
                                <span><?= htmlspecialchars($booking['customer_phone']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--gray-light); border-radius: 10px;">
                                <span style="font-weight: bold;">Tanggal Booking:</span>
                                <span><?= date('d M Y', strtotime($booking['booking_date'])) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($booking['customer_address'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--gray-light); border-radius: 10px;">
                        <div style="font-weight: bold; margin-bottom: 0.5rem;">Alamat Customer:</div>
                        <div style="line-height: 1.5;"><?= htmlspecialchars($booking['customer_address']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($booking['notes'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--light-gold); border-radius: 10px;">
                        <div style="font-weight: bold; margin-bottom: 0.5rem;">Catatan Booking:</div>
                        <div style="line-height: 1.5;"><?= htmlspecialchars($booking['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Financial Summary -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Ringkasan Finansial</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <div style="display: flex; justify-content: space-between; padding: 1rem; background: var(--gray-light); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Harga Unit:</span>
                            <span style="font-weight: bold;">Rp <?= number_format($booking['unit_price'], 0, ',', '.') ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 1rem; background: var(--light-gold); border-radius: 10px; margin-bottom: 1rem;">
                            <span style="font-weight: bold;">Biaya Booking:</span>
                            <span style="font-weight: bold; color: var(--primary-black);">Rp <?= number_format($booking['booking_fee'], 0, ',', '.') ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding: 1rem; background: var(--gradient-gold); border-radius: 10px;">
                            <span style="font-weight: bold; color: var(--primary-black);">Sisa Pembayaran:</span>
                            <span style="font-weight: bold; color: var(--primary-black);">
                                Rp <?= number_format($booking['unit_price'] - $booking['booking_fee'], 0, ',', '.') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Aksi</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <?php if ($booking['status'] === 'pending'): ?>
                        <button onclick="cancelBooking(<?= $booking['id'] ?>)" class="btn btn-danger" style="width: 100%; padding: 1rem; margin-bottom: 1rem;">
                            ❌ Batalkan Booking
                        </button>
                        <?php endif; ?>
                        
                        <a href="contact.php" class="btn btn-primary" style="width: 100%; padding: 1rem; text-align: center; margin-bottom: 1rem;">
                            📞 Hubungi Marketing
                        </a>
                        
                        <a href="unit_detail.php?id=<?= $booking['unit_id'] ?>" class="btn btn-secondary" style="width: 100%; padding: 1rem; text-align: center; margin-bottom: 1rem;">
                            🏠 Lihat Detail Unit
                        </a>
                        
                        <button onclick="printBooking()" class="btn btn-secondary" style="width: 100%; padding: 1rem;">
                            🖨️ Cetak Booking
                        </button>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Kontak Support</h2>
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
                            📞
                        </div>
                        <h4 style="margin-bottom: 1rem;">Tim Customer Service</h4>
                        <div style="space-y: 0.5rem; margin-bottom: 1rem;">
                            <p style="margin: 0.25rem 0; color: var(--gray-dark);">📞 (0380) 123-456</p>
                            <p style="margin: 0.25rem 0; color: var(--gray-dark);">📱 +62 812-3456-7890</p>
                            <p style="margin: 0.25rem 0; color: var(--gray-dark);">✉️ cs@perumahankupang.com</p>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--gray-dark); line-height: 1.5;">
                            <strong>Jam Operasional:</strong><br>
                            Senin - Jumat: 08:00 - 17:00<br>
                            Sabtu: 08:00 - 15:00
                        </div>
                    </div>
                </div>

                <!-- Booking History -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Riwayat Booking</h2>
                    </div>
                    
                    <div style="space-y: 1rem;">
                        <div style="padding: 1rem; border-left: 4px solid var(--accent-gold); background: var(--gray-light);">
                            <div style="font-weight: bold; margin-bottom: 0.25rem;">Booking Dibuat</div>
                            <div style="font-size: 0.9rem; color: var(--gray-dark);">
                                <?= date('d M Y H:i', strtotime($booking['created_at'])) ?> WITA
                            </div>
                        </div>
                        
                        <?php if ($booking['status'] !== 'pending'): ?>
                        <div style="padding: 1rem; border-left: 4px solid <?= $booking['status'] === 'confirmed' ? '#28a745' : '#dc3545' ?>; background: var(--gray-light);">
                            <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                Status: <?= ucfirst($booking['status']) ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--gray-dark);">
                                <?= date('d M Y H:i', strtotime($booking['updated_at'])) ?> WITA
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        function cancelBooking(bookingId) {
            confirmDialog(
                'Apakah Anda yakin ingin membatalkan booking ini? Tindakan ini tidak dapat dibatalkan dan biaya booking mungkin tidak dapat dikembalikan.',
                function() {
                    // Here you would typically make an AJAX call to cancel the booking
                    window.location.href = `cancel_booking.php?id=${bookingId}`;
                }
            );
        }

        function printBooking() {
            // Create a print-friendly version
            const printContent = `
                <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h1 style="color: #1a1a1a; margin-bottom: 10px;">Sistem Informasi Perumahan Kota Kupang</h1>
                        <h2 style="color: #ffd700;">Detail Booking #<?= $booking['id'] ?></h2>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h3>Informasi Unit</h3>
                        <p><strong>Unit:</strong> <?= htmlspecialchars($booking['unit_number']) ?></p>
                        <p><strong>Tipe:</strong> <?= htmlspecialchars($booking['type']) ?></p>
                        <p><strong>Proyek:</strong> <?= htmlspecialchars($booking['project_name']) ?></p>
                        <p><strong>Lokasi:</strong> <?= htmlspecialchars($booking['location']) ?></p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h3>Informasi Customer</h3>
                        <p><strong>Nama:</strong> <?= htmlspecialchars($booking['customer_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($booking['customer_email']) ?></p>
                        <p><strong>Telepon:</strong> <?= htmlspecialchars($booking['customer_phone']) ?></p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h3>Informasi Finansial</h3>
                        <p><strong>Harga Unit:</strong> Rp <?= number_format($booking['unit_price'], 0, ',', '.') ?></p>
                        <p><strong>Biaya Booking:</strong> Rp <?= number_format($booking['booking_fee'], 0, ',', '.') ?></p>
                        <p><strong>Sisa Pembayaran:</strong> Rp <?= number_format($booking['unit_price'] - $booking['booking_fee'], 0, ',', '.') ?></p>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #666;">
                        Dicetak pada: ${new Date().toLocaleDateString('id-ID')}
                    </div>
                </div>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Booking #<?= $booking['id'] ?></title>
                        <style>
                            body { margin: 0; padding: 20px; }
                            @media print { body { margin: 0; } }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>