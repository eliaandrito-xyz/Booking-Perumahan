<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'includes/navbar.php';

requireAdmin();

$db = new Database();
$success = '';
$error = '';

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $message_id = $_POST['message_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        if (!empty($message_id) && !empty($new_status)) {
            try {
                $stmt = $db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $message_id);
                
                if ($stmt->execute()) {
                    $success = 'Status pesan berhasil diperbarui';
                } else {
                    $error = 'Terjadi kesalahan saat memperbarui status';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $message_id = $_POST['message_id'] ?? '';
        
        try {
            $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->bind_param("i", $message_id);
            
            if ($stmt->execute()) {
                $success = 'Pesan berhasil dihapus';
            } else {
                $error = 'Terjadi kesalahan saat menghapus pesan';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Get messages with filters
$messages = [];
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$subject_filter = $_GET['subject'] ?? '';

try {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ? OR message LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
        $types .= 'sss';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if (!empty($subject_filter)) {
        $where_conditions[] = "subject LIKE ?";
        $params[] = "%$subject_filter%";
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT * FROM contact_messages 
        $where_clause
        ORDER BY created_at DESC
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
        $messages[] = $row;
    }
} catch (Exception $e) {
    $error = "Terjadi kesalahan saat mengambil data pesan.";
}

// Get statistics
$stats = [
    'total' => count($messages),
    'unread' => count(array_filter($messages, fn($m) => $m['status'] === 'unread')),
    'read' => count(array_filter($messages, fn($m) => $m['status'] === 'read')),
    'replied' => count(array_filter($messages, fn($m) => $m['status'] === 'replied'))
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Kontak - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
</head>
<body>
    <!-- Header -->

    <div class="container">
        <!-- Page Header -->
        <div class="card">
            <div>
                <h1 style="margin-bottom: 0.5rem; color: var(--primary-black);">
                    💬 Pesan Kontak
                </h1>
                <p style="margin: 0; color: var(--gray-dark);">
                    Kelola dan balas pesan dari pengunjung website
                </p>
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
                <div class="stat-icon">💬</div>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Pesan</div>
            </div>
            <div class="stat-card hover-lift" style="background: #fff3cd;">
                <div class="stat-icon" style="background: #856404; color: white;">📩</div>
                <div class="stat-number" style="color: #856404;"><?= $stats['unread'] ?></div>
                <div class="stat-label" style="color: #856404;">Belum Dibaca</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d1ecf1;">
                <div class="stat-icon" style="background: #0c5460; color: white;">👁️</div>
                <div class="stat-number" style="color: #0c5460;"><?= $stats['read'] ?></div>
                <div class="stat-label" style="color: #0c5460;">Sudah Dibaca</div>
            </div>
            <div class="stat-card hover-lift" style="background: #d4edda;">
                <div class="stat-icon" style="background: #155724; color: white;">✅</div>
                <div class="stat-number" style="color: #155724;"><?= $stats['replied'] ?></div>
                <div class="stat-label" style="color: #155724;">Sudah Dibalas</div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="card">
            <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Cari Pesan</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama, email, atau isi pesan..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="unread" <?= $status_filter === 'unread' ? 'selected' : '' ?>>Belum Dibaca</option>
                        <option value="read" <?= $status_filter === 'read' ? 'selected' : '' ?>>Sudah Dibaca</option>
                        <option value="replied" <?= $status_filter === 'replied' ? 'selected' : '' ?>>Sudah Dibalas</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Subjek</label>
                    <select name="subject" class="form-control">
                        <option value="">Semua Subjek</option>
                        <option value="Informasi Proyek" <?= $subject_filter === 'Informasi Proyek' ? 'selected' : '' ?>>Informasi Proyek</option>
                        <option value="Booking Unit" <?= $subject_filter === 'Booking Unit' ? 'selected' : '' ?>>Booking Unit</option>
                        <option value="Konsultasi" <?= $subject_filter === 'Konsultasi' ? 'selected' : '' ?>>Konsultasi</option>
                        <option value="Keluhan" <?= $subject_filter === 'Keluhan' ? 'selected' : '' ?>>Keluhan</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <a href="messages.php" class="btn btn-secondary" style="margin-left: 0.5rem;">🔄 Reset</a>
                </div>
            </form>
        </div>

        <!-- Messages List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daftar Pesan</h2>
                <div style="color: var(--gray-dark);">
                    Total: <?= count($messages) ?> pesan
                </div>
            </div>
            
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 4rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">💬</div>
                    <h3>Belum Ada Pesan</h3>
                    <p style="color: var(--gray-dark);">
                        Pesan dari pengunjung akan muncul di sini.
                    </p>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 1.5rem;">
                    <?php foreach ($messages as $message): ?>
                    <div style="
                        border: 2px solid <?= $message['status'] === 'unread' ? 'var(--accent-gold)' : '#e0e0e0' ?>;
                        border-radius: 15px;
                        padding: 1.5rem;
                        transition: all 0.3s ease;
                        background: <?= $message['status'] === 'unread' ? 'var(--light-gold)' : 'white' ?>;
                    " onmouseover="this.style.transform='translateY(-2px)'"
                       onmouseout="this.style.transform='translateY(0)'">
                        
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="
                                    width: 50px;
                                    height: 50px;
                                    background: var(--gradient-gold);
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 1.2rem;
                                ">
                                    👤
                                </div>
                                <div>
                                    <h3 style="margin: 0; color: var(--primary-black);">
                                        <?= htmlspecialchars($message['name']) ?>
                                    </h3>
                                    <p style="margin: 0; color: var(--gray-dark); font-size: 0.9rem;">
                                        <?= htmlspecialchars($message['email']) ?>
                                        <?php if ($message['phone']): ?>
                                            • <?= htmlspecialchars($message['phone']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div style="text-align: right;">
                                <select onchange="updateMessageStatus(<?= $message['id'] ?>, this.value)" style="
                                    padding: 0.25rem 0.5rem;
                                    border-radius: 15px;
                                    border: none;
                                    font-size: 0.8rem;
                                    font-weight: bold;
                                    background: <?= 
                                        $message['status'] === 'unread' ? '#fff3cd' : 
                                        ($message['status'] === 'read' ? '#d1ecf1' : '#d4edda') 
                                    ?>;
                                    color: <?= 
                                        $message['status'] === 'unread' ? '#856404' : 
                                        ($message['status'] === 'read' ? '#0c5460' : '#155724') 
                                    ?>;
                                    margin-bottom: 0.5rem;
                                ">
                                    <option value="unread" <?= $message['status'] === 'unread' ? 'selected' : '' ?>>Belum Dibaca</option>
                                    <option value="read" <?= $message['status'] === 'read' ? 'selected' : '' ?>>Sudah Dibaca</option>
                                    <option value="replied" <?= $message['status'] === 'replied' ? 'selected' : '' ?>>Sudah Dibalas</option>
                                </select>
                                <div style="font-size: 0.8rem; color: var(--gray-dark);">
                                    <?= date('d M Y H:i', strtotime($message['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="
                                background: var(--gradient-gold);
                                color: var(--primary-black);
                                padding: 0.5rem 1rem;
                                border-radius: 20px;
                                display: inline-block;
                                font-weight: bold;
                                font-size: 0.9rem;
                                margin-bottom: 1rem;
                            ">
                                <?= htmlspecialchars($message['subject']) ?>
                            </div>
                            
                            <div style="
                                background: white;
                                padding: 1.5rem;
                                border-radius: 10px;
                                border-left: 4px solid var(--accent-gold);
                                line-height: 1.6;
                                color: var(--gray-dark);
                            ">
                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                            <button onclick="replyMessage('<?= htmlspecialchars($message['email']) ?>', '<?= htmlspecialchars($message['subject']) ?>')" 
                                    class="btn btn-primary" style="padding: 0.5rem 1rem;">
                                📧 Balas Email
                            </button>
                            <button onclick="deleteMessage(<?= $message['id'] ?>, '<?= htmlspecialchars($message['name']) ?>')" 
                                    class="btn btn-danger" style="padding: 0.5rem 1rem;">
                                🗑️ Hapus
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Update Status Form (Hidden) -->
    <form id="updateStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="message_id" id="statusMessageId">
        <input type="hidden" name="new_status" id="newMessageStatus">
    </form>

    <!-- Delete Form (Hidden) -->
    <form id="deleteMessageForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="message_id" id="deleteMessageId">
    </form>

    <script src="../assets/js/script.js"></script>
    <script>
        function updateMessageStatus(messageId, newStatus) {
            const currentStatus = event.target.getAttribute('data-current') || event.target.value;
            
            if (newStatus !== currentStatus) {
                document.getElementById('statusMessageId').value = messageId;
                document.getElementById('newMessageStatus').value = newStatus;
                document.getElementById('updateStatusForm').submit();
            }
        }

        function replyMessage(email, subject) {
            const replySubject = 'Re: ' + subject;
            const mailtoLink = `mailto:${email}?subject=${encodeURIComponent(replySubject)}`;
            window.open(mailtoLink);
        }

        function deleteMessage(messageId, senderName) {
            confirmDialog(
                `Apakah Anda yakin ingin menghapus pesan dari "${senderName}"? Tindakan ini tidak dapat dibatalkan.`,
                function() {
                    document.getElementById('deleteMessageId').value = messageId;
                    document.getElementById('deleteMessageForm').submit();
                }
            );
        }

        // Store current status for reset functionality
        document.querySelectorAll('select[onchange*="updateMessageStatus"]').forEach(select => {
            select.setAttribute('data-current', select.value);
        });

        // Auto-mark as read when hovering over unread messages
        document.querySelectorAll('div[style*="var(--light-gold)"]').forEach(messageDiv => {
            messageDiv.addEventListener('mouseenter', function() {
                const select = this.querySelector('select');
                if (select && select.value === 'unread') {
                    setTimeout(() => {
                        if (select.value === 'unread') {
                            select.value = 'read';
                            updateMessageStatus(select.getAttribute('onchange').match(/\d+/)[0], 'read');
                        }
                    }, 2000); // Mark as read after 2 seconds of hovering
                }
            });
        });
    </script>
</body>
</html>