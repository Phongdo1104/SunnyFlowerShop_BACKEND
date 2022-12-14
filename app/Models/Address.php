<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        "customer_id",
        "first_name_receiver",
        "last_name_receiver",
        "phone_receiver",
        "street_name",
        "district",
        "ward",
        "city"
    ];

    public function customers() {
        return $this->belongsTo(Customer::class);
    }
}
