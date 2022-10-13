<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "first_name" => [
                "required",
                "string",
                "min:2",
                "max:50",
            ],
            "last_name" => [
                "required",
                "string",
                "min:2",
                "max:50",
            ],
            "email" => [
                "required",
                "email",
            ],
            "password" => [
                "required",
                "min:6",
                "max:24",
            ],
            "phone_number" => [
                "required",
                "string",
            ],
        ];
    }
}