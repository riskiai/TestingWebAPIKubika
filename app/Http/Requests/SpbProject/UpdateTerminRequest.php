<?php

namespace App\Http\Requests\SpbProject;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateTerminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Anda bisa menambahkan logika otorisasi di sini jika diperlukan
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // Validasi untuk riwayat_termin yang merupakan array
            'riwayat_termin' => 'required|array',
            'riwayat_termin.*.id' => 'required|exists:spb_project_termins,id',
            'riwayat_termin.*.harga_termin' => 'required|numeric|min:0',
            'riwayat_termin.*.deskripsi_termin' => 'required|string|max:255',
            'riwayat_termin.*.type_termin_spb' => 'nullable|in:1,2', // Validasi yang kamu inginkan
            'riwayat_termin.*.tanggal' => 'required|date',
            'riwayat_termin.*.attachment_file_spb' => 'nullable|array',
            'riwayat_termin.*.attachment_file_spb.*' => 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls|max:3072',
        ];

        // Validasi file attachment jika ada
        /* if ($this->hasFile('attachment_file_spb')) {
            $rules['attachment_file_spb'] = 'array';
            $rules['attachment_file_spb.*'] = 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls,heic|max:3072';
        } */

        return $rules;
    }


    /**
     * Customize the failed validation response.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return \Illuminate\Http\JsonResponse
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
