<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMFA;
use App\Models\AuditTrail;
use App\Services\AuditTrailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailActionsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'empno' => 'TEST001',
            'fname' => 'Test',
            'lname' => 'User',
            'email' => 'test@example.com'
        ]);
    }

    public function test_login_success_audit_trail()
    {
        AuditTrailService::logLogin($this->user->empno, true, 'Test login success');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGIN_SUCCESS',
            'module' => 'authentication',
            'description' => 'Test login success'
        ]);
    }

    public function test_login_failed_audit_trail()
    {
        AuditTrailService::logLogin($this->user->empno, false, 'Test login failed');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGIN_FAILED',
            'module' => 'authentication',
            'description' => 'Test login failed'
        ]);
    }

    public function test_logout_audit_trail()
    {
        AuditTrailService::logLogout($this->user->empno, 'Test logout');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGOUT',
            'module' => 'authentication',
            'description' => 'Test logout'
        ]);
    }

    public function test_mfa_setup_audit_trail()
    {
        AuditTrailService::logMFASetup($this->user->empno, true, 'MFA setup completed');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_SETUP',
            'module' => 'mfa',
            'description' => 'MFA setup completed'
        ]);
    }

    public function test_mfa_verify_success_audit_trail()
    {
        AuditTrailService::logMFAVerify($this->user->empno, true, 'MFA verification successful');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_VERIFY',
            'module' => 'mfa',
            'description' => 'MFA verification successful'
        ]);
    }

    public function test_mfa_verify_failed_audit_trail()
    {
        AuditTrailService::logMFAVerify($this->user->empno, false, 'MFA verification failed');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_VERIFY_FAILED',
            'module' => 'mfa',
            'description' => 'MFA verification failed'
        ]);
    }

    public function test_mfa_disable_audit_trail()
    {
        AuditTrailService::logMFADisable($this->user->empno, 'MFA disabled by user');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_DISABLE',
            'module' => 'mfa',
            'description' => 'MFA disabled by user'
        ]);
    }

    public function test_mfa_reset_audit_trail()
    {
        $adminEmpno = 'ADMIN001';
        AuditTrailService::logMFAReset($this->user->empno, $adminEmpno, 'MFA reset by admin');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $adminEmpno,
            'action' => 'MFA_RESET',
            'module' => 'mfa',
            'record_id' => $this->user->empno,
            'description' => 'MFA reset by admin'
        ]);
    }

    public function test_audit_trail_filtering_by_action()
    {
        // Create multiple audit trail entries with different actions
        AuditTrailService::logLogin($this->user->empno, true);
        AuditTrailService::logMFASetup($this->user->empno, true);
        AuditTrailService::logLogout($this->user->empno);

        // Test filtering by LOGIN_SUCCESS
        $loginEntries = AuditTrail::byAction('LOGIN_SUCCESS')->get();
        $this->assertCount(1, $loginEntries);
        $this->assertEquals('LOGIN_SUCCESS', $loginEntries->first()->action);

        // Test filtering by MFA_SETUP
        $mfaEntries = AuditTrail::byAction('MFA_SETUP')->get();
        $this->assertCount(1, $mfaEntries);
        $this->assertEquals('MFA_SETUP', $mfaEntries->first()->action);

        // Test filtering by LOGOUT
        $logoutEntries = AuditTrail::byAction('LOGOUT')->get();
        $this->assertCount(1, $logoutEntries);
        $this->assertEquals('LOGOUT', $logoutEntries->first()->action);
    }

    public function test_audit_trail_filtering_by_module()
    {
        // Create entries with different modules
        AuditTrailService::logLogin($this->user->empno, true);
        AuditTrailService::logMFASetup($this->user->empno, true);

        // Test filtering by authentication module
        $authEntries = AuditTrail::byModule('authentication')->get();
        $this->assertCount(1, $authEntries);
        $this->assertEquals('authentication', $authEntries->first()->module);

        // Test filtering by mfa module
        $mfaEntries = AuditTrail::byModule('mfa')->get();
        $this->assertCount(1, $mfaEntries);
        $this->assertEquals('mfa', $mfaEntries->first()->module);
    }

    public function test_audit_trail_filtering_by_employee()
    {
        $user2 = User::factory()->create(['empno' => 'TEST002']);

        AuditTrailService::logLogin($this->user->empno, true);
        AuditTrailService::logLogin($user2->empno, true);

        // Test filtering by specific employee
        $user1Entries = AuditTrail::byEmployee($this->user->empno)->get();
        $this->assertCount(1, $user1Entries);
        $this->assertEquals($this->user->empno, $user1Entries->first()->empno);

        $user2Entries = AuditTrail::byEmployee($user2->empno)->get();
        $this->assertCount(1, $user2Entries);
        $this->assertEquals($user2->empno, $user2Entries->first()->empno);
    }

    public function test_all_required_actions_are_defined()
    {
        $requiredActions = [
            'CREATE', 'UPDATE', 'DELETE',
            'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT',
            'MFA_SETUP', 'MFA_VERIFY', 'MFA_VERIFY_FAILED',
            'MFA_DISABLE', 'MFA_DISABLE_FAILED', 'MFA_RESET'
        ];

        // Create audit entries for each action
        foreach ($requiredActions as $action) {
            AuditTrailService::logCustomAction(
                $this->user->empno,
                $action,
                'test_module',
                "Test {$action} action"
            );
        }

        // Verify all actions were logged correctly
        foreach ($requiredActions as $action) {
            $this->assertDatabaseHas('audit_trails', [
                'empno' => $this->user->empno,
                'action' => $action,
                'description' => "Test {$action} action"
            ]);
        }
    }
}
