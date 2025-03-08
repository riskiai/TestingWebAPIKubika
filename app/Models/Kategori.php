<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; // Import Carbon untuk tanggal
use Illuminate\Database\Eloquent\SoftDeletes;

class Kategori extends Model
{
    use HasFactory, SoftDeletes;

    protected $dates = ['deleted_at']; 
    // Tentukan nama tabel yang sesuai di database
    protected $table = 'kategori';

    // Tentukan kolom-kolom yang bisa diisi secara massal
    protected $fillable = [
        'name',
        'kode_kategori', // Tambahkan kode_kategori di sini
    ];

    // Overriding method boot untuk menggenerate kode_kategori secara otomatis
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->kode_kategori = $model->generateKodeKategori();
        });
    }

    // Fungsi untuk generate kode_kategori
    public function generateKodeKategori()
    {
        $date = Carbon::now()->format('d-m-Y');
        
        // Mengambil huruf pertama, tengah, dan terakhir dari nama kategori
        $name = str_replace(' ', '', $this->name); // Hilangkan spasi
        $firstChar = substr($name, 0, 1);
        $middleChar = substr($name, (int)(strlen($name) / 2), 1);
        $lastChar = substr($name, -1);

        // Gabungkan menjadi kode singkatan
        $nameSlug = strtoupper($firstChar . $middleChar . $lastChar);

        // Mengambil nomor increment dari database
        $lastCategory = self::latest('id')->first();
        $nextNumber = $lastCategory ? sprintf('%03d', intval(substr($lastCategory->kode_kategori, 4, 3)) + 1) : '001';

        return 'KTG-' . $nextNumber . '-' . $nameSlug . '-' . $date;
    }

     // Relasi ke produk
     public function products()
     {
         return $this->hasMany(Product::class, 'id_kategori');
     }
}
