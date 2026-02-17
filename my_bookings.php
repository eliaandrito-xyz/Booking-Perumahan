<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/navbar.php';

requireLogin();

$db = new Database();
$user_id = getUserId();

// Get user's bookings
$bookings = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, hu.unit_number, hu.type, hp.name as project_name, hp.location, d.name as developer_name
        FROM bookings b
        LEFT JOIN house_units hu ON b.unit_id = hu.id
        LEFT JOIN housing_projects hp ON hu.project_id = hp.id
        LEFT JOIN developers d ON hp.developer_id = d.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data booking.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Saya - Sistem Informasi Perumahan Kota Kupang</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
  

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--primary-black);">
                    📋 Booking Saya
                </h1>
                <p style="color: var(--gray-dark); font-size: 1.1rem;">
                    Kelola dan pantau status booking unit rumah Anda
                </p>
            </div>
        </div>

        <!-- Bookings List -->
        <?php if (empty($bookings)): ?>
            <div class="card" style="text-align: center; padding: 4rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                <h3>Belum Ada Booking</h3>
                <p style="color: var(--gray-dark); margin-bottom: 2rem;">
                    Anda belum melakukan booking unit rumah. Mulai cari unit impian Anda sekarang!
                </p>
                <a href="units.php" class="btn btn-primary">
                    🏠 Cari Unit Rumah
                </a>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 2rem;">
                <?php foreach ($bookings as $booking): ?>
                <div class="card hover-lift">
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 2rem; align-items: start;">
                        <!-- Booking Info -->
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                                        Booking #<?= $booking['id'] ?>
                                    </h3>
                                    <p style="color: var(--gray-dark); margin: 0;">
                                        📅 <?= date('d M Y H:i', strtotime($booking['booking_date'])) ?>
                                    </p>
                                </div>
                                <span style="
                                    padding: 0.5rem 1rem;
                                    border-radius: 20px;
                                    font-size: 0.9rem;
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

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
                                <!-- Unit Details -->
                                <div>
                                    <h4 style="margin-bottom: 1rem; color: var(--primary-black);">Detail Unit</h4>
                                    <div style="space-y: 0.5rem;">
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Unit:</strong> <?= htmlspecialchars($booking['unit_number']) ?>
                                        </p>
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Tipe:</strong> <?= htmlspecialchars($booking['type']) ?>
                                        </p>
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Proyek:</strong> <?= htmlspecialchars($booking['project_name']) ?>
                                        </p>
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Lokasi:</strong> <?= htmlspecialchars($booking['location']) ?>
                                        </p>
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Developer:</strong> <?= htmlspecialchars($booking['developer_name']) ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Customer Details -->
                                <div>
                                    <h4 style="margin-bottom: 1rem; color: var(--primary-black);">Detail Customer</h4>
                                    <div style="space-y: 0.5rem;">
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Nama:</strong> <?= htmlspecialchars($booking['customer_name']) ?>
                                        </p>
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Telepon:</strong> <?= htmlspecialchars($booking['customer_phone']) ?>
                                        </p>
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Email:</strong> <?= htmlspecialchars($booking['customer_email']) ?>
                                        </p>
                                        <?php if ($booking['customer_address']): ?>
                                        <p style="margin: 0.25rem 0; color: var(--gray-dark);">
                                            <strong>Alamat:</strong> <?= htmlspecialchars($booking['customer_address']) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Fee -->
                            <div style="background: var(--light-gold); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-weight: bold; color: var(--primary-black);">Biaya Booking:</span>
                                    <span style="font-size: 1.2rem; font-weight: bold; color: var(--primary-black);">
                                        Rp <?= number_format($booking['booking_fee'], 0, ',', '.') ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Notes -->
                            <?php if ($booking['notes']): ?>
                            <div style="background: var(--gray-light); padding: 1rem; border-radius: 10px;">
                                <h5 style="margin-bottom: 0.5rem; color: var(--primary-black);">Catatan:</h5>
                                <p style="margin: 0; color: var(--gray-dark); line-height: 1.5;">
                                    <?= htmlspecialchars($booking['notes']) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div style="display: flex; flex-direction: column; gap: 1rem; min-width: 150px;">
                            <a href="booking_detail.php?id=<?= $booking['id'] ?>" class="btn btn-primary" style="text-align: center;">
                                📋 Detail
                            </a>
                            
                            <?php if ($booking['status'] === 'pending'): ?>
                            <button onclick="cancelBooking(<?= $booking['id'] ?>)" class="btn btn-danger" style="text-align: center;">
                                ❌ Batalkan
                            </button>
                            <?php endif; ?>
                            
                            <a href="contact.php" class="btn btn-secondary" style="text-align: center;">
                                📞 Hubungi CS
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Ringkasan Booking</h2>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <?php
                    $total_bookings = count($bookings);
                    $pending_bookings = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
                    $confirmed_bookings = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
                    $cancelled_bookings = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));
                    ?>
                    
                    <div style="text-align: center; padding: 1.5rem; background: var(--gray-light); border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-black);"><?= $total_bookings ?></div>
                        <div style="color: var(--gray-dark);">Total Booking</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1.5rem; background: #fff3cd; border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏳</div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #856404;"><?= $pending_bookings ?></div>
                        <div style="color: #856404;">Pending</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1.5rem; background: #d4edda; border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #155724;"><?= $confirmed_bookings ?></div>
                        <div style="color: #155724;">Terkonfirmasi</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1.5rem; background: #f8d7da; border-radius: 10px;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">❌</div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #721c24;"><?= $cancelled_bookings ?></div>
                        <div style="color: #721c24;">Dibatalkan</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        function cancelBooking(bookingId) {
            confirmDialog(
                'Apakah Anda yakin ingin membatalkan booking ini? Tindakan ini tidak dapat dibatalkan.',
                function() {
                    // Here you would typically make an AJAX call to cancel the booking
                    window.location.href = `cancel_booking.php?id=${bookingId}`;
                }
            );
        }
    </script>
</body>
</html>