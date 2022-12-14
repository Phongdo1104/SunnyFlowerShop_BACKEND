<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Http\Requests\Admin\Store\StoreProductRequest;
use App\Http\Requests\Admin\Update\UpdateProductRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Delete\DeleteAdminBasicRequest;
use App\Http\Requests\Admin\Delete\DeleteMultipleProductRequest;
use App\Http\Requests\Admin\Get\GetAdminBasicRequest;
use App\Http\Requests\BulkInsertProductRequest;
use App\Http\Resources\V1\ProductDetailResource;
use App\Http\Resources\V1\ProductListCollection;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(GetAdminBasicRequest $request)
    {
        // $data = Product::with("categories")->paginate();
        $data = Product::with("categories");
        $count = $data->get()->count();

        if (empty($count)) {
            return response()->json([
                "success" => false,
                "errors" => "Product list is empty"
            ]);
        }

        // Will change later, this is just temporary
        if (!empty($request->get("q"))) {
            $check = (int)$request->get("q");
            $column = "";
            $operator = "";
            $value = "";

            if ($check == 0) {
                $column = "name";
                $operator = "like";
                $value = "%" . $request->get("q") . "%";
            } else {
                $column = "id";
                $operator = "=";
                $value = $request->get("q");
            }

            $search = Product::where("$column", "$operator", "$value")->get();
        }

        // $count = DB::table("products")->count();

        // return response()->json([
        //     "success" => true,
        //     "total" => $count,
        //     "data" => new ProductListCollection($data)
        // ]);

        return new ProductListCollection($data->paginate(12)->appends($request->query()));
    }

    public function paginator($arr, $request)
    {
        $total = count($arr);
        $per_page = 12;
        $current_page = $request->input("page") ?? 1;

        $starting_point = ($current_page * $per_page) - $per_page;

        $arr = array_slice($arr, $starting_point, $per_page, true);

        $arr = new LengthAwarePaginator($arr, $total, $per_page, $current_page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return $arr;
    }

    public function indexAdmin(GetAdminBasicRequest $request)
    {
        $data = Product::with("categories")->get();

        if (!empty($request->get('orderBy'))) {
            $order_type = $request->get('orderBy');

            $data = Product::with("categories")->orderBy("price", $order_type)->get();
        }

        $arr = [];
        // $arr['customer_id'] = $customer->id;

        for ($i = 0; $i < sizeof($data); $i++) {
            if ($data[$i]->deleted_at !== null) {
                continue;
            }
            $arr[$i]['id'] = $data[$i]->id;
            $arr[$i]['name'] = $data[$i]->name;
            $arr[$i]['description'] = $data[$i]->description;
            $arr[$i]['price'] = $data[$i]->price;
            $arr[$i]['percentSale'] = $data[$i]->percent_sale;
            $arr[$i]['img'] = $data[$i]->img;
            $arr[$i]['quantity'] = $data[$i]->quantity;
            $arr[$i]['status'] = $data[$i]->status;
            $arr[$i]['createdAt'] = date_format($data[$i]->created_at, "d/m/Y");

            for ($j = 0; $j < sizeof($data[$i]->categories); $j++) {
                $arr[$i]['categories'][$j]['id'] = $data[$i]->categories[$j]->id;
                $arr[$i]['categories'][$j]['name'] = $data[$i]->categories[$j]->name;
            }
        }

        // return $arr;
        return $this->paginator($arr, $request);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreProductRequest  $request
     * @return \Illuminate\Http\Response
     */

    public function store(StoreProductRequest $request)
    {
        $check_existed = Product::where("name", "=", $request->name)->exists();
        $check_existed_category = Category::where("id", "=", $request->categoryId)->exists();

        // Check if the existence of name product in database
        if ($check_existed) {
            return response()->json([
                'success' => false,
                'errors' => "T??n s???n ph???m ???? t???n t???i."
            ]);
        }

        if (!$check_existed_category) {
            return response()->json([
                "success" => false,
                "errors" => "T??n danh m???c s???n ph???m kh??ng h???p l???."
            ]);
        }

        $filtered = $request->except(["percentSale"]);
        $filtered['percent_sale'] = $request->percent_sale ?? 0; // Just in case percent_sale doesn't get filled

        $data = Product::create($filtered);

        // Checking if insert into database is isSuccess
        if (empty($data->id)) {
            return response()->json([
                "success" => false,
                "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
            ]);
        }

        $data->categories()->attach($request->categoryId);

        // // Add each categories to pivot table "category_product"
        // for ($i = 0; $i < sizeof($filtered['category']); $i++) {
        //     $category_id = $filtered['category'][$i]['id'];

        //     $category = Category::find($category_id);

        //     // Checking category id - If it doesn't exist just skip
        //     if (empty($category)) {
        //         continue;
        //     }

        //     $data->categories()->attach($category_id);
        // }

        return response()->json([
            'success' => true,
            "message" => "T???o s???n ph???m th??nh c??ng."
        ]);
    }

    // This function has been updated but haven't been tested yet
    public function bulkStore(BulkInsertProductRequest $request)
    {
        // Main Data use for blueprint
        $bulk = collect($request->all())->map(function ($arr, $key) {
            return Arr::except($arr, ["categoryId", "percentSale"]);
        });

        // Data use for searching in category table to insert to intermediate (category_product) table - $data is an array
        $data = $request->toArray();

        // Data use for insert into product table - $product is an array
        $products = $bulk->toArray();

        // Count variable to check how many product successfully added to database
        $count = 0;

        for ($i = 0; $i < sizeof($products); $i++) {
            // Check if data is already in database
            $check = Product::where("name", "=", ($products[$i]['name']))->first();

            // If product has already existed ==> skip
            if ($check) continue;

            // Insert value into product table with $products at $i index
            $result = Product::create($products[$i]);

            if (!$result) {
                return response()->json([
                    "success" => false,
                    "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
                ]);
            }

            $check_existed_category = Category::where("id", "=", $request->categoryId)->exists();
            if (!$check_existed_category) {
                continue;
            }

            $result->categories()->attach($request->categoryId);

            // Insert each category id to pivot table "category_product"
            // for ($j = 0; $j < sizeof($data[$i]['category']); $j++) {
            //     // Find Category ID in category table using $data variable
            //     $category_id = $data[$i]['category'][$j]["id"];
            //     $category = Category::find($category_id);
            //     $product = Product::find($result->id);

            //     $product->categories()->attach($category);
            // }

            $count++;
        }

        return response()->json([
            "success" => true,
            "message" => "???? th??m th??nh c??ng " . $count . " s???n ph???m."
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function show(GetAdminBasicRequest $request)
    {
        $data = Product::find($request->id);

        if (empty($data) || $data->deleted_at !== null) {
            return response()->json([
                "success" => false,
                "errors" => "Product doesn't not exist"
            ]);
        }

        $average_quality = DB::table("customer_product_feedback")
            ->where("product_id", "=", $data->id);

        // calculate average of total quality that product has
        $quality = 0;

        /** Checking if quality of feedback has been made */
        // If not then average of total quality is 0
        if (!$average_quality->exists()) {
            $quality = 0;
        }
        // If so then calculate it
        else {
            $total = $average_quality->get(); // Get all quality feedback

            for ($i = 0; $i < sizeof($total); $i++) { // Sum all quality to make an average calculation
                $quality += $total[$i]->quality;
            }

            $quality = $quality / sizeof($total);

            $float_point = explode(".", $quality);

            if (sizeof($float_point) >= 2) {
                $decimal_number = (int)$float_point[1];

                while ($decimal_number > 10) {
                    $decimal_number = $decimal_number / 10;
                }

                if ($decimal_number >= 5) {
                    $quality = ceil($quality);
                } else {
                    $quality = floor($quality);
                }
            }
        }

        $data['quality'] = $quality;

        return response()->json([
            "success" => true,
            "data" => new ProductDetailResource($data)
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateProductRequest  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */

    public function update(UpdateProductRequest $request, $productId)
    {
        $data = $request->except(['categoryId', "percentSale"]);

        // Checking Product ID
        $product = Product::find($productId);
        if (empty($product)) {
            return response()->json([
                'success' => false,
                'errors' => "ID S???n ph???m kh??ng h???p l???."
            ]);
        }

        $check_existed = Product::where("name", "=", $request->name)->exists();
        $check_existed_category = Category::where("id", "=", $request->categoryId)->exists();

        if (!$check_existed_category) {
            return response()->json([
                "success" => false,
                "errors" => "ID Danh m???c s???n ph???m kh??ng h???p l???."
            ]);
        }

        // Check if the existence of name product in database
        if ($check_existed) {
            return response()->json([
                'success' => false,
                'errors' => "T??n s???n ph???m ???? t???n t???i."
            ]);
        }

        // Save all value was changed
        foreach ($data as $key => $value) {
            $product->{$key} = $value;
        }

        $result = $product->save();

        // If result is false, that means save process has occurred some issues
        if (!$result) {
            return response()->json([
                'success' => false,
                "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
            ]);
        }

        // Remove all existed categories from product to readd everything back
        $product->categories()->detach();

        $product->categories()->attach($request->categoryId);

        // Check product status
        if ($data['status'] === 0) { // if new status product is 0, then proceed to delete product out of "customer_product_cart"
            DB::table("customer_product_cart")
                ->where("product_id", "=", $product->id)
                ->delete();
        }

        // Checking all categories that product has to decide to attach new categories or skip
        // for ($i = 0; $i < sizeof($request['category']); $i++) {
        //     $category_id = $request['category'][$i]['id'];

        //     $category = Category::find($category_id);
        //     $product->categories()->attach($category);
        // }

        return response()->json([
            'success' => true,
            "message" => "C???p nh???t th??ng tin s???n ph???m th??nh c??ng."
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */

    // This is SOFT DELETE not permanent delete
    public function destroy(DeleteAdminBasicRequest $request)
    {
        $data = Product::find($request->id);

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'errors' => "ID S???n ph???m kh??ng h???p l???."
            ]);
        }

        // Check state variable to switch between 2 mode: (Soft) Delete and Reverse Delete
        // If value is 1, it will be (Soft) Delete
        if ((int)$request->state === 1) {

            // Check if product Has already been deleted?
            if ($data->deleted_at !== null) {
                return response()->json([
                    "success" => false,
                    "errors" => "S???n ph???m v???i ID = " . $request->id . " ???? ???????c x??a."
                ]);
            }

            $data->{"deleted_at"} = 1;
            $result = $data->save();

            if (!$result) {
                return response()->json([
                    "success" => false,
                    "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh."
                ]);
            }

            // Delete product out of "customer_product_cart"
            DB::table("customer_product_cart")
                ->where("product_id", "=", $data->id)
                ->delete();

            return response()->json(
                [
                    'success' => true,
                    'errors' => "X??a th??nh c??ng v???i s???n ph???m c?? ID = " . $request->id
                ]
            );

            // If value is not 1, it will be Reverse Delete
        } else {
            // Check if product Has already been reversed delete?
            if ($data->deleted_at === null) {
                return response()->json([
                    "success" => false,
                    "errors" => "S???n ph???m v???i ID = " . $request->id . " ???? ???????c ho??n t??c x??a."
                ]);
            }

            $data->{"deleted_at"} = null;

            $result = $data->save();

            if (!$result) {
                return response()->json([
                    "success" => false,
                    "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh."
                ]);
            }

            return response()->json(
                [
                    'success' => true,
                    'errors' => "???? ho??n t??c vi???c x??a th??nh c??ng v???i s???n ph???m c?? ID = " . $request->id
                ]
            );
        }
    }

    public function destroyBulk(DeleteMultipleProductRequest $request)
    {
        $count = 0;
        $invalid_count = 0;
        $invalid_product_id_array = [];
        $errors_product_id_array = [];
        $errors_count = 0;

        $products = $request->all();

        // If state is 1, then display is "deleted"
        if ((int) $request->state === 1) {
            $display = "x??a";
        }
        // If state is 0, then display is "reversed deleted"
        else {
            $display = "ho??n t??c vi???c x??a";
        }

        for ($i = 0; $i < sizeof($products); $i++) {
            $query = Product::where("id", "=", $products[$i]['id']);

            if (!$query->exists()) {
                $invalid_product_id_array[] = $products[$i]['id'];
                $invalid_count++;
                continue;
            }

            $product = $query->first();

            // If state is 0, then proceed to reverse delete
            if ((int) $request->state === 0) {

                if ($product->deleted_at === null) {
                    continue;
                }

                $product->deleted_at = null;
                $result = $product->save();

                if (!$result) {
                    $errors_product_id_array[] = $products[$i]['id'];
                    $errors_count++;
                }

                $count++;
            }
            // If state is 1, then proceed to delete
            else {
                if ($product->deleted_at === 1) {
                    continue;
                }

                $product->deleted_at = 1;
                $result = $product->save();

                if (!$result) {
                    $errors_product_id_array[] = $products[$i]['id'];
                    $errors_count++;
                }

                $count++;
            }
        }

        if ($invalid_count !== 0) {
            return response()->json([
                "success" => false,
                "errors" => "C?? t???ng c???ng " . $invalid_count . " s???n ph???m c?? ID kh??ng h???p l???. C??c ID ???? l??: " . implode(", ", $invalid_product_id_array)
            ]);
        }

        if ($errors_count !== 0) {
            return response()->json([
                "success" => false,
                "errors" => "C?? t???ng c???ng " . $errors_count . " l???i x???y ra trong qu?? tr??nh x??a s???n ph???m. Nh???ng ID s???n ph???m sau g??y ra v???n ?????: " . implode(", ", $errors_product_id_array)
            ]);
        }

        return response()->json([
            "success" => true,
            "message" => "C?? t???ng c???ng " . $count . " s???n ph???m ???? ???????c " . $display
        ]);
    }

    public function changeCategory(DeleteAdminBasicRequest $request, Category $category, Product $product)
    {
        $product->categories()->detach();
        $product->categories()->attach($category->id);

        return response()->json([
            "success" => true,
            "message" => "Danh m???c ???? ???????c ?????i th??nh c??ng."
        ]);
    }
}
