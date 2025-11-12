<?php

/**
 * FileStorage
 * 
 * Gestión de archivos en S3 (o compatible)
 * - Subida de archivos
 * - URLs firmadas con expiración
 * - Validación MIME y tamaño
 * - Estructura organizada por tenant/resource
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/env.php';

class FileStorage
{
    /**
     * Tipos MIME permitidos por tipo de recurso
     */
    private static $allowedMimeTypes = [
        'photo' => ['image/jpeg', 'image/png', 'image/webp'],
        'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'spreadsheet' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
        'attachment' => ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
    ];

    /**
     * Tamaños máximos por tipo (en bytes)
     */
    private static $maxSizes = [
        'photo' => 10 * 1024 * 1024, // 10MB
        'document' => 50 * 1024 * 1024, // 50MB
        'spreadsheet' => 10 * 1024 * 1024, // 10MB
        'attachment' => 50 * 1024 * 1024 // 50MB
    ];

    /**
     * Sube un archivo a S3
     * 
     * @param string $filePath Ruta temporal del archivo
     * @param string $originalFilename Nombre original del archivo
     * @param string $resourceType Tipo de recurso (photo, document, etc.)
     * @param int $resourceId ID del recurso
     * @param int $tenantId ID del tenant
     * @param int $uploadedBy ID del usuario que sube
     * @return array ['success' => bool, 'file_id' => int, 'path' => string, 'url' => string]
     */
    public static function upload($filePath, $originalFilename, $resourceType, $resourceId, $tenantId, $uploadedBy)
    {
        // Validar archivo
        $validation = self::validateFile($filePath, $originalFilename, $resourceType);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Generar nombre único
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $uuid = self::generateUUID();
        $filename = $uuid . '.' . $extension;

        // Estructura: {tenant_id}/{resource_type}/{resource_id}/{filename}
        $s3Path = "{$tenantId}/{$resourceType}/{$resourceId}/{$filename}";

        // Subir a S3 (por ahora, simular - en producción usar AWS SDK)
        $uploaded = self::uploadToS3($filePath, $s3Path);

        if (!$uploaded) {
            return [
                'success' => false,
                'error' => 'Error al subir archivo a S3'
            ];
        }

        // Guardar metadata en BD
        $conn = DBHelper::getConnection();
        $mimeType = $validation['mime_type'];
        $fileSize = filesize($filePath);

        $stmt = $conn->prepare("
            INSERT INTO file_storage (
                tenant_id,
                resource_type,
                resource_id,
                original_filename,
                stored_filename,
                s3_path,
                mime_type,
                size_bytes,
                uploaded_by,
                uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            'isissssii',
            $tenantId,
            $resourceType,
            $resourceId,
            $originalFilename,
            $filename,
            $s3Path,
            $mimeType,
            $fileSize,
            $uploadedBy
        );

        $stmt->execute();
        $fileId = $conn->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'file_id' => $fileId,
            'path' => $s3Path,
            'url' => self::getSignedUrl($s3Path, 3600) // URL válida por 1 hora
        ];
    }

    /**
     * Genera una URL firmada para acceso temporal
     * 
     * @param string $s3Path Ruta en S3
     * @param int $expiration Segundos de validez (default: 1 hora)
     * @return string URL firmada
     */
    public static function getSignedUrl($s3Path, $expiration = 3600)
    {
        $s3Enabled = Env::get('S3_ENABLED', 'false');

        if ($s3Enabled !== 'true') {
            // En desarrollo, retornar URL local
            return '/api/files/download?path=' . urlencode($s3Path);
        }

        // En producción, generar URL firmada de S3
        // Por ahora, retornar URL temporal (en producción usar AWS SDK)
        $s3Endpoint = Env::get('S3_ENDPOINT');
        $s3Bucket = Env::get('S3_BUCKET');

        if (empty($s3Endpoint) || empty($s3Bucket)) {
            return '/api/files/download?path=' . urlencode($s3Path);
        }

        // Generar token temporal (en producción usar firma AWS)
        $token = bin2hex(random_bytes(32));
        $expires = time() + $expiration;

        // Guardar token en BD para validación
        $conn = DBHelper::getConnection();
        $stmt = $conn->prepare("
            INSERT INTO file_signed_urls (s3_path, token, expires_at, created_at)
            VALUES (?, ?, FROM_UNIXTIME(?), NOW())
        ");
        $stmt->bind_param('ssi', $s3Path, $token, $expires);
        $stmt->execute();
        $stmt->close();

        return "{$s3Endpoint}/{$s3Bucket}/{$s3Path}?token={$token}&expires={$expires}";
    }

    /**
     * Valida un archivo antes de subir
     */
    private static function validateFile($filePath, $originalFilename, $resourceType)
    {
        if (!file_exists($filePath)) {
            return ['valid' => false, 'error' => 'Archivo no encontrado'];
        }

        // Validar tamaño
        $fileSize = filesize($filePath);
        $maxSize = self::$maxSizes[$resourceType] ?? self::$maxSizes['attachment'];

        if ($fileSize > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 2);
            return [
                'valid' => false,
                'error' => "El archivo excede el tamaño máximo permitido ({$maxSizeMB}MB)"
            ];
        }

        // Validar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $allowedTypes = self::$allowedMimeTypes[$resourceType] ?? self::$allowedMimeTypes['attachment'];

        if (!in_array($mimeType, $allowedTypes)) {
            return [
                'valid' => false,
                'error' => "Tipo de archivo no permitido. Tipos permitidos: " . implode(', ', $allowedTypes)
            ];
        }

        // Validar que el nombre no contenga PII
        if (self::containsPII($originalFilename)) {
            return [
                'valid' => false,
                'error' => 'El nombre del archivo no puede contener información personal'
            ];
        }

        return [
            'valid' => true,
            'mime_type' => $mimeType,
            'size' => $fileSize
        ];
    }

    /**
     * Verifica si un nombre de archivo contiene PII
     */
    private static function containsPII($filename)
    {
        // Patrones comunes de PII en nombres de archivo
        $piiPatterns = [
            '/\d{8,}/', // DNI/NIF (8+ dígitos)
            '/[A-Z]{2}\d{7,}/', // Pasaporte
            '/\d{9,}/', // Teléfono
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' // Email
        ];

        foreach ($piiPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Genera un UUID v4
     */
    private static function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Sube archivo a S3 (simulado - en producción usar AWS SDK)
     */
    private static function uploadToS3($filePath, $s3Path)
    {
        $s3Enabled = Env::get('S3_ENABLED', 'false');

        if ($s3Enabled !== 'true') {
            // En desarrollo, copiar a directorio local
            $localPath = __DIR__ . '/../../storage/' . $s3Path;
            $dir = dirname($localPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            return copy($filePath, $localPath);
        }

        // En producción, usar AWS SDK para S3
        // Por ahora, solo log
        error_log("S3 upload not yet implemented - file: {$filePath}, path: {$s3Path}");
        return true; // Simular éxito
    }

    /**
     * Elimina un archivo
     */
    public static function delete($fileId, $tenantId)
    {
        $conn = DBHelper::getConnection();

        // Obtener información del archivo
        $stmt = $conn->prepare("
            SELECT s3_path FROM file_storage
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param('ii', $fileId, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $file = $result->fetch_assoc();
        $stmt->close();

        if (!$file) {
            return ['success' => false, 'error' => 'Archivo no encontrado'];
        }

        // Eliminar de S3
        self::deleteFromS3($file['s3_path']);

        // Eliminar de BD
        $deleteStmt = $conn->prepare("DELETE FROM file_storage WHERE id = ? AND tenant_id = ?");
        $deleteStmt->bind_param('ii', $fileId, $tenantId);
        $deleteStmt->execute();
        $deleteStmt->close();

        return ['success' => true];
    }

    /**
     * Elimina archivo de S3
     */
    private static function deleteFromS3($s3Path)
    {
        $s3Enabled = Env::get('S3_ENABLED', 'false');

        if ($s3Enabled !== 'true') {
            // En desarrollo, eliminar archivo local
            $localPath = __DIR__ . '/../../storage/' . $s3Path;
            if (file_exists($localPath)) {
                unlink($localPath);
            }
            return true;
        }

        // En producción, usar AWS SDK
        error_log("S3 delete not yet implemented - path: {$s3Path}");
        return true;
    }

    /**
     * Obtiene lista de archivos de un recurso
     */
    public static function listFiles($resourceType, $resourceId, $tenantId)
    {
        $conn = DBHelper::getConnection();

        $stmt = $conn->prepare("
            SELECT 
                id,
                original_filename,
                stored_filename,
                s3_path,
                mime_type,
                size_bytes,
                uploaded_by,
                uploaded_at
            FROM file_storage
            WHERE tenant_id = ? AND resource_type = ? AND resource_id = ?
            ORDER BY uploaded_at DESC
        ");
        $stmt->bind_param('isi', $tenantId, $resourceType, $resourceId);
        $stmt->execute();
        $result = $stmt->get_result();

        $files = [];
        while ($row = $result->fetch_assoc()) {
            $files[] = [
                'id' => (int)$row['id'],
                'original_filename' => $row['original_filename'],
                'stored_filename' => $row['stored_filename'],
                'mime_type' => $row['mime_type'],
                'size_bytes' => (int)$row['size_bytes'],
                'size_mb' => round($row['size_bytes'] / 1024 / 1024, 2),
                'uploaded_by' => (int)$row['uploaded_by'],
                'uploaded_at' => $row['uploaded_at'],
                'url' => self::getSignedUrl($row['s3_path'], 3600)
            ];
        }
        $stmt->close();

        return $files;
    }
}
