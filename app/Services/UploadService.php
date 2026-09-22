<?php

namespace App\Services;

/**
 * File & Image Upload Service
 */
class UploadService
{
    private static array $allowedMime = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    ];

    private static array $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    public static function handleImageUpload(array $file, string $context = 'misc'): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Berkas tidak ditemukan.'];
        }

        // Validate MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::$allowedMime, true)) {
            return ['success' => false, 'error' => 'Tipe file tidak diizinkan. Gunakan PNG, JPG, GIF, WEBP, atau SVG.'];
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png');
        if (!in_array($ext, self::$allowedExt, true)) {
            return ['success' => false, 'error' => 'Ekstensi file tidak valid.'];
        }

        // Validate size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Ukuran file maksimal 2MB.'];
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : realpath(dirname(__DIR__, 2));
        $storageDir = $basePath . '/storage/uploads/';
        $publicDir  = $basePath . '/uploads/';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        $safeContext = preg_replace('/[^a-zA-Z0-9_-]/', '', $context) ?: 'upload';
        $filename = $safeContext . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        
        // Save in public uploads and storage
        $destPublic = $publicDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPublic)) {
            return ['success' => false, 'error' => 'Gagal menyimpan file.'];
        }

        // Copy backup to storage/uploads
        @copy($destPublic, $storageDir . $filename);

        return [
            'success' => true,
            'path'    => 'uploads/' . $filename,
            'filename'=> $filename
        ];
    }
}

