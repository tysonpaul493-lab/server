<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CloudDatabaseController extends Controller
{
    /**
     * Get Database Cloud Status, Record Counts, and Health
     */
    public function status(): JsonResponse
    {
        try {
            $userCount = DB::table('users')->count();
            $driverCount = DB::table('drivers')->count();
            $bookingCount = DB::table('bookings')->count();
            $chatCount = DB::table('booking_chat_messages')->count();
            $docCount = DB::table('driver_documents')->count();

            $backupFile = database_path('liferoute_database.sql');
            $backupExists = File::exists($backupFile);
            $backupSize = $backupExists ? File::size($backupFile) : 0;
            $backupModified = $backupExists ? date('c', File::lastModified($backupFile)) : null;

            $isCloud = !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost']);

            return response()->json([
                'status' => 'success',
                'database' => [
                    'connection' => config('database.default'),
                    'host' => config('database.connections.mysql.host'),
                    'port' => config('database.connections.mysql.port'),
                    'database' => config('database.connections.mysql.database'),
                    'is_cloud_connected' => $isCloud,
                    'storage_mode' => $isCloud ? 'Remote Cloud Database' : 'Local MariaDB with Cloud Backup API',
                ],
                'record_counts' => [
                    'registered_users' => $userCount,
                    'registered_drivers' => $driverCount,
                    'total_ambulance_fleet' => $driverCount,
                    'emergency_bookings' => $bookingCount,
                    'chat_telemetry_messages' => $chatCount,
                    'driver_license_rc_documents' => $docCount,
                ],
                'snapshot' => [
                    'file' => 'database/liferoute_database.sql',
                    'exists' => $backupExists,
                    'size_bytes' => $backupSize,
                    'size_kb' => round($backupSize / 1024, 2),
                    'last_updated' => $backupModified,
                    'download_url' => url('/api/v1/database/export?format=sql'),
                ],
                'cloud_guide' => [
                    'overview' => 'Your database can be connected to any free/managed cloud MySQL provider (Railway, Aiven, AWS RDS, Supabase, Clever Cloud).',
                    'env_variables' => [
                        'DB_HOST' => 'your-cloud-db-host.com',
                        'DB_PORT' => '3306',
                        'DB_DATABASE' => 'liferoute',
                        'DB_USERNAME' => 'your_user',
                        'DB_PASSWORD' => 'your_password',
                    ],
                ],
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database query failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export complete database records to JSON or SQL snapshot
     */
    public function export(Request $request): JsonResponse|BinaryFileResponse
    {
        $format = strtolower((string) $request->query('format', 'json'));

        if ($format === 'sql') {
            $backupFile = database_path('liferoute_database.sql');
            if (File::exists($backupFile)) {
                return response()->download($backupFile, 'liferoute_backup_'.date('Ymd_His').'.sql', [
                    'Content-Type' => 'application/sql',
                ]);
            }
        }

        try {
            $data = [
                'exported_at' => now()->toIso8601String(),
                'platform' => 'LifeRoute Emergency System',
                'version' => '1.0.0',
                'data' => [
                    'users' => DB::table('users')->get(),
                    'drivers' => DB::table('drivers')->get(),
                    'bookings' => DB::table('bookings')->latest('id')->limit(500)->get(),
                    'booking_chat_messages' => DB::table('booking_chat_messages')->latest('id')->limit(500)->get(),
                    'driver_documents' => DB::table('driver_documents')->get(),
                ],
            ];

            return response()->json([
                'status' => 'success',
                'export' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger fresh database dump to preserve on disk and cloud
     */
    public function triggerSnapshot(): JsonResponse
    {
        try {
            $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
            $target = database_path('liferoute_database.sql');

            if (File::exists($mysqldump)) {
                $cmd = sprintf('"%s" -u root -P 3306 liferoute --result-file="%s"', $mysqldump, $target);
                exec($cmd, $output, $returnCode);

                if ($returnCode === 0 && File::exists($target)) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Database snapshot created successfully.',
                        'file' => 'database/liferoute_database.sql',
                        'size_kb' => round(File::size($target) / 1024, 2),
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }
            }

            // Fallback JSON export
            $tables = ['users', 'drivers', 'bookings', 'booking_chat_messages', 'driver_documents'];
            $snapshotData = [];
            foreach ($tables as $tbl) {
                $snapshotData[$tbl] = DB::table($tbl)->get();
            }
            $jsonTarget = database_path('liferoute_database.json');
            File::put($jsonTarget, json_encode($snapshotData, JSON_PRETTY_PRINT));

            return response()->json([
                'status' => 'success',
                'message' => 'Database JSON snapshot created successfully.',
                'file' => 'database/liferoute_database.json',
                'size_kb' => round(File::size($jsonTarget) / 1024, 2),
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Snapshot failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
