<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentSPB extends Model
{
    use HasFactory;
    // 

    protected $table = 'document_spb';
    protected $guarded = [];

    public function spbProject()
    {
        return $this->hasOne(SpbProject::class, 'doc_no_spb', 'doc_no_spb');
    }

     // Event boot untuk menghapus file fisik saat document dihapus
     protected static function boot()
     {
         parent::boot();
 
         // Menghapus file fisik saat document dihapus
         static::deleting(function($document) {
             // Pastikan file path ada dan file fisik benar-benar ada
             if (Storage::disk('public')->exists($document->file_path)) {
                 Storage::disk('public')->delete($document->file_path); // Menghapus file fisik
             }
         });
     }
}
