<?php

namespace App\Models\ClientVerification;

use Illuminate\Database\Eloquent\Model;

class UserModules extends Model
{
    public $timestamps = false;
    protected $table = 'user_modules';
    protected $primaryKey = 'module_id';
    protected $connection = 'cvs_mysql';
}
