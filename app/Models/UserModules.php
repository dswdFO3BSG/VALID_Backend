<?php

namespace App\Models;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;

class UserModules extends Model
{
    use AuditTrailTrait;

    protected $table = 'user_modules';
    protected $connection = 'cvs_mysql';
    protected $primaryKey = 'module_id';

    public $timestamps = false;
    protected $fillable = [
        'label',
        'icon',
        'to',
        'main_menu',
        'order',
        'parent',
        'parent_to',
        'parent_label',
        'parent_icon',
        'status',
    ];

    // Audit trail configuration
    protected static $auditModule = 'user_modules';
    protected static $auditTableName = 'user_modules';
}



