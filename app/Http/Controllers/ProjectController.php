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
use App\Facades\MessageActeeve;
use App\Models\ProjectUserProduk;
use App\Models\SpbProject_Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductCompanySpbProject;
use App\Http\Requests\Project\StoreRequest;
use App\Http\Requests\Project\UpdateRequest;
use App\Http\Resources\Project\ProjectCollection;
use App\Http\Requests\Project\UpdatePengunaMuatanRequest;

class ProjectController extends Controller
{

    public function projectall(Request $request)
    {
        $query = Project::query();

        // Filter berdasarkan status proyek
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);

            $query->whereBetween('created_at', $date);
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

         // Terapkan filter berdasarkan peran pengguna (role MARKETING)
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
        $query->select(
            DB::raw('SUM(billing) as billing'),
            DB::raw('SUM(cost_estimate) as cost_estimate'),
            DB::raw('SUM(margin) as margin')
        );

        /* if (auth()->user()->role_id == Role::MARKETING) {
            $query->where(function ($query) {
                $query->whereHas('purchases', function ($query) {
                    $query->where('user_id', auth()->user()->id);
                });
            });
        } */

        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%');
                $query->orWhere('name', 'like', '%' . $request->search . '%');
                $query->orWhereHas('company', function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%');
                });
            });
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

        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);

            $query->whereBetween('created_at', $date);
        }
        $projectStats = $query->first();

        if (!$projectStats->billing || !$projectStats->margin || !$projectStats->cost_estimate) {
            return [
                "billing" => 0,
                "cost_estimate" => 0,
                "margin" => 0,
                "percent" => '0%',
            ];
        }

        $percent = ($projectStats->margin / $projectStats->billing) * 100;

        // Membatasi persentase agar tidak lebih dari 100%
        if ($percent > 100) {
            $percent = 100;
        }

        // Membulatkan persentase hingga dua angka desimal dan menambah tanda persen
        $percent = round($percent, 2) . "%";

        // Mengembalikan hasil perhitungan
        return [
            "billing" => $projectStats->billing,
            "cost_estimate" => $projectStats->cost_estimate,
            "margin" => $projectStats->margin,
            "percent" => $percent,
        ];

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

            // Set harga_type_project to 0 if it's not provided
            $project->harga_type_project = $request->has('harga_type_project') ? $request->harga_type_project : 0;

            // Periksa apakah file dilampirkan sebelum menyimpannya
            if ($request->hasFile('attachment_file')) {
                $project->file = $request->file('attachment_file')->store(Project::ATTACHMENT_FILE);
            } else {
                $project->file = null; 
            }

            if ($request->hasFile('attachment_file_spb')) {
                $project->spb_file = $request->file('attachment_file_spb')->store(Project::ATTACHMENT_FILE_SPB);
            } else {
                $project->spb_file = null; // Tidak ada file, set null
            }

            // Simpan proyek ke database, ID proyek akan ter-set setelah ini
            $project->save();  // Pastikan proyek disimpan terlebih dahulu untuk mendapatkan ID

             // Ambil produk_id dan user_id dari request
            $produkIds = array_filter($request->input('produk_id', []));  // Hapus nilai kosong
            $userIds = array_filter($request->input('user_id', []));  // Hapus nilai kosong

            // Sinkronisasi produk_id di pivot table hanya jika ada produk_id yang valid
            if (!empty($produkIds)) {
                $project->product()->syncWithoutDetaching($produkIds); // Sinkronkan produk ke pivot table
            }

            // Sinkronisasi user_id di pivot table hanya jika ada user_id yang valid
            if (!empty($userIds)) {
                $project->tenagaKerja()->syncWithoutDetaching($userIds); // Sinkronkan user ke pivot table
            }

            // Commit transaksi
            DB::commit(); // Commit transaksi
            return MessageActeeve::success("Project created successfully.");
        } catch (\Exception $e) {
            // Rollback jika terjadi error
            DB::rollBack();
            Log::error("Error creating project: " . $e->getMessage());
            return MessageActeeve::error("Error creating project.");
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

    public function update(UpdateRequest $request, $id)
    {
        DB::beginTransaction(); // Mulai transaksi manual
    
        try {
            // Temukan proyek berdasarkan ID yang diberikan
            $project = Project::find($id);
    
            if (!$project) {
                return MessageActeeve::notFound('Project data not found!');
            }
    
            // Validasi role pengguna login sebagai "Marketing"
            /* $currentUser = auth()->user();
            if ($currentUser->role_id !== 3) {
                throw new \Exception("Hanya pengguna dengan role 'Marketing' yang dapat mengupdate proyek.");
            } */
    
            // Temukan perusahaan berdasarkan client_id yang dikirimkan di request
            $company = Company::find($request->client_id);
            if (!$company) {
                throw new \Exception("Client data not found!");
            }
    
            // Gabungkan data client_id ke dalam request
            $request->merge([
                'company_id' => $company->id,
            ]);

             // Ambil tahun dari tanggal baru dan tanggal lama
            $newYear = date('y', strtotime($request->date));  // Tahun dari tanggal baru
            $currentYear = date('y', strtotime($project->date)); // Tahun dari tanggal lama

            // Jika tahun berubah, update ID proyek
            if ($newYear != $currentYear) {
                // Generate new ID for the new year
                $newId = 'PRO-' . $newYear . '-' . Project::generateSequenceNumber($newYear); // Static call
                $project->id = $newId; // Update ID proyek dengan ID baru
            }
    
            // Jika ada file baru (attachment_file), hapus file lama dan simpan yang baru
            if ($request->hasFile('attachment_file')) {
                if ($project->file) {
                    Storage::delete($project->file); // Hapus file lama jika ada
                }
                $request->merge([
                    'file' => $request->file('attachment_file')->store(Project::ATTACHMENT_FILE),
                ]);
            }

            // Jika ada file baru (attachment_file_spb), hapus file lama dan simpan yang baru
            if ($request->hasFile('attachment_file_spb')) {
                if ($project->spb_file) {
                    Storage::delete($project->spb_file); // Hapus file lama jika ada
                }
                $request->merge([
                    'spb_file' => $request->file('attachment_file_spb')->store(Project::ATTACHMENT_FILE_SPB),
                ]);
            }

            // Pastikan harga_type_project default ke 0 jika tidak disediakan
            if ($request->has('harga_type_project')) {
                $request->merge([
                    'harga_type_project' => $request->input('harga_type_project') ?? 0,
                ]);
            }
    
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
            'client' => [
                'id' => optional($project->company)->id,
                'name' => optional($project->company)->name,
                'contact_type' => optional($project->company->contactType)->name,
            ],
            /* 'produk' => optional($project->product)->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'nama' => $product->nama,
                        'deskripsi' => $product->deskripsi,
                        'stok' => $product->stok,
                        'harga' => $product->harga,
                        'type_pembelian' => $product->type_pembelian,
                        'kode_produk' => $product->kode_produk,
                    ];
                }), */
            'tukang' => $project->tenagaKerja() // Gunakan tenagaKerja() untuk mendapatkan user dengan role_id = 7
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'divisi' => [
                        'id' => optional($user->divisi)->id,
                        'name' => optional($user->divisi)->name,
                    ],
                ];
            }),
             // Menampilkan seluruh produk yang terkait tanpa memfilter berdasarkan status PAID
             'spb_projects' => $project->spbProjects->map(function ($spbProject) {
                    return [
                        'doc_no_spb' => $spbProject->doc_no_spb,
                        'doc_type_spb' => $spbProject->doc_type_spb,
                        'unit_kerja' => $spbProject->unit_kerja,
                        'tanggal_dibuat_spb' => $spbProject->tanggal_dibuat_spb,
                        'tanggal_berahir_spb' => $spbProject->tanggal_berahir_spb,
                        // Menampilkan seluruh produk yang terkait, tanpa filter status PAID
                        'produk' => $spbProject->productCompanySpbprojects->map(function ($product) use ($spbProject) {
                            return [
                                'produk_id' => $product->produk_id,
                                'produk_nama' => $product->product->nama ?? 'Unknown', // Hanya nama produk
                                'vendor_id' => $product->company->id ?? 'Unknown',
                                'vendor_name' => $product->company->name ?? 'Unknown',
                                'total_per_produk' => $product->total_produk, // Total per produk
                            ];
                        }),
                        'total_keseluruhanproduk' => $spbProject->total_produk,
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
            'cost_progress_paid_spb' => $this->costProgress($project),
            'harga_type_project' => $project->harga_type_project ?? 0,
            // 'status_step_project' => $this->getStepStatus($project->status_step_project),
            'request_status_owner' => $this->getRequestStatus($project->request_status_owner),
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

        // Kembalikan data dalam format yang sudah ditentukan
        return MessageActeeve::render($data);
    }

    protected function costProgress($project)
    {
        $status = Project::STATUS_OPEN;
        $total = 0;

        // Ambil semua SPB Project dengan status 'PAID'
        $spbProjects = $project->spbProjects()->where('tab_spb', SpbProject::TAB_PAID)->get();

        // Hitung total cost dari semua SPB Projects yang statusnya 'PAID'
        foreach ($spbProjects as $spbProject) {
            // Ambil total dari masing-masing SpbProject
            $total += $spbProject->getTotalProdukAttribute(); 
        }

        // Cek jika cost_estimate lebih besar dari 0 sebelum melakukan pembagian
        if ($project->cost_estimate > 0) {
            $costEstimate = round(($total / $project->cost_estimate) * 100, 2);
        } else {
            // Default value jika cost_estimate adalah 0
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
            'real_cost' => $total
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


    public function accept($id)
    {
        DB::beginTransaction();

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
