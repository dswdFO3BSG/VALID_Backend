<?php

namespace App\Traits;

use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait AuditTrailTrait
{
    /**
     * Boot the auditable trait for the model.
     */
    protected static function bootAuditTrailTrait(): void
    {
        static::created(function (Model $model) {
            static::logModelAction($model, 'CREATE');
        });

        static::updated(function (Model $model) {
            static::logModelAction($model, 'UPDATE');
        });

        static::deleted(function (Model $model) {
            static::logModelAction($model, 'DELETE');
        });
    }

    /**
     * Log model action to audit trail
     */
    protected static function logModelAction(Model $model, string $action): void
    {
        // Skip logging if audit trail is disabled
        if (static::auditTrailDisabled()) {
            return;
        }

        try {
            $tableName = $model->getTable();
            $recordId = $model->getKey();
            $module = static::getAuditModule();
            
            switch ($action) {
                case 'CREATE':
                    AuditTrailService::logCreate(
                        $module,
                        $tableName,
                        $recordId,
                        $model->getAttributes(),
                        static::getAuditEmpno($model),
                        static::getAuditDescription($model, $action)
                    );
                    break;
                    
                case 'UPDATE':
                    $oldValues = $model->getOriginal();
                    $newValues = $model->getAttributes();
                    
                    AuditTrailService::logUpdate(
                        $module,
                        $tableName,
                        $recordId,
                        $oldValues,
                        $newValues,
                        static::getAuditEmpno($model),
                        static::getAuditDescription($model, $action)
                    );
                    break;
                    
                case 'DELETE':
                    AuditTrailService::logDelete(
                        $module,
                        $tableName,
                        $recordId,
                        $model->getOriginal(),
                        static::getAuditEmpno($model),
                        static::getAuditDescription($model, $action)
                    );
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Audit Trail Trait Error: ' . $e->getMessage());
        }
    }

    /**
     * Get the module name for audit trail
     * Override this method in your model to specify the module
     */
    protected static function getAuditModule(): string
    {
        return 'general';
    }

    /**
     * Get the employee number for audit trail
     * Override this method in your model if needed
     */
    protected static function getAuditEmpno(Model $model): ?string
    {
        // Try to get empno from the model first
        if (isset($model->empno)) {
            return $model->empno;
        }
        
        // Try to get from authenticated user
        try {
            $user = Auth::user();
            return $user ? $user->empno : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get custom description for audit trail
     * Override this method in your model to provide custom descriptions
     */
    protected static function getAuditDescription(Model $model, string $action): ?string
    {
        $tableName = $model->getTable();
        $recordId = $model->getKey();
        
        switch ($action) {
            case 'CREATE':
                return "Created new {$tableName} record with ID: {$recordId}";
            case 'UPDATE':
                return "Updated {$tableName} record with ID: {$recordId}";
            case 'DELETE':
                return "Deleted {$tableName} record with ID: {$recordId}";
            default:
                return null;
        }
    }

    /**
     * Manually log a custom audit trail entry for this model
     */
    public function logAuditTrail(string $action, string $description, array $oldValues = null, array $newValues = null): void
    {
        AuditTrailService::logCustomAction(
            static::getAuditEmpno($this),
            $action,
            static::getAuditModule(),
            $description,
            $this->getTable(),
            $this->getKey(),
            $oldValues,
            $newValues
        );
    }

    /**
     * Temporarily disable audit trail for this model
     */
    public static function withoutAuditTrail(\Closure $callback)
    {
        static::$auditTrailDisabled = true;
        
        try {
            return $callback();
        } finally {
            static::$auditTrailDisabled = false;
        }
    }

    /**
     * Check if audit trail is disabled
     */
    protected static function auditTrailDisabled(): bool
    {
        return static::$auditTrailDisabled ?? false;
    }

    /**
     * Property to track if audit trail is disabled
     */
    protected static bool $auditTrailDisabled = false;
}
