<?php

namespace App\Http\Requests\Project;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdatePengunaMuatanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Proses data sebelum validasi.
     */
    protected function prepareForValidation()
    {
        // Mengubah produk_id dan user_id menjadi array jika hanya satu nilai yang diberikan
        $this->merge([
            // 'produk_id' => is_array($this->input('produk_id')) ? $this->input('produk_id') : [$this->input('produk_id')],
            'user_id' => is_array($this->input('user_id')) ? $this->input('user_id') : [$this->input('user_id')],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'produk_id' => 'required|array',
            // 'produk_id.*' => 'exists:products,id|numeric|min:1', 
            'attachment_file_spb' => 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls|max:3072',

            'user_id' => 'required|array',
            'user_id.*' => 'exists:users,id|numeric|min:1', // Validasi tambahan untuk user_id
        ];
    }

    public function attributes()
    {
        return [
            // 'produk_id' => 'product ID',
            'user_id' => 'user ID',
            'attachment_file_spb' => 'attachment file spb',
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
