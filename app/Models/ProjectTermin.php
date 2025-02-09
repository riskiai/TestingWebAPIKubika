<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTermin extends Model
{
    use HasFactory;

    protected $table = 'project_termins';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'project_id',
        'harga_termin',
        'deskripsi_termin',
        'type_termin',
        'file_attachment_pembayaran', // Harus berupa string
        'tanggal_payment'
    ];

    /**
     * Pastikan `file_attachment_pembayaran` selalu string.
     */
    public function getFileAttachmentPembayaranAttribute($value)
    {
        return is_null($value) ? null : (string) $value;
    }

    /**
     * Relasi ke model Project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * Konversi `type_termin` ke dalam format array yang benar.
     */
    public function getTypeTerminAttribute()
    {
        $status = $this->attributes['type_termin'] ?? null;

        if (is_null($status)) {
            return null;
        }

        return [
            'id' => (string) $status,
            'name' => $status == Project::TYPE_TERMIN_PROYEK_LUNAS ? 'Lunas' : 'Belum Lunas',
        ];
    }
}
