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
            'spbproject_category_id' => 'nullable|exists:spb_project__categories,id',
            'project_id' => 'nullable|string|max:255',
            'produk_id' => 'required|array',
            'produk_id.*' => 'exists:products,id|numeric|min:1',
            'tanggal_berahir_spb' => 'nullable|date',
            'tanggal_dibuat_spb' => 'nullable|date',
            'unit_kerja' => 'nullable|string|max:255',
            'nama_toko' => 'nullable|string|max:255',
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
