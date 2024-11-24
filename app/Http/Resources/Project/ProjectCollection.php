<?php

namespace App\Http\Resources\Project;

use App\Models\Project;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProjectCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this as $key => $project) {
            $data[] = [
                'id' => $project->id,
                'client' => [
                    'id' => $project->company->id,
                    'name' => $project->company->name,
                    'contact_type' => $project->company->contactType->name,
                ],
                'date' => $project->date,
                'name' => $project->name,
                'billing' => $project->billing,
                'cost_estimate' => $project->cost_estimate,
                'margin' => $project->margin,
                'percent' => $this->formatPercent($project->percent),
                'file_attachment' => [
                    'name' => date('Y', strtotime($project->created_at)) . '/' . $project->id . '.' . pathinfo($project->file, PATHINFO_EXTENSION),
                    'link' => asset("storage/$project->file")
                ],
                'cost_progress' => $project->status_cost_progress,
                'status_step_project' => $this->getStepStatus($project->status_step_project),
                'request_status_owner' => $this->getRequestStatus($project->request_status_owner),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ];

            if ($project->user) {
                $data[$key]['created_by'] = [
                    "id" => $project->user->id,
                    "name" => $project->user->name,
                ];
            }
        }

        return $data;
    }

    /**
     * Format percent by removing "%" and rounding the value.
     */
    protected function formatPercent($percent): float
    {
        // Remove "%" if present and convert to float before rounding
        return round(floatval(str_replace('%', '', $percent)), 2);
    }

    /**
     * Get the status of the project.
     */
    protected function getRequestStatus($status)
    {
        $statuses = [
            Project::PENDING => "Pending",
            Project::ACTIVE => "Active",
            Project::REJECTED => "Rejected",
        ];

        return [
            "id" => $status,
            "name" => $statuses[$status] ?? "Unknown",
        ];
    }

    protected function getStepStatus($step)
    {
        $steps = [
            Project::INFORMASI_PROYEK => "Informasi Proyek",
            Project::PENGGUNA_MUATAN => "Pengguna Muatan",
            Project::PRATINJAU => "Pratinjau",
        ];

        return [
            "id" => $step,
            "name" => $steps[$step] ?? "Unknown",
        ];
    }

    /**
     * Calculate the cost progress and determine the project status.
     *
     * @param Project $project
     * @return array
     */
    /* protected function costProgress($project)
    {
        $status = Project::STATUS_OPEN;
        $total = 0;

        $purchases = $project->purchases()->where('tab', Purchase::TAB_PAID)->get();

        foreach ($purchases as $purchase) {
            $total += $purchase->sub_total;
        }

        // Check if cost_estimate is greater than zero before dividing
        if ($project->cost_estimate > 0) {
            $costEstimate = round(($total / $project->cost_estimate) * 100, 2);
        } else {
            // Default value if cost_estimate is zero
            $costEstimate = 0;
        }

        if ($costEstimate > 90) {
            $status = Project::STATUS_NEED_TO_CHECK;
        }

        if ($costEstimate == 100) {
            $status = Project::STATUS_CLOSED;
        }

        // Update the project status in the database
        $project->update(['status_cost_progress' => $status]);

        return [
            'status_cost_progress' => $status,
            'percent' => $costEstimate . '%',
            'real_cost' => $total
        ];
    } */
}
