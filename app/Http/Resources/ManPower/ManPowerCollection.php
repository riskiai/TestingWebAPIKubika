<?php

namespace App\Http\Resources\ManPower;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ManPowerCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rows = [];

        foreach ($this->collection as $manPower) {
            $rows[] = [
                'id'                            => $manPower->id,
                'work_type'                     => $manPower->work_type ? 'Tukang Harian' : 'Tukang Borongan',
                'project_type'                  => $manPower->project_type ? 'Project Aktif' : 'Project Non Aktif',
                'daily_salary_master'           => $manPower->daily_salary_master,
                'hourly_salary_master'          => $manPower->hourly_salary_master,
                'hourly_overtime_salary_master' => $manPower->hourly_overtime_salary_master,
                'hour_salary'                   => $manPower->hour_salary,
                'hour_overtime'                 => $manPower->hour_overtime,
                'current_salary'                => $manPower->current_salary,
                'current_overtime_salary'       => $manPower->current_overtime_salary,
                'total_salary'                  => $manPower->current_salary + $manPower->current_overtime_salary,
                'description'                   => $manPower->description,
                'entry_at'                      => $manPower->entry_at,
                'project'                       => $manPower->project ? [
                    'id'   => $manPower->project->id,
                    'nama' => $manPower->project->name,
                ] : [
                    'id'   => null,
                    'nama' => null,
                ],
                'user'                          => $manPower->user ? [
                    'id'     => $manPower->user->id,
                    'name'   => $manPower->user->name,
                    'divisi' => $manPower->user->divisi ? [
                        'name'        => $manPower->user->divisi->name,
                        'kode_divisi' => $manPower->user->divisi->kode_divisi,
                    ] : null,
                ] : null,
                'created_by'                    => [
                    'name'       => $manPower->creator->name ?? $manPower->created_by,
                    'created_at' => Carbon::parse($manPower->created_at)
                                         ->timezone('Asia/Jakarta')
                                         ->toDateTimeString(),
                ],
                'created_at'                    => $manPower->created_at,
                'updated_at'                    => $manPower->updated_at,
            ];
        }

        return [ 'data' => $rows ];
    }
}
