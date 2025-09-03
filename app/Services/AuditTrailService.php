<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditTrailService
{
    /**
     * Log a create action
     */
    public static function logCreate(string $module, string $tableName, $recordId, array $newValues, string $empno = null, string $description = null): void
    {
        try {
            AuditTrail::log([
                'empno' => $empno ?? self::getCurrentEmpno(),
                'action' => 'CREATE',
                'module' => $module,
                'table_name' => $tableName,
                'record_id' => (string) $recordId,
                'old_values' => null,
                'new_values' => $newValues,
                'description' => $description ?? "Created new {$tableName} record with ID: {$recordId}",
            ]);
        } catch (\Exception $e) {
            Log::error('Audit Trail Create Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Log an update action
     */
    public static function logUpdate(string $module, string $tableName, $recordId, array $oldValues, array $newValues, string $empno = null, string $description = null): void
    {
        try {
            // Only log if there are actual changes
            $changes = array_diff_assoc($newValues, $oldValues);
            if (empty($changes)) {
                return; // No changes to log
            }

            AuditTrail::log([
                'empno' => $empno ?? self::getCurrentEmpno(),
                'action' => 'UPDATE',
                'module' => $module,
                'table_name' => $tableName,
                'record_id' => (string) $recordId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'description' => $description ?? "Updated {$tableName} record with ID: {$recordId}",
            ]);
        } catch (\Exception $e) {
            Log::error('Audit Trail Update Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Log a delete action
     */
    public static function logDelete(string $module, string $tableName, $recordId, array $deletedValues, string $empno = null, string $description = null): void
    {
        try {
            AuditTrail::log([
                'empno' => $empno ?? self::getCurrentEmpno(),
                'action' => 'DELETE',
                'module' => $module,
                'table_name' => $tableName,
                'record_id' => (string) $recordId,
                'old_values' => $deletedValues,
                'new_values' => null,
                'description' => $description ?? "Deleted {$tableName} record with ID: {$recordId}",
            ]);
        } catch (\Exception $e) {
            Log::error('Audit Trail Delete Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Log a login action
     */
    public static function logLogin(string $empno, bool $success = true, string $description = null): void
    {
        try {
            AuditTrail::log([
                'empno' => $empno,
                'action' => $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED',
                'module' => 'authentication',
                'table_name' => 'userprofile',
                'record_id' => $empno,
                'old_values' => null,
                'new_values' => null,
                'description' => $description ?? ($success ? 'User logged in successfully' : 'Failed login attempt'),
            ]);
        } catch (\Exception $e) {
            Log::error('Audit Trail Login Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Log a logout action
     */
    public static function logLogout(string $empno, string $description = null): void
    {
        try {
            AuditTrail::log([
                'empno' => $empno,
                'action' => 'LOGOUT',
                'module' => 'authentication',
                'table_name' => 'userprofile',
                'record_id' => $empno,
                'old_values' => null,
                'new_values' => null,
                'description' => $description ?? 'User logged out',
            ]);
        } catch (\Exception $e) {
            Log::error('Audit Trail Logout Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Log a custom action
     */
    public static function logCustomAction(
        string $empno,
        string $action,
        string $module,
        string $description,
        string $tableName = null,
        $recordId = null,
        array $oldValues = null,
        array $newValues = null
    ): void {
        try {
            AuditTrail::log([
                'empno' => $empno,
                'action' => strtoupper($action),
                'module' => $module,
                'table_name' => $tableName,
                'record_id' => $recordId ? (string) $recordId : null,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'description' => $description,
            ]);
        } catch (\Exception $e) {
            Log::error('Audit Trail Custom Action Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Log MFA actions
     */
    public static function logMFAAction(string $empno, string $action, bool $success = true, string $description = null): void
    {
        try {
            AuditTrail::log([
                'empno' => $empno,
                'action' => "MFA_{$action}" . ($success ? '_SUCCESS' : '_FAILED'),
                'module' => 'mfa',
                'table_name' => 'user_mfa',
                'record_id' => $empno,
                'old_values' => null,
                'new_values' => null,
                'description' => $description ?? "MFA {$action} " . ($success ? 'successful' : 'failed'),
            ]);
        } catch (\Exception $e) {
            Log::error('Audit Trail MFA Log Error: ' . $e->getMessage());
        }
    }

    /**
     * Get current employee number from authenticated user
     */
    private static function getCurrentEmpno(): string
    {
        try {
            $user = Auth::user();
            return $user ? $user->empno : 'SYSTEM';
        } catch (\Exception $e) {
            return 'UNKNOWN';
        }
    }

    /**
     * Batch log multiple actions
     */
    public static function logBatch(array $logEntries): void
    {
        try {
            $entries = [];
            foreach ($logEntries as $entry) {
                $entries[] = array_merge($entry, [
                    'empno' => $entry['empno'] ?? self::getCurrentEmpno(),
                    'performed_at' => now(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'session_id' => session()->getId(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            AuditTrail::insert($entries);
        } catch (\Exception $e) {
            Log::error('Audit Trail Batch Log Error: ' . $e->getMessage());
        }
    }
}
