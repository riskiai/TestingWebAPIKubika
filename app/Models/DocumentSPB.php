<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSPB extends Model
{
    use HasFactory;

    protected $table = 'document_spb';
    protected $guarded = [];

    public function spbProject()
    {
        return $this->hasOne(SpbProject::class, 'doc_no_spb', 'doc_no_spb');
    }
}
