<?php

namespace App\Http\Requests\Admin\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Will fix later
        $user = $this->user();

        $tokenCan = $user->tokenCan('admin') || $user->tokenCan('super_admin');

        return $user != null && $tokenCan;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "name" => [
                "required",
                "string",
                "min:2",
                "max:100",
            ],
            "description" => [
                "required",
                "string",
                "min:10",
            ],
            "price" => [
                "required",
                "integer",
            ],
            "percentSale" => [
                "integer",
                "min:0",
                "max:100",
            ],
            "img" => [
                "required",
                "string",
            ],
            "quantity" => [
                "required",
                "integer"
            ],
            "categoryId" => [
                "required",
                "integer",
            ],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            // 'category_id' => $this->categoryId,
            'percent_sale' => $this->percentSale,
        ]);
    }
}
