<?php

namespace App\Http\Requests\SpbProject;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        // Pastikan produk_id selalu menjadi array, meskipun hanya ada satu nilai
        $this->merge([
            'produk_id' => is_array($this->input('produk_id')) ? $this->input('produk_id') : [$this->input('produk_id')],
        ]);
    }

    public function rules(): array
    {
        return [
            'spbproject_category_id' => 'required|exists:spb_project__categories,id',
            'project_id' => 'required|string|max:255',
            'tanggal_dibuat_spb' => 'required|date',
            'tanggal_berahir_spb' => 'required|date',
            'unit_kerja' => 'required|string|max:255',
            'produk' => 'required|array',
            'produk.*.produk_data.*.produk_id' => 'required|exists:products,id',
            'produk.*.produk_data.*.vendor_id' => 'required|exists:companies,id',
            'produk.*.produk_data.*.ongkir' => 'nullable|numeric|min:0',
            'produk.*.produk_data.*.harga' => 'required|numeric|min:0',
            'produk.*.produk_data.*.stok' => 'required|integer|min:0',
            'produk.*.produk_data.*.tax_ppn' => 'nullable|numeric|min:0|max:100',
            'produk.*.produk_data.*.date' => 'nullable|date',
            'produk.*.produk_data.*.due_date' => 'nullable|date',
        ];
    }

    public function attributes(): array
    {
        return [
            'spbproject_category_id' => 'SPB project category',
            'project_id' => 'project ID',
            'produk_id' => 'product ID',
            'tanggal_berahir_spb' => 'SPB expiry date',
            'tanggal_dibuat_spb' => 'SPB creation date',
            'unit_kerja' => 'work unit',
            'nama_toko' => 'store name',
        ];
    }

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
