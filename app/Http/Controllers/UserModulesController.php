<?php

namespace App\Http\Controllers;

use App\Models\UserModules;
use App\Services\AuditTrailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class UserModulesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $modules = UserModules::where('status', 1)->orderBy('order')->get();
            return response()->json([
                'status' => true,
                'data' => $modules
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user modules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'label' => 'required|string|max:255',
                'icon' => 'nullable|string|max:255',
                'to' => 'required|string|max:255',
                'main_menu' => 'nullable|string|max:255',
                'order' => 'required|integer',
                'parent' => 'nullable|integer',
                'parent_to' => 'nullable|string|max:255',
                'parent_label' => 'nullable|string|max:255',
                'parent_icon' => 'nullable|string|max:255',
                'status' => 'nullable|integer|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Set default status to 1 if not provided
            $data = $request->all();
            if (!isset($data['status'])) {
                $data['status'] = 1;
            }

            $module = UserModules::create($data);
            
            // Log audit trail
            AuditTrailService::logCreate('user_modules', 'user_modules', $module->id, $module->toArray(), null, 'User module created');

            return response()->json([
                'status' => true,
                'message' => 'User module created successfully',
                'data' => $module
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create user module',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $module = UserModules::where('module_id', $id)->where('status', 1)->firstOrFail();
            return response()->json([
                'status' => true,
                'data' => $module
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'User module not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $module = UserModules::where('module_id', $id)->where('status', 1)->firstOrFail();
            $oldData = $module->toArray();

            $validator = Validator::make($request->all(), [
                'label' => 'required|string|max:255',
                'icon' => 'nullable|string|max:255',
                'to' => 'required|string|max:255',
                'main_menu' => 'nullable|integer|max:255',
                'order' => 'required|integer',
                'parent' => 'nullable|integer',
                'parent_to' => 'nullable|string|max:255',
                'parent_label' => 'nullable|string|max:255',
                'parent_icon' => 'nullable|string|max:255',
                'status' => 'nullable|integer|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $module->update($request->all());
            
            // Log audit trail
            AuditTrailService::logUpdate('user_modules', 'user_modules', $module->module_id, $oldData, $module->toArray(), null, 'User module updated');

            return response()->json([
                'status' => true,
                'message' => 'User module updated successfully',
                'data' => $module
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update user module',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            // Find the module by module_id
            $module = UserModules::where('module_id', $id)->first();
            
            if (!$module) {
                return response()->json([
                    'status' => false,
                    'message' => 'User module not found'
                ], 404);
            }
            
            // Check if status column exists, if not fall back to hard delete
            if (!Schema::hasColumn('user_modules', 'status')) {
                // Hard delete if status column doesn't exist
                $oldData = $module->toArray();
                $module->delete();
                
                AuditTrailService::logDelete('user_modules', 'user_modules', $id, $oldData, null, 'User module deleted (hard delete - no status column)');
                
                return response()->json([
                    'status' => true,
                    'message' => 'User module deleted successfully'
                ], 200);
            }
            
            // Check if module is already deleted (status = 0)
            if (isset($module->status) && $module->status == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'User module is already deleted'
                ], 400);
            }
            
            $oldData = $module->toArray();
            
            // Soft delete by setting status to 0
            $module->update(['status' => 0]);
            
            // Log audit trail
            AuditTrailService::logDelete('user_modules', 'user_modules', $id, $oldData, null, 'User module soft deleted (status set to 0)');

            return response()->json([
                'status' => true,
                'message' => 'User module deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete user module',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
