<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Role;
use App\Models\User;
use App\Models\Project;
use App\Models\ManPower;
use App\Models\SpbProject;
use Illuminate\Http\Request;
use App\Models\SpbProject_Status;
use App\Http\Resources\Project\ProjectCollection;
use App\Http\Resources\ManPower\ManPowerCollection;
use App\Http\Resources\SPBproject\SPBprojectCollection;

class ReportController extends Controller
{
    public function reportPPH(Request $request)
    {
        $query = SpbProject::query();
        
        // Filter berdasarkan role pengguna
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->where('user_id', auth()->user()->id);
        }

        $query->with(['user', 'products', 'project', 'status', 'vendors', 'taxPph']);

        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }
        

        if ($request->has('status')) {
            $status = $request->status;
            // Pastikan status valid dan cocok dengan ID status
            if (in_array($status, [
                SpbProject_Status::AWAITING,
                SpbProject_Status::VERIFIED,
                SpbProject_Status::OPEN,
                SpbProject_Status::OVERDUE,
                SpbProject_Status::DUEDATE,
                SpbProject_Status::REJECTED,
                SpbProject_Status::PAID
            ])) {
                $query->whereHas('status', function ($query) use ($status) {
                    $query->where('id', $status);
                });
            }
        }

        // Filter berdasarkan tab_spb
        if ($request->has('tab_spb')) {
            $tab = $request->get('tab_spb');
            if (in_array($tab, [
                SpbProject::TAB_SUBMIT,
                SpbProject::TAB_VERIFIED,
                SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAID
            ])) {
                $query->where('tab_spb', $tab);
            }
        }

        // Filter berdasarkan project ID
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                // Tentukan nama tabel untuk kolom 'id'
                $query->where('projects.id', $request->project);
            });
        }

        // Filter berdasarkan range date (tanggal_dibuat_spb atau tanggal tertentu dari SPB Project)
        if ($request->has('tanggal_dibuat_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_dibuat_spb)); // Parsing tanggal range
            $query->whereBetween('tanggal_dibuat_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }


        // Filter berdasarkan due_date (tanggal_berahir_spb)
        if ($request->has('tanggal_berahir_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_berahir_spb)); // Parsing tanggal range
            $query->whereBetween('tanggal_berahir_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }

         // Additional filters
        if ($request->has('pph_name')) {
            $query->whereHas('taxPph', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->pph_name . '%');
            });
        }

        // Pengurutan berdasarkan tab
        if ($request->has('tab')) {
            switch ($request->get('tab')) {
                case SpbProject::TAB_SUBMIT:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                case SpbProject::TAB_VERIFIED:
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $query->orderBy('tanggal_berahir_spb', 'asc')->orderBy('doc_no_spb', 'asc');
                    break;
                case SpbProject::TAB_PAID:
                    $query->orderBy('updated_at', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                default:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
            }
        } else {
            // Jika tidak ada tab yang dipilih, urutkan berdasarkan tanggal dibuat secara descending
            $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
        }

        // Pagination
        $spbProjects = $query->paginate($request->per_page);

        // Return data dalam bentuk koleksi
        return new SPBprojectCollection($spbProjects);
    }

    public function reportPPN(Request $request)
    {
        $query = SpbProject::query();
        
        // Filter berdasarkan role pengguna
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->where('user_id', auth()->user()->id);
        }
    
        $query->with(['user', 'products', 'project', 'status', 'vendors', 'taxPpn']);
    
        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }
    
        if ($request->has('status')) {
            $status = $request->status;
            // Pastikan status valid dan cocok dengan ID status
            if (in_array($status, [
                SpbProject_Status::AWAITING,
                SpbProject_Status::VERIFIED,
                SpbProject_Status::OPEN,
                SpbProject_Status::OVERDUE,
                SpbProject_Status::DUEDATE,
                SpbProject_Status::REJECTED,
                SpbProject_Status::PAID
            ])) {
                $query->whereHas('status', function ($query) use ($status) {
                    $query->where('id', $status);
                });
            }
        }
    
        // Filter berdasarkan project ID
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                $query->where('projects.id', $request->project);
            });
        }
    
        // Filter berdasarkan range date (tanggal_dibuat_spb atau tanggal tertentu dari SPB Project)
        if ($request->has('tanggal_dibuat_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_dibuat_spb)); // Parsing tanggal range
            $query->whereBetween('tanggal_dibuat_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }
    
        // Filter berdasarkan due_date (tanggal_berahir_spb)
        if ($request->has('tanggal_berahir_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_berahir_spb)); // Parsing tanggal range
            $query->whereBetween('tanggal_berahir_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }
    
        // Filter PPN greater than 0 and not null
        $query->where(function ($query) {
            $query->whereNotNull('ppn')
                  ->where('ppn', '>', 0);
        });
    
        // Pengurutan berdasarkan tab
        if ($request->has('tab')) {
            switch ($request->get('tab')) {
                case SpbProject::TAB_SUBMIT:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                case SpbProject::TAB_VERIFIED:
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $query->orderBy('tanggal_berahir_spb', 'asc')->orderBy('doc_no_spb', 'asc');
                    break;
                case SpbProject::TAB_PAID:
                    $query->orderBy('updated_at', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                default:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
            }
        } else {
            // Jika tidak ada tab yang dipilih, urutkan berdasarkan tanggal dibuat secara descending
            $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
        }
    
        // Pagination
        $spbProjects = $query->paginate($request->per_page);
    
        // Return data dalam bentuk koleksi
        return new SPBprojectCollection($spbProjects);
    }

    public function reportPaid(Request $request)
    {
        $query = SpbProject::query();
        
        // Filter berdasarkan role pengguna
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->where('user_id', auth()->user()->id);
        }
    
        $query->with(['user', 'products', 'project', 'status', 'vendors']);
    
        // Filter berdasarkan nomor dokumen SPB (optional)
        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }
    
        // Filter berdasarkan status PAID saja
        $query->whereHas('status', function ($query) {
            $query->where('id', SpbProject_Status::PAID);
        });
         
        // Filter berdasarkan type_project (1: Project, 2: Non-Project)
         if ($request->has('type_project')) {
            $typeProject = $request->type_project;
            if (in_array($typeProject, [SpbProject::TYPE_PROJECT_SPB, SpbProject::TYPE_NON_PROJECT_SPB])) {
                $query->where('type_project', $typeProject);
            }
        }

        if ($request->has('status_produk')) {
            $status_produk = $request->status_produk;
        
            $query->whereHas('products', function ($query) use ($status_produk) {
                $query->where('status_produk', $status_produk);
            });
        }        


        // Filter berdasarkan tab_spb
        if ($request->has('tab_spb')) {
            $tab = $request->get('tab_spb');
            if (in_array($tab, [
                SpbProject::TAB_SUBMIT,
                SpbProject::TAB_VERIFIED,
                SpbProject::TAB_PAYMENT_REQUEST,
                SpbProject::TAB_PAID
            ])) {
                $query->where('tab_spb', $tab);
            }
        }
    
        // Filter berdasarkan project ID
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                $query->where('projects.id', $request->project);
            });
        }
    
        // Filter berdasarkan range date (tanggal_dibuat_spb atau tanggal tertentu dari SPB Project)
        if ($request->has('tanggal_dibuat_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_dibuat_spb)); // Parsing tanggal range
            $query->whereBetween('tanggal_dibuat_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }
    
        // Filter berdasarkan due_date (tanggal_berahir_spb)
        if ($request->has('tanggal_berahir_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_berahir_spb)); // Parsing tanggal range
            $query->whereBetween('tanggal_berahir_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }
    
        // Pengurutan berdasarkan tab
        if ($request->has('tab')) {
            switch ($request->get('tab')) {
                case SpbProject::TAB_SUBMIT:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                case SpbProject::TAB_VERIFIED:
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $query->orderBy('tanggal_berahir_spb', 'asc')->orderBy('doc_no_spb', 'asc');
                    break;
                case SpbProject::TAB_PAID:
                    $query->orderBy('updated_at', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
                default:
                    $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
                    break;
            }
        } else {
            // Jika tidak ada tab yang dipilih, urutkan berdasarkan tanggal dibuat secara descending
            $query->orderBy('tanggal_dibuat_spb', 'desc')->orderBy('doc_no_spb', 'desc');
        }
    
        // Pagination
        $spbProjects = $query->paginate($request->per_page);
    
        // Return data dalam bentuk koleksi
        return new SPBprojectCollection($spbProjects);
    }

    protected $manPower, $user, $project;

    public function __construct(ManPower $manPower, User $user, Project $project)
    {
        $this->manPower = $manPower;
        $this->user = $user;
        $this->project = $project;
    }
    
    public function reportManpower(Request $request)
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

        // Filter berdasarkan work_type
        if ($request->has('work_type')) {
            $workType = $request->work_type;
            if ($workType == 1) {
                $query->where('work_type', 1); // Hanya ambil data dengan work_type = 1 (true)
            } elseif ($workType == 0) {
                $query->where('work_type', 0); // Hanya ambil data dengan work_type = 0 (false)
            }
        }

         // Filter berdasarkan work_type
         if ($request->has('project_type')) {
            $projecType = $request->project_type;
            if ($projecType == 1) {
                $query->where('project_type', 1); // Hanya ambil data dengan work_type = 1 (true)
            } elseif ($projecType == 0) {
                $query->where('project_type', 0); // Hanya ambil data dengan work_type = 0 (false)
            }
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Urutkan data berdasarkan created_at secara descending
        $query->orderBy('created_at', 'desc');

        // Mendapatkan data dengan pagination
        $manPowers = $query->paginate($request->per_page);

        return new ManPowerCollection($manPowers);
    }

    public function reportProject(Request $request)
    {
        $query = Project::query();
                 
        // Eager load untuk mengurangi query N+1
        $query->with(['company', 'user', 'product', 'tenagaKerja']);

        if (auth()->user()->role_id == Role::MARKETING) {
            // Jika yang login adalah MARKETING, tampilkan hanya project yang dibuat oleh user tersebut
            $query->where('user_id', auth()->user()->id);
        }

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
        if ($request->has('status_cost_progres')) {
            $query->where('status_cost_progres', $request->status_cost_progres);
        }

        // Filter berdasarkan ID proyek
        if ($request->has('project')) {
            $query->where('id', $request->project);
        }

        // Filter berdasarkan vendor
        if ($request->has('contact')) {
            $query->where('company_id', $request->contact);
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

       // Filter berdasarkan tenaga kerja (tukang)
        if ($request->has('tukang')) {
            $tukangIds = explode(',', $request->tukang); // Mengambil ID tukang dari parameter yang dipisah dengan koma
            $query->whereHas('tenagaKerja', function ($query) use ($tukangIds) {
                $query->whereIn('users.id', $tukangIds); // Pastikan menggunakan 'users.id'
            });
        }

        // Filter berdasarkan work_type jika ada parameter di request
        if ($request->has('work_type')) {
            $workType = $request->work_type;
            if ($workType == 1) {
                $query->whereHas('manPowers', function ($q) {
                    $q->where('work_type', 1); // Hanya ambil tukang harian
                });
            } elseif ($workType == 0) {
                $query->whereHas('manPowers', function ($q) {
                    $q->where('work_type', 0); // Hanya ambil tukang borongan
                });
            }
        }

        // Urutkan berdasarkan tahun dan increment ID proyek
        $projects = $query->selectRaw('*, CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(id, "-", -2), "-", 1) AS UNSIGNED) as year')
        ->selectRaw('CAST(SUBSTRING_INDEX(id, "-", -1) AS UNSIGNED) as increment')
        ->orderBy('year', 'desc')  // Urutkan berdasarkan tahun (PRO-25 vs PRO-24)
        ->orderBy('increment', 'desc')  // Urutkan berdasarkan increment (001, 002, ...)
        ->orderBy('updated_at', 'desc') // Jika tahun dan increment sama, urutkan berdasarkan updated_at
        ->orderBy('created_at', 'desc') // Jika tahun, increment, dan updated_at sama, urutkan berdasarkan created_at
        ->paginate($request->per_page);

        return new ProjectCollection($projects);
    }
}
