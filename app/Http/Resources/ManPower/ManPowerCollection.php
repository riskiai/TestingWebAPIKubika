<?php

namespace App\Http\Resources\ManPower;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ManPowerCollection extends ResourceCollection
{
    /**
     * Format tiap baris man power.
     */
    public function toArray($request): array
    {
        return $this->collection->map(function ($mp) {
            return [
                'id'                           => $mp->id,
                'work_type'                    => $mp->work_type ? 'Tukang Harian' : 'Tukang Borongan',
                'project_type'                 => $mp->project_type ? 'Project Aktif' : 'Project Non Aktif',

                'daily_salary_master'          => $mp->daily_salary_master,
                'hourly_salary_master'         => $mp->hourly_salary_master,
                'hourly_overtime_salary_master'=> $mp->hourly_overtime_salary_master,
                'hour_salary'                  => $mp->hour_salary,
                'hour_overtime'                => $mp->hour_overtime,

                'current_salary'               => $mp->current_salary,
                'current_overtime_salary'      => $mp->current_overtime_salary,
                'total_salary'                 => $mp->total_salary,            // integer
                'total_salary_formatted'       => $mp->total_salary_formatted,  // 14.261.250,00

                'description'                  => $mp->description,
                'entry_at'                     => $mp->entry_at,

                'project' => $mp->project
                    ? [
                        'id'   => $mp->project->id,
                        'name' => $mp->project->name,
                    ]
                    : [
                        'id'   => null,
                        'name' => null,
                    ],

                'user' => $mp->user
                    ? [
                        'id'   => $mp->user->id,
                        'name' => $mp->user->name,
                        'divisi' => $mp->user->divisi
                            ? [
                                'name'        => $mp->user->divisi->name,
                                'kode_divisi' => $mp->user->divisi->kode_divisi,
                            ]
                            : null,
                    ]
                    : null,

                'created_by' => [
                    'name'       => optional($mp->creator)->name ?? $mp->created_by,
                    'created_at' => Carbon::parse($mp->created_at)
                                      ->timezone('Asia/Jakarta')
                                      ->toDateTimeString(),
                ],

                'created_at' => $mp->created_at,
                'updated_at' => $mp->updated_at,
            ];
        })->all();
    }

    /**
     * Tambah meta aggregate agar total siap pakai.
     */
    public function with(Request $request): array
    {
        $totalCurrent  = $this->collection->sum('current_salary');
        $totalOvertime = $this->collection->sum('current_overtime_salary');
        $totalCost     = $totalCurrent + $totalOvertime;

        return [
            'meta' => [
                'total_current_salary'   => $totalCurrent,
                'total_overtime_salary'  => $totalOvertime,
                'total_man_power_cost'   => $totalCost,                     // == costProgress
                'total_formatted'        => number_format($totalCost, 2, ',', '.'),
                'count'                  => $this->collection->count(),
            ],
        ];
    }
}
