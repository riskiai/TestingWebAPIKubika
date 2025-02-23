<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCompanySpbProject extends Model
{
    use HasFactory;

    // Tentukan nama tabel pivot
    protected $table = 'product_company_spbproject';

    const FILE_PEMBAYARAN_VENDOR = 'attachment/spbproject/vendor/file';

    // Buat Status Produk
    const AWAITING_PRODUCT = 1;
    const VERIFIED_PRODUCT = 2;
    const OPEN_PRODUCT = 3;
    const OVERDUE_PRODUCT = 4;
    const DUEDATE_PRODUCT = 5;
    const REJECTED_PRODUCT = 6;
    const PAID_PRODUCT = 7;

    // Buat Status Produk
    const TEXT_AWAITING_PRODUCT = "Awaiting";
    const TEXT_VERIFIED_PRODUCT = "Verified";
    const TEXT_OPEN_PRODUCT = "Open";
    const TEXT_OVERDUE_PRODUCT = "Overdue";
    const TEXT_DUEDATE_PRODUCT = "Due Date";
    const TEXT_REJECTED_PRODUCT = "Rejected";
    const TEXT_PAID_PRODUCT = "Paid";

    // Tentukan kolom-kolom yang bisa diisi
    protected $fillable = [
        'spb_project_id', 
        'produk_id',   
        'company_id',   
        'ongkir', 
        'harga',
        'stok',    
        'ppn',
        'pph',
        'subtotal_produk',
        'status_produk',
        'date',
        'due_date',
        'note_reject_produk',
        'description',
        'payment_date',
        'file_payment',
        'status_vendor',
        // 'type_pembelian_produk',
    ];

   
    /* public function getPpnDetailAttribute()
    {
        $harga = floatval($this->harga);
        $stok = intval($this->stok);
        $ppn = floatval($this->ppn);
        
        // Subtotal sebelum PPN: hanya (harga * stok)
        $subtotalSebelumPpn = $harga * $stok;
        
        // Kalkulasi nilai PPN
        $ppnValue = $ppn > 0 ? round(($subtotalSebelumPpn * $ppn) / 100) : 0;
        
        return [
            'ppn_percentage' => $ppn, // Persentase PPN
            'ppn_value' => $ppnValue, // Nilai PPN
        ];
    } */
    
    /* public function getSubtotalProdukAttribute()
    {
        $harga = floatval($this->harga);
        $stok = intval($this->stok);
        $ongkir = floatval($this->ongkir);
        
        // Menggunakan ppn_value dari accessor getPpnDetailAttribute
        $ppnValue = $this->ppn_detail['ppn_value'];
        
        // Perhitungan subtotal
        return round(($harga * $stok) + $ongkir + $ppnValue);
    } */

    public function getPpnDetailAttribute()
    {
        $harga = floatval($this->harga);

        // Jika harga kosong, gunakan harga dari produk terkait
        if ($harga == 0) {
            $harga = $this->product->harga_product ? floatval($this->product->harga_product) : 0;
        }

        $stok = intval($this->stok);
        $ppn = floatval($this->ppn);

        // Subtotal sebelum PPN: hanya (harga * stok)
        $subtotalSebelumPpn = $harga * $stok;

        // Kalkulasi nilai PPN
        $ppnValue = $ppn > 0 ? round(($subtotalSebelumPpn * $ppn) / 100) : 0;

        return [
            'ppn_percentage' => $ppn, // Persentase PPN
            'ppn_value' => $ppnValue, // Nilai PPN
        ];
    }

    public function getTotalVendorAttribute()
    {
        // Menggunakan array untuk beberapa kondisi where
        $vendorProducts = $this->where([
                ['company_id', '=', $this->company_id],
                ['spb_project_id', '=', $this->spb_project_id] // Menambahkan filter berdasarkan spb_project_id
            ])
            ->get();
    
        // Hitung total subtotal produk yang terkait dengan vendor
        $totalVendor = $vendorProducts->sum('subtotal_produk');
    
        return round($totalVendor); // Membulatkan hasil total produk
    }
    

    public function getSubtotalProdukAttribute()
    {
        $harga = floatval($this->harga); 
        $stok = intval($this->stok);
        $ongkir = floatval($this->ongkir);
    
        // Jika harga kosong, gunakan harga dari produk terkait
        if ($harga == 0) {
            $harga = $this->product->harga_product ? floatval($this->product->harga_product) : 0;
        }
    
        // Menggunakan ppn_value dari accessor getPpnDetailAttribute
        $ppnValue = $this->ppn_detail['ppn_value'];
    
        // Perhitungan subtotal
        return round(($harga * $stok) + $ongkir + $ppnValue);
    }

    // Model ProductCompanySpbproject
    public function spbProject()
    {
        return $this->belongsTo(SpbProject::class, 'spb_project_id', 'doc_no_spb');
    }

    // Tentukan relasi dengan Product (many-to-one)
    public function product()
    {
        return $this->belongsTo(Product::class, 'produk_id');
    }

    // Tentukan relasi dengan Company (many-to-one)
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function taxPpn(): HasOne
    {
        return $this->hasOne(Tax::class, 'id', 'ppn');
    }

    public function taxPph(): HasOne
    {
        return $this->hasOne(Tax::class, 'id', 'pph');
    }
}



  