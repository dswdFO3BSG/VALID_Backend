<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, AuditTrailTrait;

    protected $table = 'userprofile';
    protected $connection = 'erm_mysql';
    protected $primaryKey = 'empno';
    public $incrementing = false;
    protected $keyType = 'string';

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
                return "Created new user account: {$model->empno}";
            case 'UPDATE':
                return "Updated user profile: {$model->empno}";
            case 'DELETE':
                return "Deleted user account: {$model->empno}";
            default:
                return parent::getAuditDescription($model, $action);
        }
    }
}
