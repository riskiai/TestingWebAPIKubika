<?php

// app/Models/ProductCompanySpbProject.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCompanySpbProject extends Model
{
    use HasFactory;

    // Tentukan nama tabel pivot
    protected $table = 'product_company_spbproject';

    // Tentukan kolom-kolom yang bisa diisi
    protected $fillable = [
        'spb_project_id', // ID proyek SPB
        'produk_id',      // ID produk yang terhubung
        'company_id',     // ID perusahaan/vendor yang terkait
    ];

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
}
