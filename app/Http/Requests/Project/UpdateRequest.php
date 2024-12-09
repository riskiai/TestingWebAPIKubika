<?php

namespace App\Http\Requests\Project;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan untuk membuat request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     */
    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:companies,id',
            'name' => 'required|string',
            'billing' => 'required|numeric',
            'margin' => 'required|numeric',
            'cost_estimate' => 'required|numeric',
            'percent' => 'required|numeric',
            'date' => 'required|date',
            'harga_type_project' => 'nullable|numeric',
            'attachment_file' => 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls|max:3072',
            'attachment_file_spb' => 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls|max:3072',

            // Produk dan User ID harus berupa array
            'produk_id' => 'nullable|array',
            'produk_id.*' => 'exists:products,id|numeric|min:1',

            'user_id' => 'nullable|array',
            'user_id.*' => 'exists:users,id|numeric|min:1',
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
     * Mendapatkan nama atribut untuk digunakan dalam pesan error.
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'client name',
            'produk_id' => 'product IDs',
            'user_id' => 'user IDs',
            'attachment_file' => 'attachment file',
            'attachment_file_spb' => 'attachment file spb',
        ];
    }

    /**
     * Mengatur pengaturan ketika validasi gagal.
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
