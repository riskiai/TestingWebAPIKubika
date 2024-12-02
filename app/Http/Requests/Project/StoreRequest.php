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
            'attachment_file' => 'nullable|mimes:pdf,png,jpg,jpeg,xlsx,xls,heic|max:3072', // File attachment
            'date' => 'nullable|date',
            'request_status_owner' => 'nullable|string',
            'status_step_project' => 'nullable|string|max:100',
        ];
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
            'user_id' => 'user',
            'produk_id' => 'product',
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
