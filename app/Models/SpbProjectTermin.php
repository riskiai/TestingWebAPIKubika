<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpbProjectTermin extends Model
{
    use HasFactory;

    protected $fillable = [
        'doc_no_spb',
        'harga_termin',
        'deskripsi_termin',
        'tanggal',
        'type_termin_spb',
        'file_attachment_id'
    ];

    // Relasi ke model SpbProject
    public function spbProject()
    {
        return $this->belongsTo(SpbProject::class, 'doc_no_spb', 'doc_no_spb');
    }

    public function fileAttachment()
    {
        return $this->belongsTo(DocumentSPB::class, 'file_attachment_id', 'id');
    }
}
