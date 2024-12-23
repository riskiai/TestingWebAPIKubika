<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ProductCompanySpbProject;


class SpbProject extends Model
{
    use HasFactory;

    protected $table = 'spb_projects';
    protected $primaryKey = 'doc_no_spb';
    public $incrementing = false;
    protected $keyType = 'string';

    const ATTACHMENT_FILE_SPB = 'attachment/spbproject';

    const TAB_SUBMIT = 1;
    const TAB_VERIFIED = 2;
    const TAB_PAYMENT_REQUEST = 3;
    const TAB_PAID = 4;

    protected $fillable = [
        'doc_no_spb',
        'doc_type_spb',
        'spbproject_category_id',
        'spbproject_status_id',
        'tab_spb',
        'user_id',
        'company_id',
        'project_id',
        'produk_id',
        'unit_kerja',
        'tanggal_berahir_spb',
        'tanggal_dibuat_spb',
        'nama_toko',
        'reject_note',
        'know_supervisor',
        'know_marketing',
        'know_kepalagudang',
        'request_owner',
        'file_pembayaran',
       /*  'subtotal',
        'ppn',
        'pph', */
        'updated_at',
    ];

    /* public function getSubtotal()
    {
        $subtotal = 0;  // Inisialisasi subtotal keseluruhan
    
        // Ambil semua data produk dan ongkir untuk spb_project_id tertentu
        $produkRelated = DB::table('product_company_spbproject')
                            ->where('spb_project_id', $this->doc_no_spb) // Gunakan doc_no_spb untuk mencari data
                            ->get();
    
        // Kelompokkan produk berdasarkan company_id (vendor_id)
        $produkByVendor = $produkRelated->groupBy('company_id');
    
        // Looping untuk menghitung subtotal per vendor
        foreach ($produkByVendor as $vendorId => $produkList) {
            $vendorSubtotal = 0;  // Inisialisasi subtotal untuk vendor
    
            // Hitung subtotal produk
            foreach ($produkList as $produk) {
                $product = DB::table('products')->find($produk->produk_id);  // Ambil detail produk
                $productPrice = (float) $product->harga;  // Ambil harga produk
                $vendorSubtotal += $productPrice;  // Tambahkan harga produk ke subtotal vendor
            }
    
            // Ambil ongkir untuk vendor ini
            $ongkir = (float) $produkList->first()->ongkir;  // Ambil ongkir (anggap ongkir per vendor sama)
            $vendorSubtotal += $ongkir;  // Tambahkan ongkir ke subtotal vendor
    
            // Tambahkan subtotal vendor ke subtotal keseluruhan
            $subtotal += $vendorSubtotal;
        }
    
        return round($subtotal);  // Mengembalikan subtotal keseluruhan yang sudah dibulatkan
    } */

    public function getTotalProdukAttribute()
    {
        // Ambil semua produk terkait dengan SPB Project ini
        $products = $this->productCompanySpbprojects;

        // Hitung total dari semua produk
        $grandTotal = $products->sum(function ($product) {
            return $product->total_produk; // Menggunakan atribut total_produk dari ProductCompanySpbProject
        });

        return round($grandTotal); // Pembulatan ke bilangan bulat
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SpbProject_Category::class, 'spbproject_category_id', 'id');
    }

    public function status()
    {
        return $this->belongsTo(SpbProject_Status::class, 'spbproject_status_id');
    }

        // Tambahkan relasi hasMany ke ProductCompanySpbProject
    public function productCompanySpbprojects()
    {
        return $this->hasMany(ProductCompanySpbproject::class, 'spb_project_id', 'doc_no_spb');
    }

    // Relasi dengan Vendor (Company)
    public function vendors()
    {
        return $this->belongsToMany(Company::class, 'product_company_spbproject', 'spb_project_id', 'company_id')
                    ->withPivot(['ongkir', 'harga', 'stok', 'ppn', 'status_produk', 'pph', 'note_reject_produk']);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_company_spbproject', 'spb_project_id', 'produk_id')
                    ->withPivot(['ongkir', 'harga', 'stok', 'ppn', 'status_produk', 'pph', 'note_reject_produk']);
    }

    public function taxPpn(): HasOne
    {
        return $this->hasOne(Tax::class, 'id', 'ppn');
    }

    public function taxPph(): HasOne
    {
        return $this->hasOne(Tax::class, 'id', 'pph');
    }

    public function documents()
    {
        return $this->hasMany(DocumentSPB::class, 'doc_no_spb', 'doc_no_spb');
    }

    public function document()
    {
        return $this->morphOne(DocumentSPB::class, 'documentable');
    }

     public function company(): HasOne
     {
         return $this->hasOne(Company::class, 'id', 'company_id');
     }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id'); // Menggunakan 'project_id' di tabel spb_projects
    }
    
    public function logs()
    {
        return $this->hasMany(LogsSPBProject::class, 'spb_project_id', 'doc_no_spb');
    }
    
    /* public function project()
    {
        return $this->belongsToMany(Project::class, 'project_spb_project', 'spb_project_id', 'project_id');
    } */
}
