<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class LogsKubikaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $currentPage = $request->input('page', 1);

        // **1. Dapatkan tanggal terbaru dari semua tabel**
        $latestDates = [
            DB::table('log_man_powers')->max('created_at'),
            DB::table('logs_spbprojects')->max('created_at'),
            DB::table('projects')->max('created_at'),
            DB::table('spb_projects')->max('created_at'),
            DB::table('product_company_spbproject')->max('created_at'),
            DB::table('spb_project_termins')->max('created_at'),
            DB::table('project_termins')->max('created_at'),
            DB::table('log_man_powers')->max('updated_at'),
            DB::table('logs_spbprojects')->max('updated_at'),
            DB::table('projects')->max('updated_at'),
            DB::table('spb_projects')->max('updated_at'),
            DB::table('product_company_spbproject')->max('updated_at'),
            DB::table('spb_project_termins')->max('updated_at'),
            DB::table('project_termins')->max('updated_at'),
        ];

        // **Ambil tanggal terbaru di antara semua tabel**
        $maxDate = collect($latestDates)->filter()->max();

        // Pastikan format tanggal sesuai (Y-m-d)
        $maxDate = Carbon::parse($maxDate)->format('Y-m-d');

        // **2. Ambil data berdasarkan tanggal terbaru**
        $logManPowers = DB::table('log_man_powers')
            ->whereDate('log_man_powers.created_at', $maxDate)
            ->orWhereDate('log_man_powers.updated_at', $maxDate)
            ->select(
                'id',
                'man_power_id as reference_id',
                'created_by',
                'message',
                'created_at',
                'updated_at',
                'deleted_at',
                'deleted_by'
            )
            ->addSelect(DB::raw("'man_power' as type"))
            ->orderByDesc('created_at')
            ->get();

        $logsSpbProjects = DB::table('logs_spbprojects')
            ->whereDate('logs_spbprojects.created_at', $maxDate)
            ->orWhereDate('logs_spbprojects.updated_at', $maxDate)
            ->select(
                'id',
                'spb_project_id as reference_id',
                'name as created_by',
                'message',
                'created_at',
                'updated_at',
                'deleted_at',
                'deleted_by'
            )
            ->addSelect(DB::raw("'spb_project' as type"))
            ->orderByDesc('created_at')
            ->get();

        $logsProjects = DB::table('projects')
            ->join('users', 'projects.user_id', '=', 'users.id')
            ->whereDate('projects.created_at', $maxDate)
            ->orWhereDate('projects.updated_at', $maxDate)
            ->select(
                DB::raw('NULL as id'),
                'projects.id as reference_id',
                'users.name as created_by',
                DB::raw("'Created project' as message"),
                'projects.created_at',
                'projects.updated_at'
            )
            ->addSelect(DB::raw("'project' as type"))
            ->orderByDesc('projects.created_at')
            ->get();

        $createdSpbs = DB::table('spb_projects')
            ->join('users', 'spb_projects.user_id', '=', 'users.id')
            ->whereDate('spb_projects.created_at', $maxDate)
            ->orWhereDate('spb_projects.updated_at', $maxDate)
            ->select(
                DB::raw('NULL as id'),
                'spb_projects.doc_no_spb as reference_id',
                'users.name as created_by',
                DB::raw("'Created SPB' as message"),
                'spb_projects.created_at',
                'spb_projects.updated_at',
                'spb_projects.deleted_at'
            )
            ->addSelect(DB::raw("'spb_project' as type"))
            ->orderByDesc('spb_projects.created_at')
            ->get();

        // **3. Gabungkan semua data**
        $combinedLogs = collect()
            ->merge($logManPowers)
            ->merge($logsSpbProjects)
            ->merge($logsProjects)
            ->merge($createdSpbs);

        // **4. Urutkan berdasarkan created_at secara descending**
        $sortedLogs = $combinedLogs->sortByDesc('created_at')->values();

        // **5. Paginasi hasil gabungan**
        $total = $sortedLogs->count();
        $paginatedLogs = new LengthAwarePaginator(
            $sortedLogs->forPage($currentPage, $perPage),
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // **6. Format hasil log**
        $formattedLogs = $paginatedLogs->map(function ($log) {
            return [
                'id' => $log->id ?? null,
                'reference_id' => $log->reference_id,
                'created_by' => $log->created_by,
                'deleted_by' => isset($log->deleted_by) ? $log->deleted_by : null,
                'message' => $log->message,
                'type' => $log->type,
                'created_at' => Carbon::parse($log->created_at)->timezone('Asia/Jakarta')->toDateTimeString(),
                'updated_at' => Carbon::parse($log->updated_at)->timezone('Asia/Jakarta')->toDateTimeString(),
                'deleted_at' => isset($log->deleted_at) ? Carbon::parse($log->deleted_at)->timezone('Asia/Jakarta')->toDateTimeString() : null
            ];
        });

        // **7. Kembalikan data dalam format JSON**
        return response()->json([
            'status' => 'success',
            'logs' => $formattedLogs->values()->all(),
            'pagination' => [
                'current_page' => $paginatedLogs->currentPage(),
                'last_page' => $paginatedLogs->lastPage(),
                'per_page' => $paginatedLogs->perPage(),
                'total' => $paginatedLogs->total(),
            ]
        ]);
    }
}
