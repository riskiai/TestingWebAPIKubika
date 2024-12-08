<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'tab',
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
    ];

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
         return $this->belongsToMany(Company::class, 'product_company_spbproject', 'spb_project_id', 'company_id');
     }
 
     // Relasi SpbProject ke Product
     public function products()
     {
         return $this->belongsToMany(Product::class, 'product_company_spbproject', 'spb_project_id', 'produk_id');
     }

     public function company(): HasOne
     {
         return $this->hasOne(Company::class, 'id', 'company_id');
     }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // public function project()
    // {
    //     return $this->belongsToMany(Project::class, 'project_spb_project', 'spb_project_id', 'project_id');
    // }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id'); // Menggunakan 'project_id' di tabel spb_projects
    }

    public function logs()
    {
        return $this->hasMany(LogsSPBProject::class, 'spb_project_id', 'doc_no_spb');
    }

}
