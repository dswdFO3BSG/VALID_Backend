<?php

namespace App\Models\ClientVerification;

use Illuminate\Database\Eloquent\Model;

class QueueManager extends Model
{
    public $timestamps = false;
    protected $table = 'queues';
    protected $primaryKey = 'id';
    protected $connection = 'cvs_mysql';

    protected $fillable = [
        'description',
        'sector_id',
        'program_id',
        'last_queue_number',
        'last_queue_number_timestamp',  
        'status'
    ];
}
