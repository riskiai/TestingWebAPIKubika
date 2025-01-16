<?php

namespace App\Http\Requests\SpbProject;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Models\SpbProject_Category;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'spbproject_category_id' => 'required|exists:spb_project__categories,id',
            'project_id' => 'nullable|string|max:255',
            'tanggal_dibuat_spb' => 'required|date',
            'tanggal_berahir_spb' => 'required|date',
            'type_project' => 'nullable|in:1,2',
            'unit_kerja' => 'nullable|string|max:255',
           
        ];

        if ($this->spbproject_category_id == SpbProject_Category::BORONGAN) {
            $rules['harga_total_pembayaran_borongan_spb'] = 'nullable|numeric|min:0';
            $rules['harga_termin_spb'] = 'nullable|numeric|min:0';
            $rules['deskripsi_termin_spb'] = 'nullable|string|max:255';
            $rules['type_termin_spb'] = 'nullable|in:1,2';
        }
        

        if ($this->hasFile('attachment_file_spb')) {
            $rules['attachment_file_spb'] = 'array';
            $rules['attachment_file_spb.*'] = 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls,heic|max:3072';
        }

        return $rules;
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
