<?php

namespace App\Models;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;

class UserMFA extends Model
{
    use AuditTrailTrait;
    
    protected $table = 'users_mfa';
    protected $connection = 'cvs_mysql';
    public $timestamps = false;
    
    protected $fillable = [
        'empno','mfa_secret','enabled_mfa',
        'mfa_remember_hash','mfa_remember_expires',
    ];

    protected $hidden = ['mfa_secret','mfa_remember_hash'];

    protected $casts = [
        'mfa_remember_expires' => 'datetime',
    ];

    // Override the updateOrCreate method to ensure no timestamps are added
    public static function updateOrCreate(array $attributes, array $values = [])
    {
        // First try to find existing record
        $instance = static::where($attributes)->first();
        
        if ($instance) {
            // Update existing record
            $instance->fill($values)->save();
            return $instance;
        }
        
        // Create new record
        return static::create(array_merge($attributes, $values));
    }

    /**
     * Override to specify the audit module
     */
    protected static function getAuditModule(): string
    {
        return 'mfa';
    }

    /**
     * Override to provide custom audit descriptions
     */
    protected static function getAuditDescription($model, string $action): ?string
    {
        switch ($action) {
            case 'CREATE':
                return "MFA setup initiated for user: {$model->empno}";
            case 'UPDATE':
                return "MFA settings updated for user: {$model->empno}";
            case 'DELETE':
                return "MFA disabled for user: {$model->empno}";
            default:
                return parent::getAuditDescription($model, $action);
        }
    }
}
