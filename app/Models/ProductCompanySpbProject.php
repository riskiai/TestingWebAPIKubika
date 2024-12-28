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
        // 'type_pembelian_produk',
    ];

    /**
     * Perhitungan nilai PPN
     */
    /**
     * Perhitungan nilai PPN
     */
    public function getPpnValueAttribute()
    {
        $harga = floatval($this->harga);
        $stok = intval($this->stok);
        $ppn = floatval($this->ppn);
    
        // Subtotal sebelum PPN: hanya (harga * stok)
        $subtotalSebelumPpn = $harga * $stok;
    
        // Kalkulasi PPN
        return $ppn > 0 ? round(($subtotalSebelumPpn * $ppn) / 100) : 0;
    }
    
    
    /**
     * Perhitungan subtotal produk (harga * stok + ongkir + PPN)
     */
    public function getSubtotalProdukAttribute()
    {
        $harga = floatval($this->harga);
        $stok = intval($this->stok);
        $ongkir = floatval($this->ongkir);
        $ppnValue = $this->ppn_value;
    
        return round(($harga * $stok) + $ongkir + $ppnValue); 
    }

    /**
     * Perhitungan total produk (subtotal - PPH)
     */
   /*  public function getTotalProdukAttribute()
    {
        $subtotal = $this->subtotal_produk;
        $pphValue = $this->pph_value;

        return round($subtotal - $pphValue); 
    } */

     /**
     * Perhitungan nilai PPH
     */
    /* public function getPphValueAttribute()
    {
        $subtotal = $this->subtotal_produk;
        $pphPercent = $this->taxPph ? $this->taxPph->percent : 0;

        return $pphPercent > 0 ? round(($subtotal * $pphPercent) / 100) : 0; // Pembulatan ke bilangan bulat
    } */

    
    // Tentukan relasi dengan SPBProject (many-to-one)
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



  