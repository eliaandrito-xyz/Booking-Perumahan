# Sistem Informasi Perumahan Kota Kupang

Sistem informasi perumahan modern dengan tampilan yang menarik, banyak animasi, dan responsif menggunakan kombinasi warna hitam dan emas sebagai unsur kejayaan.

## Fitur Utama

### 🏠 **Untuk User**
- Registrasi dan login user
- Melihat daftar proyek perumahan
- Mencari dan melihat detail unit rumah
- Melakukan booking unit rumah
- Melihat status booking
- Membaca berita dan artikel
- Mengirim pesan kontak

### 👨‍💼 **Untuk Admin**
- Dashboard admin dengan statistik lengkap
- Manajemen proyek perumahan
- Manajemen unit rumah
- Manajemen booking
- Manajemen user
- Manajemen developer
- Manajemen berita/artikel
- Melihat pesan kontak

## Teknologi yang Digunakan

- **Backend**: PHP 7.4+ (tanpa framework)
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Server**: Apache (XAMPP)

## Instalasi

### 1. Persiapan Environment
- Install XAMPP (PHP 7.4+, MySQL, Apache)
- Pastikan Apache dan MySQL berjalan

### 2. Setup Database
1. Buka phpMyAdmin (http://localhost/phpmyadmin)
2. Buat database baru dengan nama `perumahan_kupang`
3. Import file `database.sql` ke database tersebut

### 3. Setup Aplikasi
1. Copy semua file ke folder `htdocs/perumahan-kupang/`
2. Buka browser dan akses `http://localhost/perumahan-kupang/`

### 4. Login Demo
- **Admin**: username `admin`, password `password`
- **User**: Daftar akun baru melalui halaman registrasi

## Struktur Database

### Tabel Utama:
- `users` - Data pengguna (admin & user)
- `developers` - Data developer perumahan
- `housing_projects` - Data proyek perumahan
- `house_units` - Data unit rumah
- `bookings` - Data booking unit
- `news` - Berita dan artikel
- `contact_messages` - Pesan kontak

## Fitur Desain

### 🎨 **Visual Design**
- Kombinasi warna hitam (#1a1a1a) dan emas (#ffd700)
- Gradient effects dan shadow yang elegan
- Typography yang modern dan readable
- Consistent spacing system (8px grid)

### ✨ **Animasi & Interaksi**
- Fade in animations untuk elemen
- Hover effects dengan transform dan glow
- Loading states untuk form submission
- Smooth transitions untuk semua interaksi
- Pulse animation untuk elemen penting

### 📱 **Responsive Design**
- Mobile-first approach
- Breakpoints: 480px, 768px, 1200px
- Flexible grid system
- Touch-friendly interface

### 🚀 **User Experience**
- Intuitive navigation
- Clear visual hierarchy
- Progressive disclosure
- Contextual feedback
- Error handling yang user-friendly

## Konfigurasi

### Database Connection
Edit file `config/database.php` untuk menyesuaikan koneksi database:

```php
private $host = "localhost";
private $username = "root";
private $password = "";
private $database = "perumahan_kupang";
```

### Session Configuration
File `config/session.php` mengatur session management dan authorization.

## File Structure

```
perumahan-kupang/
├── config/
│   ├── database.php      # Konfigurasi database
│   └── session.php       # Manajemen session
├── assets/
│   ├── css/
│   │   └── style.css     # Stylesheet utama
│   └── js/
│       └── script.js     # JavaScript utilities
├── admin/               # Panel admin (akan dibuat)
├── index.php           # Halaman utama
├── login.php           # Halaman login
├── register.php        # Halaman registrasi
├── dashboard.php       # Dashboard user/admin
├── projects.php        # Daftar proyek (akan dibuat)
├── units.php          # Daftar unit (akan dibuat)
├── news.php           # Halaman berita (akan dibuat)
├── contact.php        # Halaman kontak (akan dibuat)
├── logout.php         # Logout handler
├── database.sql       # Script database
└── README.md          # Dokumentasi
```

## Keamanan

- Password hashing menggunakan `password_hash()`
- Prepared statements untuk mencegah SQL injection
- Session management yang aman
- Input validation dan sanitization
- CSRF protection (akan ditambahkan)

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Kontribusi

Untuk berkontribusi pada proyek ini:
1. Fork repository
2. Buat branch fitur baru
3. Commit perubahan
4. Push ke branch
5. Buat Pull Request

## Lisensi

Proyek ini menggunakan lisensi MIT. Lihat file LICENSE untuk detail.

## Kontak

Untuk pertanyaan atau dukungan, hubungi:
- Email: info@perumahankupang.com
- Website: http://localhost/perumahan-kupang/

---

**Sistem Informasi Perumahan Kota Kupang** - Portal terpercaya untuk menemukan hunian berkualitas di jantung Nusa Tenggara Timur.