<?php

namespace App\Models;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;

class VerifiedClients extends Model
{
    use AuditTrailTrait;
    
    protected $table = 'verified_clients';
    protected $connection = 'cvs_mysql';
    protected $primaryKey = 'id';

    public $timestamps = false;
    protected $fillable = [
        'beneficiary_id',
        'psn',
        'face_url',
        'first_name',
        'middle_name',
        'last_name',
        'suffix_name',
        'sex',
        'marital_status',
        'email',
        'mobile_number',
        'birth_date',
        'full_address',
        'address_line_1',
        'address_line_2',
        'barangay',
        'municipality',
        'province',
        'country',
        'postal_code',
        'present_full_address',
        'present_address_line_1',
        'present_address_line_2',
        'present_barangay',
        'present_municipality',
        'present_province',
        'present_country',
        'present_postal_code',
        'residency_status',
        'place_of_birth',
        'pob_municipality',
        'pob_province',
        'pob_country',
        'verified_at',
        'verified_by',
        'verification_result',
        'bene_id_created',
        'bene_id_creation_date',
        'status',
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
        $fullName = trim("{$model->first_name} {$model->middle_name} {$model->last_name}");
        
        switch ($action) {
            case 'CREATE':
                return "Added new beneficiary: {$fullName} (ID: {$model->beneficiary_id})";
            case 'UPDATE':
                return "Updated beneficiary information: {$fullName} (ID: {$model->beneficiary_id})";
            case 'DELETE':
                return "Removed beneficiary: {$fullName} (ID: {$model->beneficiary_id})";
            default:
                return parent::getAuditDescription($model, $action);
        }
    }
}


