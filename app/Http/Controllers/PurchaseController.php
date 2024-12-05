<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Facades\Filters\Purchase\ByTab;
use App\Facades\Filters\Purchase\ByTax;
use App\Facades\Filters\Purchase\ByDate;
use Illuminate\Pipeline\Pipeline;
use App\Facades\Filters\Purchase\BySearch;
use App\Facades\Filters\Purchase\ByStatus;
use App\Facades\Filters\Purchase\ByVendor;
use App\Facades\Filters\Purchase\ByDoctype;
use App\Facades\Filters\Purchase\ByDuedate;
use App\Facades\Filters\Purchase\ByProject;
use App\Facades\Filters\Purchase\ByUpdated;
use App\Facades\Filters\Purchase\ByPurchaseID;
use App\Http\Resources\Purchase\PurchaseCollection;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Purchase::query();
        
        // Tambahkan filter berdasarkan tanggal terkini (komentar saja)
        // $query->whereDate('date', Carbon::today());

        // Terapkan filter berdasarkan peran pengguna
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->where('user_id', auth()->user()->id);
        }

        $purchases = app(Pipeline::class)
            ->send($query)
            ->through([
                ByDate::class,
                ByUpdated::class,
                ByPurchaseID::class,
                ByTab::class,
                ByStatus::class,
                ByVendor::class,
                ByProject::class,
                ByTax::class,
                BySearch::class,
                ByDoctype::class, 
                ByDuedate::class,
            ])
            ->thenReturn();

        // Kondisi untuk pengurutan berdasarkan tab
        if ($request->has('tab')) {
            switch ($request->get('tab')) {
                case Purchase::TAB_SUBMIT:
                    $purchases->orderBy('date', 'desc')->orderBy('doc_no', 'desc');
                    break;
                case Purchase::TAB_VERIFIED:
                case Purchase::TAB_PAYMENT_REQUEST:
                    $purchases->orderBy('due_date', 'asc')->orderBy('doc_no', 'asc');
                    break;
                case Purchase::TAB_PAID:
                    $purchases->orderBy('updated_at', 'desc')->orderBy('doc_no', 'desc');
                    break;
                default:
                    $purchases->orderBy('date', 'desc')->orderBy('doc_no', 'desc');
                    break;
            }
        } else {
            // Jika tidak ada tab yang dipilih, urutkan berdasarkan date secara descending
            $purchases->orderBy('date', 'desc')->orderBy('doc_no', 'desc');
        }

        $purchases = $purchases->paginate($request->per_page);

        return new PurchaseCollection($purchases);
    }
}
