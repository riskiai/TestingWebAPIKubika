<?php

namespace App\Http\Requests\SpbProject;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class CreateRequest extends FormRequest
{
public function authorize(): bool
{
    return true;
}

protected function prepareForValidation()
{
    // Pastikan semua data dalam bentuk array yang sesuai
    $this->merge([
        'produk' => is_array($this->input('produk')) ? $this->input('produk') : [],
    ]);
}

public function rules(): array
    {
        return [
            'spbproject_category_id' => 'required|exists:spb_project__categories,id',
            'project_id' => 'nullable|string|max:255',
            'tanggal_dibuat_spb' => 'required|date',
            'tanggal_berahir_spb' => 'required|date',
            'unit_kerja' => 'nullable|string|max:255',
            'harga_total_pembayaran_borongan_spb' => 'nullable|numeric|min:0',
            'type_termin_spb' => 'nullable|in:1,2',
            'vendor_borongan_id' => 'nullable|exists:companies,id',
            'produk_data' => 'nullable|array',
            'produk_data.*.produk_id' => 'required|exists:products,id',
            'produk_data.*.vendor_id' => 'required|exists:companies,id',
            'produk_data.*.ongkir' => 'nullable|numeric|min:0',
            'produk_data.*.harga' => 'nullable|numeric|min:0',
            'produk_data.*.stok' => 'nullable|integer|min:0',
            'produk_data.*.description' => 'nullable|string|max:255',
            'produk_data.*.tax_ppn' => 'nullable|numeric|min:0|max:100',
            'produk_data.*.date' => 'nullable|date',
            'produk_data.*.due_date' => 'nullable|date',
        ];
    }


public function attributes(): array
{
    return [
        'spbproject_category_id' => 'SPB project category',
        'project_id' => 'project ID',
        'produk' => 'products',
        'tanggal_berahir_spb' => 'SPB expiry date',
        'tanggal_dibuat_spb' => 'SPB creation date',
        'unit_kerja' => 'work unit',
    ];
}

protected function failedValidation(Validator $validator)
{
    $response = new JsonResponse([
        'status' => 'error',
        'message' => $validator->errors(),
    ], 422);

    throw new ValidationException($validator, $response);
}
}
