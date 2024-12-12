<?php

namespace App\Http\Resources\ManPower;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ManPowerCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this as $manPower) {
            $data[] = [
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
                "project" => [
                    "id" => $manPower->project->id,
                    "name" => $manPower->project->name,
                ],
                "user" => [
                    "id" => $manPower->user->id,
                    "name" => $manPower->user->name,
                ],
                "created_by" => $manPower->created_by,
                "created_at" => $manPower->created_at,
                "updated_at" => $manPower->updated_at,
            ];
        }

        return $data;
    }
}
