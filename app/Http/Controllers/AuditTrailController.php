<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuditTrailController extends Controller
{
    /**
     * Get audit trail records with filtering and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = AuditTrail::query()->with('user')
                ->orderBy('performed_at', 'desc');

            // Filter by employee
            if ($request->has('empno') && $request->empno) {
                $query->byEmployee($request->empno);
            }

            // Filter by module
            if ($request->has('module') && $request->module) {
                $query->byModule($request->module);
            }

            // Filter by action
            if ($request->has('action') && $request->action) {
                $query->byAction($request->action);
            }

            // Filter by date range
            if ($request->has('start_date') || $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            // Filter by table name
            if ($request->has('table_name') && $request->table_name) {
                $query->where('table_name', $request->table_name);
            }

            // Search in description
            if ($request->has('search') && $request->search) {
                $query->where('description', 'LIKE', '%' . $request->search . '%');
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $auditTrails = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Audit trails retrieved successfully',
                'data' => $auditTrails
            ], 200);

        } catch (\Exception $e) {
            Log::error('Audit Trail Index Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve audit trails',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific audit trail record
     */
    public function show($id)
    {
        try {
            $auditTrail = AuditTrail::with('user')->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Audit trail retrieved successfully',
                'data' => $auditTrail
            ], 200);

        } catch (\Exception $e) {
            Log::error('Audit Trail Show Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Audit trail not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get audit trail statistics
     */
    public function statistics(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->get('end_date', now()->format('Y-m-d'));

            $query = AuditTrail::query()->dateRange($startDate, $endDate);

            $statistics = [
                'total_actions' => $query->count(),
                'actions_by_type' => $query->selectRaw('action, COUNT(*) as count')
                    ->groupBy('action')
                    ->pluck('count', 'action'),
                'actions_by_module' => $query->selectRaw('module, COUNT(*) as count')
                    ->groupBy('module')
                    ->pluck('count', 'module'),
                'most_active_users' => $query->selectRaw('empno, COUNT(*) as count')
                    ->groupBy('empno')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->with('user')
                    ->get(),
                'daily_activity' => $query->selectRaw('DATE(performed_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => $statistics
            ], 200);

        } catch (\Exception $e) {
            Log::error('Audit Trail Statistics Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export audit trail data
     */
    public function export(Request $request)
    {
        try {
            $query = AuditTrail::query()->with('user')
                ->orderBy('performed_at', 'desc');

            // Apply same filters as index
            if ($request->has('empno') && $request->empno) {
                $query->byEmployee($request->empno);
            }

            if ($request->has('module') && $request->module) {
                $query->byModule($request->module);
            }

            if ($request->has('action') && $request->action) {
                $query->byAction($request->action);
            }

            if ($request->has('start_date') || $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            $auditTrails = $query->limit(5000)->get(); // Limit export to 5000 records

            return response()->json([
                'status' => true,
                'message' => 'Audit trails exported successfully',
                'data' => $auditTrails
            ], 200);

        } catch (\Exception $e) {
            Log::error('Audit Trail Export Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to export audit trails',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
