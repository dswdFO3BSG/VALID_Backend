<?php

namespace App\Models\ClientVerification;

use Illuminate\Database\Eloquent\Model;

class UserAccess extends Model
{
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
}
