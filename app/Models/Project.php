<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

    const ATTACHMENT_FILE = 'attachment/project/file';

    // Ini Buat Status Cost Progress
    const STATUS_OPEN = 'OPEN';
    const STATUS_CLOSED = 'CLOSED';
    const STATUS_NEED_TO_CHECK = 'NEED TO CHECK';

    // Ini Buat Melihat Status dari request_status_owner => user_id role => owner
    const PENDING = 1;
    const ACTIVE = 2;
    const REJECTED = 3;

    // Step Status Project
    const INFORMASI_PROYEK = 1;
    const PENGGUNA_MUATAN = 2;
    const PRATINJAU = 3;

    const DEFAULT_STATUS = self::PENDING;

    protected $table = 'projects';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'date',
        'name',
        'billing',
        'cost_estimate',
        'margin',
        'percent',
        'status_cost_progress',
        'file',
        'request_status_owner',
        'user_id',
        'status_step_project',
    ];


    /* Ngebuat Data ID Bisa String */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = 'PRO-' . date('y') . '-' . $model->generateSequenceNumber();
            $model->status = self::DEFAULT_STATUS;
        });
    }

    /* Ngebut Data Id Menjadi otomatis nambah */
    protected function generateSequenceNumber()
    {
        $lastId = static::max('id');
        $numericPart = (int) substr($lastId, strpos($lastId, '-0') + 1);
        $nextNumber = sprintf('%03d', $numericPart + 1);
        return $nextNumber;
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function spbProjects(): HasMany
    {
        return $this->hasMany(SpbProject::class, 'project_id', 'id');
    }

    public function updateStepStatus()
    {
        if (
            $this->company_id &&
            $this->name &&
            $this->billing &&
            $this->cost_estimate &&
            $this->margin &&
            $this->percent &&
            $this->file &&
            $this->date
        ) {
            $this->status_step_project = self::INFORMASI_PROYEK;
        }

        if (
            $this->produk_id &&
            $this->relationLoaded('spbProjects') &&
            $this->spbProjects->isNotEmpty()
        ) {
            $this->status_step_project = self::PENGGUNA_MUATAN;
        }

        if (
            $this->company_id &&
            $this->name &&
            $this->billing &&
            $this->cost_estimate &&
            $this->margin &&
            $this->percent &&
            $this->file &&
            $this->date &&
            $this->produk_id &&
            $this->relationLoaded('spbProjects') &&
            $this->spbProjects->isNotEmpty()
        ) {
            $this->status_step_project = self::PRATINJAU;
        }

        $this->save();
    }

    /* 
        public function purchases(): HasMany
        {
            return $this->hasMany(Purchase::class, 'project_id', 'id');
        } 
    */
}
