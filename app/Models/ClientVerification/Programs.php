<?php

namespace App\Models\ClientVerification;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;

class Programs extends Model
{
    use AuditTrailTrait;
    
    public $timestamps = false;
    protected $table = 'programs';
    protected $primaryKey = 'id';
    protected $connection = 'cvs_mysql';

    protected $fillable = [
        'description',
        'status'
    ];

    /**
     * Override to specify the audit module
     */
    protected static function getAuditModule(): string
    {
        return 'masterlist';
    }

    /**
     * Override to provide custom audit descriptions
     */
    protected static function getAuditDescription($model, string $action): ?string
    {
        switch ($action) {
            case 'CREATE':
                return "Created new program: {$model->description}";
            case 'UPDATE':
                return "Updated program: {$model->description}";
            case 'DELETE':
                return "Deleted program: {$model->description}";
            default:
                return parent::getAuditDescription($model, $action);
        }
    }
}
