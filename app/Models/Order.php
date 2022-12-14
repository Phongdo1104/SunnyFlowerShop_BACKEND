<?php

namespace App\Models;

use App\Mail\PlaceOrderMail;
use App\Notifications\PlaceOrderNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        "customer_id",
        "voucher_id",
        "id_delivery",
        "date_order",
        "address",
        "name_receiver",
        "phone_receiver",
        "total_price",
        "paid_type",
        "deleted_by",
    ];

    public function customers()
    {
        return $this->belongsTo(Customer::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, "order_product", "order_id", "product_id")->withPivot("quantity", "price", "percent_sale");
    }

    public function vouchers()
    {
        return $this->belongsTo(Voucher::class);
    }
}
