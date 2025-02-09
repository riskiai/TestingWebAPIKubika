<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\Project;
use App\Models\SpbProject;
use App\Models\ContactType;
use Illuminate\Http\Request;
use App\Models\ProjectTermin;
use App\Facades\MessageActeeve;
use App\Models\ProjectUserProduk;
use App\Models\SpbProject_Status;
use Illuminate\Support\Facades\DB;
use App\Models\SpbProject_Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductCompanySpbProject;
use App\Http\Requests\Project\StoreRequest;
use App\Http\Requests\Project\UpdateRequest;
use App\Http\Resources\Project\ProjectCollection;
use App\Http\Requests\Project\PaymentTerminRequest;
use App\Http\Requests\Project\UpdatePaymentTerminRequest;
use App\Http\Requests\Project\UpdatePengunaMuatanRequest;
use App\Http\Requests\SpbProject\UpdateTerminRequest;

class ProjectController extends Controller
{

    public function projectall(Request $request)
    {
        $query = Project::query();

        if ($request->has('role_id')) {
            // Ambil array role_id dari request, pastikan dalam bentuk array
            $roleIds = is_array($request->role_id) ? $request->role_id : explode(',', $request->role_id);

            // Terapkan filter untuk role_id
            $query->whereHas('tenagaKerja', function ($q) use ($roleIds) {
                $q->whereIn('role_id', $roleIds); // Pastikan menggunakan role_id
            });
        }

         // Filter berdasarkan divisi (name)
        if ($request->has('divisi_name')) {
            $divisiNames = is_array($request->divisi_name) ? $request->divisi_name : explode(',', $request->divisi_name);

            $query->whereHas('tenagaKerja.divisi', function ($q) use ($divisiNames) {
                $q->whereIn('name', $divisiNames); // Filter berdasarkan name divisi
            });
        }

        // Filter berdasarkan status proyek
         // Filter berdasarkan status request_status_owner
         if ($request->has('request_status_owner')) {
            $query->where('request_status_owner', $request->request_status_owner);
        }

        // Filter berdasarkan status cost progress
        if ($request->has('status_cost_progres')) {
            $query->where('status_cost_progres', $request->status_cost_progres);
        }

        if ($request->has('type_projects')) {
            $typeProjects = is_array($request->type_projects) 
                ? $request->type_projects 
                : explode(',', $request->type_projects);
    
            $query->whereIn('type_projects', $typeProjects); // Filter proyek berdasarkan type_projects
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

        // Tambahkan kondisi untuk menyortir data berdasarkan nama proyek
        $query->orderBy('name', 'asc');

        // Ambil daftar proyek yang sudah diurutkan
        $projects = $query->get();

        return new ProjectCollection($projects);
    }

    public function index(Request $request)
    {
        $query = Project::query();
                 
        // Eager load untuk mengurangi query N+1
        $query->with(['company', 'user', 'product', 'tenagaKerja']);

         // Terapkan filter berdasarkan peran pengguna
         if (auth()->user()->role_id == Role::MARKETING) {
            $query->where(function ($q) {
                $q->where('user_id', auth()->user()->id) // Proyek yang dibuat oleh Marketing
                  ->orWhereHas('tenagaKerja', function ($q) {
                      $q->where('user_id', auth()->user()->id); // Proyek di mana Marketing menjadi tenaga kerja
                  });
            });
        }
    
        // Filter untuk Supervisor
        if (auth()->user()->role_id == Role::SUPERVISOR) {
            $query->whereHas('tenagaKerja', function ($q) {
                $q->where('user_id', auth()->user()->id); // Proyek di mana Supervisor menjadi tenaga kerja
            });
        }      

        // Terapkan filter berdasarkan peran pengguna
        if ($request->has('role_id')) {
            // Ambil array role_id dari request, pastikan dalam bentuk array
            $roleIds = is_array($request->role_id) ? $request->role_id : explode(',', $request->role_id);

            // Terapkan filter untuk role_id
            $query->whereHas('tenagaKerja', function ($q) use ($roleIds) {
                $q->whereIn('role_id', $roleIds); // Pastikan menggunakan role_id
            });
        }

         // Filter berdasarkan divisi (name)
        if ($request->has('divisi_name')) {
            $divisiNames = is_array($request->divisi_name) ? $request->divisi_name : explode(',', $request->divisi_name);

            $query->whereHas('tenagaKerja.divisi', function ($q) use ($divisiNames) {
                $q->whereIn('name', $divisiNames); // Filter berdasarkan name divisi
            });
        }

        // Filter berdasarkan status_bonus_project
        if ($request->has('status_bonus_project')) {
            $statusBonus = $request->status_bonus_project;
            $query->where('status_bonus_project', $statusBonus);
        }

        // Filter pencarian
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%')
                      ->orWhere('name', 'like', '%' . $request->search . '%')
                      ->orWhere('no_dokumen_project', 'like', '%' . $request->search . '%') // Tambahkan ini
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
            $statusCostProgress = $request->status_cost_progres;
            $query->where('status_cost_progres', $statusCostProgress);
        }

        if ($request->has('no_dokumen_project')) {
            $query->where('no_dokumen_project', 'like', '%' . $request->no_dokumen_project . '%');
        }        
        

        if ($request->has('type_projects')) {
            $typeProjects = is_array($request->type_projects) 
                ? $request->type_projects 
                : explode(',', $request->type_projects);
    
            $query->whereIn('type_projects', $typeProjects); // Filter proyek berdasarkan type_projects
        }

        // Filter berdasarkan ID proyek
        if ($request->has('project')) {
            $query->where('id', $request->project);
        }

        // Filter berdasarkan vendor
        if ($request->has('contact')) {
            $query->where('company_id', $request->contact);
        }

       /*  if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
        
            $query->whereBetween('date', $date); // Ganti 'created_at' sesuai dengan kolom yang sesuai
        }

        if ($request->has('year')) {
            $year = $request->year;
            $query->whereYear('date', $year);
        } */

        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date); 
            $date = explode(", ", $date); 
            
            $query->whereRaw('STR_TO_DATE(date, "%Y-%m-%d") BETWEEN ? AND ?', [$date[0], $date[1]]);
        }

        if ($request->has('year')) {
            $year = $request->year;
            $query->whereRaw('YEAR(STR_TO_DATE(date, "%Y-%m-%d")) = ?', [$year]);
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

        if ($request->has('marketing_id')) {
            $query->whereHas('tenagaKerja', function ($q) use ($request) {
                $q->where('users.id', $request->marketing_id) // Filter berdasarkan ID marketing
                  ->whereHas('role', function ($roleQuery) {
                      $roleQuery->where('role_id', Role::MARKETING); // Pastikan role adalah Marketing
                  });
            });
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

    public function invoice(Request $request, $id)
    {
        // Menemukan proyek berdasarkan ID
        $project = Project::find($id);
        if (!$project) {
            return MessageActeeve::notFound('Project not found!');
        }

        // Mengambil semua SPB Projects yang terkait dengan Project
        $spbProjects = $project->spbProjects()->paginate($request->per_page);

        // Menyiapkan array untuk data output
        $data = [];
        foreach ($spbProjects as $spbProject) {
            // Ambil semua data dari pivot table product_company_spbproject berdasarkan spb_project_id
            $pivotData = $spbProject->productCompanySpbprojects;

            // Mengambil status pembayaran dengan pengecekan null
            $status = $spbProject->status ? $spbProject->status->name : 'Unknown';

            // Siapkan array untuk menyimpan vendor
            $vendors = [];
            $vendorIds = []; // Array untuk menyimpan vendor_id yang sudah ada

            // Loop untuk mengambil informasi vendor
            foreach ($pivotData as $pivot) {
                // Cek apakah vendor dengan vendor_id yang sama sudah ada
                if (!in_array($pivot->company_id, $vendorIds)) {
                    // Ambil nama perusahaan dari pivot table
                    $company = $pivot->company; // Menggunakan relasi yang sudah ada di model ProductCompanySpbProject
                    $companyName = $company ? $company->name : 'Unknown';
    
                    // Menyusun data vendor
                    $vendorData = [
                        'vendor_id' => $pivot->company_id, // Assuming the vendor_id is the company_id
                        'company_name' => $companyName,
                        'produk_id' => $pivot->produk_id,
                        'produk_nama' => $pivot->product->nama ?? 'Unknown', // Mengambil nama produk melalui relasi
                    ];

                    // Tambahkan vendor ke dalam array vendors
                    $vendors[] = $vendorData;

                    // Simpan vendor_id yang sudah ditambahkan ke dalam array vendorIds
                    $vendorIds[] = $pivot->company_id;
                }
            }

            // Menambahkan data SPB project ke dalam array data
            $data[] = [
                "doc_no_spb" => $spbProject->doc_no_spb,
                "doc_type_spb" => $spbProject->doc_type_spb,
                "total" => $spbProject->total_produk,
                "status" => [
                    'id' => $spbProject->status ? $spbProject->status->id : null,
                    'tab_spb' => $spbProject->tab_spb,
                    'name' => $status,
                ],
                "produk" => $vendors, // Menambahkan data vendor yang telah disusun
            ];
        }

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $data,
            'meta' => [
                'current_page' => $spbProjects->currentPage(),
                'from' => $spbProjects->firstItem(),
                'last_page' => $spbProjects->lastPage(),
                'path' => $spbProjects->path(),
                'per_page' => $spbProjects->perPage(),
                'to' => $spbProjects->lastItem(),
                'total' => $spbProjects->total(),
            ]
        ]);
    }

    public function counting(Request $request)
    {
        $query = Project::query();

        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%');
                $query->orWhere('name', 'like', '%' . $request->search . '%');
                $query->orWhereHas('company', function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

       /*  if ($request->has('year')) {
            $year = $request->year;
            $query->whereYear('date', $year);
        } */

          // Terapkan filter berdasarkan peran pengguna
          if ($request->has('role_id')) {
            // Ambil array role_id dari request, pastikan dalam bentuk array
            $roleIds = is_array($request->role_id) ? $request->role_id : explode(',', $request->role_id);

            // Terapkan filter untuk role_id
            $query->whereHas('tenagaKerja', function ($q) use ($roleIds) {
                $q->whereIn('role_id', $roleIds); // Pastikan menggunakan role_id
            });
        }

        if ($request->has('status_cost_progres')) {
            $statusCostProgress = $request->status_cost_progres;
            $query->where('status_cost_progres', $statusCostProgress);
        }

        if ($request->has('divisi_name')) {
            $divisiNames = is_array($request->divisi_name) ? $request->divisi_name : explode(',', $request->divisi_name);

            $query->whereHas('tenagaKerja.divisi', function ($q) use ($divisiNames) {
                $q->whereIn('name', $divisiNames); // Filter berdasarkan name divisi
            });
        }

        if ($request->has('request_status_owner')) {
            $query->where('request_status_owner', $request->request_status_owner);
        }

        if ($request->has('no_dokumen_project')) {
            $query->where('no_dokumen_project', 'like', '%' . $request->no_dokumen_project . '%');
        } 

        if ($request->has('type_projects')) {
            $typeProjects = is_array($request->type_projects) 
                ? $request->type_projects 
                : explode(',', $request->type_projects);
    
            $query->whereIn('type_projects', $typeProjects); // Filter proyek berdasarkan type_projects
        }

        // Lakukan filter berdasarkan project jika ada
        if ($request->has('project')) {
            $query->where('id', $request->project);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('vendor')) {
            $query->where('company_id', $request->vendor);
        }

        if ($request->has('marketing_id')) {
            $query->whereHas('tenagaKerja', function ($q) use ($request) {
                $q->where('users.id', $request->marketing_id) // Filter berdasarkan ID marketing
                ->whereHas('role', function ($roleQuery) {
                    $roleQuery->where('role_id', Role::MARKETING); // Pastikan role adalah Marketing
                });
            });
        }

        /* if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
            $query->whereBetween('created_at', $date);
        } */

        /* if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
        
            $query->whereRaw('STR_TO_DATE(created_at, "%Y-%m-%d") BETWEEN ? AND ?', [$date[0], $date[1]]);
        }        */ 

        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date); 
            $date = explode(", ", $date); 
            
            $query->whereRaw('STR_TO_DATE(date, "%Y-%m-%d") BETWEEN ? AND ?', [$date[0], $date[1]]);
        }

        if ($request->has('year')) {
            $year = $request->year;
            $query->whereRaw('YEAR(STR_TO_DATE(date, "%Y-%m-%d")) = ?', [$year]);
        }        

        // Ambil seluruh data tanpa paginasi
        $collection = $query->get();

        // Menghitung total billing, cost_estimate, dan margin untuk seluruh data
        $totalBilling = $collection->sum('billing');
        $totalCostEstimate = $collection->sum('cost_estimate');
        $totalMargin = $collection->sum('margin');

        // Menghitung persentase margin terhadap billing
        $percent = ($totalBilling > 0) ? ($totalMargin / $totalBilling) * 100 : 0;
        $percent = round($percent, 2) . '%';

        $totalHargaType = (float) $query->sum('harga_type_project');

        $totalProjects = $collection->count();

        // Response data
        return response()->json([
            "billing" => $totalBilling,
            "cost_estimate" => $totalCostEstimate,
            "margin" => $totalMargin,
            "percent" => $percent,
            "harga_type_project_total_borongan" => $totalHargaType,
            "total_projects" => $totalProjects,
        ]);
    }


    public function createInformasi(StoreRequest $request)
    {
        DB::beginTransaction(); // Mulai transaksi manual

        try {
            // Temukan perusahaan berdasarkan client_id
            $company = Company::find($request->client_id);
            if (!$company || $company->contact_type_id != ContactType::CLIENT) {
                return MessageActeeve::warning("This contact is not a client type");
            }

            // Persiapkan data yang akan disimpan
            $project = new Project();

            // Ambil tahun dari tanggal input
            $year = date('y', strtotime($request->date)); // Ambil tahun dua digit dari input tanggal

            // Generate ID dengan mengirimkan tahun ke function generateSequenceNumber
            $sequenceNumber = Project::generateSequenceNumber($year); // Panggil ID generator dengan $year
            $project->id = 'PRO-' . $year . '-' . $sequenceNumber; // Generate ID

            // Isi field lainnya
            $project->name = $request->name;
            $project->billing = $request->billing;
            $project->cost_estimate = $request->cost_estimate;
            $project->margin = $request->margin;
            $project->percent = $request->percent;
            $project->date = $request->date;
            $project->company_id = $company->id;
            $project->user_id = auth()->user()->id;
            $project->request_status_owner = Project::DEFAULT_STATUS;
            $project->status_bonus_project = Project::DEFAULT_STATUS_NO_BONUS;
            $project->type_projects = $request->type_projects;
            $project->no_dokumen_project = $request->no_dokumen_project;

            // Set harga_type_project to 0 if it's not provided
            $project->harga_type_project = $request->has('harga_type_project') ? $request->harga_type_project : 0;

             // Simpan file ke disk public
                $project->file = $request->hasFile('attachment_file') 
                ? $request->file('attachment_file')->store(Project::ATTACHMENT_FILE, 'public') 
                : null;

            $project->spb_file = $request->hasFile('attachment_file_spb') 
                ? $request->file('attachment_file_spb')->store(Project::ATTACHMENT_FILE_SPB, 'public') 
                : null;

            // Simpan proyek ke database
            $project->save();

             // Ambil produk_id dan user_id dari request
            $produkIds = array_filter($request->input('produk_id', []));  // Hapus nilai kosong
            $userIds = array_filter($request->input('user_id', []));  // Hapus nilai kosong

            // $userIds[] = auth()->user()->id;
            if (auth()->user()->role_id == Role::MARKETING) {
                $userIds[] = auth()->user()->id; // Tambahkan pembuat proyek ke daftar tenaga kerja
            }

            // Sinkronisasi produk_id di pivot table hanya jika ada produk_id yang valid
            if (!empty($produkIds)) {
                $project->product()->syncWithoutDetaching($produkIds); // Sinkronkan produk ke pivot table
            }

            // Sinkronisasi user_id di pivot table hanya jika ada user_id yang valid
            if (!empty($userIds)) {
                $project->tenagaKerja()->syncWithoutDetaching($userIds); 
            }

            // $project->tenagaKerja()->syncWithoutDetaching($userIds);

            // Commit transaksi
            DB::commit(); // Commit transaksi
            return MessageActeeve::success("Project created successfully. $project->id");
        } catch (\Exception $e) {
            // Rollback jika terjadi error
            DB::rollBack();
            Log::error("Error creating project: " . $e->getMessage());
            return MessageActeeve::error("Error creating project.");
        }
    }

    /* public function paymentTermin(PaymentTerminRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $project = Project::findOrFail($id);

            // Periksa apakah ada file yang diunggah
            $fileAttachment = null;
            if ($request->hasFile('attachment_file_termin_proyek')) {
                $file = $request->file('attachment_file_termin_proyek');

                // Pastikan hanya ada satu file yang diunggah
                if (is_array($file)) {
                    throw new \Exception("Hanya satu file yang diperbolehkan untuk attachment_file_termin_proyek");
                }

                $fileAttachment = $file->store(Project::ATTACHMENT_FILE_TERMIN_PROYEK, 'public');
            }

            // Pastikan `type_termin_proyek` bukan array
            $typeTermin = $request->input('type_termin_proyek');
            if (is_array($typeTermin)) {
                throw new \Exception("Field type_termin_proyek harus berupa string atau integer, bukan array.");
            }

            // Konversi jika perlu
            $typeTermin = (string) $typeTermin;

            $termin = ProjectTermin::create([
                'project_id' => $project->id,
                'harga_termin' => $request->harga_termin_proyek,
                'deskripsi_termin' => $request->deskripsi_termin_proyek,
                'type_termin' => $typeTermin,
                'tanggal_payment' => $request->payment_date_termin_proyek,
                'file_attachment_pembayaran' => $fileAttachment ?? '', // Pastikan tidak menyimpan array
            ]);

            // Ambil termin terbaru setelah penyimpanan
            $latestTermin = $project->projectTermins()->first();

            if ($latestTermin) {
                $project->update([
                    'file_pembayaran_termin' => $latestTermin->file_attachment_pembayaran,
                    'deskripsi_termin_proyek' => $latestTermin->deskripsi_termin,
                    'type_termin_proyek' => $latestTermin->type_termin,
                    'harga_termin_proyek' => $latestTermin->harga_termin,
                    'payment_date_termin_proyek' => $latestTermin->tanggal_payment,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Termin pembayaran berhasil ditambahkan!',
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'ERROR',
                'message' => $th->getMessage()
            ], 500);
        }
    } */
   
    public function paymentTermin(PaymentTerminRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $project = Project::findOrFail($id);

            // Periksa apakah ada file yang diunggah
            $fileAttachment = null;
            if ($request->hasFile('attachment_file_termin_proyek')) {
                $file = $request->file('attachment_file_termin_proyek');

                // Pastikan hanya ada satu file yang diunggah
                if (is_array($file)) {
                    throw new \Exception("Hanya satu file yang diperbolehkan untuk attachment_file_termin_proyek");
                }

                $fileAttachment = $file->store(Project::ATTACHMENT_FILE_TERMIN_PROYEK, 'public');
            }

            // Pastikan `type_termin_proyek` bukan array dan konversi ke string
            $typeTermin = $request->input('type_termin_proyek');
            if (is_array($typeTermin)) {
                throw new \Exception("Field type_termin_proyek harus berupa string atau integer, bukan array.");
            }
            $typeTermin = (string) $typeTermin;

            // Simpan data termin baru
            $termin = ProjectTermin::create([
                'project_id' => $project->id,
                'harga_termin' => (float) $request->harga_termin_proyek, // Pastikan harga dalam format angka
                'deskripsi_termin' => $request->deskripsi_termin_proyek,
                'type_termin' => $typeTermin,
                'tanggal_payment' => $request->payment_date_termin_proyek,
                'file_attachment_pembayaran' => is_string($fileAttachment) ? $fileAttachment : null, // Pastikan string
            ]);

            // **Ambil termin terbaru berdasarkan `created_at` (untuk menangani banyak pembayaran dalam satu hari)**
            $latestTermin = $project->projectTermins()
                ->orderBy('tanggal_payment', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            // **Hitung total harga_termin dari semua termin terkait proyek ini**
            $totalHargaTermin = $project->projectTermins()->sum('harga_termin');

            if ($latestTermin) {
                $typeTerminData = is_array($latestTermin->type_termin) ? $latestTermin->type_termin : json_decode($latestTermin->type_termin, true);

                if (!is_array($typeTerminData)) {
                    $typeTerminData = [
                        "id" => (string) ($latestTermin->type_termin ?? ""),
                        "name" => ($latestTermin->type_termin == Project::TYPE_TERMIN_PROYEK_LUNAS) ? "Lunas" : "Belum Lunas",
                    ];
                }

                $project->update([
                    'file_pembayaran_termin' => is_string($latestTermin->file_attachment_pembayaran) ? $latestTermin->file_attachment_pembayaran : null,
                    'deskripsi_termin_proyek' => $latestTermin->deskripsi_termin,
                    'type_termin_proyek' => json_encode($typeTerminData, JSON_UNESCAPED_UNICODE), // Pastikan disimpan sebagai JSON
                    'harga_termin_proyek' => (float) $totalHargaTermin, // Pastikan total harga adalah angka
                    'payment_date_termin_proyek' => $latestTermin->tanggal_payment,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Termin pembayaran berhasil ditambahkan!',
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'ERROR',
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function updateTermin(UpdatePaymentTerminRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $project = Project::with(['projectTermins'])->findOrFail($id);

            if (!$project) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Project not found!',
                ], 404);
            }

            // Variabel untuk menyimpan data termin terakhir yang diupdate
            $lastUpdatedTerminData = null;

            // Loop untuk memperbarui setiap termin dalam request
            foreach ($request->riwayat_termin as $terminData) {
                $termin = $project->projectTermins->where('id', $terminData['id'])->first();

                if (!$termin) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => "Termin with ID {$terminData['id']} not found!",
                    ], 404);
                }

                // **Cek dan Update File Attachment**
                $fileAttachmentPath = $termin->file_attachment_pembayaran;

                if ($request->hasFile("riwayat_termin.{$terminData['id']}.attachment_file_termin_proyek")) {
                    $file = $request->file("riwayat_termin.{$terminData['id']}.attachment_file_termin_proyek");

                    if ($file->isValid()) {
                        // Hapus file lama jika ada
                        if ($fileAttachmentPath && Storage::disk('public')->exists($fileAttachmentPath)) {
                            Storage::disk('public')->delete($fileAttachmentPath);
                        }

                        // Simpan file baru
                        $fileAttachmentPath = $file->store(Project::ATTACHMENT_FILE_TERMIN_PROYEK, 'public');
                    } else {
                        return response()->json([
                            'status' => 'ERROR',
                            'message' => 'File upload failed',
                        ], 400);
                    }
                }

                // **Update Data Termin**
                $termin->update([
                    'harga_termin' => (float) $terminData['harga_termin_proyek'],
                    'deskripsi_termin' => $terminData['deskripsi_termin_proyek'],
                    'type_termin' => (string) $terminData['type_termin_proyek'],
                    'tanggal_payment' => $terminData['payment_date_termin_proyek'],
                    'file_attachment_pembayaran' => $fileAttachmentPath, // Simpan string path file
                ]);

                // Simpan data termin terakhir yang diupdate
                $lastUpdatedTerminData = $terminData;
            }

            // **Update Deskripsi & Type Termin di Project**
            if ($lastUpdatedTerminData) {
                $project->update([
                    'deskripsi_termin_proyek' => $lastUpdatedTerminData['deskripsi_termin_proyek'],
                    'type_termin_proyek' => json_encode([
                        "id" => (string) $lastUpdatedTerminData['type_termin_proyek'],
                        "name" => $lastUpdatedTerminData['type_termin_proyek'] == Project::TYPE_TERMIN_PROYEK_LUNAS ? "Lunas" : "Belum Lunas",
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // **Hitung ulang total harga_termin**
            $totalHargaTermin = $project->projectTermins()->sum('harga_termin');
            $project->update([
                'harga_termin_proyek' => (float) $totalHargaTermin,
            ]);

            // **Ambil termin terbaru berdasarkan `tanggal_payment` & `created_at`**
            $latestTermin = $project->projectTermins()
                ->orderBy('tanggal_payment', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestTermin) {
                $project->update([
                    'file_pembayaran_termin' => is_string($latestTermin->file_attachment_pembayaran) ? $latestTermin->file_attachment_pembayaran : null,
                    'payment_date_termin_proyek' => $latestTermin->tanggal_payment,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Termin updated successfully!',
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'ERROR',
                'message' => $th->getMessage(),
            ], 500);
        }
    }



    public function deleteTermin(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            // Validasi input JSON
            if (!$request->has('riwayat_termin') || !is_array($request->riwayat_termin)) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid JSON format. "riwayat_termin" must be an array of IDs.',
                ], 400);
            }

            // Ambil ID termin yang akan dihapus
            $terminIdsToDelete = $request->riwayat_termin;

            // Cari termin yang sesuai dengan ID yang diberikan
            $terminsToDelete = ProjectTermin::where('project_id', $id)
                ->whereIn('id', $terminIdsToDelete)
                ->get();

            if ($terminsToDelete->isEmpty()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'No valid termin IDs found for deletion!',
                ], 404);
            }

            // Hanya hapus termin yang belum lunas
            $terminsToDelete = $terminsToDelete->filter(function ($termin) {
                return $termin->type_termin != Project::TYPE_TERMIN_PROYEK_LUNAS;
            });

            if ($terminsToDelete->isEmpty()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'All selected termin(s) are marked as "Lunas" and cannot be deleted.',
                ], 400);
            }

            // Loop untuk menghapus setiap termin
            foreach ($terminsToDelete as $termin) {
                // Hapus file attachment jika ada
                if (!empty($termin->file_attachment_pembayaran)) {
                    if (Storage::disk('public')->exists($termin->file_attachment_pembayaran)) {
                        Storage::disk('public')->delete($termin->file_attachment_pembayaran);
                    }
                }

                // Hapus termin dari database
                $termin->delete();
            }

            // Hitung ulang total harga termin
            $totalHargaTermin = ProjectTermin::where('project_id', $id)->sum('harga_termin');

            // Jika tidak ada termin yang tersisa
            if ($totalHargaTermin == 0) {
                Project::where('id', $id)->update([
                    'deskripsi_termin_proyek' => null,
                    'type_termin_proyek' => json_encode([
                        "id" => null,
                        "name" => null,
                    ], JSON_UNESCAPED_UNICODE),
                    'harga_termin_proyek' => 0,
                    'file_pembayaran_termin' => null,
                    'payment_date_termin_proyek' => null,
                ]);
            } else {
                // Jika masih ada termin, ambil termin terakhir untuk update deskripsi dan type
                $latestTermin = ProjectTermin::where('project_id', $id)
                    ->orderBy('tanggal_payment', 'desc')
                    ->first();

                // Pastikan `type_termin_proyek` tidak menyebabkan array-to-string conversion
                $typeTerminFormatted = is_array($latestTermin->type_termin) ? $latestTermin->type_termin['id'] : (string) $latestTermin->type_termin;

                Project::where('id', $id)->update([
                    'deskripsi_termin_proyek' => $latestTermin->deskripsi_termin,
                    'type_termin_proyek' => json_encode([
                        "id" => (string) $typeTerminFormatted,
                        "name" => ($typeTerminFormatted == Project::TYPE_TERMIN_PROYEK_LUNAS) ? "Lunas" : "Belum Lunas",
                    ], JSON_UNESCAPED_UNICODE),
                    'harga_termin_proyek' => (float) $totalHargaTermin,
                    'file_pembayaran_termin' => $latestTermin->file_attachment_pembayaran ?? null,
                    'payment_date_termin_proyek' => $latestTermin->tanggal_payment,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Selected termin(s) deleted successfully!',
                'remaining_total_termin' => $totalHargaTermin,
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'ERROR',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

 

    public function UpdatePenggunaMuatan(UpdatePengunaMuatanRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            // Validasi role pengguna login sebagai "Marketing"
            $currentUser = auth()->user();
            if ($currentUser->role_id !== 3) {
                throw new \Exception("Hanya pengguna dengan role 'Marketing' yang dapat mengupdate proyek.");
            }

            // Temukan proyek berdasarkan ID
            $project = Project::findOrFail($id);

            // Ambil data produk dan user dari request dan pastikan menjadi array
            // $produkIds = $request->input('produk_id');  // Sudah pasti menjadi array setelah prepareForValidation
            $userIds = $request->input('user_id');      // Sudah pasti menjadi array setelah prepareForValidation
            
            // Periksa apakah file dilampirkan sebelum menyimpannya
            if ($request->hasFile('attachment_file_spb')) {
                // Simpan file attachment dan dapatkan pathnya
                $path = $request->file('attachment_file_spb')->store(Project::ATTACHMENT_FILE_SPB);
                // Simpan path file ke kolom 'spb_file' (bukan 'file')
                $project->spb_file = $path;
            }

            // Hapus duplikat produk_id dan user_id jika ada
            // $produkIds = array_unique($produkIds);  // Pastikan produk_id hanya ada satu ID per produk
            $userIds = array_unique($userIds); 

            // Debug: Cek isi produk_id dan user_id
            // Log::info("Produk ID: " . json_encode($produkIds));
            Log::info("User ID: " . json_encode($userIds));

            // Menyinkronkan data produk dan user pada tabel pivot
            $project->tenagaKerja()->sync($userIds);  // Sync user_id
            // $project->product()->sync($produkIds);    // Sync produk_id

            // Update status langkah proyek setelah perubahan
            $project->updateStepStatus();

            DB::commit();

            return MessageActeeve::success("Project {$project->name} has been updated successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Error during update project: " . $th->getMessage());
            return MessageActeeve::error($th->getMessage());
        }
    }

   /*  public function update(UpdateRequest $request, $id)
    {
        DB::beginTransaction(); // Mulai transaksi manual
    
        try {
            // Temukan proyek berdasarkan ID yang diberikan
            $project = Project::find($id);
    
            if (!$project) {
                return MessageActeeve::notFound('Project data not found!');
            }

    
            // Temukan perusahaan berdasarkan client_id yang dikirimkan di request
            $company = Company::find($request->client_id);
            if (!$company) {
                throw new \Exception("Client data not found!");
            }
    
            // Gabungkan data client_id ke dalam request
            $request->merge([
                'company_id' => $company->id,
            ]);

            $currentStatus = $project->request_status_owner;

             // Ambil tahun dari tanggal baru dan tanggal lama
            $newYear = date('y', strtotime($request->date));  // Tahun dari tanggal baru
            $currentYear = date('y', strtotime($project->date)); // Tahun dari tanggal lama

            // Jika tahun berubah, update ID proyek
            if ($newYear != $currentYear) {
                // Generate new ID for the new year
                $newId = 'PRO-' . $newYear . '-' . Project::generateSequenceNumber($newYear); // Static call
                $project->id = $newId; // Update ID proyek dengan ID baru
            }

            if ($currentStatus == Project::REJECTED) {
                $project->request_status_owner = Project::PENDING;
            }

            // Logika perubahan status otomatis
            if ($project->request_status_owner == Project::ACTIVE) {
                $project->request_status_owner = Project::PENDING; // Set status ke pending
            }

            // Simpan perubahan status
            $project->save();
    
            // Jika ada file baru (attachment_file), hapus file lama dan simpan yang baru
            if ($request->hasFile('attachment_file')) {
                if ($project->file) {
                    Storage::delete($project->file); // Hapus file lama jika ada
                }
                $request->merge([
                    'file' => $request->file('attachment_file')->store(Project::ATTACHMENT_FILE),
                ]);
            }

             // Simpan file attachment_file
             $filePath = $project->file; // Gunakan path lama jika file tidak di-upload
             if ($request->hasFile('attachment_file')) {
                 // Hapus file lama jika ada
                 if ($filePath && Storage::disk('public')->exists($filePath)) {
                     Storage::disk('public')->delete($filePath);
                 }
                 // Simpan file baru
                 $filePath = $request->file('attachment_file')->store(Project::ATTACHMENT_FILE, 'public');
             }
 
             // Simpan file attachment_file_spb
             $spbFilePath = $project->spb_file; // Gunakan path lama jika file tidak di-upload
             if ($request->hasFile('attachment_file_spb')) {
                 // Hapus file lama jika ada
                 if ($spbFilePath && Storage::disk('public')->exists($spbFilePath)) {
                     Storage::disk('public')->delete($spbFilePath);
                 }
                 // Simpan file baru
                 $spbFilePath = $request->file('attachment_file_spb')->store(Project::ATTACHMENT_FILE_SPB, 'public');
             }
    
             $project->type_projects = $request->type_projects;

             // Update proyek dengan data baru
             $project->update($request->except(['produk_id', 'user_id'])); // Update proyek tanpa produk_id dan user_id
    
             // Ambil data produk_id dan user_id dari request, pastikan keduanya berupa array
             $produkIds = $request->input('produk_id', []); // Default ke array kosong jika tidak ada produk_id
             $userIds = $request->input('user_id', []); // Default ke array kosong jika tidak ada user_id
     
             // Hapus duplikat produk_id dan user_id jika ada
             $produkIds = array_unique($produkIds);
             $userIds = array_unique($userIds);
     
             // Sinkronkan data produk dan user pada tabel pivot
             $project->product()->sync($produkIds); // Menyinkronkan data produk
             $project->tenagaKerja()->sync($userIds); // Menyinkronkan data user
    
            // Commit transaksi
            DB::commit();
    
            return MessageActeeve::success("Project {$project->name} has been updated successfully.");
        } catch (\Throwable $th) {
            // Rollback jika terjadi error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    } */

    public function update(UpdateRequest $request, $id)
    {
        DB::beginTransaction(); // Mulai transaksi manual

        try {
            // Temukan proyek berdasarkan ID yang diberikan
            $project = Project::find($id);

            if (!$project) {
                return MessageActeeve::notFound('Project data not found!');
            }

            // Temukan perusahaan berdasarkan client_id yang dikirimkan di request
            $company = Company::find($request->client_id);
            if (!$company) {
                throw new \Exception("Client data not found!");
            }

            // Gabungkan data client_id ke dalam request
            $request->merge([
                'company_id' => $company->id,
            ]);

            $currentStatus = $project->request_status_owner;

            // Ambil tahun dari tanggal baru dan tanggal lama
            $newYear = date('y', strtotime($request->date));  // Tahun dari tanggal baru
            $currentYear = date('y', strtotime($project->date)); // Tahun dari tanggal lama

            if ($newYear != $currentYear) {
                // Generate ID baru berdasarkan tahun baru
                $newId = 'PRO-' . $newYear . '-' . Project::generateSequenceNumber($newYear);

                // Tambahkan ID baru ke tabel projects (simpan sementara)
                Project::create([
                    'id' => $newId,
                    'name' => $project->name,
                    'billing' => $project->billing,
                    'cost_estimate' => $project->cost_estimate,
                    'margin' => $project->margin,
                    'percent' => $project->percent,
                    'date' => $request->date,
                    'company_id' => $project->company_id,
                    'user_id' => $project->user_id,
                    'request_status_owner' => $project->request_status_owner,
                    'type_projects' => $project->type_projects,
                    'no_dokumen_project' => $project->no_dokumen_project,
                ]);

                // Update foreign key di tabel project_user_produk
                DB::table('project_user_produk')
                    ->where('project_id', $id)
                    ->update(['project_id' => $newId]);

                // Update foreign key di tabel spb_projects
                DB::table('spb_projects')
                    ->where('project_id', $id)
                    ->update(['project_id' => $newId]);

                // Update foreign key di tabel man_powers
                DB::table('man_powers')
                    ->where('project_id', $id)
                    ->update(['project_id' => $newId]);

                // Hapus ID lama dari tabel projects
                $project->delete();

                // Update variabel proyek ke ID baru
                $project = Project::find($newId);
            }

            if ($currentStatus == Project::REJECTED) {
                $project->request_status_owner = Project::PENDING;
            }

            // Logika perubahan status otomatis
            if ($project->request_status_owner == Project::ACTIVE) {
                $project->request_status_owner = Project::PENDING; // Set status ke pending
            }

            // Simpan perubahan status
            $project->save();

            // Jika ada file baru (attachment_file), hapus file lama dan simpan yang baru
            if ($request->hasFile('attachment_file')) {
                if ($project->file) {
                    Storage::delete($project->file); // Hapus file lama jika ada
                }
                $project->file = $request->file('attachment_file')->store(Project::ATTACHMENT_FILE, 'public');
            }

            // Jika ada file baru (attachment_file_spb), hapus file lama dan simpan yang baru
            if ($request->hasFile('attachment_file_spb')) {
                if ($project->spb_file) {
                    Storage::delete($project->spb_file); // Hapus file lama jika ada
                }
                $project->spb_file = $request->file('attachment_file_spb')->store(Project::ATTACHMENT_FILE_SPB, 'public');
            }

            // Update proyek dengan data baru
            $project->update($request->except(['produk_id', 'user_id']));

            // Ambil data produk_id dan user_id dari request
            $produkIds = $request->input('produk_id', []);
            $userIds = $request->input('user_id', []);

            // Sinkronkan data produk dan user pada tabel pivot
            $project->product()->sync(array_unique($produkIds)); // Sinkronkan data produk
            $project->tenagaKerja()->sync(array_unique($userIds)); // Sinkronkan data user

            // Commit transaksi
            DB::commit();

            return MessageActeeve::success("Project {$project->name} has been updated successfully.");
        } catch (\Throwable $th) {
            // Rollback jika terjadi error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }
    
    public function UpdateLengkap($id)
    {
        DB::beginTransaction();
    
        try {
            // Temukan proyek berdasarkan ID
            $project = Project::findOrFail($id);
    
            // Pastikan data proyek sudah lengkap untuk menuju status PRATINJAU
            if (
                $project->company_id &&
                $project->name &&
                $project->billing &&
                $project->cost_estimate &&
                $project->margin &&
                $project->percent &&
                $project->file &&
                $project->date &&
                $project->tenagaKerja->isNotEmpty()  // Pastikan tenaga kerja sudah terisi
                // $project->product->isNotEmpty()  // Pastikan produk sudah terisi
            ) {
                // Update status proyek menjadi PRATINJAU
                $project->update([
                    "status_step_project" => Project::PRATINJAU
                ]);
    
                DB::commit();
                return MessageActeeve::success("Project '{$project->name}' has been updated to PRATINJAU status.");
            } else {
                throw new \Exception("Project data is not complete for PRATINJAU status.");
            }
    
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function show($id)
    {
        // Ambil project berdasarkan ID
        $project = Project::find($id);

        // Cek apakah proyek ditemukan
        if (!$project) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Ambil role user yang sedang login
        $user = auth()->user();
        $role = $user->role_id;
        $totalUnapprovedSpb = 0;

        // Hitung jumlah SPB yang belum disetujui berdasarkan role yang relevan
        switch ($role) {
            case Role::GUDANG:
                $totalUnapprovedSpb = $project->spbProjects()
                    ->whereHas('category', function ($q) {
                        $q->where('spbproject_category_id', '!=', SpbProject_Category::FLASH_CASH);
                    })
                    ->whereNull('know_kepalagudang') // Belum disetujui oleh GUDANG
                    ->count();
                break;

            case Role::SUPERVISOR:
                $totalUnapprovedSpb = $project->spbProjects()
                    ->whereHas('category', function ($q) {
                        $q->where('spbproject_category_id', '!=', SpbProject_Category::FLASH_CASH);
                    })
                    ->whereNull('know_supervisor') // Belum disetujui oleh SUPERVISOR
                    ->count();
                break;

            case Role::OWNER:
                $totalUnapprovedSpb = $project->spbProjects()
                    ->whereHas('category', function ($q) {
                        $q->where('spbproject_category_id', '!=', SpbProject_Category::FLASH_CASH);
                    })
                    ->whereNull('request_owner') // Belum disetujui oleh OWNER
                    ->count();
                break;

            default:
                // Jika role tidak dikenali, tidak ada data SPB yang dihitung
                break;
        }

        // Siapkan file attachment jika ada
        $file_attachment = null;
        if ($project->file) {
            $file_attachment = [
                'name' => date('Y', strtotime($project->created_at)) . '/' . $project->id . '.' . pathinfo($project->file, PATHINFO_EXTENSION),
                'link' => asset("storage/$project->file")
            ];
        }

        // Siapkan data proyek untuk dikembalikan
        $data = [
            'id' => $project->id,
            'no_dokumen_project' => $project->no_dokumen_project,
            'client' => [
                'id' => optional($project->company)->id,
                'name' => optional($project->company)->name,
                'contact_type' => optional($project->company->contactType)->name,
            ],
                'marketing' => $project->tenagaKerja()
                    ->whereHas('role', function ($query) {
                        $query->where('role_name', 'Marketing');
                    })
                    ->first() // Mengambil hanya satu data
                    ?->loadMissing(['salary', 'divisi']) // Memastikan salary dan divisi dimuat
                    ? [
                        'id' => $project->tenagaKerja()
                            ->whereHas('role', function ($query) {
                                $query->where('role_name', 'Marketing');
                            })
                            ->first()?->id ?? null,
                        'name' => $project->tenagaKerja()
                            ->whereHas('role', function ($query) {
                                $query->where('role_name', 'Marketing');
                            })
                            ->first()?->name ?? null,
                        'daily_salary' => $project->tenagaKerja()
                            ->whereHas('role', function ($query) {
                                $query->where('role_name', 'Marketing');
                            })
                            ->first()?->salary->daily_salary ?? 0,
                        'hourly_salary' => $project->tenagaKerja()
                            ->whereHas('role', function ($query) {
                                $query->where('role_name', 'Marketing');
                            })
                            ->first()?->salary->hourly_salary ?? 0,
                        'hourly_overtime_salary' => $project->tenagaKerja()
                            ->whereHas('role', function ($query) {
                                $query->where('role_name', 'Marketing');
                            })
                            ->first()?->salary->hourly_overtime_salary ?? 0,
                        'divisi' => [
                            'id' => $project->tenagaKerja()
                                ->whereHas('role', function ($query) {
                                    $query->where('role_name', 'Marketing');
                                })
                                ->first()?->divisi->id ?? null,
                            'name' => $project->tenagaKerja()
                                ->whereHas('role', function ($query) {
                                    $query->where('role_name', 'Marketing');
                                })
                                ->first()?->divisi->name ?? null,
                        ],
                    ]
                    : null,
            'tukang' => $project->tenagaKerja() 
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'daily_salary' => $user->salary ? $user->salary->daily_salary : 0,
                        'hourly_salary' => $user->salary ? $user->salary->hourly_salary : 0,
                        'hourly_overtime_salary' => $user->salary ? $user->salary->hourly_overtime_salary : 0,
                        'divisi' => [
                            'id' => optional($user->divisi)->id,
                            'name' => optional($user->divisi)->name,
                        ],
                    ];
                }),
                'total_spb_unapproved_for_role' => $totalUnapprovedSpb,
              'spb_projects' => $project->spbProjects->map(function ($spbProject) {
                // Memeriksa kategori SPB, jika kategori BORONGAN maka ambil nilai harga_total_pembayaran_borongan_spb
                $spbCategory = $spbProject->spbproject_category_id;
                $isBorongan = $spbCategory == \App\Models\SpbProject_Category::BORONGAN;

                return [
                    'doc_no_spb' => $spbProject->doc_no_spb,
                    'doc_type_spb' => $spbProject->doc_type_spb,
                    'unit_kerja' => $spbProject->unit_kerja,
                    'tanggal_dibuat_spb' => $spbProject->tanggal_dibuat_spb,
                    'tanggal_berahir_spb' => $spbProject->tanggal_berahir_spb,
                    
                    // Menambahkan informasi kategori Borongan
                    'kategori_spb' => \App\Models\SpbProject_Category::getCategoryName($spbProject->spbproject_category_id),
                    
                    // Menampilkan produk yang terkait
                    'produk' => $spbProject->productCompanySpbprojects->map(function ($product) use ($spbProject) {
                        return [
                            'produk_id' => $product->produk_id,
                            'produk_nama' => $product->product->nama ?? 'Unknown',
                            'harga_product' => $product->product ? $product->product->harga_product : 'Unknown',
                            'vendor_id' => $product->company->id ?? 'Unknown',
                            'vendor_name' => $product->company->name ?? 'Unknown',
                            'subtotal_produk' => $product->subtotal_produk,
                        ];
                    }),
                    'total_keseluruhanproduk' => $spbProject->total_produk,

                    // Menambahkan kondisi untuk biaya borongan
                    'spb_borongan_cost' => $isBorongan ? $spbProject->harga_total_pembayaran_borongan_spb : null, // Menampilkan harga borongan jika kategori borongan
                ];
            }),
            'summary_salary_manpower' => [
                'tukang_harian' => $this->tukangHarianSalary($project->manPowers()),
                'tukang_borongan' => $this->tukangBoronganSalary($project->manPowers()),
                'total' => $this->tukangHarianSalary($project->manPowers()) + $this->tukangBoronganSalary($project->manPowers()),
            ],
            /* 'file_attachment_spb' => [
                    'name' => $project->spb_file ? 'SPB-PROJECT-' . date('Y', strtotime($project->created_at)) . '/' . $project->id . '.' . pathinfo($project->spb_file, PATHINFO_EXTENSION) : null,
                    'link' => $project->spb_file ? asset("storage/$project->spb_file") : null,
                ], */
            'date' => $project->date,
            'name' => $project->name,
            'billing' => $project->billing,
            'cost_estimate' => $project->cost_estimate,
            'margin' => $project->margin,
            'percent' => round($project->percent, 2),
            'file_attachment' => $file_attachment,
            'cost_progress_project' => $this->costProgress($project),
            'harga_type_project' => $project->harga_type_project ?? 0,
            // 'status_step_project' => $this->getStepStatus($project->status_step_project),
            'request_status_owner' => $this->getRequestStatus($project->request_status_owner),
            'status_bonus_project' => $this->getRequestStatusBonus($project->status_bonus_project),
            'type_projects' => $this->getDataTypeProject($project->type_projects),
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ];

        // Jika ada data user yang membuat proyek, tambahkan informasi tersebut
        if ($project->user) {
            $data['created_by'] = [
                "id" => $project->user->id,
                "name" => $project->user->name,
                "created_at" => Carbon::parse($project->created_at)->timezone('Asia/Jakarta')->toDateTimeString(),
            ];
        }

        if ($project->user) {
            $data['updated_by'] = [
                "id" => $project->user->id,
                "name" => $project->user->name,
                "updated_at" => Carbon::parse($project->updated_at)->timezone('Asia/Jakarta')->toDateTimeString(),
            ];
        }

        // Kembalikan data dalam format yang sudah ditentukan
        return MessageActeeve::render($data);
    }

    protected function costProgress($project)
    {
        $status = Project::STATUS_OPEN;
        $totalSpbCost = 0;
        $totalManPowerCost = 0;
        $totalSpbBoronganCost = 0;

        // Ambil SPB projects berdasarkan kondisi kategori
        $spbProjects = $project->spbProjects()->get(); // Ambil semua SPB Projects
        
        foreach ($spbProjects as $spbProject) {
            // Jika kategori adalah Borongan, tampilkan meskipun belum di tab 'paid'
            if ($spbProject->spbproject_category_id == SpbProject_Category::BORONGAN) {
                $totalSpbBoronganCost += $spbProject->harga_total_pembayaran_borongan_spb ?? 0;
            } else {
                // Jika kategori bukan Borongan, hanya ambil yang sudah di tab 'paid'
                if ($spbProject->tab_spb == SpbProject::TAB_PAID) {
                    $totalSpbCost += $spbProject->getTotalProdukAttribute();
                }
            }
        }

        // Hitung total salary dari ManPower terkait proyek
        $manPowers = $project->manPowers()->get();
        foreach ($manPowers as $manPower) {
            $totalManPowerCost += $manPower->current_salary + $manPower->current_overtime_salary;
        }

        // Total biaya aktual (real cost)
        $totalCost = $totalSpbCost + $totalManPowerCost + $totalSpbBoronganCost;

        // Percent Itu didapat dari cost estimate project dibagi dengan total cost * 100
        if ($project->cost_estimate > 0) {
            $costEstimate = round(($totalCost / $project->cost_estimate) * 100, 2);
        } else {
            $costEstimate = 0;
        }

        // Tentukan status berdasarkan progres biaya
        if ($costEstimate > 90) {
            $status = Project::STATUS_NEED_TO_CHECK;
        }

        if ($costEstimate == 100) {
            $status = Project::STATUS_CLOSED;
        }

        // Update status proyek di database
        $project->update(['status_cost_progres' => $status]);

        // Kembalikan data progres biaya
        return [
            'status_cost_progres' => $status,
            'percent' => $costEstimate . '%',
            'real_cost' => $totalCost,
            'spb_produk_cost' => $totalSpbCost,
            'spb_borongan_cost' => $totalSpbBoronganCost, 
            'man_power_cost' => $totalManPowerCost,
        ];
    }

    protected function tukangHarianSalary($query) {
        return (int) $query->selectRaw("SUM(current_salary + current_overtime_salary) as total")->where("work_type", true)->first()->total;
    }

    protected function tukangBoronganSalary($query) {
        return (int) $query->selectRaw("SUM(current_salary + current_overtime_salary) as total")->where("work_type", false)->first()->total;
    }
    
    private function getVendorsWithProducts(SpbProject $spbProject)
    {
        // Ambil data produk yang terkait dengan spb_project_id tertentu
        $produkRelated = DB::table('product_company_spbproject')
                            ->where('spb_project_id', $spbProject->doc_no_spb)
                            ->get();

        // Ambil produk berdasarkan vendor_id dan relasikan dengan produk yang terkait
        return is_iterable($spbProject->vendors)
            ? $spbProject->vendors->sortBy('id')
                ->groupBy('id')  // Mengelompokkan vendor berdasarkan id
                ->map(function ($vendors) use ($spbProject, $produkRelated) {
                    // Ambil vendor pertama dalam kelompok
                    $vendor = $vendors->first();

                    // Filter produk yang sesuai dengan company_id vendor
                    $produkData = $produkRelated->where('company_id', $vendor->id)
                        ->map(function ($produk) {
                            // Ambil detail produk berdasarkan produk_id
                            $product = DB::table('products')->find($produk->produk_id);
                            return [
                                'produk_id' => $product->id,
                                'produk_data' => [
                                    'nama' => $product->nama,
                                    'id_kategori' => $product->id_kategori,
                                    'deskripsi' => $product->deskripsi,
                                    'harga' => $product->harga,
                                    'stok' => $product->stok,
                                    'type_pembelian' => $product->type_pembelian
                                ]
                            ];
                        });

                    // Menghindari duplikasi produk dalam vendor
                    return [
                        "vendor_id" => $vendor->id,
                        "produk" => $this->removeDuplicatesByProductId($produkData->toArray())
                    ];
                })
                ->values()  // Mengubah array menjadi numerik tanpa key angka
            : [];
    }

    /**
     * Fungsi untuk menghapus duplikasi produk berdasarkan produk_id
     */
    private function removeDuplicatesByProductId(array $produkData)
    {
        $seen = [];
        $result = [];

        foreach ($produkData as $produk) {
            if (!in_array($produk['produk_id'], $seen)) {
                $seen[] = $produk['produk_id'];
                $result[] = $produk;
            }
        }

        return $result;
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
            "name" => $steps[$step] ?? "Unknown", // Tetap tampilkan "Unknown" jika step tidak dikenal
        ];
    }

    protected function getDataTypeProject($status) {
        $statuses = [

            Project::HIK => "HIK PROJECT",
            Project::DWI => "DWI PROJECT",
         ];

        return [
            "id" => $status,
            "name" => $statuses[$status] ?? "Unknown",
        ];
    }

    protected function getRequestStatusBonus($status) {
        $statuses = [

            Project::BELUM_DIKASIH_BONUS => "Belum Dikasih Bonus",
            Project::SUDAH_DIKASIH_BONUS => "Sudah Dikasih Bonus",
         ];

        return [
            "id" => $status,
            "name" => $statuses[$status] ?? "Unknown",
        ];
    }

    protected function getRequestStatus($status)
    {
        $statuses = [
            Project::PENDING => "Pending",
            Project::ACTIVE => "Active",
            Project::REJECTED => "Rejected",
            Project::CLOSED => "Closed",
        ];

        return [
            "id" => $status,
            "name" => $statuses[$status] ?? "Unknown",
        ];
    }

    public function bonus($id) {
        DB::beginTransaction();

        // Pastikan user yang login memiliki role OWNER
        if (!auth()->user()->hasRole(Role::OWNER)) {
            return response()->json([
                'message' => 'Access denied! Only owners can add bonus projects.'
            ], 403);
        }

        $project = Project::find($id);
        if (!$project) {
            return MessageActeeve::notFound('data not found!');
        }

        // Validasi bahwa status owner adalah ACTIVE
        if ($project->request_status_owner != Project::CLOSED) {
            return response()->json([
                'message' => 'Project cannot be closed because the owner status is Closed.'
            ], 400);
        }

        try {
            $project->update([
                "status_bonus_project" => Project::SUDAH_DIKASIH_BONUS
            ]);

            DB::commit();
            return MessageActeeve::success("project $project->name has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function closed($id) {
        DB::beginTransaction();

        // Pastikan user yang login memiliki role OWNER
        if (!auth()->user()->hasRole(Role::OWNER)) {
            return response()->json([
                'message' => 'Access denied! Only owners can closed projects.'
            ], 403);
        }

        $project = Project::find($id);
        if (!$project) {
            return MessageActeeve::notFound('data not found!');
        }

        // Validasi bahwa status owner adalah ACTIVE
        if ($project->request_status_owner != Project::ACTIVE) {
            return response()->json([
                'message' => 'Project cannot be closed because the owner status is not ACTIVE.'
            ], 400);
        }

        try {
            $project->update([
                "request_status_owner" => Project::CLOSED
            ]);

            DB::commit();
            return MessageActeeve::success("project $project->name has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    } 

    public function accept($id)
    {
        DB::beginTransaction();

        // Pastikan user yang login memiliki role OWNER
        if (!auth()->user()->hasRole(Role::OWNER)) {
            return response()->json([
                'message' => 'Access denied! Only owners can accept projects.'
            ], 403);
        }

        $project = Project::find($id);
        if (!$project) {
            return MessageActeeve::notFound('data not found!');
        }

        try {
            $project->update([
                "request_status_owner" => Project::ACTIVE
            ]);

            DB::commit();
            return MessageActeeve::success("project $project->name has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function reject($id)
    {
        DB::beginTransaction();

        $project = Project::find($id);
        if (!$project) {
            return MessageActeeve::notFound('data not found!');
        }

        try {
            $project->update([
                "request_status_owner" => Project::REJECTED
            ]);

            DB::commit();
            return MessageActeeve::success("project $project->name has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
    
        try {
            // Temukan proyek berdasarkan ID, atau akan gagal jika tidak ditemukan
            $project = Project::findOrFail($id);
    
            // Hapus hubungan many-to-many terlebih dahulu jika ada
            $project->product()->detach();  // Menanggalkan hubungan dengan produk
            $project->tenagaKerja()->detach();  // Menanggalkan hubungan dengan user
    
            // Hapus file terkait proyek (jika ada)
            if ($project->file) {
                Storage::delete($project->file);
            }
    
            // Hapus proyek dari database
            $project->delete();
    
            // Commit transaksi
            DB::commit();
    
            return MessageActeeve::success("Project $project->name has been deleted successfully.");
        } catch (\Throwable $th) {
            // Rollback jika terjadi error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }
    
    


}
