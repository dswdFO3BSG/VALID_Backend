<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMFA;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MfaManagementController extends Controller
{
    /**
     * Get all users with their MFA status
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check if user is superadmin (you may need to adjust this based on your auth system)
            // Assuming you have a role or permission check
            // if (!Auth::user()->isSuperAdmin()) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Unauthorized. Only superadmins can access MFA management.'
            //     ], 403);
            // }

            $query = User::select('empno', 'fname', 'mname', 'sname')
                ->from('userprofile');

            // Add search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('fname', 'LIKE', "%{$search}%")
                      ->orWhere('sname', 'LIKE', "%{$search}%")
                      ->orWhere('empno', 'LIKE', "%{$search}%");
                });
            }

            $users = $query->get();

            // Get all MFA records
            $mfaRecords = UserMFA::select('empno', 'enabled_mfa', 'mfa_remember_expires')
                ->get()
                ->keyBy('empno');

            // Combine user data with MFA status
            $usersWithMfa = $users->map(function ($user) use ($mfaRecords) {
                $mfaRecord = $mfaRecords->get($user->empno);
                
                return [
                    'id' => $user->empno,
                    'empno' => $user->empno,
                    'full_name' => trim("{$user->fname} {$user->mname} {$user->sname}"),
                    'first_name' => $user->fname,
                    'middle_name' => $user->mname,
                    'last_name' => $user->sname,
                    'mfa_enabled' => $mfaRecord ? (bool) $mfaRecord->enabled_mfa : false,
                    'mfa_status' => $mfaRecord ? 
                        ($mfaRecord->enabled_mfa ? 'Enabled' : 'Disabled') : 'Not Setup',
                    'has_recovery_codes' => $mfaRecord ? 
                        ($mfaRecord->mfa_remember_expires ? 'Yes' : 'No') : 'N/A',
                    'mfa_remember_expires' => $mfaRecord ? $mfaRecord->mfa_remember_expires : null,
                ];
            });

            // Filter by MFA status if requested
            if ($request->has('mfa_status') && !empty($request->mfa_status)) {
                $mfaStatusFilter = $request->mfa_status;
                $usersWithMfa = $usersWithMfa->filter(function ($user) use ($mfaStatusFilter) {
                    if ($mfaStatusFilter === 'enabled') {
                        return $user['mfa_enabled'] === true;
                    } elseif ($mfaStatusFilter === 'disabled') {
                        return $user['mfa_enabled'] === false;
                    }
                    return true;
                });
            }

            return response()->json([
                'status' => true,
                'message' => 'Users retrieved successfully',
                'data' => $usersWithMfa->values(), // Reset array keys
                'total' => $usersWithMfa->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching users for MFA management: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific user's MFA details
     */
    public function show(string $empno): JsonResponse
    {
        try {
            // Get user details
            $user = User::where('empno', $empno)->first();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get MFA details
            $mfaRecord = UserMFA::where('empno', $empno)->first();

            $userData = [
                'id' => $user->empno,
                'empno' => $user->empno,
                'full_name' => trim("{$user->fname} {$user->mname} {$user->sname}"),
                'first_name' => $user->fname,
                'middle_name' => $user->mname,
                'last_name' => $user->sname,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'mfa_enabled' => $mfaRecord ? (bool) $mfaRecord->enabled_mfa : false,
                'mfa_status' => $mfaRecord ? 
                    ($mfaRecord->enabled_mfa ? 'Enabled' : 'Disabled') : 'Not Setup',
                'has_mfa_setup' => $mfaRecord !== null,
                'mfa_remember_expires' => $mfaRecord ? $mfaRecord->mfa_remember_expires : null,
                'has_recovery_codes' => $mfaRecord ? 
                    ($mfaRecord->mfa_remember_expires ? 'Yes' : 'No') : 'N/A',
            ];

            return response()->json([
                'status' => true,
                'message' => 'User MFA details retrieved successfully',
                'data' => $userData
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching MFA details for user {$empno}: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user MFA details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset/Remove user's MFA setup
     */
    public function resetUserMfa(string $empno): JsonResponse
    {
        try {
            // Check if user exists
            $user = User::where('empno', $empno)->first();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if user has MFA setup
            $mfaRecord = UserMFA::where('empno', $empno)->first();
            
            if (!$mfaRecord) {
                return response()->json([
                    'status' => false,
                    'message' => 'User does not have MFA setup'
                ], 404);
            }

            // Delete the MFA record
            $mfaRecord->delete();

            // Log MFA reset action
            \App\Services\AuditTrailService::logMFAReset(
                $empno,
                Auth::user()->empno ?? null,
                "MFA reset for user: {$user->fname} {$user->sname} by admin: " . (Auth::user()->empno ?? 'system')
            );

            Log::info("MFA reset for user: {$empno} by admin: " . (Auth::user()->empno ?? 'system'));

            return response()->json([
                'status' => true,
                'message' => "MFA has been successfully reset for user: {$user->fname} {$user->sname}",
                'data' => [
                    'empno' => $empno,
                    'user_name' => trim("{$user->fname} {$user->mname} {$user->sname}"),
                    'reset_at' => now()->toDateTimeString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error resetting MFA for user {$empno}: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to reset user MFA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reset MFA for multiple users
     */
    public function bulkResetMfa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userIds = $request->user_ids;
            $resetResults = [];
            $successCount = 0;
            $errorCount = 0;

            DB::beginTransaction();

            foreach ($userIds as $empno) {
                try {
                    // Check if user exists
                    $user = User::where('empno', $empno)->first();
                    
                    if (!$user) {
                        $resetResults[] = [
                            'empno' => $empno,
                            'status' => 'error',
                            'message' => 'User not found'
                        ];
                        $errorCount++;
                        continue;
                    }

                    // Check if user has MFA setup
                    $mfaRecord = UserMFA::where('empno', $empno)->first();
                    
                    if (!$mfaRecord) {
                        $resetResults[] = [
                            'empno' => $empno,
                            'user_name' => trim("{$user->fname} {$user->mname} {$user->sname}"),
                            'status' => 'skipped',
                            'message' => 'No MFA setup found'
                        ];
                        continue;
                    }

                    // Delete the MFA record
                    $mfaRecord->delete();

                    // Log individual MFA reset action
                    \App\Services\AuditTrailService::logMFAReset(
                        $empno,
                        Auth::user()->empno ?? null,
                        "MFA reset for user: {$user->fname} {$user->sname} via bulk reset by admin: " . (Auth::user()->empno ?? 'system')
                    );

                    $resetResults[] = [
                        'empno' => $empno,
                        'user_name' => trim("{$user->fname} {$user->mname} {$user->sname}"),
                        'status' => 'success',
                        'message' => 'MFA reset successfully'
                    ];
                    $successCount++;

                } catch (\Exception $e) {
                    $resetResults[] = [
                        'empno' => $empno,
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                    $errorCount++;
                }
            }

            DB::commit();

            Log::info("Bulk MFA reset completed. Success: {$successCount}, Errors: {$errorCount} by admin: " . (Auth::user()->empno ?? 'system'));

            return response()->json([
                'status' => true,
                'message' => "Bulk MFA reset completed. {$successCount} users processed successfully, {$errorCount} errors occurred.",
                'data' => [
                    'summary' => [
                        'total_processed' => count($userIds),
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'processed_at' => now()->toDateTimeString()
                    ],
                    'results' => $resetResults
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk MFA reset: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to perform bulk MFA reset',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get MFA statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $totalUsers = User::count();
            $totalMfaRecords = UserMFA::count();
            $enabledMfaCount = UserMFA::where('enabled_mfa', 1)->count();
            $disabledMfaCount = UserMFA::where('enabled_mfa', 0)->count();
            $usersWithoutMfa = $totalUsers - $totalMfaRecords;

            $statistics = [
                'total_users' => $totalUsers,
                'users_with_mfa_setup' => $totalMfaRecords,
                'users_without_mfa_setup' => $usersWithoutMfa,
                'mfa_enabled_count' => $enabledMfaCount,
                'mfa_disabled_count' => $disabledMfaCount,
                'mfa_adoption_rate' => $totalUsers > 0 ? round(($totalMfaRecords / $totalUsers) * 100, 2) : 0,
                'mfa_enabled_rate' => $totalMfaRecords > 0 ? round(($enabledMfaCount / $totalMfaRecords) * 100, 2) : 0,
            ];

            return response()->json([
                'status' => true,
                'message' => 'MFA statistics retrieved successfully',
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching MFA statistics: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve MFA statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
