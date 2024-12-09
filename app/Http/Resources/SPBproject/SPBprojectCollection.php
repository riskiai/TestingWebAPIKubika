<?php

namespace App\Http\Resources\SPBproject;

use App\Models\SpbProject;
use App\Models\SpbProject_Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        foreach ($this as $key => $spbProject) {
            $data[$key] = [
                "doc_no_spb" => $spbProject->doc_no_spb,
                "doc_type_spb" => $spbProject->doc_type_spb,
                "status" => $this->getStatus($spbProject),
                // 'project' => $spbProject->project->isNotEmpty() ? [
                //     'id' => $spbProject->project->first()->id,
                //     'nama' => $spbProject->project->first()->name,
                // ] : [
                //     'id' => 'N/A',
                //     'nama' => 'No Project Available'
                // ],
                "project" => $spbProject->project ? [
                'id' => $spbProject->project->id,
                'nama' => $spbProject->project->name,
                ] : [
                    'id' => 'N/A',
                    'nama' => 'No Project Available',
                ],
                // Menangani data vendor dan produk
                "vendors" => is_iterable($spbProject->vendors) ? $spbProject->vendors->map(function ($vendor) use ($spbProject) {
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
                "unit_kerja" => $spbProject->unit_kerja,
                "tanggal_berahir_spb" => $spbProject->tanggal_berahir_spb,
                "tanggal_dibuat_spb" => $spbProject->tanggal_dibuat_spb,
                "nama_toko" => $spbProject->nama_toko,
                "know_marketing" => $this->getUserRole($spbProject->know_marketing),
                "know_supervisor" => $this->getUserRole($spbProject->know_supervisor),
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
                $data[$key]['created_by'] = [
                    "id" => $spbProject->user->id,
                    "name" => $spbProject->user->name,
                ];
            }
        }

        return $data;
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
        elseif ($spbProject->tab == SpbProject::TAB_VERIFIED) {
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



}
