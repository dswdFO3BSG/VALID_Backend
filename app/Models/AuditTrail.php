<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class AuditTrail extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     */
    protected $connection = 'cvs_mysql';

    /**
     * The table associated with the model.
     */
    protected $table = 'audit_trails';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'empno',
        'action',
        'module',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'user_agent',
        'session_id',
        'performed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'performed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Log an audit trail entry
     */
    public static function log(array $data): self
    {
        $data['performed_at'] = $data['performed_at'] ?? now();
        $data['ip_address'] = $data['ip_address'] ?? request()->ip();
        $data['user_agent'] = $data['user_agent'] ?? request()->userAgent();
        $data['session_id'] = $data['session_id'] ?? session()->getId();

        return static::create($data);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('performed_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('performed_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Scope for filtering by employee
     */
    public function scopeByEmployee($query, $empno)
    {
        return $query->where('empno', $empno);
    }

    /**
     * Scope for filtering by module
     */
    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope for filtering by action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Get the user associated with this audit trail
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'empno', 'empno');
    }
}
