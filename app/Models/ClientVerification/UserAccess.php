<?php

namespace App\Models\ClientVerification;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;

class UserAccess extends Model
{
    use AuditTrailTrait;
    
    public $timestamps = false;
    protected $table = 'user_access';
    protected $primaryKey = 'access_id';
    protected $connection = 'cvs_mysql';

    protected $fillable = [
        'empno',
        'module_id',
        'added_by',
        'added_at'
    ];

    /**
     * Override to specify the audit module
     */
    protected static function getAuditModule(): string
    {
        return 'user_access';
    }

    /**
     * Override to provide custom audit descriptions
     */
    protected static function getAuditDescription($model, string $action): ?string
    {
        switch ($action) {
            case 'CREATE':
                return "Granted access to module {$model->module_id} for user: {$model->empno}";
            case 'UPDATE':
                return "Updated access permissions for user: {$model->empno}";
            case 'DELETE':
                return "Revoked access to module {$model->module_id} for user: {$model->empno}";
            default:
                return parent::getAuditDescription($model, $action);
        }
    }
}
