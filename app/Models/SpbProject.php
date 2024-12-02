<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpbProject extends Model
{
    use HasFactory;

    protected $table = 'spb_projects';
    protected $primaryKey = 'doc_no_spb';
    public $incrementing = false;
    protected $keyType = 'string';

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
        'project_id',
        'produk_id',
        'unit_kerja',
        'tanggal_berahir_spb',
        'tanggal_dibuat_spb',
        'nama_toko',
        'reject_note',
        'know_marketing',
        'know_kepalagudang',
        'request_owner',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SpbProject_Category::class, 'spbproject_category_id', 'id');
    }

    public function status()
    {
        return $this->belongsTo(SpbProject_Status::class, 'spbproject_status_id');
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_spb_project', 'spb_project_id', 'product_id');
    }

    public function logs()
    {
        return $this->hasMany(LogsSPBProject::class, 'spb_project_id', 'doc_no_spb');
    }

}
