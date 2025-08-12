<?php

namespace App\Models\ClientVerification;

use Illuminate\Database\Eloquent\Model;

class Programs extends Model
{
    public $timestamps = false;
    protected $table = 'programs';
    protected $primaryKey = 'id';
    protected $connection = 'cvs_mysql';

    protected $fillable = [
        'description',
        'status'
    ];
}
