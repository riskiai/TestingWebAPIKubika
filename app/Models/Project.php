<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

    const ATTACHMENT_FILE = 'attachment/project/file';
    const ATTACHMENT_FILE_SPB = 'attachment/project/spb_file';

    // Status Cost Progress
    const STATUS_OPEN = 'OPEN';
    const STATUS_CLOSED = 'CLOSED';
    const STATUS_NEED_TO_CHECK = 'NEED TO CHECK';

    // Status Request Owner
    const PENDING = 1;
    const ACTIVE = 2;
    const REJECTED = 3;

    // Step Status Project
    const INFORMASI_PROYEK = 1;
    const PENGGUNA_MUATAN = 2;
    const PRATINJAU = 3;

    const DEFAULT_STATUS = self::PENDING;
    const DEFAULT_STATUS_PROJECT = self::INFORMASI_PROYEK;

    protected $primaryKey = 'id'; // Set doc_no as the primary key
    public $incrementing = false; // Indicate that the primary key is not auto-incrementing
    protected $table = 'projects';
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'user_id',
        'produk_id', 
        'name',
        'billing',
        'cost_estimate',
        'margin',
        'percent',
        'status_cost_progress',
        'file',
        'spb_file',
        'date',
        'request_status_owner',
        'status_step_project',
    ];

    /**
     * Boot method untuk menangani generate ID dan status default.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                // Generate ID only once
                $model->id = self::generateSequenceNumber(); 
            }
            $model->request_status_owner = self::DEFAULT_STATUS;
            $model->status_step_project = self::DEFAULT_STATUS_PROJECT;
        });
    }

    /**
     * Generate ID urutan proyek
     */
    public static function generateSequenceNumber()
    {
        // Ambil proyek terakhir berdasarkan ID
        $lastProject = self::orderBy('id', 'desc')->first();
        Log::info("Last Project: " . json_encode($lastProject));
    
        // Validasi format ID terakhir
        if ($lastProject && !preg_match('/^PRO-\d{2}-\d+$/', $lastProject->id)) {
            Log::error("Invalid ID format detected: {$lastProject->id}");
            $lastProject = null;
        }
    
        // Ekstrak angka terakhir dari ID
        $numericPart = ($lastProject && preg_match('/PRO-\d{2}-(\d+)/', $lastProject->id, $matches))
            ? (int) $matches[1]
            : 0;
    
        // Tambahkan 1 ke angka terakhir
        $nextNumber = sprintf('%03d', $numericPart + 1);
    
        // Format ID baru
        $generatedId = 'PRO-' . date('y') . "-$nextNumber";
    
        Log::info("Generated sequence number: $generatedId");
        return $generatedId;
    }

    /**
     * Perbarui status proyek berdasarkan kondisi.
     */
    public function updateStepStatus()
    {
        if (empty($this->id) || $this->id === "0") {
            Log::error("Invalid Project ID detected during updateStepStatus. ID: {$this->id}");
            return;
        }
    
        // Muat relasi jika belum dimuat
        $this->loadMissing(['tenagaKerja', 'product']);  // Load the 'tenagaKerja' and 'product' relations
    
        $status = null;
    
        // Kondisi untuk Informasi Proyek
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
            $status = self::INFORMASI_PROYEK;
            Log::info("Status set to INFORMASI_PROYEK for Project ID: {$this->id}");
        }
    
        // Kondisi untuk Pengguna Muatan
        if (
            $this->tenagaKerja->isNotEmpty()  // Check if there are workers assigned to the project
            // $this->product->isNotEmpty()
        ) {
            $status = self::PENGGUNA_MUATAN;
            Log::info("Status set to PENGGUNA_MUATAN for Project ID: {$this->id}");
        }
    
        // Kondisi untuk Pratinjau (Jika sudah ada informasi proyek dan pengguna muatan)
        /* if (
            $this->company_id &&
            $this->name &&
            $this->billing &&
            $this->cost_estimate &&
            $this->margin &&
            $this->percent &&
            $this->file &&
            $this->date &&
            $this->tenagaKerja->isNotEmpty() &&  
            $this->product->isNotEmpty() 
        ) {
            $status = self::PRATINJAU;
            Log::info("Status set to PRATINJAU for Project ID: {$this->id}");
        } */
    
        if ($status !== null) {
            $this->status_step_project = $status;
            $this->save();
            Log::info("Successfully updated status_step_project to: $status for Project ID: {$this->id}");
        } else {
            Log::error("Failed to update status_step_project. No conditions met for Project ID: {$this->id}");
        }
    }

    // Pada model Project
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

     // Relasi many-to-many dengan User
    public function tenagaKerja()
    {
        return $this->belongsToMany(User::class, 'project_user_produk', 'project_id', 'user_id')
                    ->withPivot('produk_id'); // Menyimpan produk_id dalam pivot
    }

    // Relasi many-to-many dengan Product
    public function product()
    {
        return $this->belongsToMany(Product::class, 'project_user_produk', 'project_id', 'produk_id');
    }


    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    // public function spbProjects()
    // {
    //     return $this->belongsToMany(SpbProject::class, 'project_spb_project', 'project_id', 'spb_project_id');
    // }

        public function spbProjects()
    {
        return $this->hasMany(SpbProject::class, 'project_id', 'id'); // Project memiliki banyak SpbProject
    }


/* 
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'project_id', 'id');
    } 
*/
}
