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
                'updated_at',
                'log_man_powers.deleted_at',    // Menambahkan kolom deleted_at dari log_man_powers
                'log_man_powers.deleted_by'
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
                'updated_at',
                'deleted_at',
                'deleted_by'
            )
            ->whereNotNull('deleted_at')
            ->addSelect(DB::raw("'spb_project' as type"))
            ->get();

        // Ambil data dari tabel projects
        $logsProjects = DB::table('projects')
            ->join('users', 'projects.user_id', '=', 'users.id')
            ->select(
                DB::raw('NULL as id'),
                'projects.id as reference_id',
                'users.name as created_by',
                DB::raw("'Created project' as message"),
                'projects.created_at',
                'projects.updated_at'
            )
            ->addSelect(DB::raw("'project' as type"))
            ->get();

        // Tambahkan log khusus untuk created SPB dan man power
        $createdSpbs = DB::table('spb_projects')
            ->join('users', 'spb_projects.user_id', '=', 'users.id')
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
            ->get();
        
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
            ->get();

        // Gabungkan semua data
        $combinedLogs = $logManPowers
            ->concat($logsSpbProjects)
            ->concat($logsProjects)
            ->concat($createdSpbs)
            ->concat($logsProductCompanySpb)
            ->concat($logsSpbProjectTermins)
            ->concat($logsProjectTermins);

        // Urutkan berdasarkan created_at secara descending
        $sortedLogs = $combinedLogs->sortByDesc('created_at');

        // Format hasil log
        $formattedLogs = $sortedLogs->map(function ($log) {
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

        // Kembalikan data dalam format JSON
        return response()->json([
            'status' => 'success',
            'logs' => $formattedLogs->values()->all(),
        ]);
    }
}
