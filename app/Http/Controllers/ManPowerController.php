<?php

namespace App\Http\Controllers;

use App\Facades\MessageActeeve;
use App\Http\Requests\ManPower\StoreRequest;
use App\Http\Requests\ManPower\UpdateRequest;
use App\Http\Resources\ManPower\ManPowerCollection;
use App\Models\ManPower;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManPowerController extends Controller
{
    protected $manPower, $user, $project;

    public function __construct(ManPower $manPower, User $user, Project $project)
    {
        $this->manPower = $manPower;
        $this->user = $user;
        $this->project = $project;
    }

    public function index(Request $request)
    {
        $query = $this->manPower->with('user');

        // Filter berdasarkan pencarian deskripsi atau nama pengguna
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                ->orWhereHas('user', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                });
            });
        }

        // Filter berdasarkan user_id
        if ($request->has('user_id')) {
            $query->where("user_id", $request->user_id);
        }

        // Filter berdasarkan project_id
        if ($request->has('project_id')) {
            $query->where("project_id", $request->project_id);
        }

        // Filter berdasarkan work_type
        if ($request->has('work_type')) {
            $workType = $request->work_type;
            if ($workType == 1) {
                $query->where('work_type', 1);
            } elseif ($workType == 0) {
                $query->where('work_type', 0);
            }
        }

        // Filter berdasarkan project_type
        if ($request->has('project_type')) {
            $projectType = $request->project_type;
            if ($projectType == 1) {
                $query->where('project_type', 1);
            } elseif ($projectType == 0) {
                $query->where('project_type', 0);
            }
        }

        // Filter berdasarkan rentang tanggal entry_at
    if ($request->has('entry_at')) {
        $dates = explode(',', $request->entry_at);  // Memisahkan tanggal yang dipisah koma
        if (count($dates) == 2) {
            $start_date = trim($dates[0]);
            $end_date = trim($dates[1]);

            // Pastikan tanggalnya valid dengan format yang benar
            if (strtotime($start_date) && strtotime($end_date)) {
                $start_date = date('Y-m-d 00:00:00', strtotime($start_date)); // Pastikan waktu mulai pada pukul 00:00
                $end_date = date('Y-m-d 23:59:59', strtotime($end_date));   // Pastikan waktu selesai pada pukul 23:59

                // Apply filter untuk rentang tanggal
                $query->whereBetween('entry_at', [$start_date, $end_date]);
            } else {
                // Jika tanggal tidak valid, beri respon error
                return response()->json(['error' => 'Invalid date format'], 400);
            }
        } else {
            // Jika parameter tanggal tidak valid (misal tidak ada koma atau hanya satu tanggal)
            return response()->json(['error' => 'Invalid date range format. Format should be: YYYY-MM-DD,YYYY-MM-DD'], 400);
        }
    }

        // Filter berdasarkan tanggal tertentu (optional)
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Urutkan berdasarkan entry_at secara descending
        $query->orderBy('entry_at', 'desc');

        // Mendapatkan data dengan pagination
        $manPowers = $query->paginate($request->per_page);

        return new ManPowerCollection($manPowers);
    }


    public function store(StoreRequest $request)
    {
        DB::beginTransaction();

        $user = $this->user->find($request->user_id);
        if (!$user) {
            return MessageActeeve::notFound('User not found!');
        }

        if (!$user->salary) {
            return MessageActeeve::notFound('Salary master not found!');
        }

        $project = $this->project->find($request->project_id);
        if (!$project) {
            return MessageActeeve::notFound('Project not found!');
        }

        if (!$request->has("entry_at")) {
            $request->merge([
                "entry_at" => Carbon::now()
            ]);
        }

        $manPower = $this->manPower->where([
            "user_id" => $user->id,
            "project_id" => $project->id,
            "project_type" => $request->project_type
        ])->whereDate("entry_at", $request->entry_at)->first();

        if ($manPower && $manPower->project_type == true) {
            return MessageActeeve::warning('Man power active project has exists!');
        }

        if ($manPower && $manPower->project_type == false) {
            return MessageActeeve::warning('Man power non project has exists!');
        }

        $request->merge([
            'daily_salary_master' => $user->salary->daily_salary,
            'hourly_salary_master' => $user->salary->hourly_salary,
            'hourly_overtime_salary_master' => $user->salary->hourly_overtime_salary,
        ]);

        $sumSalary = $this->sumSalary($request);

        try {
            $manPower = $this->manPower->create([
                "user_id" => $user->id,
                "project_id" => $project->id,
                "work_type" => $request->work_type,
                "project_type" => $request->project_type,
                "hour_salary" => $request->hour_salary,
                "hour_overtime" => $request->hour_overtime,
                "description" => $request->description,
                "daily_salary_master" => $request->daily_salary_master,
                "hourly_salary_master" => $request->hourly_salary_master,
                "hourly_overtime_salary_master" => $request->hourly_overtime_salary_master,
                "current_salary" => $sumSalary["currentSalary"],
                "current_overtime_salary" => $sumSalary["currentOvertimeSalary"],
                "created_by" => $request->user()->name ?? '-',
                "entry_at"  => $request->entry_at
            ]);

            $manPower->logs()->create([
                "created_by" => $request->user()->name ?? '-',
                "message" => $request->description,
            ]);

            DB::commit();
            return MessageActeeve::success("Man power has been successfully created");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function show(string $id)
    {
        // Mencari data ManPower berdasarkan ID
        $manPower = $this->manPower->find($id);
        
        // Jika tidak ditemukan, kembalikan pesan error 404
        if (!$manPower) {
            return MessageActeeve::notFound('Man power not found!');
        }
    
        // Mengubah format response sesuai dengan struktur ManPowerCollection
        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => [
                "id" => $manPower->id,
                "work_type" => $manPower->work_type ? "Tukang Harian" : "Tukang Borongan",
                "project_type" => $manPower->project_type ? "Project Aktif" : "Project Non Aktif",
                "daily_salary_master" => $manPower->daily_salary_master,
                "hourly_salary_master" => $manPower->hourly_salary_master,
                "hourly_overtime_salary_master" => $manPower->hourly_overtime_salary_master,
                "hour_salary" => $manPower->hour_salary,
                "hour_overtime" => $manPower->hour_overtime,
                "current_salary" => $manPower->current_salary,
                "current_overtime_salary" => $manPower->current_overtime_salary,
                "total_salary" => $manPower->current_salary + $manPower->current_overtime_salary,
                "description" => $manPower->description,
                "entry_at" => $manPower->entry_at,
                "project" => [
                    "id" => $manPower->project->id,
                    "name" => $manPower->project->name,
                ],
                "user" => [
                    "id" => $manPower->user->id,
                    "name" => $manPower->user->name,
                    'divisi' => [
                        'name' => $manPower->user->divisi->name ?? null,
                        'kode_divisi' => $manPower->user->divisi->kode_divisi ?? null,
                    ],
                ],
                "created_by" => $manPower->created_by,
                "created_at" => $manPower->created_at,
                "updated_at" => $manPower->updated_at,
            ]
        ]);
    }
    

    public function update(UpdateRequest $request, string $id)
    {
        DB::beginTransaction();

        $manPower = $this->manPower->find($id);
        if (!$manPower) {
            return MessageActeeve::notFound('Man power not found!');
        }

        if (!$request->has("entry_at")) {
            $request->merge([
                "entry_at" => $manPower->entry_at
            ]);
        }

        $request->merge([
            'daily_salary_master' => $manPower->daily_salary_master,
            'hourly_salary_master' => $manPower->hourly_salary_master,
            'hourly_overtime_salary_master' => $manPower->hourly_overtime_salary_master,
        ]);

        $sumSalary = $this->sumSalary($request);

        try {
            $manPower->update([
                "work_type" => $request->work_type,
                "hour_salary" => $request->hour_salary,
                "hour_overtime" => $request->hour_overtime,
                "description" => $request->description,
                "current_salary" => $sumSalary["currentSalary"],
                "current_overtime_salary" => $sumSalary["currentOvertimeSalary"],
                "entry_at"  => $request->entry_at
            ]);

            $manPower->logs()->create([
                "created_by" => $request->user()->name ?? '-',
                "message" => $request->description,
            ]);

            DB::commit();
            return MessageActeeve::success("Man power has been successfully updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function destroy(string $id)
    {
        DB::beginTransaction();

        $manPower = $this->manPower->find($id);
        if (!$manPower) {
            return MessageActeeve::notFound('Man power not found!');
        }

        try {
            $manPower->delete();

            DB::commit();
            return MessageActeeve::success("Man power has been successfully deleted");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    protected function sumSalary($request)
    {
        $currentSalary = $request->hour_salary * $request->hourly_salary_master;
        $currentOvertimeSalary = $request->hour_overtime * $request->hourly_overtime_salary_master;

        return [
            "currentSalary" => $currentSalary,
            "currentOvertimeSalary" => $currentOvertimeSalary,
        ];
    }
}
