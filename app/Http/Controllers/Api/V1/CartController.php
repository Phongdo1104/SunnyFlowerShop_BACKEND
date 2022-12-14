<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Delete\DeleteCustomerRequest;
use App\Http\Requests\Customer\Get\GetCustomerBasicRequest;
use App\Http\Requests\Customer\Store\StoreProductToCartRequest;
use App\Http\Requests\Customer\Update\UpdateProductToCartRequest;
use App\Http\Resources\V1\CartViewResource;
use App\Http\Resources\V1\CustomerOverviewCollection;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    // NEED TO RECONSIDER ADDING "CREATED_AT" & "UDPATED_ATT" COLUMN TO TABLE
    // REASON FOR CHECKING USER ACTIVITIES TO MAKE A DECISION TO FREE UP SPACE IN DATABASE VIA PIVOT CART TABLE

    // Paginator function
    public function paginator($arr, $request)
    {
        $total = count($arr);
        $per_page = 5;
        $current_page = $request->input("page") ?? 1;

        $starting_point = ($current_page * $per_page) - $per_page;

        $arr = array_slice($arr, $starting_point, $per_page, true);

        $arr = new LengthAwarePaginator($arr, $total, $per_page, $current_page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return $arr;
    }

    /** Admin & CUSTOMER FUNCTION */
    public function generateProductsArray(GetCustomerBasicRequest $request)
    {
        $customer = Customer::where("id", "=", $request->user()->id)->first();
        // $customer['products'] = $customer->customer_product_cart;
        $products_in_cart = $customer->customer_product_cart;

        $arr = [];
        // $arr['customer_id'] = $customer->id;

        for ($i = 0; $i < sizeof($products_in_cart); $i++) {
            $arr[$i]['id'] = $products_in_cart[$i]['id'];
            $arr[$i]['name'] = $products_in_cart[$i]['name'];
            $arr[$i]['description'] = $products_in_cart[$i]['description'];
            $arr[$i]['price'] = $products_in_cart[$i]['price'];
            $arr[$i]['percentSale'] = $products_in_cart[$i]['percentSale'];
            $arr[$i]['img'] = $products_in_cart[$i]['img'];
            $arr[$i]['quantity'] = $products_in_cart[$i]['pivot']->quantity;
            $arr[$i]['status'] = $products_in_cart[$i]['status'];
            $arr[$i]['deletedAt'] = $products_in_cart[$i]['deleted_at'];
            $categories = DB::table("category_product")
                ->where("product_id", "=", $products_in_cart[$i]['id'])
                ->get();

            for ($j = 0; $j < sizeof($categories); $j++) {
                $category = Category::where("id", "=", $categories[$j]->category_id)
                    ->first();

                $arr[$i]['categories'][$j]['id'] = $category->id;
                $arr[$i]['categories'][$j]['name'] = $category->name;
            }
            // $arr[$i]['categories'] = 
        }

        // return $customer;
        return $arr;
    }

    public function index(GetCustomerBasicRequest $request)
    {
        $check = DB::table("customer_product_cart")
            ->where("customer_id", "=", $request->user()->id)->exists();

        // If cart is empty
        if (!$check) {
            return response()->json([
                "success" => false,
                "errors" => "Gi??? h??ng hi???n ??ang tr???ng."
            ]);
        }

        $arr = $this->generateProductsArray($request);

        // if state is "all" then return all
        if ($request->state === "all") {
            return [
                "total" => sizeof($arr),
                "data" => $arr
            ];
        }

        $new_arr = $this->paginator($arr, $request);
        return [
            "total" => sizeof($arr),
            "data" => $new_arr
        ];
    }

    /** CUSTOMER FUNCTION */
    public function store(StoreProductToCartRequest $request)
    {
        if ($request->quantity < 0) {
            return response()->json([
                "success" => false,
                "errors" => "Kh??ng th??? th??m s???n ph???m v???i s??? l?????ng l?? s??? ??m."
            ]);
        }

        $customer = Customer::find($request->user()->id);

        $product = Product::find($request->product_id);

        if (empty($product)) {
            return response()->json([
                "success" => false,
                "errors" => "Vui l??ng ki???m tra l???i ID S???n ph???m."
            ]);
        }

        if ($product->quantity < $request->quantity) {
            return response()->json([
                "success" => false,
                "errors" => "S??? l?????ng s???n ph???m ???? g???n h???t, vui l??ng gi???m s??? l?????ng s???n ph???m tr?????c khi th??m v??o gi??? h??ng."
            ]);
        }

        $data = DB::table("customer_product_cart")->where("customer_id", "=", $customer->id);

        $check = $data->where("product_id", "=", $product->id)->exists();

        if (empty($check)) {
            $customer->customer_product_cart()->attach($product, [
                "quantity" => $request->quantity
            ]);

            return response()->json([
                "success" => true,
                "message" => "Th??m s???n ph???m v??o gi??? h??ng th??nh c??ng."
            ]);
        } else {
            $data = $data->where("product_id", "=", $request->product_id)->first();

            $total = $data->quantity + $request->quantity;
            if ($total > $product->quantity) {
                return response()->json([
                    "success" => false,
                    "errors" => "T???ng s??? l?????ng s???n ph???m ???? ?????n gi???i h???n, vui l??ng gi???m s??? l?????ng s???n ph???m."
                ]);
            }

            $result = $customer->customer_product_cart()->updateExistingPivot($product, [
                "quantity" => $total
            ]);

            if (!$result) {
                return response()->json([
                    "success" => false,
                    "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
                ]);
            }

            return response()->json([
                "success" => true,
                "message" => "C???p nh???t th??nh c??ng s??? l?????ng S???n ph???m c?? ID = " . $product->id
            ]);
        }
    }

    // 1 quantity at a the time for each product
    public function singleQuantity(GetCustomerBasicRequest $request)
    {
        if ($request->quantity < 0) {
            return response()->json([
                "success" => false,
                "errors" => "S??? l?????ng s???n ph???m kh??ng h???p l???."
            ]);
        }

        $customer = Customer::find($request->user()->id);

        $product = Product::find($request->id);

        if (empty($product)) {
            return response()->json([
                "success" => false,
                "errors" => "Vui l??ng ki???m tra l???i ID S???n ph???m."
            ]);
        }

        $data = DB::table("customer_product_cart")->where("customer_id", "=", $customer->id);

        $check = $data->where("product_id", "=", $product->id)->exists();

        // Check total quantity of product has exceeded quantity limit (10 quantity per product in cart)
        if ($check) {
            $productQuantity = $data->where("product_id", "=", $product->id)->first();

            if ($productQuantity->quantity >= 10) {
                return response()->json([
                    "success" => false,
                    "message" => "M???t s???n ph???m trong gi??? h??ng ch??? ???????c th??m t???i ??a 10 s??? l?????ng tr??n 1 s???n ph???m."
                ]);
            }
        }

        if ($product->quantity < $request->quantity) {
            return response()->json([
                "success" => false,
                "errors" => "S??? l?????ng s???n ph???m ???? g???n h???t, vui l??ng gi???m s??? l?????ng s???n ph???m tr?????c khi th??m v??o gi??? h??ng."
            ]);
        }

        if (empty($check)) {
            $customer->customer_product_cart()->attach($product, [
                "quantity" => 1
            ]);

            return response()->json([
                "success" => true,
                "message" => "Th??m s???n ph???m v??o gi??? h??ng th??nh c??ng."
            ]);
        } else {
            $data = $data->where("product_id", "=", $request->id)->first();

            $total = $data->quantity + 1;
            if ($total > $product->quantity) {
                return response()->json([
                    "success" => false,
                    "errors" => "T???ng s??? l?????ng s???n ph???m ???? ?????n gi???i h???n, vui l??ng gi???m s??? l?????ng s???n ph???m."
                ]);
            }

            $result = $customer->customer_product_cart()->updateExistingPivot($product, [
                "quantity" => $total
            ]);

            if (!$result) {
                return response()->json([
                    "success" => false,
                    "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
                ]);
            }

            return response()->json([
                "success" => true,
                "message" => "C???p nh???t th??nh c??ng s??? l?????ng S???n ph???m c?? ID = " . $product->id
            ]);
        }
    }

    public function reduce(GetCustomerBasicRequest $request)
    {
        // request->id is Product ID
        $customer = Customer::find($request->user()->id);

        $product = Product::find($request->id);

        if (empty($customer) || empty($product)) {
            return response()->json([
                "success" => false,
                "errors" => "S???n ph???m kh??ng t???n t???i."
            ]);
        }

        $query = DB::table("customer_product_cart")
            ->where("customer_id", "=", $customer->id)
            ->where("product_id", "=", $product->id);

        $check = $query->exists();

        if (empty($check)) {
            return response()->json([
                "success" => false,
                "errors" => "Vui l??ng ki???m tra l???i ID Kh??ch h??ng v?? ID S???n ph???m."
            ]);
        }

        $data = $query->first();

        if ($data->quantity === 1) {
            $customer->customer_product_cart()->detach($product);

            return response()->json([
                "success" => true,
                "message" => "X??a th??nh c??ng S???n ph???m c?? ID = " . $request->id . " kh???i gi??? h??ng."
            ]);
        }

        $customer->customer_product_cart()->updateExistingPivot($product, [
            "quantity" => $data->quantity - 1
        ]);

        return response()->json([
            "success" => true,
            "message" => "S???n ph???m c?? ID = " . $request->id . " ???? ???????c gi???m ??i 1 ????n v??? s??? l?????ng s???n ph???m."
        ]);
    }

    public function update(UpdateProductToCartRequest $request)
    {
        $customer = Customer::find($request->user()->id);

        $product = Product::find($request->product_id);

        if (empty($customer) || empty($product)) {
            return response()->json([
                "success" => false,
                "errors" => "Vui l??ng ki???m tra l???i ID Kh??ch h??ng v?? ID S???n ph???m."
            ]);
        }

        // Check Request Quantity before update quantity value to cart
        if ($product->quantity < $request->quantity) {
            return response()->json([
                "success" => false,
                "errors" => "S??? l?????ng s???n ph???m ???? g???n h???t, vui l??ng gi???m s??? l?????ng s???n ph???m tr?????c khi th??m v??o gi??? h??ng."
            ]);
        }

        $query = DB::table("customer_product_cart")
            ->where("customer_id", "=", $customer->id)
            ->where("product_id", "=", $product->id);

        // If customer hasn't added this product to cart yet, then add it
        /** THIF CHECK CREATE FOR ADMIN TO USE */
        if (!$query->exists()) {
            if ($request->quantity < 0) {
                return response()->json([
                    "success" => false,
                    "errors" => "Kh??ng th??? th??m s???n ph???m v??o gi??? h??ng v???i s??? l?????ng l?? s??? ??m."
                ]);
            }

            $customer->customer_product_cart()->attach($product, [
                "quantity" => $request->quantity
            ]);

            return response()->json([
                "success" => true,
                "message" => "S???n ph???m c?? ID = " . $request->product_id . " ???? ???????c th??m v??o gi??? h??ng v???i s??? l?????ng s???n ph???m l?? " . $request->quantity
            ]);
        }

        $data = $query->first();

        // If $request->quantity value is negative
        if ($data->quantity <= ($request->quantity * -1)) { // **$request->quantity * -1** use for checking negative number
            $customer->customer_product_cart()->detach($product);

            return response()->json([
                "success" => true,
                "message" => "X??a th??nh S???n ph???m c?? ID = " . $request->id . " kh???i gi??? h??ng."
            ]);
        }

        // Check current total quantity product before add
        $total = $data->quantity + $request->quantity;
        if ($total > $product->quantity) {
            return response()->json([
                "success" => false,
                "errors" => "T???ng s??? l?????ng s???n ph???m ???? ?????n gi???i h???n, vui l??ng gi???m s??? l?????ng s???n ph???m."
            ]);
        }

        $customer->customer_product_cart()->updateExistingPivot($product, [
            "quantity" => $total
        ]);

        if ($request->quantity < 0) {
            return response()->json([
                "success" => true,
                "message" => "S???n ph???m v??i ID = " . $request->product_id . " ???? ???????c gi???m th??nh c??ng v???i s??? l?????ng l?? " . $request->quantity * (-1)
            ]);
        }

        return response()->json([
            "success" => true,
            "message" => "C???p nh???t th??nh c??ng s??? l?????ng s???n ph???m l?? " . $request->quantity . " cho m???t s???n ph???m trong gi??? h??ng."
        ]);
    }

    public function destroy(DeleteCustomerRequest $request)
    {
        // request->id is Product ID
        $customer = Customer::find($request->user()->id);

        $product = Product::find($request->id);

        if (empty($customer) || empty($product)) {
            return response()->json([
                "success" => false,
                "errors" => "Vui l??ng ki???m tra l???i ID S???n ph???m v?? ID Kh??ch h??ng."
            ]);
        }

        $check = DB::table("customer_product_cart")
            ->where("customer_id", "=", $customer->id)
            ->where("product_id", "=", $request->id)
            ->exists();

        if (empty($check)) {
            return response()->json([
                "success" => false,
                "errors" => "S???n ph???m c?? th??? kh??ng t???n t???i trong gi??? h??ng c???a Kh??ch h??ng. Vui l??ng ki???m tra l???i ID S???n ph???m v?? ID Kh??ch h??ng."
            ]);
        }

        $customer->customer_product_cart()->detach($product);

        return response()->json([
            "success" => true,
            "message" => "S???n ph???m c?? ID = " . $request->id . " ???? ???????c x??a kh???i gi??? h??ng."
        ]);
    }

    public function empty(GetCustomerBasicRequest $request)
    {
        $products_in_cart = DB::table("customer_product_cart")
            ->where("customer_id", "=", $request->user()->id)
            ->get()
            ->count();

        if ($products_in_cart === 0) {
            return response()->json([
                "success" => false,
                "messasge" => "Gi??? h??ng hi???n ??ang tr???ng."
            ]);
        }

        $customer = Customer::find($request->user()->id);

        $customer->customer_product_cart()->detach();

        return response()->json([
            "success" => true,
            "message" => "L??m tr???ng gi??? h??ng th??nh c??ng."
        ]);
    }
}
