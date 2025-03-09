<?php

namespace App\Http\Resources\SPBproject;

use Carbon\Carbon;
use App\Models\Role;
use App\Models\SpbProject;
use Illuminate\Http\Request;
use App\Models\SpbProject_Status;
use Illuminate\Support\Facades\DB;
use App\Models\ProductCompanySpbProject;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SPBprojectCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];
        $tanggalDateProduk = $request->tanggal_date_produk ?? null;
        $tanggalDueDateProduk = $request->tanggal_due_date_produk ?? null;

        foreach ($this as $key => $spbProject) {
            $company = $spbProject->company;
            $hargaTotalPembayaranBorongan = $spbProject->harga_total_pembayaran_borongan_spb;

            // Filter produk berdasarkan tanggal yang diterima di request
            $filteredProducts = $spbProject->productCompanySpbprojects->filter(function ($product) use ($tanggalDateProduk, $tanggalDueDateProduk) {
                if ($tanggalDateProduk && $tanggalDueDateProduk) {
                    return (Carbon::parse($product->date)->between($tanggalDateProduk, $tanggalDueDateProduk) &&
                            Carbon::parse($product->due_date)->between($tanggalDateProduk, $tanggalDueDateProduk));
                } elseif ($tanggalDateProduk) {
                    return Carbon::parse($product->date)->isSameDay($tanggalDateProduk);
                } elseif ($tanggalDueDateProduk) {
                    return Carbon::parse($product->due_date)->isSameDay($tanggalDueDateProduk);
                }
                return true; // Jika tidak ada filter tanggal, masukkan semua produk
            });

            
            // Hitung total produk yang sesuai dengan filter
            $totalProduk = $filteredProducts->sum(function ($product) {
                return $product->subtotal_produk;
            });

              // Determine the tab name
            $tabNames = [
                SpbProject::TAB_SUBMIT => 'Submit',
                SpbProject::TAB_VERIFIED => 'Verified',
                SpbProject::TAB_PAYMENT_REQUEST => 'Payment Request',
                SpbProject::TAB_PAID => 'Paid',
            ];

            $tabName = $tabNames[$spbProject->tab_spb] ?? 'Unknown';

            // Menentukan nama berdasarkan type_project
            $typeSpbProject = [
                'id' => $spbProject->type_project,
                'name' => $spbProject->type_project == SpbProject::TYPE_PROJECT_SPB
                    ? SpbProject::TEXT_PROJECT_SPB
                    : SpbProject::TEXT_NON_PROJECT_SPB,
            ];
            
            $data[$key] = [
                "doc_no_spb" => $spbProject->doc_no_spb,
                "doc_type_spb" => $spbProject->doc_type_spb,
                "is_payment_vendor" => (bool) $spbProject->is_payment_vendor,
                "status_spb" => $this->getStatus($spbProject),
                // Menambahkan logs ke dalam data proyek
                'logs_spb' => $spbProject->logs->groupBy('name')->map(function ($logsByUser) use ($spbProject) {
                    // Ambil log terakhir berdasarkan created_at untuk setiap pengguna
                    $lastLog = $logsByUser->sortByDesc('created_at')->first();

                    // Ambil reject_note dari spbProject
                    $rejectNote = $spbProject->reject_note;  // Ambil reject_note langsung dari spbProject

                    return [
                        'tab_spb' => $lastLog->tab_spb, // Ambil tab dari log terakhir
                        'name' => $lastLog->name, // Ambil nama pengguna
                        'created_at' => $lastLog->created_at, // Ambil waktu terakhir log
                        'message' => $lastLog->message, // Ambil pesan dari log terakhir
                        'reject_note' => $rejectNote, // Tambahkan reject_note dari spbProject
                    ];
                })->values()->all(),
                "type_spb_project" => $typeSpbProject,
                'supervisor' => $spbProject->project && $spbProject->project->tenagaKerja->isNotEmpty()
                    ? $spbProject->project->tenagaKerja()
                        ->whereHas('role', function ($query) {
                            $query->where('role_name', 'Supervisor'); // Filter berdasarkan role 'Supervisor'
                        })
                        ->first() // Ambil hanya supervisor pertama yang ada
                        ? [
                            'id' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                $query->where('role_name', 'Supervisor');
                            })->first())->id ?? null,
                            'name' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                $query->where('role_name', 'Supervisor');
                            })->first())->name ?? null,
                            'divisi' => [
                                'id' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                    $query->where('role_name', 'Supervisor');
                                })->first()->divisi)->id,
                                'name' => optional($spbProject->project->tenagaKerja()->whereHas('role', function ($query) {
                                    $query->where('role_name', 'Supervisor');
                                })->first()->divisi)->name,
                            ],
                        ]
                        : null 
                    : null, 

                'tukang' => $spbProject->project && $spbProject->project->tenagaKerja->isNotEmpty()
                    ? $spbProject->project->tenagaKerja()
                        ->whereHas('role', function ($query) {
                            // Filter berdasarkan role yang diinginkan
                            $query->whereIn('role_name', ['Owner', 'Marketing', 'Supervisor']);
                        })
                        ->get() // Menambahkan get() untuk mengambil koleksi
                        ->map(function ($user) {
                            return [
                                'id' => $user->id ?? null,
                                'name' => $user->name ?? null,
                                'divisi' => [
                                    'id' => optional($user->divisi)->id, // Pastikan divisi bisa null jika tidak ada
                                    'name' => optional($user->divisi)->name,
                                ],
                            ];
                        })
                    : [], 
                "project" => $spbProject->project ? [
                'id' => $spbProject->project->id,
                'nama' => $spbProject->project->name,
                ] : [
                    'id' => null,
                    'nama' => null,
                ],
                'produk' => $spbProject->productCompanySpbprojects->map(function ($product) use ($spbProject) {
                            $dueDate = Carbon::createFromFormat("Y-m-d", $product->due_date); // Membaca due_date
                            $nowDate = Carbon::now(); // Mendapatkan tanggal sekarang
                            $status = $product->status_produk; // Status awal produk
                            $status = $product->status_vendor; // Status awal produk

                            // Periksa jika status produk adalah "Paid"
                            if ($status === ProductCompanySpbProject::TEXT_PAID_PRODUCT) {
                                // Jika status adalah "Paid", set status ke "Paid"
                                $notePaid = $product->note_paid_produk; // Ambil note_paid_produk jika statusnya "Paid"
                                return [
                                    'produk_data' => [
                                        'produk_id' => $product->produk_id ?? null,
                                        'nama' => $product->product->nama ?? null,
                                        'id_kategori' => $product->product->id_kategori ?? null,
                                        'type_pembelian' => $product->product->type_pembelian ?? null,
                                        'harga_product' => $product->product->harga_product ?? null,
                                    ],
                                    'vendor' => [
                                        'id' => $product->company->id ?? 'Unknown',
                                        'name' => $product->company->name ?? 'Unknown',
                                        'bank_name' => $product->company->bank_name ?? 'Unknown',
                                        'account_name' => $product->company->account_name ?? 'Unknown',
                                    ],
                                    'total_vendor' => $product->getTotalVendorAttribute(),
                                    'status_vendor' => $status,
                                    'status_produk' => $status, // Status produk adalah "Paid"
                                    'note_paid_produk' => $notePaid, // Catatan jika produk sudah dibayar
                                    'date' => $product->date,
                                    'due_date' => $product->due_date,
                                    'description' => $product->description,
                                    'ppn' => $product->ppn_detail, 
                                    'ongkir' => $product->ongkir ?? 0,
                                    'harga' => $product->harga ?? 0,
                                    'stok' => $product->stok ?? 0,
                                    'subtotal_item' => $product->subtotal_produk,
                                    'payment_date' => $product->payment_date ?? null,  
                                    'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                                ];
                            }

                            // Cek jika produk sudah ditolak (Rejected), maka langsung set statusnya ke Rejected
                            if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                                $noteReject = $product->note_reject_produk; // Ambil note_reject_produk jika statusnya "Rejected"
                                return [
                                    'produk_data' => [
                                        'produk_id' => $product->produk_id ?? null,
                                        'nama' => $product->product->nama ?? null,
                                        'id_kategori' => $product->product->id_kategori ?? null,
                                        // 'deskripsi' => $product->product->deskripsi ?? '',
                                        'type_pembelian' => $product->product->type_pembelian ?? null,
                                        'harga_product' => $product->product->harga_product ?? null,
                                    ],
                                    'vendor' => [
                                        'id' => $product->company->id ?? 'Unknown',
                                        'name' => $product->company->name ?? 'Unknown',
                                        'bank_name' => $product->company->bank_name ?? 'Unknown',
                                        'account_name' => $product->company->account_name ?? 'Unknown',
                                    ],
                                    'total_vendor' => $product->getTotalVendorAttribute(),
                                    'status_vendor' => $status,
                                    'status_produk' => $status,
                                    'note_reject_produk' => $noteReject, 
                                    'date' => $product->date,
                                    'due_date' => $product->due_date,
                                    'description' => $product->description,
                                    'ppn' => $product->ppn_detail, 
                                    'ongkir' => $product->ongkir ?? 0,
                                    'harga' => $product->harga ?? 0,
                                    'stok' => $product->stok ?? 0,
                                    'subtotal_item' => $product->subtotal_produk,
                                    'payment_date' => $product->payment_date ?? null,  
                                    'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                                ];
                            }

                            // Periksa apakah status produk bukan open, overdue, atau duedate
                           /*  if (!in_array($status, [
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
                            } */

                            // Menangani status "Rejected" jika tidak ditemukan sebelumnya
                            if ($status === ProductCompanySpbProject::TEXT_REJECTED_PRODUCT) {
                                $noteReject = $product->note_reject_produk;
                            } else {
                                $noteReject = null;
                            }

                            return [
                                'produk_data' => [
                                    'produk_id' => $product->produk_id ?? null,
                                    'nama' => $product->product->nama ?? null,
                                    'id_kategori' => $product->product->id_kategori ?? null,
                                    // 'deskripsi' => $product->product->deskripsi ?? '',
                                    'type_pembelian' => $product->product->type_pembelian ?? null,
                                    'harga_product' => $product->product->harga_product ?? null,
                                ],
                                'vendor' => [
                                    'id' => $product->company->id ?? 'Unknown',
                                    'name' => $product->company->name ?? 'Unknown',
                                    'bank_name' => $product->company->bank_name ?? 'Unknown',
                                    'account_name' => $product->company->account_name ?? 'Unknown',
                                ],
                            'total_vendor' => $product->getTotalVendorAttribute(),
                            'status_vendor' => $product->status_vendor,
                            'status_produk' => $product->status_produk,
                            'note_reject_produk' => $noteReject,
                            'date' => $product->date,
                            'due_date' => $product->due_date,
                            'description' => $product->description,
                            'ppn' => $product->ppn_detail, 
                            'ongkir' => $product->ongkir ?? 0,
                            'harga' => $product->harga ?? 0,
                            'stok' => $product->stok ?? 0,
                            'subtotal_item' => $product->subtotal_produk,
                            'payment_date' => $product->payment_date ?? null,  
                            'file_payment' => $product->file_payment ? asset("storage/$product->file_payment") : null,
                            /* 'pph' => [
                                'pph_type' => $product->taxPph->name ?? 'Unknown',
                                'pph_rate' => $product->taxPph->percent ?? 0,
                                'pph_hasil' => $product->pph_value,
                            ], */
                            // 'total_item' => $product->total_produk,
                        ];
                    }),
                'total' => $totalProduk,
                'sisa_pembayaran' => $spbProject->sisaPembayaranProductVendor(),
                'total_terbayarkan' => $spbProject->totalTerbayarProductVendor(), // Memanggil method dari SpbProject
                'file_attachement' => $this->getDocument($spbProject),
                "unit_kerja" => $spbProject->unit_kerja,
                "vendor_borongan" => $company ? [
                        "id" => $company->id,
                        "name" => $company->name,
                        "bank_name" => $company->bank_name,
                        "account_name" => $company->account_name,
                    ] : null,
                // "harga_total_pembayaran_borongan_spb" => $spbProject->harga_total_pembayaran_borongan_spb ?? null,
                "harga_total_pembayaran_borongan_spb" => $hargaTotalPembayaranBorongan, 
                'sisa_pembayaran_termin_spb' => $this->getDataSisaPemabayaranTerminSpb($spbProject),
                "harga_total_termin_spb" => $this->getHargaTerminSpb($spbProject),
                "deskripsi_termin_spb" => $this->getDeskripsiTerminSpb($spbProject),
                "type_termin_spb" => $this->getDataTypetermin($spbProject->type_termin_spb),
                "riwayat_termin" => $this->getRiwayatTermin($spbProject),
                "tanggal_dibuat_spb" => $spbProject->tanggal_dibuat_spb,
                "tanggal_berahir_spb" => $spbProject->tanggal_berahir_spb,
                "know_spb_marketing" => $this->getUserRole($spbProject->know_marketing),
                "know_spb_supervisor" => $this->getUserRole($spbProject->know_supervisor),
                "know_spb_kepalagudang" => $this->getUserRole($spbProject->know_kepalagudang),
                "accept_spb_finance" => $this->getUserRole($spbProject->know_finance), 
                "payment_request_owner" => auth()->user()->hasRole(Role::OWNER) || $spbProject->request_owner ? $this->getUserRole($spbProject->request_owner) : null,
                "created_at" => $spbProject->created_at->format('Y-m-d'),
                "updated_at" => $spbProject->updated_at->format('Y-m-d'),
            ];

            // Add created_by if user is associated
            if ($spbProject->user) {
                $data[$key]['created_by'] = [
                    "id" => $spbProject->user->id,
                    "name" => $spbProject->user->name,
                    "created_at" => Carbon::parse($spbProject->created_at)->timezone('Asia/Jakarta')->toDateTimeString(),
                ];
            }

            // Jika ada PPH, tambahkan data PPH
            /* if ($spbProject->pph) {
            $data[$key]['pph'] = $this->getPph($spbProject);
            } */
        }

        return $data;
    }

    /* protected function sisaPembayaranProductVendor($spbProject)
    {
        // Ambil total produk dari SPB Project
        $totalProduk = $spbProject->getTotalProdukAttribute();
    
        // Ambil total yang sudah terbayar menggunakan totalTerbayarProductVendor
        $totalTerbayar = $this->totalTerbayarProductVendor($spbProject);
    
        // Hitung sisa pembayaran
        $sisaPembayaran = $totalProduk - $totalTerbayar;
    
        // Pastikan sisa pembayaran tidak negatif
        return max(0, round($sisaPembayaran)); // Membulatkan dan memastikan nilai minimal adalah 0
    }    

    protected function totalTerbayarProductVendor($spbProject)
    {
        // Filter produk yang status_vendor-nya "Paid"
        $paidProducts = $spbProject->productCompanySpbprojects->filter(function ($product) {
            return $product->status_vendor === ProductCompanySpbProject::TEXT_PAID_PRODUCT;
        });

        // Hitung total subtotal produk yang status_vendor-nya "Paid"
        $totalPaid = $paidProducts->sum(function ($product) {
            return $product->subtotal_produk;
        });

        return round($totalPaid); // Membulatkan total yang terbayar
    } */
    

    protected function getDataSisaPemabayaranTerminSpb($spbProject)
    {
        // Ambil total harga termin yang sudah dibayar (total harga termin yang ada di SPB project)
        $totalHargaTermin = $this->getHargaTerminSpb($spbProject);
    
        // Pastikan harga_total_pembayaran_borongan_spb dalam bentuk angka
        $hargaPembayaranBorongan = floatval($spbProject->harga_total_pembayaran_borongan_spb ?? 0);
    
        // Jika belum ada pembayaran termin (totalHargaTermin adalah 0), kembalikan harga total borongan SPB
        if ($totalHargaTermin == 0) {
            return $hargaPembayaranBorongan; // Mengembalikan harga borongan SPB sebagai angka
        }
    
        // Jika sudah ada pembayaran termin, hitung sisa pembayaran
        $sisaPembayaran = $hargaPembayaranBorongan - $totalHargaTermin;
    
        return $sisaPembayaran; // Mengembalikan sisa pembayaran dalam bentuk angka
    }


    protected function getHargaTerminSpb($spbProject)
    {
        return $spbProject->harga_termin_spb ?? 0;
    }

    protected function getDeskripsiTerminSpb($spbProject)
    {
        return $spbProject->deskripsi_termin_spb ?? null;
    }

    protected function getRiwayatTermin($spbProject)
    {
        return $spbProject->termins->map(function ($termin) use ($spbProject) {
            return [
                'id' => $termin->id, 
                'harga_termin' => $termin->harga_termin,
                'deskripsi_termin' => $termin->deskripsi_termin,
                'type_termin_spb' => $this->getDataTypetermin($termin->type_termin_spb),
                'tanggal' => $termin->tanggal,
                'file_attachment' => $termin->fileAttachment ? [
                    'id' => $termin->fileAttachment->id,
                    'name' => $termin->fileAttachment->spbProject->doc_type_spb . "/{$termin->fileAttachment->doc_no_spb}.{$termin->fileAttachment->id}/" . date('Y', strtotime($termin->fileAttachment->created_at)) . "." . pathinfo($termin->fileAttachment->file_path, PATHINFO_EXTENSION),
                    'link' => asset("storage/{$termin->fileAttachment->file_path}"),
                ] : null,
            ];
        });
    }

   
    protected function getDataTypetermin($status) {
        $statuses = [

            SpbProject::TYPE_TERMIN_BELUM_LUNAS => "Belum Lunas",
            SpbProject::TYPE_TERMIN_LUNAS => "Lunas",
         ];

        return [
            "id" => $status,
            "name" => $statuses[$status] ?? "Unknown",
        ];
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
        // Cek apakah properti ppn ada dan nilai lebih dari 0
        if (isset($spbProject->ppn) && is_numeric($spbProject->ppn) && $spbProject->ppn > 0) {
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
            // Ambil data pengguna berdasarkan user_id
            $user = \App\Models\User::find($userId);
    
            if ($user) {
                // Ambil approve_date langsung dari tabel spb_projects untuk user terkait
                $approveDate = DB::table('spb_projects')
                    ->where(function ($query) use ($userId) {
                        $query->where('know_marketing', $userId)
                              ->orWhere('know_supervisor', $userId)
                              ->orWhere('know_kepalagudang', $userId)
                              ->orWhere('know_finance', $userId)
                              ->orWhere('request_owner', $userId);
                    })
                    ->orderByDesc('approve_date')
                    ->value('approve_date'); // Ambil nilai approve_date
    
                // Ubah waktu approve_date ke timezone Jakarta
                $formattedApproveDate = $approveDate 
                    ? \Carbon\Carbon::parse($approveDate)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s')
                    : 'Not approved yet';
    
                return [
                    'user_name' => $user->name,
                    'role_name' => $user->role ? $user->role->role_name : 'Unknown',
                    'approve_date' => $formattedApproveDate,
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
            // Pastikan jika statusnya REJECTED, maka status akan diubah menjadi REJECTED dalam respons
            if ($spbProject->status->id == SpbProject_Status::REJECTED) {
                $data = [
                    "id" => SpbProject_Status::REJECTED,
                    "name" => 'Rejected',
                    "tab_spb" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                    "reject_note" => $spbProject->reject_note ?? 'No reject note',  // Menambahkan catatan reject
                ];
            } else {
                // Jika status lainnya, tetap ikuti kondisi sebelumnya
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
        }

        // Kembalikan data status yang sesuai dengan tab
        return $data;
    }

}
