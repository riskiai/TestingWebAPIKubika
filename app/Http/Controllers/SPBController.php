<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Tax;
use App\Models\Role;
use App\Models\Company;
use App\Models\Product;
use App\Models\Project;
use App\Models\SpbProject;
use App\Models\ContactType;
use App\Models\DocumentSPB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\LogsSPBProject;
use App\Facades\MessageActeeve;
use App\Models\SpbProject_Status;
use Illuminate\Support\Facades\DB;
use App\Models\SpbProject_Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductCompanySpbProject;
use App\Http\Requests\SpbProject\AcceptRequest;
use App\Http\Requests\SpbProject\ActivateProdukRequest;
use App\Http\Requests\SpbProject\CreateRequest;
use App\Http\Requests\SpbProject\UpdateRequest;
use App\Http\Requests\SpbProject\PaymentRequest;
use App\Http\Requests\SpbProject\RejectProdukRequest;
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

        $query->with(['user', 'products', 'project', 'status', 'vendors']);

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

    public function counting(Request $request)
    {
        $userId = auth()->id();
        $role = auth()->user()->role_id;

        // Ambil semua data SPB (tidak melakukan filter berdasarkan doc_no_spb)
        $query = SpbProject::query();

        // Filter berdasarkan project ID jika ada
        if ($request->has('project')) {
            $query->whereHas('project', function ($query) use ($request) {
                $query->where('projects.id', $request->project);
            });
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

        if ($request->has('doc_no_spb')) {
            $query->where('doc_no_spb', 'like', '%' . $request->doc_no_spb . '%');
        }

        // Filter berdasarkan range date (tanggal_dibuat_spb)
        if ($request->has('tanggal_dibuat_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_dibuat_spb));
            $query->whereBetween('tanggal_dibuat_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }

        // Filter berdasarkan tanggal_berahir_spb
        if ($request->has('tanggal_berahir_spb')) {
            $dateRange = explode(",", str_replace(['[', ']'], '', $request->tanggal_berahir_spb));
            $query->whereBetween('tanggal_berahir_spb', [Carbon::parse($dateRange[0]), Carbon::parse($dateRange[1])]);
        }

        // Inisialisasi variabel untuk menghitung masing-masing status
        $submit = 0;
        $verified = 0;
        $over_due = 0;
        $open = 0;
        $due_date = 0;  // Pastikan variabel ini diinisialisasi dengan 0
        $payment_request = 0;
        $paid = 0;

        // Ambil semua data SPB yang sudah difilter
        $spbProjects = $query->get();

        // Mengambil jumlah total SPB yang dibeli
        $received = $spbProjects->count();

        foreach ($spbProjects as $spbProject) {
            $total = $spbProject->getTotalProdukAttribute(); // Mengambil nilai total dari setiap objek SPB
            Log::debug('SPB Total: ' . $total . ' for SPB: ' . $spbProject->doc_no_spb);

            // Tambahkan logika untuk status due_date
            $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            switch ($spbProject->tab_spb) {
                case SpbProject::TAB_VERIFIED:
                    $verified += $total;
                    if ($spbProject->tanggal_berahir_spb > now()) {
                        $open += $total;
                    } elseif ($spbProject->tanggal_berahir_spb == today()) {
                        $due_date += $total;  // Menambahkan ke due_date jika tanggal berahir_spb adalah hari ini
                    }
                    if ($spbProject->tanggal_berahir_spb < Carbon::now()) {
                        $over_due += $total;
                    }
                    break;
                case SpbProject::TAB_PAYMENT_REQUEST:
                    $payment_request += $total;
                    if ($spbProject->tanggal_berahir_spb < Carbon::now()) {
                        $over_due += $total;
                    }
                    break;
                case SpbProject::TAB_PAID:
                    $paid += $total;
                    break;
                case SpbProject::TAB_SUBMIT:
                    $submit += $total;
                    break;
            }

            // Pastikan jika status due_date dihitung pada kondisi status yang sesuai
            if ($nowDate->toDateString() == $spbProject->tanggal_berahir_spb) {
                // Jika tanggal akhir adalah hari ini, tambahkan ke due_date
                $due_date += $total;
            }
        }

        return response()->json([
            'received' => $received,
            'submit' => $submit,
            'verified' => $verified,
            'over_due' => $over_due,
            'open' => $open,
            'due_date' => $due_date,  // Pastikan due_date mengembalikan jumlah yang benar
            'payment_request' => $payment_request,
            'paid' => $paid
        ]);

    }

    public function storegakdipake(Request $request)
    {
            DB::beginTransaction();

            try {
                
                $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
                if (!$spbCategory) {
                    throw new \Exception("Kategori SPB tidak ditemukan.");
                }

               
                $project = Project::find($request->project_id);
                if (!$project) {
                    throw new \Exception("Project dengan ID {$request->project_id} tidak ditemukan.");
                }

                // Validasi bahwa tidak ada SPB dengan project_id yang sama
                /* $existingSpb = SpbProject::where('project_id', $request->project_id)->first();

                if ($existingSpb) {
                    throw new \Exception("SPB dengan Project ID {$request->project_id} sudah ada.");
                } */

                // Mendapatkan doc_no_spb terakhir berdasarkan kategori SPB
                $maxDocNo = SpbProject::where('spbproject_category_id', $request->spbproject_category_id)
                    ->orderByDesc('doc_no_spb')
                    ->first();

                // Ambil bagian numerik terakhir dari doc_no_spb
                $maxNumericPart = $maxDocNo ? (int) substr($maxDocNo->doc_no_spb, strpos($maxDocNo->doc_no_spb, '-') + 1) : 0;

                // Menambahkan data untuk doc_no_spb dan doc_type_spb
                $request->merge([
                    'doc_no_spb' => $this->generateDocNo($maxNumericPart, $spbCategory),
                    'doc_type_spb' => strtoupper($spbCategory->name),
                    'spbproject_status_id' => SpbProject_Status::AWAITING,
                    'user_id' => auth()->user()->id,
                ]);

            
            // Membuat SPB baru
            $spbProject = SpbProject::create($request->only([
                'doc_no_spb',
                'doc_type_spb',
                'spbproject_category_id',
                'spbproject_status_id',
                'user_id',
                'project_id',
                'unit_kerja',
                'nama_toko',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
            ]));

              // Data untuk produk dan vendor
            $productData = [];

            // Loop untuk menyimpan vendor dan produk
            foreach ($request->vendors as $vendorData) {
                $vendor = Company::find($vendorData['vendor_id']);
                if (!$vendor || $vendor->contact_type_id != ContactType::VENDOR) {
                    throw new \Exception("Vendor ID {$vendorData['vendor_id']} tidak valid atau bukan vendor.");
                }

                // Proses ongkir
                $ongkir = isset($vendorData['ongkir']) ? (is_array($vendorData['ongkir']) ? $vendorData['ongkir'][0] : $vendorData['ongkir']) : 0;

                // Loop untuk menyimpan produk terkait dengan vendor
                foreach ($vendorData['produk'] as $produkData) {
                    // Jika produk_id ada, masukkan produk ke pivot table
                    if (count($produkData['produk_id']) > 0) {
                        foreach ($produkData['produk_id'] as $produkId) {
                            // Simpan produk yang sudah ada ke pivot table
                            ProductCompanySpbProject::firstOrCreate([
                                'spb_project_id' => $spbProject->doc_no_spb,
                                'produk_id' => $produkId,
                                'company_id' => $vendor->id,
                                'ongkir' =>  $ongkir // Menambahkan ongkir ke pivot table
                            ]);
                        }
                    }

                    // Jika ada produk_data baru, buat produk baru dan simpan
                    if (count($produkData['produk_id']) > 0 && count($produkData['produk_data']) > 0) {
                        foreach ($produkData['produk_data'] as $newProductData) {
                            // Buat produk baru
                            $newProduct = Product::create([
                                'nama' => $newProductData['nama'],
                                'id_kategori' => $newProductData['id_kategori'] ?: null, // Jika kosong, set null
                                'deskripsi' => $newProductData['deskripsi'],
                                'harga' => $newProductData['harga'],
                                'stok' => $newProductData['stok'],
                                'type_pembelian' => $newProductData['type_pembelian']
                            ]);

                            // Simpan produk baru ke pivot table
                            $productData[] = [
                                'spb_project_id' => $spbProject->doc_no_spb,
                                'produk_id' => $newProduct->id, // ID produk yang baru dibuat
                                'company_id' => $vendor->id,
                                'ongkir' =>  $ongkir, // Menambahkan ongkir ke pivot table
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    // Jika hanya produk_data baru dan produk_id kosong, langsung simpan produk baru ke pivot table
                    if (count($produkData['produk_id']) == 0 && count($produkData['produk_data']) > 0) {
                        foreach ($produkData['produk_data'] as $newProductData) {
                            // Buat produk baru
                            $newProduct = Product::create([
                                'nama' => $newProductData['nama'],
                                'id_kategori' => $newProductData['id_kategori'] ?: null, // Jika kosong, set null
                                'deskripsi' => $newProductData['deskripsi'],
                                'harga' => $newProductData['harga'],
                                'stok' => $newProductData['stok'],
                                'type_pembelian' => $newProductData['type_pembelian']
                            ]);

                            // Simpan produk baru ke pivot table
                            $productData[] = [
                                'spb_project_id' => $spbProject->doc_no_spb,
                                'produk_id' => $newProduct->id, // Gunakan produk ID yang baru dibuat
                                'company_id' => $vendor->id,
                                'ongkir' =>  $ongkir, // Menambahkan ongkir ke pivot table
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                // Insert atau update data produk secara massal menggunakan upsert untuk vendor
                if (count($productData) > 0) {
                    ProductCompanySpbProject::upsert($productData, ['spb_project_id', 'produk_id', 'company_id'], ['updated_at']);
                }

                // Reset array untuk vendor berikutnya
                $productData = [];
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

    public function store(CreateRequest $request)
    {
        DB::beginTransaction();

        try {
            
            $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
            if (!$spbCategory) {
                throw new \Exception("Kategori SPB tidak ditemukan.");
            }

           
            $project = Project::find($request->project_id);
            if (!$project) {
                throw new \Exception("Project dengan ID {$request->project_id} tidak ditemukan.");
            }

            // Generate doc_no_spb
            $maxDocNo = SpbProject::where('spbproject_category_id', $request->spbproject_category_id)
                    ->orderByDesc('doc_no_spb')
                    ->first();

            // Ambil bagian numerik terakhir dari doc_no_spb
            $maxNumericPart = $maxDocNo ? (int) substr($maxDocNo->doc_no_spb, strpos($maxDocNo->doc_no_spb, '-') + 1) : 0;

            // Merge data untuk SPB Project
            $request->merge([
                'doc_no_spb' => $this->generateDocNo($maxNumericPart, $spbCategory),
                'doc_type_spb' => strtoupper($spbCategory->name),
                'spbproject_status_id' => SpbProject_Status::AWAITING,
                'user_id' => auth()->user()->id,
            ]);

            // Buat SPB Project baru
            $spbProject = SpbProject::create($request->only([
                'doc_no_spb',
                'doc_type_spb',
                'spbproject_category_id',
                'spbproject_status_id',
                'user_id',
                'project_id',
                'unit_kerja',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
            ]));

             // Menyimpan data produk
            foreach ($request->produk as $productGroup) {
                foreach ($productGroup['produk_data'] as $item) {
                    // Menggunakan produk_id tunggal
                    ProductCompanySpbProject::create([
                        'spb_project_id' => $spbProject->doc_no_spb,
                        'produk_id' => $item['produk_id'],  // Produk_id tunggal, bukan array
                        'company_id' => $item['vendor_id'],
                        'ongkir' => $item['ongkir'] ?? 0,
                        'harga' => $item['harga'],
                        'stok' => $item['stok'],
                        'ppn' => $item['tax_ppn'] ?? 0,
                        'date' => $item['date'],
                        'due_date' => $item['due_date'],
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB dengan doc_no {$spbProject->doc_no_spb} berhasil dibuat.",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
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
            // Validasi kategori SPB yang dipilih
            $spbCategory = SpbProject_Category::find($request->spbproject_category_id);
            if (!$spbCategory) {
                throw new \Exception("Kategori SPB tidak ditemukan.");
            }

            // Mendapatkan SpbProject yang akan diperbarui
            $spbProject = SpbProject::with('productCompanySpbprojects')->where('doc_no_spb', $docNoSpb)->first();
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found.',
                ], 404);
            }

            // Mengupdate data SPB Project sesuai dengan input pada request
            $spbProject->update($request->only([
                'doc_type_spb',
                'spbproject_category_id',
                'project_id',
                'unit_kerja',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
            ]));

              // Loop untuk memproses produk dari request
        foreach ($request->produk as $productGroup) {
            foreach ($productGroup['produk_data'] as $item) {
                $vendorId = $item['vendor_id'];
                $productId = $item['produk_id']; // produk_id adalah nilai tunggal, bukan array

                // Cek apakah produk dengan vendor_id dan produk_id sudah ada
                $existingProduct = $spbProject->productCompanySpbprojects()
                    ->where('company_id', $vendorId)
                    ->where('produk_id', $productId)
                    ->first();

                if ($existingProduct) {
                    // Jika produk sudah ada, update data produk tersebut
                    $existingProduct->update([
                        'harga' => $item['harga'],
                        'stok' => $item['stok'],
                        'ppn' => $item['tax_ppn'] ?? 0, // Menambahkan PPN
                        'ongkir' => $item['ongkir'] ?? 0,
                        'date' => $item['date'],
                        'due_date' => $item['due_date'],
                        'updated_at' => now(),  // Update waktu
                    ]);
                } else {
                    // Jika produk belum ada, tambahkan ke database
                    ProductCompanySpbProject::create([
                        'spb_project_id' => $spbProject->doc_no_spb,
                        'produk_id' => $productId, // produk_id adalah nilai tunggal
                        'company_id' => $vendorId,
                        'ongkir' => $item['ongkir'] ?? 0,
                        'harga' => $item['harga'],
                        'stok' => $item['stok'],
                        'ppn' => $item['tax_ppn'] ?? 0, // Menambahkan PPN
                        'date' => $item['date'],
                        'due_date' => $item['due_date'],
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, // Status produk baru
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Commit transaksi jika semua berhasil
        DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$spbProject->doc_no_spb} has been updated successfully.",
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

         // Determine the tab name
    $tabNames = [
        SpbProject::TAB_SUBMIT => 'Submit',
        SpbProject::TAB_VERIFIED => 'Verified',
        SpbProject::TAB_PAYMENT_REQUEST => 'Payment Request',
        SpbProject::TAB_PAID => 'Paid',
    ];

    $tabName = $tabNames[$spbProject->tab_spb] ?? 'Unknown';

    // Siapkan data proyek untuk dikembalikan
    $data = [
        "doc_no_spb" => $spbProject->doc_no_spb,
        "doc_type_spb" => $spbProject->doc_type_spb,
        "status_spb" => $this->getStatus($spbProject),
        'logs_spb' => $spbProject->logs->groupBy('name')->map(function ($logsByUser) use ($spbProject) {
            // Ambil log terakhir berdasarkan created_at untuk setiap pengguna
            $lastLog = $logsByUser->sortByDesc('created_at')->first();

            // Ambil reject_note dari spbProject
            $rejectNote = $spbProject->reject_note;

            return [
                'tab_spb' => $lastLog->tab_spb,
                'name' => $lastLog->name,
                'created_at' => $lastLog->created_at,
                'message' => $lastLog->message,
                'reject_note' => $rejectNote,
            ];
        })->values()->all(),
        "project" => $spbProject->project ? [
            'id' => $spbProject->project->id,
            'nama' => $spbProject->project->name,
        ] : [
            'id' => 'N/A',
            'nama' => 'No Project Available',
        ],
        'produk' => $spbProject->productCompanySpbprojects->map(function ($product) use ($spbProject) {
                            $dueDate = Carbon::createFromFormat("Y-m-d", $product->due_date); // Membaca due_date
                            $nowDate = Carbon::now(); // Mendapatkan tanggal sekarang
                            $status = $product->status_produk; // Status awal produk

                            // Periksa jika status produk adalah "Paid"
                            if ($status === ProductCompanySpbProject::TEXT_PAID_PRODUCT) {
                                // Jika status adalah "Paid", set status ke "Paid"
                                $notePaid = $product->note_paid_produk; // Ambil note_paid_produk jika statusnya "Paid"
                                return [
                                    'produk_data' => [
                                        'produk_id' => $product->produk_id ?? 'Unknown',
                                        'nama' => $product->product->nama ?? 'Unknown',
                                        'id_kategori' => $product->product->id_kategori ?? null,
                                        'deskripsi' => $product->product->deskripsi ?? '',
                                        'type_pembelian' => $product->product->type_pembelian ?? 'Unknown',
                                    ],
                                    'vendor' => [
                                        'id' => $product->company->id ?? 'Unknown',
                                        'name' => $product->company->name ?? 'Unknown',
                                        'bank_name' => $product->company->bank_name ?? 'Unknown',
                                        'account_name' => $product->company->account_name ?? 'Unknown',
                                    ],
                                    'status_produk' => $status, // Status produk adalah "Paid"
                                    'note_paid_produk' => $notePaid, // Catatan jika produk sudah dibayar
                                    'date' => $product->date,
                                    'due_date' => $product->due_date,
                                    'ppn' => $product->ppn_value,
                                    'ongkir' => $product->ongkir ?? 0,
                                    'harga' => $product->harga ?? 0,
                                    'stok' => $product->stok ?? 0,
                                    'subtotal_item' => $product->subtotal_produk,
                                    'pph' => [
                                        'pph_type' => $product->taxPph->name ?? 'Unknown',
                                        'pph_rate' => $product->taxPph->percent ?? 0,
                                        'pph_hasil' => $product->pph_value,
                                    ],
                                    'total_item' => $product->total_produk,
                                ];
                            }

                            // Cek jika produk sudah ditolak (Rejected), maka langsung set statusnya ke Rejected
                            if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                                $noteReject = $product->note_reject_produk; // Ambil note_reject_produk jika statusnya "Rejected"
                                return [
                                    'produk_data' => [
                                        'produk_id' => $product->produk_id ?? 'Unknown',
                                        'nama' => $product->product->nama ?? 'Unknown',
                                        'id_kategori' => $product->product->id_kategori ?? null,
                                        'deskripsi' => $product->product->deskripsi ?? '',
                                        'type_pembelian' => $product->product->type_pembelian ?? 'Unknown',
                                    ],
                                    'vendor' => [
                                        'id' => $product->company->id ?? 'Unknown',
                                        'name' => $product->company->name ?? 'Unknown',
                                        'bank_name' => $product->company->bank_name ?? 'Unknown',
                                        'account_name' => $product->company->account_name ?? 'Unknown',
                                    ],
                                    'status_produk' => $status, // Status produk adalah "Rejected"
                                    'note_reject_produk' => $noteReject, // Catatan ditolak
                                    'date' => $product->date,
                                    'due_date' => $product->due_date,
                                    'ppn' => $product->ppn_value,
                                    'ongkir' => $product->ongkir ?? 0,
                                    'harga' => $product->harga ?? 0,
                                    'stok' => $product->stok ?? 0,
                                    'subtotal_item' => $product->subtotal_produk,
                                    'pph' => [
                                        'pph_type' => $product->taxPph->name ?? 'Unknown',
                                        'pph_rate' => $product->taxPph->percent ?? 0,
                                        'pph_hasil' => $product->pph_value,
                                    ],
                                    'total_item' => $product->total_produk,
                                ];
                            }

                            // Periksa apakah status produk bukan open, overdue, atau duedate
                            if (!in_array($status, [
                                ProductCompanySpbProject::TEXT_OPEN_PRODUCT,
                                ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT,
                                ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT
                            ])) {
                                // Jika status produk bukan open, overdue, atau duedate, set status ke Awaiting
                                $status = ProductCompanySpbProject::TEXT_AWAITING_PRODUCT;
                            } else {
                                // Jika status produk valid, periksa status berdasarkan due_date dan tab_spb
                                $dueDateDayYear = $dueDate->format('d-Y'); // Format tanggal hanya hari dan tahun
                                $nowDateDayYear = $nowDate->format('d-Y'); // Tanggal sekarang (hari dan tahun)

                                // Periksa status berdasarkan due_date dan tab_spb hanya jika status belum "Awaiting"
                                if ($status !== ProductCompanySpbProject::TEXT_AWAITING_PRODUCT) {
                                    if ($spbProject->tab_spb == SpbProject::TAB_VERIFIED || $spbProject->tab_spb == SpbProject::TAB_PAYMENT_REQUEST) {
                                        if ($nowDateDayYear > $dueDateDayYear) {
                                            // Jika tanggal sekarang lebih besar dari due_date (terlambat), set status ke OVERDUE
                                            $status = ProductCompanySpbProject::TEXT_OVERDUE_PRODUCT;
                                        } elseif ($nowDateDayYear == $dueDateDayYear) {
                                            // Jika tanggal sekarang sama dengan due_date (tepat waktu), set status ke DUEDATE
                                            $status = ProductCompanySpbProject::TEXT_DUEDATE_PRODUCT;
                                        } elseif ($nowDateDayYear < $dueDateDayYear) {
                                            // Jika tanggal sekarang lebih kecil dari due_date (belum lewat), set status ke OPEN
                                            $status = ProductCompanySpbProject::TEXT_OPEN_PRODUCT;
                                        }
                                    }
                                }
                            }

                            // Menangani status "Rejected" jika tidak ditemukan sebelumnya
                            if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                                $noteReject = $product->note_reject_produk;
                            } else {
                                $noteReject = null;
                            }

                            return [
                                'produk_data' => [
                                    'produk_id' => $product->produk_id ?? 'Unknown',
                                    'nama' => $product->product->nama ?? 'Unknown',
                                    'id_kategori' => $product->product->id_kategori ?? null,
                                    'deskripsi' => $product->product->deskripsi ?? '',
                                    'type_pembelian' => $product->product->type_pembelian ?? 'Unknown',
                                ],
                                'vendor' => [
                                    'id' => $product->company->id ?? 'Unknown',
                                    'name' => $product->company->name ?? 'Unknown',
                                    'bank_name' => $product->company->bank_name ?? 'Unknown',
                                    'account_name' => $product->company->account_name ?? 'Unknown',
                                ],
                                'status_produk' => $status,
                                'note_reject_produk' => $noteReject,
                'date' => $product->date,
                'due_date' => $product->due_date,
                'ppn' => $product->ppn_value,
                'ongkir' => $product->ongkir ?? 0,
                'harga' => $product->harga ?? 0,
                "stok" => $product->stok ?? 0,
                'subtotal_item' => $product->subtotal_produk,
                'pph' => [
                    'pph_type' => $product->taxPph->name ?? 'Unknown',
                    'pph_rate' => $product->taxPph->percent ?? 0,
                    'pph_hasil' => $product->pph_value,
                ],
                'total_item' => $product->total_produk,
                ];
            }),
            "total" => $spbProject->total_produk,
            'file_attachement' => $this->getDocument($spbProject),
            "unit_kerja" => $spbProject->unit_kerja,
            "tanggal_berahir_spb" => $spbProject->tanggal_berahir_spb,
            "tanggal_dibuat_spb" => $spbProject->tanggal_dibuat_spb,
            // "nama_toko" => $spbProject->nama_toko,
            "know_marketing" => $this->getUserRole($spbProject->know_marketing),
            "know_supervisor" => $this->getUserRole($spbProject->know_supervisor),
            "know_kepalagudang" => $this->getUserRole($spbProject->know_kepalagudang),
            "request_owner" => $this->getUserRole($spbProject->request_owner),
            "created_at" => $spbProject->created_at->format('Y-m-d'),
            "updated_at" => $spbProject->updated_at->format('Y-m-d'),
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

    protected function getDocument($documents)
    {
        $data = [];

        // Pastikan menggunakan relasi yang benar, dalam hal ini 'documents_spb'
        foreach ($documents->documents as $document) {
            $data[] = [
                "id" => $document->id,
                "name" => $document->spbProject->doc_type_spb . "/$document->doc_no_spb.$document->id/" . date('Y', strtotime($document->created_at)) . "." . pathinfo($document->file_path, PATHINFO_EXTENSION),
                "link" => asset("storage/$document->file_path"),
            ];
        }

        return $data;
    }

    /* Validasi Data Vendors Dan Produk */
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

                    // Ambil nama perusahaan berdasarkan vendor_id (company_id)
                    $company = DB::table('companies')->where('id', $vendor->id)->first();
                    $companyName = $company ? $company->name : 'Unknown';
                    $companyBankName = $company ? $company->bank_name : 'Unknown';
                    $companyAccountNumber = $company ? $company->account_number : 'Unknown';

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

                    // Ambil ongkir hanya sekali per vendor (menghindari duplikasi ongkir)
                    $ongkir = $produkRelated->where('company_id', $vendor->id)->pluck('ongkir')->first();

                    // Menghindari duplikasi produk dalam vendor
                    return [
                        "vendor_id" => $vendor->id,
                        "company_name" => $companyName, 
                        "bank_toko_vendor" => $companyBankName,
                        "account_number_toko_vendor" => $companyAccountNumber, 
                        "ongkir" => $ongkir,  // Menampilkan ongkir hanya satu kali untuk vendor
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

    protected function getPpn($spbProject)
    {
        if (is_numeric($spbProject->ppn) && $spbProject->ppn > 0) {
            return ($spbProject->getSubtotal() * $spbProject->ppn) / 100;
        } else {
            return 0;
        }
    }

    protected function getPph($spbProject)
    {
        if (is_numeric($spbProject->pph)) {
            // Hitung hasil PPH berdasarkan nilai PPH dan subtotal
            $pphResult = round((($spbProject->getSubtotal()) * $spbProject->taxPph->percent) / 100);

            // Mengembalikan hasil PPH dalam format yang sesuai
            return [
                "pph_type" => $spbProject->taxPph->name,
                "pph_rate" => $spbProject->taxPph->percent,
                "pph_hasil" => $pphResult
            ];
        } else {
            return [
                "pph_type" => "", // Atau nilai default lainnya jika pph bukan numerik
                "pph_rate" => 0,
                "pph_hasil" => 0
            ];
        }
    }

    /**
     * Get the role_name of the user based on user ID.
     *
     * @param int|null $userId
     * @return array|null
     */
    protected function getUserRole($userId)
    {
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                return [
                    'user_name' => $user->name,
                    'role_name' => $user->role ? $user->role->role_name : 'Unknown',
                ];
            }
        }
        return null;
    }

    /**
     * Get the status of the SPB Project.
     *
     * @param SpbProject $spbProject
     * @return array
     */
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
        $tabName = $tabNames[$spbProject->tab_spb] ?? 'Unknown';  // Default jika tidak ditemukan

        // Pengecekan status yang berkaitan dengan TAB_SUBMIT
        if ($spbProject->tab_spb == SpbProject::TAB_SUBMIT) {
            // Pastikan status ada, jika tidak set default ke AWAITING
            if ($spbProject->status) {
                $data = [
                    "id" => $spbProject->status->id ?? SpbProject_Status::AWAITING,
                    "name" => $spbProject->status->name ?? SpbProject_Status::TEXT_AWAITING,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
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
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAID
        elseif ($spbProject->tab_spb == SpbProject::TAB_PAID) {
            $data = [
                "id" => $spbProject->status->id ?? null,
                "name" => $spbProject->status ? $spbProject->status->name : 'Unknown',
                "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];
        }

        // Pengecekan untuk TAB_VERIFIED
        elseif ($spbProject->tab_spb == SpbProject::TAB_VERIFIED) {
            $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            $data = [
                "id" => SpbProject_Status::OPEN,
                "name" => SpbProject_Status::TEXT_OPEN,
                "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];

            if ($nowDate->gt($dueDate)) {
                $data = [
                    "id" => SpbProject_Status::OVERDUE,
                    "name" => SpbProject_Status::TEXT_OVERDUE,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }

            if ($nowDate->toDateString() == $spbProject->tanggal_berahir_spb) {
                $data = [
                    "id" => SpbProject_Status::DUEDATE,
                    "name" => SpbProject_Status::TEXT_DUEDATE,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAYMENT_REQUEST
        elseif ($spbProject->tab_spb == SpbProject::TAB_PAYMENT_REQUEST) {
            $dueDate = Carbon::createFromFormat("Y-m-d", $spbProject->tanggal_berahir_spb);
            $nowDate = Carbon::now();

            $data = [
                "id" => SpbProject_Status::OPEN,
                "name" => SpbProject_Status::TEXT_OPEN,
                "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];

            if ($nowDate->gt($dueDate)) {
                $data = [
                    "id" => SpbProject_Status::OVERDUE,
                    "name" => SpbProject_Status::TEXT_OVERDUE,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }

            if ($nowDate->toDateString() == $spbProject->tanggal_berahir_spb) {
                $data = [
                    "id" => SpbProject_Status::DUEDATE,
                    "name" => SpbProject_Status::TEXT_DUEDATE,
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Kembalikan data status yang sesuai dengan tab
        return $data;
    }

    public function accSpbProject(Request $request, $docNoSpb)
    {
        DB::beginTransaction();

        // Cari SPB Project berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('SPB Project not found!');
        }

        try {
            // Pesan dasar untuk status perubahan
            $message = "";

            // Periksa role user yang sedang login dan lakukan pembaruan status yang sesuai
            switch (auth()->user()->role_id) {
                case Role::MARKETING:
                    // Update kolom know_marketing jika user adalah Marketing
                    $spbProject->update([
                        'know_marketing' => auth()->user()->id,
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now acknowledged by Marketing.";
                    break;

                case Role::GUDANG:
                    // Update kolom know_kepalagudang jika user adalah Kepala Gudang
                    $spbProject->update([
                        'know_kepalagudang' => auth()->user()->id,
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now acknowledged by Gudang.";
                    break;

                case Role::SUPERVISOR:
                    // Update kolom know_supervisor jika user adalah Kepala Gudang
                    $spbProject->update([
                            'know_supervisor' => auth()->user()->id,
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now acknowledged by Supervisor.";
                    break;

                case Role::OWNER:
                    // Update kolom request_owner jika user adalah Owner
                    $spbProject->update([
                        'request_owner' => auth()->user()->id,
                    ]);
                    $message = "SPB Project {$spbProject->doc_no_spb} is now Accepted by Owner.";
                    break;

                default:
                    // Jika role tidak valid, kirimkan error
                    return MessageActeeve::error('Access denied. You do not have permission to perform this action.');
            }

            // Commit transaksi
            DB::commit();

            // Dapatkan informasi siapa yang terakhir menyetujui
            $lastApprovedByMarketing = $this->getUserRole($spbProject->know_marketing);
            $lastApprovedByGudang = $this->getUserRole($spbProject->know_kepalagudang);
            $lastApprovedByOwner = $this->getUserRole($spbProject->request_owner);

            // Buat pesan tambahan berdasarkan status terakhir
            $logMessage = [
                "know_marketing" => $lastApprovedByMarketing 
                    ? "Last Marketing acknowledgment by {$lastApprovedByMarketing['user_name']} ({$lastApprovedByMarketing['role_name']})"
                    : "Marketing has not acknowledged yet.",
                "know_supervisor" => $lastApprovedByGudang 
                    ? "Last Supervisor acknowledgment by {$lastApprovedByGudang['user_name']} ({$lastApprovedByGudang['role_name']})"
                    : "Gudang has not acknowledged yet.",
                "know_kepalagudang" => $lastApprovedByGudang 
                    ? "Last Gudang acknowledgment by {$lastApprovedByGudang['user_name']} ({$lastApprovedByGudang['role_name']})"
                    : "Gudang has not acknowledged yet.",
                "request_owner" => $lastApprovedByOwner 
                    ? "Last Owner acceptance by {$lastApprovedByOwner['user_name']} ({$lastApprovedByOwner['role_name']})"
                    : "Owner has not accepted yet."
            ];

            // Mengembalikan response sukses dengan pesan tambahan
            return MessageActeeve::success($message, ['logs' => $logMessage]);

        } catch (\Throwable $th) {
            // Jika ada error, rollback transaksi
            DB::rollBack();
            return MessageActeeve::error('An error occurred: ' . $th->getMessage());
        }
    }


    public function accept(AcceptRequest $request, $id)
    {
        DB::beginTransaction();
    
        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects.taxPph'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }
    
            // Iterasi setiap produk dari request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];
                $pphId = $produkData['pph_id'] ?? null;
    
                // Cari produk terkait di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();
    
                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} and Vendor ID {$vendorId} not found!",
                    ], 404);
                }
    
                // Validasi dan update PPH
                if ($pphId) {
                    $pph = Tax::find($pphId);
                    if (!$pph || strtolower($pph->type) != Tax::TAX_PPH) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "PPH ID {$pphId} is invalid or not a PPH type.",
                        ], 400);
                    }
                }
    
                // Perbarui PPH dan status produk
                $product->update([
                    'pph' => $pphId,
                    'status_produk' => ProductCompanySpbProject::TEXT_OPEN_PRODUCT,
                ]);
            }
    
            // Perbarui status dan tab SPB Project
            $spbProject->update([
                'spbproject_status_id' => SpbProject_Status::VERIFIED,
                'tab_spb' => SpbProject::TAB_VERIFIED,
            ]);
    
            // Tambahkan log
            LogsSPBProject::create([
                'spb_project_id' => $spbProject->doc_no_spb,
                'tab_spb' => SpbProject::TAB_VERIFIED,
                'name' => auth()->user()->name,
                'message' => 'SPB Project has been accepted with updated PPH data',
            ]);
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been accepted",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
    

    public function undo($docNoSpb)
    {
        DB::beginTransaction();
    
        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::with('productCompanySpbprojects')->where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }
    
        // Pastikan status saat ini adalah VERIFIED atau PAYMENT_REQUEST yang dapat di-undo
        if ($spbProject->tab_spb == SpbProject::TAB_SUBMIT) {
            return MessageActeeve::warning("Cannot undo because tab is submit");
        }
    
        if (!in_array($spbProject->tab_spb, [SpbProject::TAB_VERIFIED, SpbProject::TAB_PAYMENT_REQUEST])) {
            return MessageActeeve::warning("Cannot undo because the tab is not VERIFIED or PAYMENT REQUEST");
        }
    
        try {
            // Mengurangi nilai tab_spb satu tingkat
            $newTab = $spbProject->tab_spb - 1;
    
            // Reset status produk di pivot table
            foreach ($spbProject->productCompanySpbprojects as $product) {
                $product->update([
                    'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, // Ubah status menjadi "Awaiting"
                    'ppn' => 0, // Reset PPN
                    'pph' => null, // Reset PPH
                ]);
            }
    
            // Update status SPB Project dan tab sesuai dengan pengurangan
            $spbProject->update([
                'spbproject_status_id' => SpbProject_Status::AWAITING,  // Status diubah kembali ke AWAITING
                'tab_spb' => $newTab,  // Tab dikurangi satu tingkat
            ]);
    
            // Tambahkan log undo
            LogsSPBProject::create([
                'spb_project_id' => $spbProject->doc_no_spb,
                'tab_spb' => $newTab,  // Tab sesuai dengan yang baru
                'name' => auth()->user()->name,
                'message' => 'SPB Project has been undone and reverted',  // Pesan untuk undo
            ]);
    
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
                'tab_spb' => SpbProject::TAB_SUBMIT, // Tab tetap di SUBMIT
            ]);

             // Mengupdate status produk yang terkait dengan SPB Project menjadi REJECTED
            $SpbProject->productCompanySpbprojects()->update([
                'status_produk' => ProductCompanySpbProject::TEXT_REJECTED_PRODUCT, // Update status produk menjadi REJECTED
            ]);

            // Mengecek apakah log dengan tab SUBMIT sudah ada sebelumnya untuk user yang sama
            $existingLog = $SpbProject->logs()->where('tab_spb', SpbProject::TAB_SUBMIT)
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
                    'tab_spb' => SpbProject::TAB_SUBMIT, // Tab tetap di SUBMIT
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

    public function rejectproduk(RejectProdukRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }

            // Iterasi setiap produk dari request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];
                $noteRejectProduk = $produkData['note_reject_produk']; // Catatan penolakan produk

                // Cari produk terkait di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();

                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} and Vendor ID {$vendorId} not found!",
                    ], 404);
                }

                // Perbarui status produk menjadi rejected dan tambahkan note penolakan
                $product->update([
                    'status_produk' => ProductCompanySpbProject::TEXT_REJECTED_PRODUCT,
                    'note_reject_produk' => $noteRejectProduk,  // Catatan penolakan
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been updated with rejection notes.",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function deleteProduk($id) {
        DB::beginTransaction();
    
        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return MessageActeeve::notFound('SPB Project not found!');
            }
    
            // Periksa apakah ada produk yang ingin dihapus
            $produkIds = collect(request()->produk)->pluck('produk_id');
            $vendorIds = collect(request()->produk)->pluck('vendor_id');
    
            // Hapus produk terkait dari tabel pivot
            $spbProject->productCompanySpbprojects()
                ->whereIn('produk_id', $produkIds)
                ->whereIn('company_id', $vendorIds)
                ->delete();
    
            DB::commit();
            return MessageActeeve::success("Produk terkait di SPB Project {$id} telah dihapus.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }
    

    public function activateproduk(ActivateProdukRequest $request, $id) {
        DB::beginTransaction();
    
        try {
            // Cari SPB Project berdasarkan ID
            $spbProject = SpbProject::with(['productCompanySpbprojects'])->find($id);
            if (!$spbProject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SPB Project not found!',
                ], 404);
            }
    
            // Iterasi setiap produk dari request
            foreach ($request->produk as $produkData) {
                $vendorId = $produkData['vendor_id'];
                $produkId = $produkData['produk_id'];
    
                // Cari produk terkait di tabel pivot
                $product = $spbProject->productCompanySpbprojects()
                    ->where('produk_id', $produkId)
                    ->where('company_id', $vendorId)
                    ->first();
    
                if (!$product) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Product with ID {$produkId} and Vendor ID {$vendorId} not found!",
                    ], 404);
                }
    
                // Update atribut produk berdasarkan request
                $product->update([
                    'harga' => $produkData['harga'],       // Update harga
                    'stok' => $produkData['stok'],         // Update stok
                    'tax_ppn' => $produkData['tax_ppn'],   // Update PPN
                    'ongkir' => $produkData['ongkir'],     // Update ongkir
                    'date' => $produkData['date'],         // Update date
                    'due_date' => $produkData['due_date'], // Update due_date
                ]);
    
                // Periksa jika produk sebelumnya berstatus rejected dan ubah statusnya menjadi awaiting
                if ($product->status_produk === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                    // Jika statusnya rejected, ubah menjadi awaiting
                    $product->update([
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, // Ubah status menjadi Awaiting
                        'note_reject_produk' => null,  // Hapus catatan penolakan
                    ]);
                } else {
                    // Jika produk tidak ditolak, langsung ubah status menjadi awaiting
                    $product->update([
                        'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT,
                    ]);
                }
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => "SPB Project {$id} has been updated with awaiting status.",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function activate(UpdateRequest $request, $docNo)
    {
        DB::beginTransaction();

        try {
            // Cari SpbProject berdasarkan doc_no_spb
            $SpbProject = SpbProject::where('doc_no_spb', $docNo)->first();
            if (!$SpbProject) {
                return MessageActeeve::notFound('Data tidak ditemukan!');
            }

            // Pastikan bahwa SPB Project status sebelumnya adalah REJECTED
            if ($SpbProject->spbproject_status_id != SpbProject_Status::REJECTED) {
                return MessageActeeve::error('SPB Project tidak dalam status REJECTED!');
            }

            // Melakukan update terhadap SpbProject dengan data yang diterima pada request
            $SpbProject->update($request->only([
                'doc_no_spb',
                'doc_type_spb',
                'spbproject_category_id',
                'spbproject_status_id',
                'user_id',
                'project_id',
                'unit_kerja',
                'nama_toko',
                'tanggal_dibuat_spb',
                'tanggal_berahir_spb',
            ]));

            // Menghapus produk lama yang terkait dengan SPB Project sebelum mengaktifkannya
            // $SpbProject->products()->detach();

            // Pastikan reject_note dihapus saat SPB Project diaktifkan
            $SpbProject->update([
                'spbproject_status_id' => SpbProject_Status::AWAITING, // Status diubah menjadi AWAITING
                'tab_spb' => SpbProject::TAB_SUBMIT, // Tab tetap di SUBMIT
                'reject_note' => null, // Menghapus reject note yang sebelumnya
            ]);

            // Menyinkronkan produk yang terkait (jika ada perubahan produk)
            foreach ($request->produk as $productGroup) {
                foreach ($productGroup['produk_data'] as $item) {
                    $vendorId = $item['vendor_id'];
                    $productId = $item['produk_id']; // produk_id adalah nilai tunggal, bukan array

                    // Cek apakah produk dengan vendor_id dan produk_id sudah ada
                    $existingProduct = $SpbProject->productCompanySpbprojects()
                        ->where('company_id', $vendorId)
                        ->where('produk_id', $productId)
                        ->first();

                    if ($existingProduct) {
                        // Jika produk sudah ada, update data produk tersebut
                        $existingProduct->update([
                            'harga' => $item['harga'],
                            'stok' => $item['stok'],
                            'ppn' => $item['tax_ppn'] ?? 0, // Menambahkan PPN
                            'ongkir' => $item['ongkir'] ?? 0,
                            'date' => $item['date'],
                            'due_date' => $item['due_date'],
                            'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, 
                            'updated_at' => now(),  // Update waktu
                        ]);
                    } else {
                        // Jika produk belum ada, tambahkan ke database
                        ProductCompanySpbProject::create([
                            'spb_project_id' => $SpbProject->doc_no_spb,
                            'produk_id' => $productId, // produk_id adalah nilai tunggal
                            'company_id' => $vendorId,
                            'ongkir' => $item['ongkir'] ?? 0,
                            'harga' => $item['harga'],
                            'stok' => $item['stok'],
                            'ppn' => $item['tax_ppn'] ?? 0, // Menambahkan PPN
                            'date' => $item['date'],
                            'due_date' => $item['due_date'],
                            'status_produk' => ProductCompanySpbProject::TEXT_AWAITING_PRODUCT, 
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }


            // Commit transaksi jika semua berhasil
            DB::commit();

            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNo telah diaktifkan dan statusnya sekarang awaiting.");

        } catch (\Throwable $th) {
            // Rollback transaksi jika ada error
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
            $existingLog = $SpbProject->logs()->where('tab_spb', SpbProject::TAB_PAYMENT_REQUEST)
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
                    'tab_spb' => SpbProject::TAB_PAYMENT_REQUEST, // Tab PAYMENT_REQUEST
                    'name' => auth()->user()->name, // Nama pengguna yang melakukan aksi
                    'message' => 'SPB Project has been requested for payment', // Pesan log
                ]);
            }
    
            // Memperbarui tab di SpbProject menjadi TAB_PAYMENT_REQUEST
            $SpbProject->update([
                'tab_spb' => SpbProject::TAB_PAYMENT_REQUEST,
            ]);
    
            DB::commit();
    
            // Kembali dengan pesan sukses
            return MessageActeeve::success("SPB Project $docNoSpb has been requested for payment");
    
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function payment(PaymentRequest $request, $docNo)
    {
        DB::beginTransaction();

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNo)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Menyimpan atau memperbarui log untuk aksi pembayaran dengan tab yang sesuai
            $existingLog = $spbProject->logs()->where('tab_spb', SpbProject::TAB_PAID)
                                                ->where('name', auth()->user()->name)
                                                ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project has been paid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Menyimpan log untuk aksi pembayaran jika belum ada
                $spbProject->logs()->create([
                    'tab_spb' => SpbProject::TAB_PAID,
                    'name' => auth()->user()->name,
                    'message' => 'SPB Project has been paid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Memperbarui status SpbProject menjadi PAID dan mengubah tab menjadi TAB_PAID
            $spbProject->update([
                'spbproject_status_id' => SpbProject_Status::PAID,
                'tab_spb' => SpbProject::TAB_PAID,
                'updated_at' => now(),
            ]);

             // Update status produk terkait dengan SpbProject menjadi PAID
            $spbProject->productCompanySpbprojects()->update([
                'status_produk' => ProductCompanySpbProject::TEXT_PAID_PRODUCT, // Update status produk ke PAID
            ]);

            // Menyimpan file attachment jika ada
            if ($request->hasFile('attachment_file_spb')) {
                foreach ($request->file('attachment_file_spb') as $key => $file) {
                    // Periksa apakah file terdeteksi dan valid
                    if ($file->isValid()) {
                        $this->saveDocument($spbProject, $file, $key + 1);
                    } else {
                        return MessageActeeve::error('File upload failed');
                    }
                }
            } else {
                return MessageActeeve::error('No file attached');
            }

            DB::commit();
            return MessageActeeve::success("SPB Project $docNo payment successfully processed");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function updatepayment(PaymentRequest $request, $docNo)
    {
        DB::beginTransaction();

        try {
            // Cari SpbProject berdasarkan doc_no_spb
            $spbProject = SpbProject::where('doc_no_spb', $docNo)->first();
            if (!$spbProject) {
                return MessageActeeve::notFound('Data not found!');
            }

            // Menyimpan atau memperbarui log untuk aksi pembayaran dengan tab yang sesuai
            $existingLog = $spbProject->logs()->where('tab_spb', SpbProject::TAB_PAID)
                                                ->where('name', auth()->user()->name)
                                                ->first();

            if ($existingLog) {
                // Jika log sudah ada, update pesan log yang sesuai
                $existingLog->update([
                    'message' => 'SPB Project payment updated',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Menyimpan log untuk aksi pembayaran jika belum ada
                $spbProject->logs()->create([
                    'tab_spb' => SpbProject::TAB_PAID,
                    'name' => auth()->user()->name,
                    'message' => 'SPB Project payment updated',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

              // Update SpbProject status dan tab
            SpbProject::where('doc_no_spb', $docNo)->update([  // Perbaiki di sini
                'spbproject_status_id' => SpbProject_Status::PAID,
                'tab_spb' => SpbProject::TAB_PAID,
                'updated_at' => $request->updated_at,  // menggunakan nilai updated_at dari request
            ]);

            $spbProject->productCompanySpbprojects()->update([
                'status_produk' => ProductCompanySpbProject::TEXT_PAID_PRODUCT, // Update status produk ke PAID
            ]);

            // Menyimpan file attachment jika ada
            if ($request->hasFile('attachment_file_spb')) {
                foreach ($request->file('attachment_file_spb') as $key => $file) {
                    // Periksa apakah file terdeteksi dan valid
                    if ($file->isValid()) {
                        $this->saveDocument($spbProject, $file, $key + 1);
                    } else {
                        return MessageActeeve::error('File upload failed');
                    }
                }
            }

            DB::commit();
            return MessageActeeve::success("SPB Project $docNo payment updated successfully");

        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }


    protected function saveDocument($spbProject, $file, $iteration)
    {
        // Menyimpan file dan mencatatkan path-nya
        $document = $file->store(SpbProject::ATTACHMENT_FILE_SPB);

        // Cek apakah file berhasil disimpan
        Log::info('Document file saved', [
            'doc_no_spb' => $spbProject->doc_no_spb,
            'file_name' => $spbProject->doc_no_spb . '.' . $iteration,
            'file_path' => $document,
        ]);

        // Menyimpan informasi dokumen ke dalam database
        return $spbProject->documents()->create([
            "doc_no_spb" => $spbProject->doc_no_spb,
            "file_name" => $spbProject->doc_no_spb . '.' . $iteration,
            "file_path" => $document,
        ]);
    }

    public function deleteDocument($id)
    {
        DB::beginTransaction();

        $spbProject = DocumentSPB::find($id);
        if (!$spbProject) {
            return MessageActeeve::notFound('data not found!');
        }

        try {
            Storage::delete($spbProject->file_path);
            $spbProject->delete();

            DB::commit();
            return MessageActeeve::success("document $id delete successfully");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }


    public function knowmarketing(Request $request, $docNoSpb )
    {
        DB::beginTransaction();
        // dd($request->user());

        // Cari SpbProject berdasarkan doc_no_spb
        $spbProject = SpbProject::where('doc_no_spb', $docNoSpb)->first();
        if (!$spbProject) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            // Update kolom know_marketing untuk menandakan bahwa proyek sudah diketahui oleh marketing
            $spbProject->update([
                "know_marketing" => auth()->user()->id, 
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
