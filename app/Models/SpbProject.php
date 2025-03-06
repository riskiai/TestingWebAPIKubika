<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductCompanySpbProject;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes; 


class SpbProject extends Model
{
    use HasFactory, SoftDeletes;
   

    protected $table = 'spb_projects';
    protected $primaryKey = 'doc_no_spb';
    public $incrementing = false;
    protected $keyType = 'string';

    const ATTACHMENT_FILE_SPB = 'attachment/spbproject';

    const TAB_SUBMIT = 1;
    const TAB_VERIFIED = 2;
    const TAB_PAYMENT_REQUEST = 3;
    const TAB_PAID = 4;

    const TEXT_PROJECT_SPB = "Project";
    const TEXT_NON_PROJECT_SPB = "Non-Project";

    const TYPE_PROJECT_SPB = 1;
    const TYPE_NON_PROJECT_SPB = 2;
    
    const TYPE_TERMIN_BELUM_LUNAS = 1;
    const TYPE_TERMIN_LUNAS = 2;

    protected $fillable = [
        'doc_no_spb',
        'doc_type_spb',
        'type_project',
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
        'know_finance',
        'request_owner',
        'approve_date',
        'file_pembayaran',
        'harga_total_pembayaran_borongan_spb',
        'harga_termin_spb',
        'deskripsi_termin_spb',
        'type_termin_spb',
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

    public function getTypeProjectNameAttribute()
    {
        return match ($this->type_project) {
            self::TYPE_PROJECT_SPB => 'Project',
            self::TYPE_NON_PROJECT_SPB => 'Non Project',
            default => 'Unknown',
        };
    }

    public function getSubtotalHargaTotalPembayaranBoronganSpbAttribute(): float
    {
        return (float) $this->where('spbproject_category_id', SpbProject_Category::BORONGAN)
            ->sum('harga_total_pembayaran_borongan_spb');
    }

    public function getSubtotalHargaTerminSpbAttribute(): float
    {
        return (float) $this->where('spbproject_category_id', SpbProject_Category::BORONGAN)
            ->where('type_termin_spb', self::TYPE_TERMIN_LUNAS)
            ->sum('harga_termin_spb');
    }

    public function getTotalVendor($spbProjectId)
    {
        // Mengambil produk yang terkait dengan SPB Project dan Vendor tertentu
        $vendorProducts = ProductCompanySpbProject::where('spb_project_id', $spbProjectId)
                                                ->where('company_id', $this->company_id) // Menyaring berdasarkan company_id vendor
                                                ->get();

        // Hitung total subtotal produk yang terkait dengan vendor dan SPB Project
        $totalVendor = $vendorProducts->sum('subtotal_produk');

        return round($totalVendor); // Membulatkan hasil total produk
    }


    public function totalTerbayarProductVendor()
    {
        // Filter produk yang status_vendor-nya "Paid"
        $paidProducts = $this->productCompanySpbprojects->filter(function ($product) {
            return $product->status_vendor === ProductCompanySpbProject::TEXT_PAID_PRODUCT;
        });

        // Hitung total subtotal produk yang status_vendor-nya "Paid"
        $totalPaid = $paidProducts->sum(function ($product) {
            return $product->subtotal_produk;
        });

        return round($totalPaid); // Membulatkan total yang terbayar
    }

    // Method untuk menghitung sisa pembayaran
    public function sisaPembayaranProductVendor()
    {
        // Ambil total produk dari SPB Project
        $totalProduk = $this->getTotalProdukAttribute();

        // Ambil total yang sudah terbayar menggunakan totalTerbayarProductVendor
        $totalTerbayar = $this->totalTerbayarProductVendor();

        // Hitung sisa pembayaran
        $sisaPembayaran = $totalProduk - $totalTerbayar;

        // Pastikan sisa pembayaran tidak negatif
        return max(0, round($sisaPembayaran)); // Membulatkan dan memastikan nilai minimal adalah 0
    }
    
    public function getTotalProdukAttribute()
    {
        // Ambil semua produk terkait dengan SPB Project ini
        $products = $this->productCompanySpbprojects;

        // Hitung total dari semua produk
        $grandTotal = $products->sum(function ($product) {
            return $product->subtotal_produk;
        });

        return round($grandTotal); // Membulatkan hasil total ke bilangan bulat
    }

   /*  public function getTotalProdukAttribute()
    {
        // Ambil semua produk terkait dengan SPB Project ini
        $products = $this->productCompanySpbprojects;

        // Mendapatkan tanggal filter dari request, jika ada
        $tanggalDateProduk = $this->tanggal_date_produk ?? null;
        $tanggalDueDateProduk = $this->tanggal_due_date_produk ?? null;

        // Jika ada filter tanggal diterima, sesuaikan produk yang dihitung
        if ($tanggalDateProduk && $tanggalDueDateProduk) {
            $products = $products->filter(function ($product) use ($tanggalDateProduk, $tanggalDueDateProduk) {
                return Carbon::parse($product->date)->between($tanggalDateProduk, $tanggalDueDateProduk) ||
                    Carbon::parse($product->due_date)->between($tanggalDateProduk, $tanggalDueDateProduk);
            });
        } elseif ($tanggalDateProduk) {
            $products = $products->filter(function ($product) use ($tanggalDateProduk) {
                return Carbon::parse($product->date)->isSameDay($tanggalDateProduk) ||
                    Carbon::parse($product->due_date)->isSameDay($tanggalDateProduk);
            });
        } elseif ($tanggalDueDateProduk) {
            $products = $products->filter(function ($product) use ($tanggalDueDateProduk) {
                return Carbon::parse($product->due_date)->isSameDay($tanggalDueDateProduk);
            });
        }

        // Hitung total dari produk yang sudah difilter
        $grandTotal = $products->sum(function ($product) {
            return $product->subtotal_produk;
        });

        return round($grandTotal); // Membulatkan hasil total ke bilangan bulat
    } */

    public function category(): BelongsTo
    {
        return $this->belongsTo(SpbProject_Category::class, 'spbproject_category_id', 'id');
    }

    public function status()
    {
        return $this->belongsTo(SpbProject_Status::class, 'spbproject_status_id');
    }

    public function termins()
    {
        return $this->hasMany(SpbProjectTermin::class, 'doc_no_spb', 'doc_no_spb');
    }

    public function productCompanySpbprojects()
    {
        return $this->hasMany(ProductCompanySpbproject::class, 'spb_project_id', 'doc_no_spb');
    }

    public function vendors()
    {
        return $this->belongsToMany(Company::class, 'product_company_spbproject', 'spb_project_id', 'company_id')
                    ->withPivot(['ongkir', 'harga', 'stok', 'ppn', 'status_produk', 'pph', 'note_reject_produk', 'description', 'payment_date', 'file_payment', 'status_vendor']);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_company_spbproject', 'spb_project_id', 'produk_id')
                    ->withPivot(['ongkir', 'harga', 'stok', 'ppn', 'status_produk', 'pph', 'note_reject_produk', 'description', 'payment_date', 'file_payment', 'status_vendor', ]);
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
        return $this->belongsTo(Project::class, 'project_id', 'id'); 
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
