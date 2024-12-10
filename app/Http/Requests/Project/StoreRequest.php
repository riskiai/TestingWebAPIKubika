<?php

namespace App\Http\Requests\Project;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StoreRequest extends FormRequest
{
/**
 * Determine if the user is authorized to make this request.
 */
public function authorize(): bool
{
    return true;
}

/**
 * Get the validation rules that apply to the request.
 *
 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
 */
public function rules(): array
{
    return [
        'company_id' => 'nullable|exists:companies,id', // Foreign key
        'user_id' => 'nullable|exists:users,id',       // Foreign key
        'produk_id' => 'nullable|exists:products,id',  // Foreign key
        'name' => 'nullable|string|unique:projects,name',
        'billing' => 'nullable|numeric',
        'cost_estimate' => 'nullable|numeric',
        'margin' => 'nullable|numeric',
        'percent' => 'nullable|numeric',
        'status_cost_progres' => 'nullable|string',
        'attachment_file' => 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls,heic|max:3072', 
        'attachment_file_spb' => 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls|max:3072',
        'date' => 'nullable|date',
        'request_status_owner' => 'nullable|string',
        'status_step_project' => 'nullable|string|max:100',
        'harga_type_project' => 'nullable|numeric',
            
        // Produk dan User ID harus berupa array
        'produk_id' => 'nullable|array',
        'produk_id.*' => 'nullable|exists:products,id|numeric|min:1',
    
        'user_id' => 'nullable|array',
        'user_id.*' => 'nullable|exists:users,id|numeric|min:1',

    ];
}

    /**
 * Menyiapkan data sebelum validasi untuk memastikan array.
 */
protected function prepareForValidation()
{
    // Mengubah produk_id dan user_id menjadi array jika hanya satu nilai yang diberikan
$this->merge([
    'produk_id' => is_array($this->input('produk_id')) ? $this->input('produk_id') : [$this->input('produk_id')],
    'user_id' => is_array($this->input('user_id')) ? $this->input('user_id') : [$this->input('user_id')],
]);
}

/**
 * Attribute names for better error messages.
 *
 * @return array<string, string>
 */
public function attributes(): array
{
    return [
        'company_id' => 'company',
        'produk_id' => 'product IDs',
        'user_id' => 'user IDs',
        'name' => 'project name',
        'billing' => 'billing amount',
        'cost_estimate' => 'cost estimate',
        'margin' => 'margin',
        'percent' => 'percent',
        'status_cost_progres' => 'status cost progress',
        'attachment_file' => 'attachment file',
        'date' => 'project date',
        'request_status_owner' => 'request status owner',
        'status_step_project' => 'status step project',
        'attachment_file_spb' => 'attachment file spb',
    ];
}

/**
 * Customize the response for validation failures.
 */
protected function failedValidation(Validator $validator)
{
    $response = new JsonResponse([
        'status' => MessageActeeve::WARNING,
        'status_code' => MessageActeeve::HTTP_UNPROCESSABLE_ENTITY,
        'message' => $validator->errors(),
    ], MessageActeeve::HTTP_UNPROCESSABLE_ENTITY);

    throw new ValidationException($validator, $response);
}
}


/* 
   // Pastikan ID proyek telah terisi
        if (!$project->id || $project->id === '0') {
            Log::error("Failed to generate valid project ID: " . $project->id);
            throw new \Exception("Failed to generate valid project ID.");
        }

        // Log ID proyek setelah berhasil disimpan
        Log::info("Project created successfully with ID: " . $project->id);

        // Jika produk_id atau user_id dikirimkan
        $produkIds = $request->input('produk_id', []); // Gunakan array kosong sebagai default jika tidak ada produk_id
        $userIds = $request->input('user_id', []); // Gunakan array kosong sebagai default jika tidak ada user_id

        // Hapus duplikat jika ada
        $produkIds = array_unique($produkIds);
        $userIds = array_unique($userIds);

        // Sinkronkan produk dan user data ke tabel pivot jika ada
        $project->product()->syncWithoutDetaching($produkIds);  // Sinkronkan produk tanpa menghapus data lain
        $project->tenagaKerja()->syncWithoutDetaching($userIds); // Sinkronkan tenaga kerja tanpa menghapus data lain 
    */