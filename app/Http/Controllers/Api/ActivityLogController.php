<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Log;

class ActivityLogController extends Controller
{
/**
 * @OA\Get(
 *     path="/v1/activity-logs",
 *     tags={"Activity Logs"},
 *     summary="Get all activity logs",
 *     description="Fetches all activity logs recorded in the system.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Activity logs retrieved successfully.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Activity logs retrieved successfully.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="log_name", type="string", example="Auth"),
 *                     @OA\Property(property="description", type="string", example="User logged in."),
 *                     @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
 *                     @OA\Property(property="user", type="object",
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="name", type="string", example="John Doe"),
 *                         @OA\Property(property="email", type="string", example="john.doe@example.com")
 *                     ),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-27T10:00:00Z")
 *                 )
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Error retrieving activity logs.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function getAllLogs()
{
    try {
        $logs = Activity::with('causer')
            ->select('id', 'log_name', 'description', 'properties', 'created_at')
            ->get();

        $logs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'ip_address' => $log->properties['ip_address'] ?? null,
                'user' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => $log->causer->name,
                    'email' => $log->causer->email,
                ] : null,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'meta' => ['code' => 200, 'success' => true, 'message' => 'Activity logs retrieved successfully.'],
            'result' => $logs,
            'errors' => []
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error retrieving all logs: ' . $e->getMessage());

        return response()->json([
            'meta' => ['code' => 500, 'success' => false, 'message' => 'Error retrieving activity logs.'],
            'result' => [],
            'errors' => [$e->getMessage()]
        ], 500);
    }
}


/**
 * @OA\Get(
 *     path="/v1/activity-logs/log-name/{log_name}",
 *     tags={"Activity Logs"},
 *     summary="Get activity logs by log name",
 *     description="Fetches activity logs filtered by log name.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="log_name",
 *         in="path",
 *         required=true,
 *         description="The name of the log to filter by",
 *         @OA\Schema(type="string", example="Auth")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Activity logs retrieved successfully by log name.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Activity logs for log name 'Auth' retrieved successfully.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="log_name", type="string", example="Auth"),
 *                     @OA\Property(property="description", type="string", example="User logged in."),
 *                     @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
 *                     @OA\Property(property="user", type="object",
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="name", type="string", example="John Doe"),
 *                         @OA\Property(property="email", type="string", example="john.doe@example.com")
 *                     ),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-27T10:00:00Z")
 *                 )
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Log name not found.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=404),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="No logs found for the provided log name.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function getLogsByLogName($log_name)
{
    try {
        $logs = Activity::with('causer')
            ->where('log_name', $log_name)
            ->get();

        if ($logs->isEmpty()) {
            return response()->json([
                'meta' => ['code' => 404, 'success' => false, 'message' => "No logs found for log name '{$log_name}'."],
                'result' => [],
                'errors' => []
            ], 404);
        }

        $logs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'ip_address' => $log->properties['ip_address'] ?? null,
                'user' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => $log->causer->name,
                    'email' => $log->causer->email,
                ] : null,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'meta' => ['code' => 200, 'success' => true, 'message' => "Logs for log name '{$log_name}' retrieved successfully."],
            'result' => $logs,
            'errors' => []
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error retrieving logs by log name: ' . $e->getMessage());

        return response()->json([
            'meta' => ['code' => 500, 'success' => false, 'message' => 'Error retrieving logs by log name.'],
            'result' => [],
            'errors' => [$e->getMessage()]
        ], 500);
    }
}


/**
 * @OA\Get(
 *     path="/v1/activity-logs/date-range",
 *     tags={"Activity Logs"},
 *     summary="Get activity logs by date and time range",
 *     description="Fetches activity logs filtered by a specific date and time range.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="start_date",
 *         in="query",
 *         required=true,
 *         description="The start date and time for the log filter in ISO 8601 format.",
 *         @OA\Schema(type="string", format="date-time", example="2024-11-01T00:00:00Z")
 *     ),
 *     @OA\Parameter(
 *         name="end_date",
 *         in="query",
 *         required=true,
 *         description="The end date and time for the log filter in ISO 8601 format.",
 *         @OA\Schema(type="string", format="date-time", example="2024-11-30T23:59:59Z")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Activity logs retrieved successfully by date range.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=200),
 *                 @OA\Property(property="success", type="boolean", example=true),
 *                 @OA\Property(property="message", type="string", example="Activity logs retrieved successfully for the specified date range.")
 *             ),
 *             @OA\Property(property="result", type="array",
 *                 @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="log_name", type="string", example="Auth"),
 *                     @OA\Property(property="description", type="string", example="User logged in."),
 *                     @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
 *                     @OA\Property(property="user", type="object",
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="name", type="string", example="John Doe"),
 *                         @OA\Property(property="email", type="string", example="john.doe@example.com")
 *                     ),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-27T10:00:00Z")
 *                 )
 *             ),
 *             @OA\Property(property="errors", type="array", @OA\Items(type="string", example="An empty array."))
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid date range.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=400),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Invalid date range.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="Start date must be earlier than end date.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal server error.",
 *         @OA\JsonContent(
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="code", type="integer", example=500),
 *                 @OA\Property(property="success", type="boolean", example=false),
 *                 @OA\Property(property="message", type="string", example="Error retrieving logs by date range.")
 *             ),
 *             @OA\Property(property="result", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="errors", type="array",
 *                 @OA\Items(type="string", example="An unexpected error occurred.")
 *             )
 *         )
 *     )
 * )
 */
public function getLogsByDateRange(Request $request)
{
    try {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Validate date range
        if (!$startDate || !$endDate || strtotime($startDate) > strtotime($endDate)) {
            return response()->json([
                'meta' => ['code' => 400, 'success' => false, 'message' => 'Invalid date range.'],
                'result' => [],
                'errors' => ['Start date must be earlier than end date.']
            ], 400);
        }

        $logs = Activity::with('causer')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        if ($logs->isEmpty()) {
            return response()->json([
                'meta' => ['code' => 200, 'success' => true, 'message' => 'No logs found for the specified date range.'],
                'result' => [],
                'errors' => []
            ], 200);
        }

        $logs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'ip_address' => $log->properties['ip_address'] ?? null,
                'user' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => $log->causer->name,
                    'email' => $log->causer->email,
                ] : null,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'meta' => ['code' => 200, 'success' => true, 'message' => 'Activity logs retrieved successfully for the specified date range.'],
            'result' => $logs,
            'errors' => []
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error retrieving logs by date range: ' . $e->getMessage());

        return response()->json([
            'meta' => ['code' => 500, 'success' => false, 'message' => 'Error retrieving logs by date range.'],
            'result' => [],
            'errors' => [$e->getMessage()]
        ], 500);
    }
}



}
