<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Resources\Project\ProjectCollection;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query();
                 
        // Eager load untuk mengurangi query N+1
        $query->with(['company', 'user']);

        // Filter pencarian
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%')
                      ->orWhere('name', 'like', '%' . $request->search . '%')
                      ->orWhereHas('company', function ($query) use ($request) {
                          $query->where('name', 'like', '%' . $request->search . '%');
                      });
            });
        }

        // Filter berdasarkan status request_status_owner
        if ($request->has('request_status_owner')) {
            $query->where('request_status_owner', $request->request_status_owner);
        }

        // Filter berdasarkan status cost progress
        if ($request->has('status_cost_progress')) {
            $query->where('status_cost_progress', $request->status_cost_progress);
        }

        // Filter berdasarkan ID proyek
        if ($request->has('project')) {
            $query->where('id', $request->project);
        }

        // Filter berdasarkan vendor
        if ($request->has('vendor')) {
            $query->where('company_id', $request->vendor);
        }

        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
        
            $query->whereBetween('date', $date); // Ganti 'created_at' sesuai dengan kolom yang sesuai
        }

        if ($request->has('year')) {
            $year = $request->year;
            $query->whereYear('date', $year);
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate($request->per_page);

        return new ProjectCollection($projects);
    }

    public function createInformasi() {
        
    }
}
