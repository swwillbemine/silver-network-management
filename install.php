<?php
// install_admin.php
require_once 'config/database.php';

// --- KONFIGURASI AKUN DEFAULT ---
$default_user = [
    'name'      => 'Super Administrator',
    'username'  => 'admin',           // Username default
    'email'     => 'admin@silvernet.id', // Email default
    'password'  => 'admin123',        // Password default (GANTI SETELAH LOGIN!)
    'role'      => 'superadmin'
];

try {
    // 1. Cek apakah user 'admin' atau email tersebut sudah ada?
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->execute([$default_user['username'], $default_user['email']]);
    
    if($check->rowCount() > 0) {
        echo "<h3 style='color:red'>Gagal: User admin sudah ada di database!</h3>";
        exit;
    }

    // 2. Hash Password (Keamanan Tingkat Tinggi)
    // Menggunakan algoritma default PHP (saat ini Bcrypt)
    $hashed_password = password_hash($default_user['password'], PASSWORD_DEFAULT);

    // 3. Insert ke Database
    $sql = "INSERT INTO users (name, username, email, password, role, is_active, created_at) 
            VALUES (:name, :user, :email, :pass, :role, 1, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'  => $default_user['name'],
        ':user'  => $default_user['username'],
        ':email' => $default_user['email'],
        ':pass'  => $hashed_password,
        ':role'  => $default_user['role']
    ]);

    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #ccc; max-width: 500px; margin: 50px auto; background: #f9f9f9;'>";
    echo "<h2 style='color:green'>Instalasi Berhasil! 🎉</h2>";
    echo "<p>Akun default telah dibuat.</p>";
    echo "<ul>";
    echo "<li>Username: <b>" . $default_user['username'] . "</b></li>";
    echo "<li>Email: <b>" . $default_user['email'] . "</b></li>";
    echo "<li>Password: <b>" . $default_user['password'] . "</b></li>";
    echo "</ul>";
    echo "<p style='color:red; font-weight:bold;'>PENTING: Segera hapus file 'install_admin.php' ini dari server Anda demi keamanan!</p>";
    echo "<a href='login.php' style='display:inline-block; padding:10px 20px; background:#6f42c1; color:white; text-decoration:none; border-radius:5px;'>Ke Halaman Login</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>