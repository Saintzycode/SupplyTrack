<?php
/**
 * includes/audit.php
 * Writes to audit_logs (NFR-3.3, Auditability). Never throws: a logging
 * failure must not break the user's action.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function audit_log(string $action, ?string $table = null, ?int $recordId = null, ?string $details = null, ?int $userId = null): void
{
    try {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address)
             VALUES (:u, :a, :t, :r, :d, :ip)'
        );
        $stmt->execute([
            ':u'  => $userId,
            ':a'  => $action,
            ':t'  => $table,
            ':r'  => $recordId,
            ':d'  => $details,
            ':ip' => client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}