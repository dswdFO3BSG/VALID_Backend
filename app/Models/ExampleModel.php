<?php

namespace App\Models;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;

class ExampleModel extends Model
{
    use AuditTrailTrait;

    protected $fillable = [
        'name',
        'description',
        'status',
        'empno'
    ];

    /**
     * Override to specify the audit module
     */
    protected static function getAuditModule(): string
    {
        return 'masterlist'; // or 'user_access', 'queue_manager', etc.
    }

    /**
     * Override to provide custom audit descriptions
     */
    protected static function getAuditDescription($model, string $action): ?string
    {
        switch ($action) {
            case 'CREATE':
                return "Created new {$model->name} in masterlist module";
            case 'UPDATE':
                return "Updated {$model->name} in masterlist module";
            case 'DELETE':
                return "Deleted {$model->name} from masterlist module";
            default:
                return parent::getAuditDescription($model, $action);
        }
    }
}
