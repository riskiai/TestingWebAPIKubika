<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class LogsKubikaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $currentPage = $request->input('page', 1);
        $forceRefresh = $request->input('force_refresh', false); // Jika ingin refresh manual

        // **1. Ambil atau Simpan Tanggal Terbaru di Cache**
        $cacheKey = 'latest_log_date';
        if ($forceRefresh || !Cache::has($cacheKey)) {
            // Ambil tanggal terbaru jika cache tidak ada atau ada permintaan refresh
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

            // **Ambil tanggal terbaru dan simpan di cache**
            $maxDate = collect($latestDates)->filter()->max();
            $maxDate = Carbon::parse($maxDate)->format('Y-m-d');

            // Simpan tanggal terbaru di cache (kadaluarsa dalam 24 jam)
            Cache::put($cacheKey, $maxDate, now()->addDay());
        } else {
            // Gunakan tanggal terbaru yang ada di cache
            $maxDate = Cache::get($cacheKey);
        }

        // **Tambahkan kondisi agar tetap menampilkan data dari 7 hari terakhir**
        $minDate = Carbon::parse($maxDate)->subDays(7)->format('Y-m-d');

        // **2. Ambil data berdasarkan tanggal terbaru**
        $logManPowers = DB::table('log_man_powers')
            ->whereBetween('log_man_powers.created_at', [$minDate, $maxDate])
            ->orWhereBetween('log_man_powers.updated_at', [$minDate, $maxDate])
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
            ->whereBetween('logs_spbprojects.created_at', [$minDate, $maxDate])
            ->orWhereBetween('logs_spbprojects.updated_at', [$minDate, $maxDate])
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
            ->whereBetween('projects.created_at', [$minDate, $maxDate])
            ->orWhereBetween('projects.updated_at', [$minDate, $maxDate])
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
            ->whereBetween('spb_projects.created_at', [$minDate, $maxDate])
            ->orWhereBetween('spb_projects.updated_at', [$minDate, $maxDate])
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

        // **Tambahkan logs yang tidak boleh dihapus**
        $logsProductCompanySpb = DB::table('product_company_spbproject')
            ->join('spb_projects', 'product_company_spbproject.spb_project_id', '=', 'spb_projects.doc_no_spb')
            ->join('users', 'spb_projects.user_id', '=', 'users.id')
            ->select(
                DB::raw('NULL as id'),
                'product_company_spbproject.id as reference_id',
                'users.name as created_by',
                DB::raw("'Updated product in SPB project' as message"),
                'product_company_spbproject.created_at',
                'product_company_spbproject.updated_at'
            )
            ->addSelect(DB::raw("'product spb project' as type"))
            ->orderByDesc('created_at')
            ->get();

            $logsSpbProjectTermins = DB::table('spb_project_termins')
            ->join('spb_projects', 'spb_project_termins.doc_no_spb', '=', 'spb_projects.doc_no_spb')
            ->join('users', 'spb_projects.user_id', '=', 'users.id')
            ->select(
                DB::raw('NULL as id'),
                'spb_project_termins.id as reference_id',
                'users.name as created_by',
                DB::raw("CASE 
                            WHEN spb_project_termins.file_attachment_id IS NOT NULL 
                            THEN 'Paid termin in SPB project' 
                            ELSE 'Updated termin in SPB project' 
                         END as message"),
                'spb_project_termins.created_at',
                'spb_project_termins.updated_at'
            )
            ->addSelect(DB::raw("'spb project termin' as type"))
            ->orderByDesc('created_at')
            ->get();

            $logsProjectTermins = DB::table('project_termins')
            ->join('projects', 'project_termins.project_id', '=', 'projects.id')
            ->join('users', 'projects.user_id', '=', 'users.id')
            ->select(
                DB::raw('NULL as id'),
                'project_termins.id as reference_id',
                'users.name as created_by',
                DB::raw("CASE 
                            WHEN project_termins.file_attachment_pembayaran IS NOT NULL 
                            THEN 'Paid termin in project' 
                            ELSE 'Updated termin in project' 
                         END as message"),
                'project_termins.created_at',
                'project_termins.updated_at'
            )
            ->addSelect(DB::raw("'project termin' as type"))
            ->orderByDesc('created_at')
            ->get();

        // **3. Gabungkan semua data**
        $combinedLogs = collect()
            ->merge($logManPowers)
            ->merge($logsSpbProjects)
            ->merge($logsProjects)
            ->merge($createdSpbs)
            ->merge($logsProductCompanySpb)
            ->merge($logsSpbProjectTermins )
            ->merge($logsProjectTermins);

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
