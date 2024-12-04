<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Role;
use App\Models\Product;
use App\Models\Project;
use App\Models\SpbProject;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\LogsSPBProject;
use App\Facades\MessageActeeve;
use App\Models\SpbProject_Status;
use Illuminate\Support\Facades\DB;
use App\Models\SpbProject_Category;
use App\Http\Requests\SpbProject\CreateRequest;
use App\Http\Requests\SpbProject\UpdateRequest;
use App\Http\Resources\SPBproject\SPBprojectCollection;

class SPBController extends Controller
{
    public function index(Request $request)
    {
        $query = SpbProject::query();
        
        // Filter berdasarkan role pengguna
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->where('user_id', auth()->user()->id);
        }

        $query->with(['user', 'products', 'project', 'status']);

        // Filter pencarian berdasarkan SPB project
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('doc_no_spb', 'like', '%' . $request->search . '%')
                      ->orWhere('doc_type_spb', 'like', '%' . $request->search . '%')
                      ->orWhereHas('company', function ($query) use ($request) {
                          $query->where('name', 'like', '%' . $request->search . '%');
                      });
            });
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

    public function store(CreateRequest $request)
    {
        DB::beginTransaction();
    
        try {
            // Mendapatkan kategori SPB yang dipilih
            $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
    
            // Pastikan kategori ditemukan
            if (!$spbCategory) {
                throw new \Exception("Kategori SPB tidak ditemukan.");
            }
    
            // Mendapatkan doc_no_spb terakhir berdasarkan kategori SPB
            $maxDocNo = SpbProject::where('spbproject_category_id', $request->spbproject_category_id)
                                    ->orderByDesc('doc_no_spb')
                                    ->first();
    
            // Ambil bagian numerik terakhir dari doc_no_spb
            $maxNumericPart = $maxDocNo ? (int) substr($maxDocNo->doc_no_spb, strpos($maxDocNo->doc_no_spb, '-') + 1) : 0;
    
            // Menambahkan data untuk doc_no_spb dan doc_type_spb
            $request->merge([
                'doc_no_spb' => $this->generateDocNo($maxNumericPart, $spbCategory),  // Generate doc_no_spb
                'doc_type_spb' => strtoupper($spbCategory->name),  // Menggunakan nama kategori SPB untuk doc_type
                'spbproject_status_id' => SpbProject_Status::AWAITING,
                'user_id' => auth()->user()->id,  // Menetapkan user_id berdasarkan pengguna yang sedang login
            ]);
    
            // Membuat SPB baru dengan data yang telah dimodifikasi
            $spbProject = SpbProject::create($request->only([
                'doc_no_spb', 'doc_type_spb', 'spbproject_category_id', 'spbproject_status_id', 'user_id', 
                'tanggal_berahir_spb', 'tanggal_dibuat_spb', 'unit_kerja', 'nama_toko'
            ]));
    
            // Mengecek apakah produk_id ada atau tidak
            if (empty($request->produk_id)) {
                // Jika produk_id kosong, buat produk baru untuk setiap data produk yang ada
                foreach ($request->produk as $productData) {
                    $product = Product::create([
                        'nama' => $productData['nama'], 
                        'id_kategori' => $productData['id_kategori'],  
                        'deskripsi' => $productData['deskripsi'],  
                        'stok' => $productData['stok'],  
                        'type_pembelian' => $productData['type_pembelian'],  
                        'harga' => $productData['harga'],  
                    ]);
    
                    // Pastikan produk berhasil dibuat dan ID-nya ada
                    if ($product && $product->exists) {
                        // Menyimpan produk yang baru dibuat ke tabel pivot
                        $spbProject->products()->syncWithoutDetaching([$product->id]);
                    } else {
                        // Jika gagal menyimpan produk, lemparkan exception
                        throw new \Exception("Produk baru gagal disimpan.");
                    }
                }
            } else {
                // Jika produk_id ada, cari produk yang sesuai
                $products = Product::whereIn('id', $request->produk_id)->get();
    
                // Menyimpan produk yang terkait ke tabel pivot
                foreach ($products as $product) {
                    $spbProject->products()->syncWithoutDetaching([$product->id]);
                }
            }
    
            // Commit transaksi jika semua berhasil
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => "doc no $spbProject->doc_no_spb has been created and products have been associated",
            ]);
        } catch (\Throwable $th) {
            // Rollback transaksi jika ada error
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
    

    protected function generateDocNo($maxNumericPart, $spbCategory)
    {
        // Pastikan kategori SPB memiliki format yang benar
        if (!$spbCategory || !isset($spbCategory->short)) {
            throw new \Exception("Kategori SPB tidak valid atau tidak ditemukan.");
        }

        // Jika tidak ada doc_no_spb sebelumnya, mulai dari nomor 001
        if ($maxNumericPart === 0) {
            return "{$spbCategory->short}-001";
        }

        // Tambahkan 1 pada bagian numerik dan format menjadi 3 digit
        $nextNumber = sprintf('%03d', $maxNumericPart + 1);
        return "{$spbCategory->short}-$nextNumber";
    }

    public function update(UpdateRequest $request, $docNoSpb)
    {
        DB::beginTransaction();
    
        try {
            // Mendapatkan kategori SPB yang dipilih
            $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
    
            // Pastikan kategori ditemukan
            if (!$spbCategory) {
                throw new \Exception("Kategori SPB tidak ditemukan.");
            }
    
            // Mendapatkan SpbProject yang akan diperbarui
            $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
    
            // Pastikan SpbProject ditemukan
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found.',
                ], 404);
            }
    
            // Mengupdate data SPB Project sesuai dengan input yang ada pada request
            $spbProject->update($request->only([
                'spbproject_category_id',
                'project_id',
                'tanggal_berahir_spb',
                'tanggal_dibuat_spb',
                'unit_kerja',
                'nama_toko'
            ]));
    
            // Mengecek apakah produk_id ada atau tidak
            if (!empty($request->produk_id)) {
                // Jika produk_id ada, cari produk yang sesuai
                $products = Product::whereIn('id', $request->produk_id)->get();
    
                // Menyinkronkan produk yang terkait ke tabel pivot product_spb_project
                // Hanya produk yang ada pada request yang akan disinkronkan, yang tidak ada akan dihapus
                $spbProject->products()->sync($products->pluck('id')->toArray());
    
                // Update data produk yang ada sesuai dengan input di request
                foreach ($products as $product) {
                    $productData = collect($request->produk)->firstWhere('id', $product->id);
                    if ($productData) {
                        $product->update([
                            'nama' => $productData['nama'] ?? $product->nama,
                            'id_kategori' => $productData['id_kategori'] ?? $product->id_kategori,
                            'deskripsi' => $productData['deskripsi'] ?? $product->deskripsi,
                            'stok' => $productData['stok'] ?? $product->stok,
                            'type_pembelian' => $productData['type_pembelian'] ?? $product->type_pembelian,
                            'harga' => $productData['harga'] ?? $product->harga,
                        ]);
                    }
                }
            } else {
                // Jika produk_id kosong, hanya update produk yang sudah ada
                foreach ($spbProject->products as $product) {
                    $productData = collect($request->produk)->firstWhere('id', $product->id);
                    if ($productData) {
                        $product->update([
                            'nama' => $productData['nama'] ?? $product->nama,
                            'id_kategori' => $productData['id_kategori'] ?? $product->id_kategori,
                            'deskripsi' => $productData['deskripsi'] ?? $product->deskripsi,
                            'stok' => $productData['stok'] ?? $product->stok,
                            'type_pembelian' => $productData['type_pembelian'] ?? $product->type_pembelian,
                            'harga' => $productData['harga'] ?? $product->harga,
                        ]);
                    }
                }
            }
    
            // Commit transaksi jika semua berhasil
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => "doc no $spbProject->doc_no_spb has been updated",
            ]);
        } catch (\Throwable $th) {
            // Rollback transaksi jika ada error
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
    


    public function addspbtoproject(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            // Temukan proyek berdasarkan ID
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found.',
                ], 404);
            }

            // Validasi array doc_no_spb yang diterima dalam request
            $docNos = $request->input('doc_no_spb', []);

            if (empty($docNos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No SPB doc_no_spb provided.',
                ], 400);
            }

            // Loop setiap doc_no_spb dan tambahkan ke proyek yang dipilih
            foreach ($docNos as $docNo) {
                $SpbProject = SpbProject::where('doc_no_spb', $docNo)->first();

                if (!$SpbProject) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "SPB Project with doc_no_spb {$docNo} not found.",
                    ], 404);
                }

                // Tambahkan relasi many-to-many ke proyek
                $project->spbProjects()->attach($SpbProject->doc_no_spb, ['project_id' => $project->id]);

                // Buat atau update log status
                $existingLog = $SpbProject->logs()->where('tab', SpbProject::TAB_VERIFIED)
                                                    ->where('name', auth()->user()->name)
                                                    ->first();

                if ($existingLog) {
                    // Update log yang sudah ada
                    $existingLog->update([
                        'message' => 'SPB Project has been accepted.',
                        'updated_at' => now(),
                    ]);
                } else {
                    // Buat log baru jika belum ada
                    LogsSPBProject::create([
                        'spb_project_id' => $SpbProject->doc_no_spb,
                        'tab' => SpbProject::TAB_VERIFIED,
                        'name' => auth()->user()->name,
                        'message' => 'SPB Project has been accepted.',
                    ]);
                }
            }

            // Commit transaksi
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Projects have been successfully assigned to the project.",
            ], 200);

        } catch (\Throwable $th) {
            // Rollback transaksi jika terjadi error
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy($docNoSpb)
    {
        DB::beginTransaction();
    
        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();  // Ganti with where() yang benar
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }
    
        try {
            // Hapus data log yang terkait dengan SpbProject
            $spbProject->logs()->delete();  // Hapus semua log terkait SpbProject
    
            // Hapus SpbProject itu sendiri
            $spbProject->delete();
    
            DB::commit();
    
            return MessageActeeve::success("SpbProject $docNoSpb has been deleted");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function show($id)
    {
        // Ambil project berdasarkan ID
        $spbProject = SpbProject::find($id);

        // Cek apakah proyek ditemukan
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Siapkan data proyek untuk dikembalikan
        $data = [
            "doc_no_spb" => $spbProject->doc_no_spb,
            "doc_type_spb" => $spbProject->doc_type_spb,
            "status" => $this->getStatus($spbProject),
            'project' => $spbProject->project->isNotEmpty() ? [
                'id' => $spbProject->project->first()->id,
                'nama' => $spbProject->project->first()->name,
            ] : [
                'id' => 'N/A',
                'nama' => 'No Project Available'
            ],  
            'produk' => $spbProject->products ? $spbProject->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'nama' => $product->nama,
                    'deskripsi' => $product->deskripsi,
                    'stok' => $product->stok,
                    'harga' => $product->harga,
                    'type_pembelian' => $product->type_pembelian,
                    'kode_produk' => $product->kode_produk,
                ];
            }) : [],
            "unit_kerja" => $spbProject->unit_kerja,
            "tanggal_berahir_spb" => $spbProject->tanggal_berahir_spb,
            "tanggal_dibuat_spb" => $spbProject->tanggal_dibuat_spb,
            "nama_toko" => $spbProject->nama_toko,
            "know_marketing" => $this->getUserRole($spbProject->know_marketing),
            "know_kepalagudang" => $this->getUserRole($spbProject->know_kepalagudang),
            "request_owner" => $this->getUserRole($spbProject->request_owner),
            "created_at" => $spbProject->created_at->format('Y-m-d'),
            "updated_at" => $spbProject->updated_at->format('Y-m-d'),
             // Menambahkan logs ke dalam data proyek
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

        // Add created_by if user is associated
        if ($spbProject->user) {
            $data['created_by'] = [
                "id" => $spbProject->user->id,
                "name" => $spbProject->user->name,
            ];
        }

        // Kembalikan data dalam format yang sudah ditentukan
        return MessageActeeve::render($data);
    }

    protected function getUserRole($userId)
    {
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                return [
                    'user_name' => $user->name,
                    'role_name' => $user->role ? $user->role->role_name : 'Unknown', // Jika ada role
                ];
            }
        }
        return null;
    }

    protected function getStatus($spbProject)
    {
        $data = [];

        // Nama tab berdasarkan konstanta yang ada di model SpbProject
        $tabNames = [
            SpbProject::TAB_SUBMIT => 'Submit',
            SpbProject::TAB_VERIFIED => 'Verified',
            SpbProject::TAB_PAYMENT_REQUEST => 'Payment Request',
            SpbProject::TAB_PAID => 'Paid',
        ];

        // Ambil nama tab berdasarkan nilai tab
        $tabName = $tabNames[$spbProject->tab] ?? 'Unknown';  // Default jika tidak ditemukan

        // Pengecekan status yang berkaitan dengan TAB_SUBMIT
        if ($spbProject->tab == SpbProject::TAB_SUBMIT) {
            // Pastikan status ada, jika tidak set default ke AWAITING
            if ($spbProject->status) {
                $data = [
                    "id" => $spbProject->status->id ?? SpbProject_Status::AWAITING,
                    "name" => $spbProject->status->name ?? SpbProject_Status::TEXT_AWAITING,
                    "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];

                // Jika status adalah REJECTED, tambahkan note
                if ($spbProject->status->id == SpbProject_Status::REJECTED) {
                    $data["reject_note"] = $spbProject->reject_note ?? 'No reject note';
                }
            } else {
                // Jika status tidak ada, set ke AWAITING
                $data = [
                    "id" => SpbProject_Status::AWAITING,
                    "name" => SpbProject_Status::TEXT_AWAITING,
                    "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAID
        elseif ($spbProject->tab == SpbProject::TAB_PAID) {
            $data = [
                "id" => $spbProject->status->id ?? null,
                "name" => $spbProject->status ? $spbProject->status->name : 'Unknown',
                "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];
        }

        // Pengecekan untuk TAB_VERIFIED
        elseif ($spbProject->tab == SpbProject::TAB_VERIFIED) {
            $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            $data = [
                "id" => SpbProject_Status::OPEN,
                "name" => SpbProject_Status::TEXT_OPEN,
                "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];

            if ($nowDate->gt($dueDate)) {
                $data = [
                    "id" => SpbProject_Status::OVERDUE,
                    "name" => SpbProject_Status::TEXT_OVERDUE,
                    "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }

            if ($nowDate->toDateString() == $spbProject->tanggal_berahir_spb) {
                $data = [
                    "id" => SpbProject_Status::DUEDATE,
                    "name" => SpbProject_Status::TEXT_DUEDATE,
                    "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAYMENT_REQUEST
        elseif ($spbProject->tab == SpbProject::TAB_PAYMENT_REQUEST) {
            $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            $data = [
                "id" => SpbProject_Status::OPEN,
                "name" => SpbProject_Status::TEXT_OPEN,
                "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];

            if ($nowDate->gt($dueDate)) {
                $data = [
                    "id" => SpbProject_Status::OVERDUE,
                    "name" => SpbProject_Status::TEXT_OVERDUE,
                    "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }

            if ($nowDate->toDateString() == $spbProject->tanggal_berahir_spb) {
                $data = [
                    "id" => SpbProject_Status::DUEDATE,
                    "name" => SpbProject_Status::TEXT_DUEDATE,
                    "tab" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Kembalikan data status yang sesuai dengan tab
        return $data;
    }


    public function accept(Request $request, $id)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan id (doc_no_spb)
        $SpbProject = SpbProject::find($id);
        if (!$SpbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Menambahkan data status dan tab untuk update
        $request->merge([
            'spbproject_status_id' => SpbProject_Status::VERIFIED,
            'tab' => SpbProject::TAB_VERIFIED,
        ]);

        // Update status dan tab pada SpbProject
        $SpbProject->update([
            'spbproject_status_id' => SpbProject_Status::VERIFIED,
            'tab' => SpbProject::TAB_VERIFIED,
        ]);

        try {
            // Mengecek apakah user yang sama sudah pernah memberikan status VERIFIED pada proyek ini
            $existingLog = $SpbProject->logs()->where('tab', SpbProject::TAB_VERIFIED)
                                            ->where('name', auth()->user()->name)
                                            ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been accepted', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                ]);
            } else {
                // Jika log belum ada, simpan log baru
                LogsSPBProject::create([
                    'spb_project_id' => $SpbProject->doc_no_spb,
                    'tab' => SpbProject::TAB_VERIFIED,
                    'name' => auth()->user()->name,
                    'message' => 'SPB Project has been accepted',
                ]);
            }

            // Commit transaction
            DB::commit();

            // Kembali dengan pesan sukses
            return MessageActeeve::success("SpbProject {$id} has been accepted");

        } catch (\Throwable $th) {
            // Rollback transaction jika ada error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function undo($docNoSpb)
    {
        DB::beginTransaction();
        
        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Pastikan status saat ini adalah VERIFIED, yang dapat di-undo ke AWAITING
        if ($spbProject->tab != SpbProject::TAB_VERIFIED) {
            return MessageActeeve::warning("Cannot undo, the tab is not VERIFIED");
        }

        try {
            // Update status SPB Project ke AWAITING dan tab kembali ke SUBMIT
            $spbProject->update([
                'spbproject_status_id' => SpbProject_Status::AWAITING, // Status diubah kembali ke AWAITING
                'tab' => SpbProject::TAB_SUBMIT, // Tab dikembalikan ke SUBMIT
            ]);

            // Mengecek log sebelumnya
            $existingLog = $spbProject->logs()->where('tab', SpbProject::TAB_SUBMIT)
                                            ->where('name', auth()->user()->name)
                                            ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been undone and reverted to awaiting', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                ]);
            } else {
                // Menyimpan log undo
                LogsSPBProject::create([
                    'spb_project_id' => $spbProject->doc_no_spb,
                    'tab' => SpbProject::TAB_SUBMIT, // Tab sesuai dengan yang baru
                    'name' => auth()->user()->name,
                    'message' => 'SPB Project has been undone and reverted to awaiting', // Pesan untuk undo
                ]);
            }

            // Commit transaction
            DB::commit();

            return MessageActeeve::success("SPB Project $docNoSpb has been undone successfully");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function reject($docNoSpb, Request $request)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $SpbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$SpbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Pastikan reject_note ada dalam request
        if (!$request->has('note') || empty($request->note)) {
            return MessageActeeve::error('Reject note is required!');
        }

        try {
            // Update status dan tab di SpbProject
            $SpbProject->update([
                'spbproject_status_id' => SpbProject_Status::REJECTED, // Status diubah menjadi REJECTED
                'reject_note' => $request->note,
                'tab' => SpbProject::TAB_SUBMIT, // Tab tetap di SUBMIT
            ]);

            // Mengecek apakah log dengan tab SUBMIT sudah ada sebelumnya untuk user yang sama
            $existingLog = $SpbProject->logs()->where('tab', SpbProject::TAB_SUBMIT)
                                            ->where('name', auth()->user()->name)
                                            ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been rejected', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                    'reject_note' => $request->note, // Simpan note_reject dari request
                ]);
            } else {
                // Menyimpan log untuk reject jika belum ada
                $SpbProject->logs()->create([
                    'tab' => SpbProject::TAB_SUBMIT, // Tab tetap di SUBMIT
                    'name' => auth()->user()->name, // Nama pengguna yang melakukan aksi
                    'message' => 'SPB Project has been rejected', // Pesan untuk aksi reject
                    'reject_note' => $request->note, // Simpan note_reject dari request
                ]);
            }

            DB::commit();

            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNoSpb has been rejected");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function activate(UpdateRequest $request, $docNo)
    {
        DB::beginTransaction();

        try {
            // Cari SpbProject berdasarkan doc_no_spb
            $SpbProject = SpbProject::where('doc_no_spb', $docNo)->first();
            if (!$SpbProject) {
                return MessageActeeve::notFound('Data not found!');
            }

            // Pastikan bahwa SPB Project status sebelumnya adalah REJECTED
            if ($SpbProject->spbproject_status_id != SpbProject_Status::REJECTED) {
                return MessageActeeve::error('SPB Project is not in rejected status!');
            }

            // Update status dan tab di SpbProject
            $SpbProject->update([
                'spbproject_status_id' => SpbProject_Status::AWAITING, // Status diubah kembali menjadi AWAITING
                'tab' => SpbProject::TAB_SUBMIT, // Tab tetap di SUBMIT
                'reject_note' => null, // Menghapus reject note yang sebelumnya
            ]);

            // Menyinkronkan produk yang terkait (jika ada perubahan produk)
            $SpbProject->products()->sync($request->produk_id);

            // Mengecek apakah log dengan tab SUBMIT sudah ada sebelumnya untuk user yang sama
            $existingLog = $SpbProject->logs()->where('tab', SpbProject::TAB_SUBMIT)
                                                ->where('name', auth()->user()->name)
                                                ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been activated', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                    'reject_note' => null, // Tidak ada reject_note saat activate
                ]);
            } else {
                // Menyimpan log untuk aksi activate jika belum ada
                $SpbProject->logs()->create([
                    'tab' => SpbProject::TAB_SUBMIT, // Tab tetap di SUBMIT
                    'name' => auth()->user()->name, // Nama pengguna yang melakukan aksi
                    'message' => 'SPB Project has been activated and status set to awaiting', // Pesan untuk aksi activate
                    'reject_note' => null, // Tidak ada reject_note saat activate
                ]);
            }

            DB::commit();

            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNo has been activated and is now in awaiting status.");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function request($docNoSpb)
    {
        DB::beginTransaction();
    
        // Cari SpbProject berdasarkan doc_no_spb
        $SpbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$SpbProject) {
            return MessageActeeve::notFound('Data not found!');
        }
    
        try {
            // Menyimpan atau memperbarui log untuk aksi request dengan tab yang sesuai
            $existingLog = $SpbProject->logs()->where('tab', SpbProject::TAB_PAYMENT_REQUEST)
                                                ->where('name', auth()->user()->name)
                                                ->first();
    
            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been requested for payment', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                ]);
            } else {
                // Menyimpan log untuk aksi request jika belum ada
                $SpbProject->logs()->create([
                    'tab' => SpbProject::TAB_PAYMENT_REQUEST, // Tab PAYMENT_REQUEST
                    'name' => auth()->user()->name, // Nama pengguna yang melakukan aksi
                    'message' => 'SPB Project has been requested for payment', // Pesan log
                ]);
            }
    
            // Memperbarui tab di SpbProject menjadi TAB_PAYMENT_REQUEST
            $SpbProject->update([
                'tab' => SpbProject::TAB_PAYMENT_REQUEST,
            ]);
    
            DB::commit();
    
            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNoSpb has been requested for payment");
    
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function payment($docNo)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNo)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Menyimpan atau memperbarui log untuk aksi pembayaran dengan tab yang sesuai
            $existingLog = $spbProject->logs()->where('tab', SpbProject::TAB_PAID)
                                            ->where('name', auth()->user()->name)
                                            ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been paid', // Update pesan log
                    'created_at' => now(), // Update timestamp jika perlu
                    'updated_at' => now(), // Pembaruan waktu log
                ]);
            } else {
                // Menyimpan log untuk aksi pembayaran jika belum ada
                $spbProject->logs()->create([
                    'tab' => SpbProject::TAB_PAID, // Tab PAID
                    'name' => auth()->user()->name, // Nama pengguna yang melakukan aksi
                    'message' => 'SPB Project has been paid', // Pesan log
                    'created_at' => now(), // Waktu pencatatan log
                    'updated_at' => now(), // Waktu pembaruan log
                ]);
            }

            // Memperbarui status SpbProject menjadi PAID dan mengubah tab menjadi TAB_PAID
            $spbProject->update([
                'spbproject_status_id' => SpbProject_Status::PAID, // Status diubah menjadi PAID
                'tab' => SpbProject::TAB_PAID,                      // Tab diubah menjadi TAB_PAID
                'updated_at' => now(),                              // Pembaruan waktu SPB Project
            ]);

            DB::commit();
            return MessageActeeve::success("SPB Project $docNo payment successfully processed");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function knowmarketing($docNoSpb)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Update kolom know_marketing untuk menandakan bahwa proyek sudah diketahui oleh marketing
            $spbProject->update([
                "know_marketing" => auth()->user()->id, // Simpan ID user marketing yang mengetahui proyek
            ]);

            DB::commit();

            // Ambil informasi pengguna yang mengetahui proyek ini
            $userRole = $this->getUserRole($spbProject->know_marketing);

            return MessageActeeve::success("SPB Project {$spbProject->doc_no_spb} is now acknowledged by marketing. Acknowledged by: {$userRole['user_name']} ({$userRole['role_name']})");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function knowmarkepalagudang($docNoSpb)
    {
        DB::beginTransaction();

        // Periksa apakah pengguna yang sedang login adalah Kepala Gudang
        if (auth()->user()->role_id != Role::GUDANG) {
            return MessageActeeve::error('Access denied. Only Kepala Gudang can perform this action.');
        }

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Update kolom know_marketing untuk menandakan bahwa proyek sudah diketahui oleh kepala gudang
            $spbProject->update([
                "know_kepalagudang" => auth()->user()->id, // Simpan ID user yang mengetahui proyek
            ]);

            DB::commit();

            // Ambil informasi pengguna yang mengetahui proyek ini
            $userRole = $this->getUserRole($spbProject->know_marketing);

            return MessageActeeve::success("SPB Project {$spbProject->doc_no_spb} is now acknowledged by Gudang.");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function menyetujuiowner($docNoSpb)
    {
        DB::beginTransaction();

        // Periksa apakah pengguna yang sedang login adalah Kepala Gudang
        if (auth()->user()->role_id != Role::OWNER) {
            return MessageActeeve::error('Access denied. Only Kepala Gudang can perform this action.');
        }

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Update kolom know_marketing untuk menandakan bahwa proyek sudah diketahui oleh kepala gudang
            $spbProject->update([
                "request_owner" => auth()->user()->id, // Simpan ID user yang mengetahui proyek
            ]);

            DB::commit();

            // Ambil informasi pengguna yang mengetahui proyek ini
            $userRole = $this->getUserRole($spbProject->know_marketing);

            return MessageActeeve::success("SPB Project {$spbProject->doc_no_spb} is now Accepted Owner.");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    
}
