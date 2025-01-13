<?php

namespace App\Http\Requests\SpbProject;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AddProdukRequest extends FormRequest
{
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
            'produk' => 'required|array',
            'produk.*.produk_id' => 'required|exists:products,id',  // Validasi produk_id
            'produk.*.vendor_id' => 'required|exists:companies,id', // Validasi vendor_id
            'produk.*.ongkir' => 'nullable|numeric|min:0',
            'produk.*.harga' => 'required|numeric|min:0',
            'produk.*.stok' => 'required|integer|min:0',
            'produk.*.tax_ppn' => 'nullable|numeric|min:0|max:100',
            'produk.*.description' => 'nullable|string|max:255',
            'produk.*.date' => 'nullable|date',
            'produk.*.due_date' => 'nullable|date',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = new JsonResponse([
            'status' => MessageActeeve::WARNING,
            'status_code' => MessageActeeve::HTTP_UNPROCESSABLE_ENTITY,
            'message' => $validator->errors()
        ], MessageActeeve::HTTP_UNPROCESSABLE_ENTITY);

        throw new ValidationException($validator, $response);
    }
}
