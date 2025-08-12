<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ClientVerification\QueueManager;
use App\Models\ClientVerification\Sectors;
use App\Models\ClientVerification\Programs;
use Illuminate\Http\Request;

class QueueManagerController extends Controller
{

    public function getSectors(Request $request) {
        try {
            $sectors = Sectors::where('status', 1)->get();

            return response()->json([
                'status' => 'success',
                'data' => $sectors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function getPrograms(Request $request) {
        try {
            $programs = Programs::where('status', 1)->get();

            return response()->json([
                'status' => 'success',
                'data' => $programs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    
    public function getQueues(Request $request) {
       
        try {
        $queueList = QueueManager::where('queues.status', 1)
            ->join('sectors', 'queues.sector_id', '=', 'sectors.id')
            ->join('programs', 'queues.program_id', '=', 'programs.id')
            ->select(
                'queues.id',
                'queues.description',
                'queues.sector_id',
                'sectors.description as sector_name',
                'queues.program_id',
                'programs.description as program_name',
                'queues.last_queue_number',
                'queues.last_queue_number_timestamp'
            )
            ->orderBy('queues.id', 'desc')
            ->get();

                    return response()->json([
                        'status' => 'success',
                        'data' => $queueList,

                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'An error occurred',
                        'error' => $e->getMessage(),
                        'status' => 500
                    ], 500);
                }
    }

public function createQueue(Request $request)
{
    $validated = $request->validate([
        'description' => 'required|string|max:255',
        'sector_id' => 'required|integer',
        'program_id' => 'required|integer',
    ]);

    try {
        $queue = QueueManager::create([
            'description' => $validated['description'],
            'sector_id' => $validated['sector_id'],
            'program_id' => $validated['program_id'],
            'status' => 1,
            'last_queue_number' => 0,
            'last_queue_number_timestamp' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $queue,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred',
            'error' => $e->getMessage(),
            'status' => 500
        ], 500);
    }
}

public function updateQueue(Request $request, $id)
{
    $validated = $request->validate([
        'description' => 'required|string|max:255',
        'sector_id' => 'required|integer',
        'program_id' => 'required|integer',
    ]);

    try {
        $queue = QueueManager::findOrFail($id);

        $queue->update([
            'description' => $validated['description'],
            'sector_id' => $validated['sector_id'],
            'program_id' => $validated['program_id'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $queue,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred',
            'error' => $e->getMessage(),
            'status' => 500
        ], 500);
    }
}

public function getQueueNumber(Request $request)
{
    $validated = $request->validate([
        'sector_id' => 'required|integer',
        'program_id' => 'required|integer',
    ]);

    try {
        $queue = QueueManager::where('sector_id', $validated['sector_id'])
            ->where('program_id', $validated['program_id'])
            ->where('status', 1)
            ->firstOrFail();

        $lastTimestamp = $queue->last_queue_number_timestamp ? \Carbon\Carbon::parse($queue->last_queue_number_timestamp) : null;
        $now = \Carbon\Carbon::now();

        if (!$lastTimestamp || !$lastTimestamp->isSameDay($now)) {
            $queue->last_queue_number = 0;
        }

        $queue->last_queue_number += 1;
        $queue->last_queue_number_timestamp = $now;
        $queue->save();

        return response()->json([
            'status' => 'success',
            'data' => $queue,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred',
            'error' => $e->getMessage(),
            'status' => 500
        ], 500);
    }
}

}
