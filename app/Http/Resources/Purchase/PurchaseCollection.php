<?php

namespace App\Http\Resources\Purchase;

use App\Models\Purchase;
use App\Models\PurchaseStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PurchaseCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this as $key => $purchase) {
            $data[$key] = [
                "doc_no" => $purchase->doc_no,
                "doc_type" => $purchase->doc_type,
                "purchase_type" => $purchase->type_purchase_id == Purchase::TYPE_EVENT ? Purchase::TEXT_EVENT : Purchase::TEXT_OPERATIONAL,
                "vendor_name" => [
                    "id" => $purchase->company->id,
                    "name" => $purchase->company->name,
                    "bank" => $purchase->company->bank_name,
                    "account_name" => $purchase->company->account_name,
                    "account_number" => $purchase->company->account_number,
                ],
                "status" => $this->getStatus($purchase),
                "description" => $purchase->description,
                "remarks" => $purchase->remarks,
                "sub_total" => $purchase->sub_total,
                "total" => $purchase->total,
                "file_attachment" => $this->getDocument($purchase),
                "date" => $purchase->date,
                "due_date" => $purchase->due_date,
                "ppn" => $this->getPpn($purchase),
                // "logs_rejected" => $purchase->logs()->select('name', 'note_reject', 'created_at')->where('note_reject', '!=', null)->orderBy('id', 'desc')->get(),
                "created_at" => $purchase->created_at->format('Y-m-d'),
                "updated_at" => $purchase->updated_at->format('Y-m-d'),                
                
            ];

            if ($purchase->user) {
                $data[$key]['created_by'] = [
                    "id" => $purchase->user->id,
                    "name" => $purchase->user->name,
                ];
            }
            if ($purchase->purchase_id == Purchase::TYPE_EVENT) {
                if ($purchase->project) {
                    $data[$key]['project'] = [
                        "id" => $purchase->project->id,
                        "name" => $purchase->project->name,
                    ];
                }
            }

            if ($purchase->pph) {
                $data[$key]['pph'] = $this->getPph($purchase);
            }
        }

        return $data;
    }

    protected function getDocument($documents)
    {
        $data = [];

        foreach ($documents->documents as $document) {
            $data[] = [
                "id" => $document->id,
                "name" => $document->purchase->doc_type . "/$document->doc_no.$document->id/" . date('Y', strtotime($document->created_at)) . "." . pathinfo($document->file_path, PATHINFO_EXTENSION),
                "link" => asset("storage/$document->file_path"),
            ];
        }

        return $data;
    }

    protected function getStatus($purchase)
    {
        $data = [];

        // Nama tab berdasarkan konstanta yang ada di model SpbProject
        $tabNames = [
            Purchase::TAB_SUBMIT => 'Submit',
            Purchase::TAB_VERIFIED => 'Verified',
            Purchase::TAB_PAYMENT_REQUEST => 'Payment Request',
            Purchase::TAB_PAID => 'Paid',
        ];

        // Ambil nama tab berdasarkan nilai tab
        $tabName = $tabNames[$purchase->tab_purchase] ?? 'Unknown';  // Default jika tidak ditemukan

        // Pengecekan status yang berkaitan dengan TAB_SUBMIT
        if ($purchase->tab_purchase == Purchase::TAB_SUBMIT) {
            // Pastikan status ada, jika tidak set default ke AWAITING
            if ($purchase->status) {
                $data = [
                    "id" => $purchase->status->id ?? PurchaseStatus::AWAITING,
                    "name" => $purchase->status->name ?? PurchaseStatus::TEXT_AWAITING,
                    "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];

                // Jika status adalah REJECTED, tambahkan note
                if ($purchase->status->id == PurchaseStatus::REJECTED) {
                    $data["reject_note"] = $purchase->reject_note ?? 'No reject note';
                }
            } else {
                // Jika status tidak ada, set ke AWAITING
                $data = [
                    "id" => PurchaseStatus::AWAITING,
                    "name" => PurchaseStatus::TEXT_AWAITING,
                    "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAID
        elseif ($purchase->tab_purchase == Purchase::TAB_PAID) {
            $data = [
                "id" => $purchase->status->id ?? null,
                "name" => $purchase->status ? $purchase->status->name : 'Unknown',
                "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];
        }

        // Pengecekan untuk TAB_VERIFIED
        elseif ($purchase->tab_purchase == Purchase::TAB_VERIFIED) {
            $dueDate = Carbon::createFromFormat("Y-m-d", $purchase->due_date);
            $nowDate = Carbon::now();

            $data = [
                "id" => PurchaseStatus::OPEN,
                "name" => PurchaseStatus::TEXT_OPEN,
                "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];

            if ($nowDate->gt($dueDate)) {
                $data = [
                    "id" => PurchaseStatus::OVERDUE,
                    "name" => PurchaseStatus::TEXT_OVERDUE,
                    "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }

            if ($nowDate->toDateString() == $purchase->due_date) {
                $data = [
                    "id" => PurchaseStatus::DUEDATE,
                    "name" => PurchaseStatus::TEXT_DUEDATE,
                    "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Pengecekan untuk TAB_PAYMENT_REQUEST
        elseif ($purchase->tab_purchase == Purchase::TAB_PAYMENT_REQUEST) {
            $dueDate = Carbon::createFromFormat("Y-m-d", $purchase->due_date);
            $nowDate = Carbon::now();

            $data = [
                "id" => PurchaseStatus::OPEN,
                "name" => PurchaseStatus::TEXT_OPEN,
                "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
            ];

            if ($nowDate->gt($dueDate)) {
                $data = [
                    "id" => PurchaseStatus::OVERDUE,
                    "name" => PurchaseStatus::TEXT_OVERDUE,
                    "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }

            if ($nowDate->toDateString() == $purchase->due_date) {
                $data = [
                    "id" => PurchaseStatus::DUEDATE,
                    "name" => PurchaseStatus::TEXT_DUEDATE,
                    "tab_purchase" => $tabName,  // Menambahkan tab dari nama yang sudah diambil
                ];
            }
        }

        // Kembalikan data status yang sesuai dengan tab
        return $data;
    }

    /* protected function getPpn($purchase)
    {
        return ($purchase->sub_total * $purchase->ppn) / 100;
    }

    protected function getPph($purchase)
    {
        // Hitung hasil PPH 
        $pphResult = round((($purchase->sub_total) * $purchase->taxPph->percent) / 100);

        // Ubah nilai pph_hasil menjadi nilai yang dibulatkan
        return [
            "pph_type" => $purchase->taxPph->name,
            "pph_rate" => $purchase->taxPph->percent,
            "pph_hasil" => $pphResult
        ];
    } */

    protected function getPpn($purchase)
    {
        if (is_numeric($purchase->ppn)) {
            return ($purchase->sub_total * $purchase->ppn) / 100;
        } else {
            return 0; // Atau nilai default lainnya jika ppn bukan numerik
        }
    }

    protected function getPph($purchase)
    {
        if (is_numeric($purchase->pph)) {
            // Hitung hasil PPH 
            $pphResult = round((($purchase->sub_total) * $purchase->taxPph->percent) / 100);

            // Ubah nilai pph_hasil menjadi nilai yang dibulatkan
            return [
                "pph_type" => $purchase->taxPph->name,
                "pph_rate" => $purchase->taxPph->percent,
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

}
