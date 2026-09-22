<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Auth\Auth;
use App\Core\Database;
use PDO;

/**
 * User & Account Management Service
 */
class UserService
{
    private UserRepository $userRepo;
    private PDO $pdo;

    public function __construct(?UserRepository $userRepo = null, ?PDO $pdo = null)
    {
        $this->pdo      = $pdo ?: Database::getConnection();
        $this->userRepo = $userRepo ?: new UserRepository($this->pdo);
    }

    public function getAllUsers(): array
    {
        return $this->pdo->query("SELECT * FROM users ORDER BY role ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        return $this->userRepo->find($id);
    }

    public function handleCreate(array $data, bool $isSuperadmin): array
    {
        if (!$isSuperadmin) {
            return ['success' => false, 'message' => 'danger:Akses ditolak.'];
        }

        $username = trim($data['username'] ?? '');
        $email    = trim($data['email'] ?? '');
        $name     = trim($data['name'] ?? '');
        $password = $data['password'] ?? '';
        $role     = $data['role'] ?? 'teknisi';

        $exists = $this->pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $exists->execute([$username, $email]);
        if ($exists->fetch()) {
            return ['success' => false, 'message' => 'danger:Username atau email sudah digunakan.'];
        }

        $stmt = $this->pdo->prepare("INSERT INTO users (name, username, password, email, phone, role, telegram_id, is_active) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $name,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $email ?: null,
            trim($data['phone'] ?? '') ?: null,
            $role,
            trim($data['telegram_id'] ?? '') ?: null,
            isset($data['is_active']) ? 1 : 0
        ]);

        return ['success' => true, 'message' => 'success:User berhasil ditambahkan.'];
    }

    public function handleUpdate(int $targetId, array $data, bool $isSuperadmin, int $currentUserId): array
    {
        if (!$isSuperadmin && $targetId !== $currentUserId) {
            return ['success' => false, 'message' => 'danger:Tidak ada akses untuk mengubah user lain.'];
        }

        $set = "name=?, email=?, phone=?, telegram_id=?";
        $params = [
            trim($data['name'] ?? ''),
            trim($data['email'] ?? '') ?: null,
            trim($data['phone'] ?? '') ?: null,
            trim($data['telegram_id'] ?? '') ?: null
        ];

        if ($isSuperadmin) {
            $set .= ", role=?, is_active=?";
            $params[] = $data['role'] ?? 'teknisi';
            $params[] = isset($data['is_active']) ? 1 : 0;
        }

        $params[] = $targetId;
        $this->pdo->prepare("UPDATE users SET $set WHERE id=?")->execute($params);

        return ['success' => true, 'message' => 'success:Data user berhasil diperbarui.'];
    }

    public function handleResetPassword(int $targetId, array $data, bool $isSuperadmin, int $currentUserId): array
    {
        if (!$isSuperadmin && $targetId !== $currentUserId) {
            return ['success' => false, 'message' => 'danger:Tidak ada akses.'];
        }

        $newPass     = $data['new_password'] ?? '';
        $confirmPass = $data['confirm_password'] ?? '';
        $oldPass     = $data['old_password'] ?? '';

        if ($newPass !== $confirmPass) {
            return ['success' => false, 'message' => 'danger:Konfirmasi password tidak cocok.'];
        }

        if (strlen($newPass) < 6) {
            return ['success' => false, 'message' => 'danger:Password minimal 6 karakter.'];
        }

        if (!$isSuperadmin || $targetId === $currentUserId) {
            $u = $this->pdo->prepare("SELECT password FROM users WHERE id=?");
            $u->execute([$targetId]);
            $row = $u->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($oldPass, $row['password'] ?? '')) {
                return ['success' => false, 'message' => 'danger:Password lama salah.'];
            }
        }

        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $this->pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $targetId]);

        return ['success' => true, 'message' => 'success:Password berhasil direset.'];
    }

    public function handleToggleActive(int $targetId, bool $isSuperadmin, int $currentUserId): array
    {
        if (!$isSuperadmin) {
            return ['success' => false, 'message' => 'danger:Akses ditolak.'];
        }

        if ($targetId === $currentUserId) {
            return ['success' => false, 'message' => 'danger:Tidak bisa menonaktifkan diri sendiri.'];
        }

        $this->pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([$targetId]);
        return ['success' => true, 'message' => 'success:Status user diubah.'];
    }

    public function handleDelete(int $targetId, bool $isSuperadmin, int $currentUserId): array
    {
        if (!$isSuperadmin) {
            return ['success' => false, 'message' => 'danger:Akses ditolak.'];
        }

        if ($targetId === $currentUserId) {
            return ['success' => false, 'message' => 'danger:Tidak bisa menghapus diri sendiri.'];
        }

        $this->pdo->prepare("DELETE FROM users WHERE id=?")->execute([$targetId]);
        return ['success' => true, 'message' => 'success:User berhasil dihapus.'];
    }
}
