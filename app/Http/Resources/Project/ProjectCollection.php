<?php

namespace App\Http\Resources\Project;

use App\Models\User;
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
                    'id' => optional($project->company)->id,
                    'name' => optional($project->company)->name,
                    'contact_type' => $project->company->contactType->name,
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
                'percent' => $this->formatPercent($project->percent),
                'harga_type_project' => $project->harga_type_project ?? 0,
                'file_attachment' => [
                    'name' => $project->file ? date('Y', strtotime($project->created_at)) . '/' . $project->id . '.' . pathinfo($project->file, PATHINFO_EXTENSION) : null,
                    'link' => $project->file ? asset("storage/$project->file") : null,
                ],
                'cost_progress' => $project->status_cost_progress,
                // 'status_step_project' => $this->getStepStatus($project->status_step_project),
                'request_status_owner' => $this->getRequestStatus($project->request_status_owner),
                'summary_salary' => [
                    'tukang_harian' => $this->tukangHarianSalary($project->manPowers()),
                    'tukang_borongan' => $this->tukangBoronganSalary($project->manPowers()),
                    'total' => $this->tukangHarianSalary($project->manPowers()) + $this->tukangBoronganSalary($project->manPowers()),
                ],
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ];

            if ($project->user) {
                $data[$key]['created_by'] = [
                    "id" => $project->user->id,
                    "name" => $project->user->name,
                ];
            }

            if ($project->user) {
                $data[$key]['updated_by'] = [
                    "id" => $project->user->id,
                    "name" => $project->user->name,
                ];
            }
        }

        return $data;
    }

    protected function tukangHarianSalary($query) {
        return (int) $query->selectRaw("SUM(current_salary + current_overtime_salary) as total")->where("work_type", true)->first()->total;
    }

    protected function tukangBoronganSalary($query) {
        return (int) $query->selectRaw("SUM(current_salary + current_overtime_salary) as total")->where("work_type", false)->first()->total;
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

   /*  protected function getStepStatus($step)
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
    } */


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
