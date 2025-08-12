<?php

namespace App\Models\ClientVerification;

use Illuminate\Database\Eloquent\Model;

class Sectors extends Model
{
    public $timestamps = false;
    protected $table = 'sectors';
    protected $primaryKey = 'id';
    protected $connection = 'cvs_mysql';

    protected $fillable = [
        'description',
        'status'
    ];
}
