<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMFA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MfaManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

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

    public function test_can_get_all_users_with_mfa_status()
    {
        // Create a user with MFA
        $mfaUser = UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'test_secret',
            'enabled_mfa' => true
        ]);

        $response = $this->actingAs($this->user)
                         ->getJson('/api/mfa-management');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true,
                     'message' => 'Users retrieved successfully'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'empno',
                             'full_name',
                             'email',
                             'mfa_enabled',
                             'mfa_status'
                         ]
                     ]
                 ]);
    }

    public function test_can_get_specific_user_mfa_details()
    {
        $response = $this->actingAs($this->user)
                         ->getJson("/api/mfa-management/users/{$this->user->empno}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true,
                     'message' => 'User MFA details retrieved successfully'
                 ]);
    }

    public function test_can_reset_user_mfa()
    {
        // Create MFA record for user
        UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'test_secret',
            'enabled_mfa' => true
        ]);

        $response = $this->actingAs($this->user)
                         ->postJson("/api/mfa-management/reset/{$this->user->empno}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true
                 ]);

        // Verify MFA record was deleted
        $this->assertDatabaseMissing('users_mfa', [
            'empno' => $this->user->empno
        ]);
    }

    public function test_cannot_reset_mfa_for_nonexistent_user()
    {
        $response = $this->actingAs($this->user)
                         ->postJson("/api/mfa-management/reset/NONEXISTENT");

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => false,
                     'message' => 'User not found'
                 ]);
    }

    public function test_can_bulk_reset_mfa()
    {
        // Create another user with MFA
        $user2 = User::factory()->create(['empno' => 'TEST002']);
        
        UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'test_secret_1',
            'enabled_mfa' => true
        ]);

        UserMFA::create([
            'empno' => $user2->empno,
            'mfa_secret' => 'test_secret_2',
            'enabled_mfa' => true
        ]);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/mfa-management/bulk-reset', [
                             'user_ids' => [$this->user->empno, $user2->empno]
                         ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true
                 ]);

        // Verify both MFA records were deleted
        $this->assertDatabaseMissing('users_mfa', ['empno' => $this->user->empno]);
        $this->assertDatabaseMissing('users_mfa', ['empno' => $user2->empno]);
    }

    public function test_can_get_mfa_statistics()
    {
        $response = $this->actingAs($this->user)
                         ->getJson('/api/mfa-management/statistics');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true,
                     'message' => 'MFA statistics retrieved successfully'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         'total_users',
                         'users_with_mfa_setup',
                         'users_without_mfa_setup',
                         'mfa_enabled_count',
                         'mfa_disabled_count',
                         'mfa_adoption_rate',
                         'mfa_enabled_rate'
                     ]
                 ]);
    }

    public function test_bulk_reset_requires_user_ids_array()
    {
        $response = $this->actingAs($this->user)
                         ->postJson('/api/mfa-management/bulk-reset', []);

        $response->assertStatus(422)
                 ->assertJson([
                     'status' => false,
                     'message' => 'Invalid request data'
                 ]);
    }

    public function test_search_users_by_name()
    {
        $response = $this->actingAs($this->user)
                         ->getJson('/api/mfa-management?search=Test');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true
                 ]);
    }

    public function test_filter_users_by_mfa_status()
    {
        // Create user with enabled MFA
        UserMFA::create([
            'empno' => $this->user->empno,
            'mfa_secret' => 'test_secret',
            'enabled_mfa' => true
        ]);

        $response = $this->actingAs($this->user)
                         ->getJson('/api/mfa-management?mfa_status=enabled');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true
                 ]);
    }
}
