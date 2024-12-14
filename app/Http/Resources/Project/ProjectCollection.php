<?php

namespace App\Http\Resources\Project;

use App\Models\User;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\SpbProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                        "vendors" => $this->getVendorsWithProducts($spbProject),
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

               /* 'file_attachment_spb' => [
                    'name' => $project->spb_file ? 'SPB-PROJECT-' . date('Y', strtotime($project->created_at)) . '/' . $project->id . '.' . pathinfo($project->spb_file, PATHINFO_EXTENSION) : null,
                    'link' => $project->spb_file ? asset("storage/$project->spb_file") : null,
                ], */
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
