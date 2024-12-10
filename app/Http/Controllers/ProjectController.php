<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\Project;
use App\Models\ContactType;
use Illuminate\Http\Request;
use App\Facades\MessageActeeve;
use App\Models\ProjectUserProduk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            $currentUser = auth()->user();
            if ($currentUser->role_id !== 3) {
                throw new \Exception("Hanya pengguna dengan role 'Marketing' yang dapat mengupdate proyek.");
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
                Storage::delete($project->file); // Hapus file lama
                $request->merge([
                    'file' => $request->file('attachment_file')->store(Project::ATTACHMENT_FILE),
                ]);
            }

            if ($request->hasFile('attachment_file_spb')) {
                Storage::delete($project->file); // Hapus file lama
                $request->merge([
                    'file' => $request->file('attachment_file_spb')->store(Project::ATTACHMENT_FILE_SPB),
                ]);
            }

            if ($request->has('harga_type_project')) {
                $request->merge([
                    'harga_type_project' => $request->input('harga_type_project') ?? 0, // Ensure it defaults to 0 if not provided
                ]);
            }
    
            // Update proyek dengan data baru
            $project->update($request->except(['produk_id', 'user_id'])); // Update proyek tanpa produk_id dan user_id
    
            // Ambil data produk_id dan user_id dari request, pastikan keduanya berupa array
            $produkIds = $request->input('produk_id');
            $userIds = $request->input('user_id');
    
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
             // Menambahkan data spbProjects yang terkait dengan project
             'spb_project' => $project->spbProjects->map(function ($spbProject) {
                return [
                    'doc_no_spb' => $spbProject->doc_no_spb,
                    'doc_type_spb' => $spbProject->doc_type_spb,
                    'vendors' => is_iterable($spbProject->vendors) ? $spbProject->vendors->map(function ($vendor) use ($spbProject) {
                        $produkData = [];

                        // Ambil produk yang sudah ada di pivot table (relasi SPB dan Vendor)
                        if (is_iterable($vendor->products)) {
                            foreach ($vendor->products as $product) {
                                // Jika produk sudah terdaftar dalam product_ids (relasi di SPB)
                                if (in_array($product->id, $spbProject->product_ids ?? [])) {
                                    $produkData[] = [
                                        'produk_id' => [$product->id],
                                        'produk_data' => []  // Kosongkan array produk_data untuk produk yang sudah ada
                                    ];
                                }
                            }
                        }

                        // Menambahkan produk baru yang belum terdaftar
                        $newProdukData = [];
                        if (is_iterable($spbProject->products)) {
                            foreach ($spbProject->products as $product) {
                                // Menambahkan produk baru jika belum ada di produkData
                                if (!in_array($product->id, array_column($produkData, 'produk_id'))) {
                                    $newProdukData[] = [
                                        'nama' => $product->nama,
                                        'id_kategori' => $product->id_kategori,
                                        'deskripsi' => $product->deskripsi,
                                        'harga' => $product->harga,
                                        'stok' => $product->stok,
                                        'type_pembelian' => $product->type_pembelian
                                        // 'ongkir' => $product->ongkir
                                    ];
                                }
                            }
                        }

                        // Menggabungkan produk yang sudah ada dan produk baru
                        return [
                            "vendor_id" => $vendor->id,
                            "produk" => array_merge($produkData, $newProdukData)
                        ];

                    }) : [],
                    'unit_kerja' => $spbProject->unit_kerja,
                    'tanggal_dibuat_spb' => $spbProject->tanggal_dibuat_spb,
                    'tanggal_berahir_spb' => $spbProject->tanggal_berahir_spb,
                    'logs' => $spbProject->logs->groupBy('name')->map(function ($logsByUser) use ($spbProject) {
                        // Ambil log terakhir berdasarkan created_at untuk setiap pengguna
                        $lastLog = $logsByUser->sortByDesc('created_at')->first();

                        // Ambil reject_note dari spbProject
                        $rejectNote = $spbProject->reject_note;  // Ambil reject_note langsung dari spbProject

                        return [
                            'tab' => $lastLog->tab, // Ambil tab dari log terakhir
                            'name' => $lastLog->name, // Ambil nama pengguna
                            'created_at' => $lastLog->created_at, // Ambil waktu terakhir log
                            'message' => $lastLog->message, // Ambil pesan dari log terakhir
                            'reject_note' => $rejectNote, // Tambahkan reject_note dari spbProject
                        ];
                    })->values()->all(),
                ];
            }),
            'file_attachment_spb' => [
                    'name' => $project->spb_file ? 'SPB-PROJECT-' . date('Y', strtotime($project->created_at)) . '/' . $project->id . '.' . pathinfo($project->spb_file, PATHINFO_EXTENSION) : null,
                    'link' => $project->spb_file ? asset("storage/$project->spb_file") : null,
                ],
            'date' => $project->date,
            'name' => $project->name,
            'billing' => $project->billing,
            'cost_estimate' => $project->cost_estimate,
            'margin' => $project->margin,
            'percent' => round($project->percent, 2),
            'file_attachment' => $file_attachment,
            'cost_progress' => $project->status_cost_progress,
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
            ];
        }

        // Kembalikan data dalam format yang sudah ditentukan
        return MessageActeeve::render($data);
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
