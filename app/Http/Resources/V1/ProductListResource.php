<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            // "description" => $this->description,
            "price" => $this->price,
            "percentSale" => $this->percent_sale,
            "img" => $this->img,
            "quantity" => $this->quantity,
            "status" => $this->status,
            "deletedAt" => $this->deleted_at,
            "createdAt" => date_format($this->created_at, "d/m/Y"),
            "categories" => CategoryListResource::collection($this->categories)
        ];
    }
}
