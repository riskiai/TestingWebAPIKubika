<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectUserProduk extends Model
{
    use HasFactory;

    // Tentukan tabel yang digunakan
    protected $table = 'project_user_produk';

    // Tentukan kolom-kolom yang bisa diisi
    protected $fillable = [
        'project_id',   // ID proyek
        'user_id',      // ID pengguna
        'produk_id',    // ID produk
        'created_at',   // Waktu pembuatan
        'updated_at',   // Waktu update
    ];

    // Tentukan relasi dengan Project (many-to-one)
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    // Tentukan relasi dengan User (many-to-one)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Tentukan relasi dengan Product (many-to-one)
    public function product()
    {
        return $this->belongsTo(Product::class, 'produk_id', 'id');
    }
}
