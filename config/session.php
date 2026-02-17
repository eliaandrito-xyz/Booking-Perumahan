<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Redirect ke login (root)
        header('Location: /gracia/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /gracia/dashboard.php');
        exit();
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserName() {
    return $_SESSION['username'] ?? null;
}

function logout() {
    // Simpan role sebelum session dihapus
    $role = $_SESSION['role'] ?? null;

    // Hapus semua session
    $_SESSION = [];
    session_destroy();

    // Redirect berdasarkan role & lokasi folder
    if ($role === 'admin') {
        header('Location: ../login.php'); // dipanggil dari /admin/logout.php
    } else {
        header('Location: login.php'); // dipanggil dari /logout.php (root)
    }
    exit();
}
