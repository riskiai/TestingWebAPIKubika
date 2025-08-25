<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use HasFactory, SoftDeletes;

    const TAX_PPN = "ppn";
    const TAX_PPH = "pph";

    protected $table = 'taxs';

    protected $fillable = [
        'name',
        'description',
        'percent',
        'type',
        'deleted_by', // <-- tambahkan
    ];

    protected $dates = ['deleted_at'];

    protected static function booted(): void
    {
        // Saat di-restore, kosongkan deleted_by supaya tidak menyesatkan
        static::restoring(function (Tax $tax) {
            $tax->deleted_by = null;
        });
    }
}
