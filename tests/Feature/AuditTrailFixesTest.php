<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\MFAController;
use App\Models\User;
use App\Models\UserMFA;
use App\Models\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuditTrailFixesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user with proper password
        $this->user = User::create([
            'empno' => 'TEST001',
            'fname' => 'Test',
            'mname' => 'Middle',
            'lname' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'account_status' => 2,
            'isLog' => 0
        ]);
    }

    public function test_successful_login_is_logged()
    {
        // Mock the request for successful login without MFA
        $response = $this->postJson('/api/login', [
            'username' => $this->user->empno,
            'password' => base64_encode('password123'), // Assume encrypted
            'recaptcha_token' => 'test_token'
        ]);

        // Should log LOGIN_SUCCESS for complete login (if no MFA required)
        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGIN_SUCCESS',
            'module' => 'authentication'
        ]);
    }

    public function test_failed_login_attempts_are_logged()
    {
        // Test wrong password
        $this->postJson('/api/login', [
            'username' => $this->user->empno,
            'password' => base64_encode('wrongpassword'),
            'recaptcha_token' => 'test_token'
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGIN_FAILED',
            'module' => 'authentication',
            'description' => 'Login failed - incorrect password'
        ]);

        // Test non-existent user
        $this->postJson('/api/login', [
            'username' => 'NONEXISTENT',
            'password' => base64_encode('password123'),
            'recaptcha_token' => 'test_token'
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'empno' => 'NONEXISTENT',
            'action' => 'LOGIN_FAILED',
            'module' => 'authentication',
            'description' => 'Login failed - user not found'
        ]);
    }

    public function test_mfa_disable_logs_correct_action()
    {
        // Create MFA for user
        $userMFA = UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'testsecret',
            'enabled_mfa' => 1
        ]);

        // Mock the MFA controller method
        $this->actingAs($this->user)
             ->postJson('/api/mfa/disable', [
                 'empno' => $this->user->empno,
                 'totp_code' => '123456'
             ]);

        // Should log MFA_DISABLE, not DELETE
        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_DISABLE',
            'module' => 'mfa',
            'description' => 'MFA disabled by user'
        ]);

        // Should NOT log automatic UPDATE from model
        $this->assertDatabaseMissing('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'UPDATE',
            'table_name' => 'users_mfa'
        ]);
    }

    public function test_mfa_setup_completion_logs_both_actions()
    {
        // Create partial MFA setup
        UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'testsecret',
            'enabled_mfa' => 0
        ]);

        $this->actingAs($this->user)
             ->postJson('/api/mfa/verify-setup', [
                 'empno' => $this->user->empno,
                 'totp_code' => '123456'
             ]);

        // Should log MFA setup completion
        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_SETUP',
            'module' => 'mfa'
        ]);

        // Should also log successful login after MFA setup
        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGIN_SUCCESS',
            'module' => 'authentication',
            'description' => 'User logged in successfully after MFA setup'
        ]);
    }

    public function test_mfa_verification_logs_login_success()
    {
        // Create enabled MFA for user
        UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'testsecret',
            'enabled_mfa' => 1
        ]);

        $this->actingAs($this->user)
             ->postJson('/api/mfa/verify-login', [
                 'empno' => $this->user->empno,
                 'totp_code' => '123456'
             ]);

        // Should log MFA verification
        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_VERIFY',
            'module' => 'mfa'
        ]);

        // Should also log successful complete login
        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGIN_SUCCESS',
            'module' => 'authentication',
            'description' => 'User logged in successfully after MFA verification'
        ]);
    }

    public function test_failed_mfa_verification_is_logged()
    {
        UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'testsecret',
            'enabled_mfa' => 1
        ]);

        $this->actingAs($this->user)
             ->postJson('/api/mfa/verify-login', [
                 'empno' => $this->user->empno,
                 'totp_code' => '000000' // Invalid code
             ]);

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'MFA_VERIFY_FAILED',
            'module' => 'mfa',
            'description' => 'MFA login verification failed - invalid TOTP code'
        ]);
    }

    public function test_logout_is_logged()
    {
        $this->actingAs($this->user)
             ->postJson('/api/auth/logout');

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGOUT',
            'module' => 'authentication'
        ]);
    }

    public function test_account_status_failures_are_logged()
    {
        // Test inactive account
        $inactiveUser = User::create([
            'empno' => 'INACTIVE001',
            'fname' => 'Inactive',
            'lname' => 'User',
            'account_status' => 0, // Inactive
            'password' => bcrypt('password123')
        ]);

        $this->postJson('/api/login', [
            'username' => 'INACTIVE001',
            'password' => base64_encode('password123'),
            'recaptcha_token' => 'test_token'
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'empno' => 'INACTIVE001',
            'action' => 'LOGIN_FAILED',
            'module' => 'authentication'
        ]);
        
        // Check that the description contains account status information
        $auditEntry = AuditTrail::where('empno', 'INACTIVE001')
                                ->where('action', 'LOGIN_FAILED')
                                ->first();
        $this->assertStringContainsString('account status', $auditEntry->description);
    }

    public function test_password_reset_required_is_logged()
    {
        // Update user to require password reset
        $this->user->update(['isLog' => 1]);

        $this->postJson('/api/login', [
            'username' => $this->user->empno,
            'password' => base64_encode('password123'),
            'recaptcha_token' => 'test_token'
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'empno' => $this->user->empno,
            'action' => 'LOGIN_FAILED',
            'module' => 'authentication',
            'description' => 'Login failed - password reset required'
        ]);
    }
}
