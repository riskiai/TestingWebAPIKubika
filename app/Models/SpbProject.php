<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpbProject extends Model
{
    use HasFactory;

    // Tentukan tabel yang terkait dengan model ini
    protected $table = 'spb_projects';

    // Tentukan kolom yang dapat diisi secara massal
    protected $fillable = [
        'spbproject_category_id',
        'spbproject_status_id',
        'user_id',
        'project_id',
        'produk_id',
        'unit_kerja',
        'tanggal_dibuat_spb',
        'nama_barang',
        'type_pembelian',
        'jumlah_barang',
        'nama_toko',
        'keterangan',
    ];

    /**
     * Relasi ke kategori SPB (many to one).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SpbProject_Category::class, 'spbproject_category_id', 'id');
    }

    /**
     * Relasi ke status SPB (many to one).
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(SpbProject_Status::class, 'spbproject_status_id', 'id');
    }

    /**
     * Relasi ke pengguna (many to one).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Relasi ke proyek (many to one).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * Relasi ke produk (many to one).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'produk_id', 'id');
    }
}
