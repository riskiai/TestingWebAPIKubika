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
        $query = $this->manPower->query();

        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        if ($request->has('user_id')) {
            $query->where("user_id", $request->user_id);
        }

        if ($request->has('project_id')) {
            $query->where("project_id", $request->project_id);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

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

        // $manPower = $this->manPower->where([
        //     "user_id" => $user->id,
        //     "project_id" => $project->id
        // ])->whereDate("created_at", Carbon::now())->first();

        // if ($manPower) {
        //     return MessageActeeve::warning('Man power date now has exists!');
        // }

        $request->merge([
            'daily_salary_master' => $user->salary->daily_salary,
            'hourly_salary_master' => $user->salary->hourly_salary,
            'hourly_overtime_salary_master' => $user->salary->hourly_overtime_salary,
        ]);

        $sumSalary = $this->sumSalary($request);

        try {
            $this->manPower->create([
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
                "created_by" => $request->user()->name,
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
        $manPower = $this->manPower->find($id);
        if (!$manPower) {
            return MessageActeeve::notFound('Man power not found!');
        }

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $manPower
        ]);
    }

    public function update(UpdateRequest $request, string $id)
    {
        DB::beginTransaction();

        $manPower = $this->manPower->find($id);
        if (!$manPower) {
            return MessageActeeve::notFound('Man power not found!');
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
                "project_type" => $request->project_type,
                "hour_salary" => $request->hour_salary,
                "hour_overtime" => $request->hour_overtime,
                "description" => $request->description,
                "current_salary" => $sumSalary["currentSalary"],
                "current_overtime_salary" => $sumSalary["currentOvertimeSalary"],
                "created_by" => $request->user()->name,
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
