<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Store\StoreAvatarCustomerRequest;
use App\Http\Requests\Customer\Delete\DeleteCustomerRequest;
use App\Http\Requests\Customer\Get\GetCustomerBasicRequest;
use App\Http\Requests\Customer\Update\UpdateCustomerRequest;
use App\Http\Requests\Customer\Update\UpdatePasswordRequest;
use App\Http\Resources\V1\CustomerDetailResource;
use App\Mail\ForgotPasswordMail;
use App\Mail\ResetPasswordSuccessMail;
use App\Models\Customer;
use App\Models\CustomerAuth;
use App\Models\Order;
use App\Models\Token;
use App\Models\Voucher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserAuthController extends Controller
{
    // ******* CUSOMTER ******* \\
    public function __construct()
    {
        $this->middleware("auth:sanctum", ["except" => ["register", "login", "retrieveToken", "upload"]]);
    }

    public function dashbooard(GetCustomerBasicRequest $request)
    {
        $totalOrders = Order::where("customer_id", "=", $request->user()->id)
            ->get()
            ->count();
        $totalCompletedOrders = Order::where("customer_id", "=", $request->user()->id)
            ->where("status", "=", 2)
            ->get()
            ->count();
        $totalPendingOrders = Order::where("customer_id", "=", $request->user()->id)
            ->where("status", "=", 0)
            ->get()
            ->count();

        return response()->json([
            "success" => true,
            "data" => [
                "totalOrders" => $totalOrders,
                "totalCompletedOrders" => $totalCompletedOrders,
                "totalPendingOrders" => $totalPendingOrders
            ]
        ]);
    }

    public function register(Request $request)
    {
        $data = Validator::make($request->all(), [
            "firstName" => "required|string|min:2|max:50",
            "lastName" => "required|string|min:2|max:50",
            "email" => "required|email",
            "password" => "required|min:6|max:24",
            "confirmPassword" => "required|string",
        ]);

        if ($data->fails()) {
            $errors = $data->errors();

            return response()->json([
                "success" => false,
                "errors" => $errors,
            ]);
        }

        // Check existence of email in database
        $check = Customer::where("email", '=', $request->email)->exists();
        if ($check) {
            return response()->json([
                "success" => false,
                "errors" => "Email ???? ???????c s??? d???ng."
            ]);
        }

        // Check password and confirm password are the same
        if ($request->password !== $request->confirmPassword) {
            return response()->json([
                "success" => false,
                "errors" => "M???t kh???u kh??ng kh???p, vui l??ng ki???m tra l???i."
            ]);
        }

        if ($data->passes()) {
            $customer = CustomerAuth::create([
                'first_name' => $request->firstName,
                'last_name' => $request->lastName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // token abilities will be detemined later - i mean will be consider to be deleted or not
            // $token = $customer->createToken("customer-$customer->id", ["update_profile", "fav_product", "place_order", "make_feedback", "create_address", "update_address", "remove_address"])->plainTextToken;

            return response()->json([
                "success" => true,
                // "token" => $token,
                // "tokenType" => "Bearer",
                // "user" => new CustomerRegisterResource($customer)
                "message" => "T???o t??i kho???n m???i th??nh c??ng."
            ]);
        }
    }

    public function login(Request $request)
    {

        // if (!Auth::guard("customer")->attempt($request->only("email", "password"))) {
        if (!Auth::guard("customer")->attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json([
                "success" => false,
                "errors" => "Email ho???c m???t kh???u kh??ng h???p l???."
            ]);
        }

        // Set to Vietnam timezone
        // date_default_timezone_set('Asia/Ho_Chi_Minh');

        // Using Auth model to check User to create Token
        $customer = CustomerAuth::where('email', "=", $request->email)->first();

        // Checking if account is disabled?
        if ($customer->disabled !== null) {

            // Log out account
            Auth::guard("customer")->logout();

            return response()->json([
                "success" => false,
                "errors" => "T??i kho???n n??y ???? b??? v?? hi???u qu?? b???i Admin."
            ]);
        }

        // Token ability base from admin perspective, "none" for not allow to do anything what admin can
        $token = $customer->createToken("Customer - " . $customer->id, ["none"])->plainTextToken;
        $token_encrypt = Crypt::encryptString($token);

        // Use normal model to check User to store token
        $customer_token = Customer::where('email', "=", $request->email)->first();

        $token_data = [
            "customer_id" => $customer_token->id,
            "token" => $token,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        ];

        $check = Token::insert($token_data);

        if (empty($check)) {
            return response()->json([
                "success" => false,
                "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
            ]);
        }

        return response()->json([
            "success" => true,
            // "tokenType" => "Encrypted",
            "tokenType" => "Bearer",
            "token" => $token,
            // "encryptedToken" => $token_encrypt,
            // "data" => new CustomerDetailResource($customer)
            "data" => [
                "customerId" => $customer->id,
                "firstName" => $customer->first_name,
                "lastName" => $customer->last_name,
                "email" => $customer->email,
                "avatar" => $customer->avatar,
                "defaultAvatar" => $customer->default_avatar,
            ]
        ]);
    }

    public function logout(GetCustomerBasicRequest $request)
    {
        Token::where('token', "=", $request->bearerToken())->delete();

        Auth::guard("customer")->logout();

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            "success" => true,
            "message" => "????ng xu???t th??nh c??ng."
        ]);
    }

    public function profile(GetCustomerBasicRequest $request)
    {
        return new CustomerDetailResource($request->user());
    }

    public function userInfo(GetCustomerBasicRequest $request)
    {
        return response()->json([
            "success" => true,
            "data" => [
                "firstName" => $request->user()->first_name,
                "lastName" => $request->user()->last_name,
                "email" => $request->user()->email,
                "avatar" => $request->user()->avatar,
                "defaultAvatar" => $request->user()->default_avatar,
            ]
        ]);
    }

    // Generate after placeorder (for front-end)
    public function filledNumber($count)
    {
        // create order count display
        if ($count < 10) {
            $order_count_display = "00" . $count;
        } else if ($count >= 10 && $count < 100) {
            $order_count_display = "0" . $count;
        } else {
            $order_count_display = $count;
        }

        return $order_count_display;
    }

    public function vipCustomerCheck(GetCustomerBasicRequest $request)
    {
        $order_count = Order::where("customer_id", "=", $request->user()->id)->get();
        $voucher_name = "";
        $order_count_display = "";
        $count = 10;
        $discount = 2; // for 10 order count

        if ($order_count->count() >= 10) {
            if ($order_count->count() === 25) {
                $count = 25;
                $discount = 5;
            } else if ($order_count->count() === 50) {
                $count = 50;
                $discount = 8;
            } else if ($order_count->count() === 100) {
                $count = 100;
                $discount = 10;
            } else if ($order_count->count() === 200) {
                $count = 200;
                $discount = 20;
            } else {
                $count = 500;
                $discount = 30;
            }

            $order_count_display = $this->filledNumber($count);
            $discount_display = $this->filledNumber($discount);

            $voucher = strtoupper($request->user()->first_name) . strtoupper($request->user()->last_name) . "_OD" . $order_count_display;
            $exists = Voucher::where("name", "like", "%" . $voucher . "%")->exists(); // Check exist voucher

            if ($exists) { // If so then return and do nothing further
                return;
            }

            // Create voucher to add to database
            $current_day = date("d");
            $current_month = date("m");
            $current_year = date("Y");
            $current_time = date("H:i:s");

            // Check if current month is exceeded december or not
            if ($current_month === 12) {
                $new_month = "01";
                $new_year = (int) $current_year + 1;
            } else {
                $new_month = (int) $current_month + 1;
                $new_year = (int) $current_year;
            }

            // Create expired date for voucher which next month after voucher is created
            $expired_date = $new_year . "-" . $new_month . "-" . $current_day . " " . $current_time;

            // attach discount display to complete voucher name
            $voucher = $voucher . "_P" . $discount_display;

            $result = Voucher::create([
                "name" => $voucher,
                "percent" => $discount,
                "usage" => 1,
                "expired_date" => $expired_date,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ]);

            if (empty($result->id)) {
                return response()->json([
                    "success" => false,
                    "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
                ]);
            }

            return response()->json([
                "success" => true,
                "data" => [
                    "name" => $voucher,
                    "usage" => "M?? gi???m gi?? n??y ch??? c?? gi?? tr??? d??ng 1 l???n.",
                    "percent" => $discount,
                    "expiredDate" => $expired_date
                ]
            ]);
        }
    }

    public function update(UpdateCustomerRequest $request)
    {
        // Check email belong to customer that being check
        $customer_email = Customer::where("email", "=", $request->email)
            ->where("id", "=", $request->user()->id)->exists();

        // If new email doesn't belong to current customer
        if (!$customer_email) {

            // Check existence of email in database
            $check = Customer::where("email", "=", $request->email)->exists();
            if ($check) {
                return response()->json([
                    "success" => false,
                    "errors" => "Email ???? ???????c s??? d???ng. Vui l??ng s??? d???ng email kh??c."
                ]);
            }
        }

        // Get customer data
        // $customer = Customer::find($request->user()->id);

        $filtered = $request->except(["firstName", "lastName"]);

        // Checking if user make chane to password
        if ($request->password !== null) {
            $filtered['password'] = Hash::make($filtered['password']);
        }

        $update = Customer::where("id", "=", $request->user()->id)->update($filtered);

        if (empty($update)) {
            return response()->json([
                "success" => false,
                "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
            ]);
        }

        return response()->json([
            "success" => true,
            "message" => "C???p nh???t th??ng tin c?? nh??n th??nh c??ng."
        ]);
    }

    /** Use this api to change password Customer */
    public function changePassword(UpdatePasswordRequest $request)
    {
        $customer = Customer::where("id", "=", $request->user()->id)->first();

        // Check old Password
        if (!Hash::check($request->oldPassword, $customer->password)) {
            return response()->json([
                "success" => false,
                "errors" => "M???t kh???u c?? kh??ng ch??nh x??c."
            ]);
        }

        if (Hash::check($request->password, $customer->password)) {
            return response()->json([
                "success" => false,
                "errors" => "M???t kh???u m???i kh??ng th??? gi???ng v???i m???t kh???u c??."
            ]);
        }

        // Check confirm password and password are the same or not
        if ($request->password !== $request->confirmPassword) {
            return response()->json([
                "success" => false,
                "errors" => "M???t kh???u kh??ng kh???p."
            ]);
        }

        $userName = $customer->first_name . " " . $customer->last_name;
        $customer->password = Hash::make($request->password);
        $result = $customer->save();

        if (empty($result)) {
            return response()->json([
                "success" => false,
                "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
            ]);
        }

        // Send email
        $title = "M???t kh???u c???a qu?? kh??ch ???? ???????c thay ?????i";
        Mail::to($customer)->queue(new ResetPasswordSuccessMail($userName, $title, $title));

        return response()->json([
            "success" => true,
            "message" => "M???t kh???u ???? ???????c thay ?????i th??nh c??ng."
        ]);
    }

    // Use when user first enter website
    public function retrieveToken(Request $request)
    {
        // $decrypt_token = Crypt::decryptString($request->token);
        // Checking token existence
        // $token = Token::where("token", "=", $decrypt_token)->first();
        $token = Token::where("token", "=", $request->bearerToken())->first();

        if ($token === null) {
            return response()->json([
                "success" => false,
                "errors" => "Kh??ng t??m th???y token."
            ]);
        }

        return response()->json([
            "success" => true,
            "token" => $token->token,
            "tokenType" => "Bearer Token",
        ]);
    }

    // public function encryptToken(Request $request)
    // {
    //     // Checking token existence
    //     $token = Token::where("token", "=", $request->bearerToken())->first();

    //     if ($token === null) {
    //         return response()->json([
    //             "success" => false,
    //             "errors" => "No token found"
    //         ]);
    //     }

    //     return response()->json([
    //         "success" => true,
    //         "token" => $request->bearerToken(),
    //         "tokenType" => "Bearer Token",
    //     ]);
    // }

    public function upload(StoreAvatarCustomerRequest $request)
    {
        $customer = Customer::where("id", "=", $request->user()->id)->first();
        $customer->avatar = $request->avatar;

        $result = $customer->save();

        // If result is false, that means save process has occurred some issues
        if (!$result) {
            return response()->json([
                'success' => false,
                "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
            ]);
        }

        return response()->json([
            "success" => true,
            "message" => "C???p nh???t ???nh ?????i di???n th??nh c??ng."
        ]);
    }

    public function destroyAvatar(DeleteCustomerRequest $request)
    {
        $customer = Customer::find($request->user()->id);

        $customer->avatar = null;
        $result = $customer->save();

        // If result is false, that means save process has occurred some issues
        if (!$result) {
            return response()->json([
                'success' => false,
                "errors" => "???? c?? l???i x???y ra trong qu?? tr??nh v???n h??nh!!"
            ]);
        }

        return response()->json([
            "success" => true,
            "message" => "X??a ???nh ?????i di???n th??nh c??ng."
        ]);
    }
}
