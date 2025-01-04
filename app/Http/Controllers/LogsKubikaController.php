<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LogsKubikaController extends Controller
{
    public function index(Request $request)
    {
        // Ambil data dari tabel log_man_powers
        $logManPowers = DB::table('log_man_powers')
            ->select(
                'id',
                'man_power_id as reference_id',
                'created_by',
                'message',
                'created_at',
                'updated_at'
            )
            ->addSelect(DB::raw("'man_power' as type"))
            ->get();

        // Ambil data dari tabel logs_spbprojects
        $logsSpbProjects = DB::table('logs_spbprojects')
            ->select(
                'id',
                'spb_project_id as reference_id',
                'name as created_by',
                'message',
                'created_at',
                'updated_at'
            )
            ->addSelect(DB::raw("'spb_project' as type"))
            ->get();

        // Ambil data dari tabel projects
        $logsProjects = DB::table('projects')
            ->join('users', 'projects.user_id', '=', 'users.id')
            ->select(
                DB::raw('NULL as id'), // Tidak memiliki ID log khusus, gunakan NULL
                'projects.id as reference_id',
                'users.name as created_by',
                DB::raw("'Created project' as message"),
                'projects.created_at',
                'projects.updated_at'
            )
            ->addSelect(DB::raw("'project' as type"))
            ->get();

        // Gabungkan ketiga data
        $combinedLogs = $logManPowers
            ->concat($logsSpbProjects)
            ->concat($logsProjects);

        // Urutkan berdasarkan created_at secara descending
        $sortedLogs = $combinedLogs->sortByDesc('created_at');

        // Format hasil log
        $formattedLogs = $sortedLogs->map(function ($log) {
            return [
                'id' => $log->id ?? null,
                'reference_id' => $log->reference_id,
                'created_by' => $log->created_by,
                'message' => $log->message,
                'type' => $log->type,
                'created_at' => Carbon::parse($log->created_at)->timezone('Asia/Jakarta')->toDateTimeString(),
                'updated_at' => Carbon::parse($log->updated_at)->timezone('Asia/Jakarta')->toDateTimeString(),
            ];
        });

        // Kembalikan data dalam format JSON
        return response()->json([
            'status' => 'success',
            'logs' => $formattedLogs->values()->all(),
        ]);
    }
}
