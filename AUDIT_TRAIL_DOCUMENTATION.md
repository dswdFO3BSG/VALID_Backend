# Audit Trail System Documentation

## Overview

The Audit Trail system tracks all actions performed in the VALID application, including masterlist operations, user access changes, queue manager activities, and authentication events.

## Components

### 1. Database Table: `audit_trails`

-   **id**: Primary key
-   **empno**: Employee number who performed the action
-   **action**: Type of action (CREATE, UPDATE, DELETE, LOGIN, etc.)
-   **module**: Module where action occurred (masterlist, user_access, queue_manager, etc.)
-   **table_name**: Database table affected (nullable)
-   **record_id**: ID of affected record (nullable)
-   **old_values**: Previous values (JSON, for updates)
-   **new_values**: New values (JSON, for creates/updates)
-   **description**: Human-readable description
-   **ip_address**: User's IP address
-   **user_agent**: User's browser/device info
-   **session_id**: Session identifier
-   **performed_at**: Timestamp when action was performed

### 2. Model: `AuditTrail`

Located at `app/Models/AuditTrail.php`

### 3. Controller: `AuditTrailController`

Provides API endpoints for viewing audit trail data.

### 4. Service: `AuditTrailService`

Helper service for logging audit trail entries.

### 5. Trait: `AuditTrailTrait`

Can be added to any model for automatic CRUD logging.

### 6. Middleware: `AuditTrailMiddleware`

Logs API requests automatically.

## API Endpoints

### GET `/api/audit-trail/`

Get paginated audit trail records with optional filters:

-   `empno`: Filter by employee number
-   `module`: Filter by module
-   `action`: Filter by action type
-   `start_date`: Filter by start date
-   `end_date`: Filter by end date
-   `table_name`: Filter by table name
-   `search`: Search in description
-   `per_page`: Records per page (default: 15)

### GET `/api/audit-trail/statistics`

Get audit trail statistics:

-   Total actions count
-   Actions by type
-   Actions by module
-   Most active users
-   Daily activity

### GET `/api/audit-trail/{id}`

Get specific audit trail record by ID.

### GET `/api/audit-trail/export`

Export audit trail data (limited to 5000 records) with same filters as index.

## Usage Examples

### 1. Using AuditTrailService directly:

```php
use App\Services\AuditTrailService;

// Log a create action
AuditTrailService::logCreate(
    'masterlist',
    'beneficiaries',
    123,
    ['name' => 'John Doe', 'age' => 30],
    '03-12833',
    'Created new beneficiary record'
);

// Log an update action
AuditTrailService::logUpdate(
    'masterlist',
    'beneficiaries',
    123,
    ['name' => 'John Doe', 'age' => 30], // old values
    ['name' => 'John Doe', 'age' => 31], // new values
    '03-12833',
    'Updated beneficiary age'
);

// Log a delete action
AuditTrailService::logDelete(
    'masterlist',
    'beneficiaries',
    123,
    ['name' => 'John Doe', 'age' => 31],
    '03-12833',
    'Deleted beneficiary record'
);

// Log login/logout
AuditTrailService::logLogin('03-12833', true, 'Successful login');
AuditTrailService::logLogout('03-12833', 'User logged out');

// Log MFA actions
AuditTrailService::logMFAAction('03-12833', 'SETUP', true, 'MFA setup completed');

// Log custom actions
AuditTrailService::logCustomAction(
    '03-12833',
    'EXPORT',
    'reports',
    'Exported beneficiary report',
    'beneficiaries',
    null,
    null,
    ['format' => 'excel', 'records' => 500]
);
```

### 2. Using AuditTrailTrait in models:

```php
use App\Traits\AuditTrailTrait;

class Beneficiary extends Model
{
    use AuditTrailTrait;

    protected $fillable = ['name', 'age', 'municipality'];

    // Override to specify the audit module
    protected static function getAuditModule(): string
    {
        return 'masterlist';
    }

    // Override to provide custom audit descriptions
    protected static function getAuditDescription($model, string $action): ?string
    {
        switch ($action) {
            case 'CREATE':
                return "Created new beneficiary: {$model->name}";
            case 'UPDATE':
                return "Updated beneficiary: {$model->name}";
            case 'DELETE':
                return "Deleted beneficiary: {$model->name}";
            default:
                return parent::getAuditDescription($model, $action);
        }
    }
}
```

Now all CRUD operations on the Beneficiary model will be automatically logged!

### 3. Manual logging in models:

```php
$beneficiary = new Beneficiary();
$beneficiary->name = 'John Doe';
$beneficiary->save();

// Log custom action
$beneficiary->logAuditTrail(
    'VERIFICATION',
    'Beneficiary data verified by field officer',
    null,
    ['verified' => true, 'verified_by' => '03-12833']
);
```

### 4. Temporarily disable audit trail:

```php
// Disable audit trail for bulk operations
Beneficiary::withoutAuditTrail(function() {
    // Bulk insert 1000 records without audit trail
    Beneficiary::insert($bulkData);
});
```

## Controller Integration

### In your existing controllers:

```php
use App\Services\AuditTrailService;

class BeneficiaryController extends Controller
{
    public function store(Request $request)
    {
        $beneficiary = Beneficiary::create($request->all());

        // Manual logging if not using trait
        AuditTrailService::logCreate(
            'masterlist',
            'beneficiaries',
            $beneficiary->id,
            $beneficiary->toArray(),
            auth()->user()->empno,
            'New beneficiary registered via API'
        );

        return response()->json($beneficiary);
    }
}
```

## Middleware Usage

To enable automatic API request logging, register the middleware in your route groups:

```php
Route::middleware(['auth:sanctum', 'audit.trail'])->group(function () {
    // Your protected routes here
});
```

## Authentication Integration

The system automatically logs:

-   Successful logins
-   Failed login attempts
-   User logouts
-   MFA setup/verification events

## Modules Tracked

1. **masterlist** - Beneficiary data, programs, locations
2. **user_access** - User permissions, roles, access control
3. **queue_manager** - Queue operations, sector assignments
4. **authentication** - Login/logout, MFA events
5. **audit_trail** - Audit trail viewing (if needed)
6. **api** - General API requests

## Best Practices

1. **Use descriptive actions**: Use clear action names like 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT'
2. **Include context**: Provide meaningful descriptions that explain what happened
3. **Don't log sensitive data**: Exclude passwords, tokens, and other sensitive information
4. **Use modules consistently**: Stick to predefined module names for better reporting
5. **Handle exceptions**: Always wrap audit logging in try-catch to prevent breaking application flow

## Performance Considerations

1. **Bulk operations**: Use `withoutAuditTrail()` for bulk inserts/updates
2. **Background processing**: Consider queuing audit logs for high-volume operations
3. **Data retention**: Implement periodic cleanup of old audit trail records
4. **Indexing**: Database indexes are already created for common query patterns

## Security

1. **Read-only access**: Audit trail records should only be created, never updated or deleted
2. **Access control**: Limit audit trail viewing to authorized personnel only
3. **IP tracking**: All actions include IP address for security analysis
4. **Session tracking**: Session IDs help correlate actions within user sessions

## Migration

The audit trails table is created on the `cvs_mysql` connection. To migrate:

```bash
php artisan migrate --path=database/migrations/2025_09_03_135743_create_audit_trails_table.php --database=cvs_mysql
```
