<?php

namespace App\Http\Requests\User;

use App\Facades\MessageActeeve;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateNotLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|exists:roles,id',
            'divisi' => 'nullable|exists:divisis,id',
            'daily_salary' => 'required|numeric|min:0',
            'hourly_salary' => 'required|numeric|min:0',
            'hourly_overtime_salary' => 'required|numeric|min:0',
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
