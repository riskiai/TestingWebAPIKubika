<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; // Import Carbon untuk tanggal

class Product extends Model
{
    use HasFactory;

    // Tentukan nama tabel yang sesuai di database
    protected $table = 'products';

    // Tentukan kolom-kolom yang bisa diisi secara massal
    protected $fillable = [
        'nama',
        'id_kategori',
        'deskripsi',
        'kode_produk',
        'stok',
    ];

    // Relasi ke model Kategori (many to one)
    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'id_kategori');
    }

    // Overriding method boot untuk menggenerate kode_produk secara otomatis
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->kode_produk = $model->generateKodeProduk();
        });
    }

    // Fungsi untuk generate kode_produk
    public function generateKodeProduk()
    {
        $date = Carbon::now()->format('d-m-Y');

        // Mengambil huruf pertama, tengah, dan terakhir dari nama produk
        $name = str_replace(' ', '', $this->nama); // Hilangkan spasi
        $firstChar = substr($name, 0, 1);
        $middleChar = substr($name, (int)(strlen($name) / 2), 1);
        $lastChar = substr($name, -1);

        // Gabungkan menjadi kode singkatan
        $nameSlug = strtoupper($firstChar . $middleChar . $lastChar);

        // Mengambil nomor increment dari database
        $lastProduct = self::latest('id')->first();
        $nextNumber = $lastProduct ? sprintf('%03d', intval(substr($lastProduct->kode_produk, 4, 3)) + 1) : '001';

        return 'PRD-' . $nextNumber . '-' . $nameSlug . '-' . $date;
    }
}
