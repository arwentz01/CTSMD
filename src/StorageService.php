<?php

declare(strict_types=1);

final class StorageService
{
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function store(PDO $db, string $projectRoot, int $actorId, array $upload, ?int $storedFileId = null): array
    {
        $prepared = self::validateAndMove($projectRoot, $upload);
        try {
            if ($storedFileId === null) {
                $stmt = $db->prepare("INSERT INTO stored_files (storage_driver,created_by_user_id,status) VALUES ('local',:actor,'active')");
                $stmt->execute(['actor' => $actorId]);
                $storedFileId = (int)$db->lastInsertId();
                $versionNumber = 1;
            } else {
                $stmt = $db->prepare("SELECT id,storage_driver,status FROM stored_files WHERE id=:id FOR UPDATE");
                $stmt->execute(['id' => $storedFileId]);
                $file = $stmt->fetch();
                if (!$file || $file['storage_driver'] !== 'local') throw new RuntimeException('That stored file is unavailable.');
                $v = $db->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM stored_file_versions WHERE stored_file_id=:id');
                $v->execute(['id' => $storedFileId]);
                $versionNumber = (int)$v->fetchColumn();
            }

            $insert = $db->prepare('INSERT INTO stored_file_versions (stored_file_id,version_number,storage_key,original_name,mime_type,extension,byte_size,sha256,uploaded_by_user_id) VALUES (:file,:version,:key,:name,:mime,:extension,:size,:sha,:actor)');
            $insert->execute([
                'file' => $storedFileId,
                'version' => $versionNumber,
                'key' => $prepared['storage_key'],
                'name' => $prepared['original_name'],
                'mime' => $prepared['mime_type'],
                'extension' => $prepared['extension'],
                'size' => $prepared['byte_size'],
                'sha' => $prepared['sha256'],
                'actor' => $actorId,
            ]);
            return $prepared + [
                'stored_file_id' => $storedFileId,
                'version_id' => (int)$db->lastInsertId(),
                'version_number' => $versionNumber,
            ];
        } catch (Throwable $e) {
            @unlink($prepared['absolute_path']);
            throw $e;
        }
    }

    public static function currentVersion(PDO $db, int $storedFileId): ?array
    {
        $stmt = $db->prepare('SELECT sfv.* FROM stored_file_versions sfv WHERE sfv.stored_file_id=:file ORDER BY sfv.version_number DESC LIMIT 1');
        $stmt->execute(['file' => $storedFileId]);
        return $stmt->fetch() ?: null;
    }

    public static function version(PDO $db, int $storedFileId, ?int $versionId = null): ?array
    {
        if ($versionId) {
            $stmt = $db->prepare('SELECT * FROM stored_file_versions WHERE id=:version AND stored_file_id=:file LIMIT 1');
            $stmt->execute(['version' => $versionId, 'file' => $storedFileId]);
            return $stmt->fetch() ?: null;
        }
        return self::currentVersion($db, $storedFileId);
    }

    public static function versions(PDO $db, int $storedFileId): array
    {
        $stmt = $db->prepare("SELECT sfv.*,CONCAT(u.first_name,' ',u.last_name) uploader FROM stored_file_versions sfv LEFT JOIN users u ON u.id=sfv.uploaded_by_user_id WHERE sfv.stored_file_id=:file ORDER BY sfv.version_number DESC");
        $stmt->execute(['file' => $storedFileId]);
        return $stmt->fetchAll();
    }

    public static function stream(string $projectRoot, array $version, bool $download = true): never
    {
        $path = self::absolutePath($projectRoot, (string)$version['storage_key']);
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            exit('File not found');
        }
        $name = self::safeDownloadName((string)$version['original_name']);
        header('Content-Type: ' . (string)$version['mime_type']);
        header('Content-Length: ' . (string)filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . addcslashes($name, "\\\"") . '"');
        readfile($path);
        exit;
    }

    public static function deletePhysical(string $projectRoot, array $stored): void
    {
        $key = (string)($stored['storage_key'] ?? '');
        if ($key === '') return;
        $path = self::absolutePath($projectRoot, $key);
        if (is_file($path)) @unlink($path);
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    private static function validateAndMove(string $projectRoot, array $upload): array
    {
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a file to upload.');
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('The upload did not complete. Please try again.');

        $tmp = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('The uploaded file could not be verified.');
        $maxMb = max(1, (int)(getenv('STORAGE_MAX_UPLOAD_MB') ?: 20));
        if ($size < 1 || $size > $maxMb * 1024 * 1024) throw new RuntimeException('Files must be ' . $maxMb . ' MB or smaller.');

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        if (!$extension) throw new RuntimeException('That file type is not allowed. Upload a PDF, Office document, text/CSV file, JPG, PNG, or WebP image.');

        $original = trim((string)($upload['name'] ?? 'file'));
        $original = preg_replace('/[\x00-\x1F\x7F]+/u', '', $original) ?: 'file.' . $extension;
        if (mb_strlen($original) > 255) $original = mb_substr($original, 0, 230) . '.' . $extension;

        $storageKey = date('Y/m') . '/' . bin2hex(random_bytes(20)) . '.' . $extension;
        $absolute = self::absolutePath($projectRoot, $storageKey);
        $directory = dirname($absolute);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('CTSMD could not prepare secure file storage.');
        if (!move_uploaded_file($tmp, $absolute)) throw new RuntimeException('CTSMD could not store the uploaded file.');
        @chmod($absolute, 0660);

        return [
            'storage_key' => $storageKey,
            'absolute_path' => $absolute,
            'original_name' => $original,
            'mime_type' => $mime,
            'extension' => $extension,
            'byte_size' => filesize($absolute) ?: $size,
            'sha256' => hash_file('sha256', $absolute),
        ];
    }

    private static function absolutePath(string $projectRoot, string $storageKey): string
    {
        $configured = trim((string)(getenv('STORAGE_PATH') ?: ''));
        $root = $configured !== '' ? $configured : $projectRoot . '/storage/private';
        if (!str_starts_with($root, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:[\\\\\/]/', $root)) $root = $projectRoot . '/' . trim($root, '/\\');
        $clean = str_replace(['..', '\\'], ['', '/'], $storageKey);
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . ltrim($clean, '/');
    }

    private static function safeDownloadName(string $name): string
    {
        $name = str_replace(['"', '\\', "\r", "\n"], '', basename($name));
        return $name !== '' ? $name : 'ctsmd-file';
    }
}
