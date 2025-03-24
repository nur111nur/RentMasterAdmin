<?php

namespace App\Http\Controllers;

use DateTime;

use Exception; 
use Carbon\Carbon;
use App\Models\Faq;
use App\Models\Type;
use App\Models\User;
use App\Models\Chats;

use App\Models\Slider;
use GuzzleHttp\Client;
use App\Models\Article;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Category;
use App\Models\Customer;

use App\Models\Language;
use App\Models\Payments;
use App\Models\Projects;
use App\Models\Property;
use App\Libraries\Paypal;
use App\Models\CityImage;


use App\Models\Favourite;
use App\Models\NumberOtp;
use App\Models\parameter;
use App\Models\Usertokens;
use App\Models\SeoSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use App\Models\ProjectPlans;
use App\Models\user_reports;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\Notifications;

// use GuzzleHttp\Client;
use App\Models\UserInterest; 
use App\Models\InterestedUser;

use App\Models\PropertyImages;

use App\Models\report_reasons;
use App\Models\VerifyCustomer;
use App\Models\BlockedChatUser;
use App\Models\Contactrequests;
use App\Services\HelperService;
use App\Models\AssignParameters;

use App\Models\ProjectDocuments;
use App\Models\OutdoorFacilities;
// use PayPal_Pro as GlobalPayPal_Pro;
use App\Services\ResponseService;

use App\Models\PropertiesDocument;
use App\Models\VerifyCustomerForm;
use Illuminate\Support\Facades\DB;
use App\Models\VerifyCustomerValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\UserPurchasedPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Twilio\Exceptions\RestException;
use App\Models\VerifyCustomerFormValue;
use App\Models\AssignedOutdoorFacilities;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client as TwilioRestClient;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use Intervention\Image\ImageManagerStatic as Image;

class ApiController extends Controller
{

    //* START :: get_system_settings   *//
    public function get_system_settings(Request $request)
    {
        $result =  Setting::select('type', 'data')->get();

        foreach ($result as $row) {


            if ($row->type == "place_api_key" || $row->type == "stripe_secret_key") {

                $publicKey = file_get_contents(base_path('public_key.pem')); // Load the public key
                $encryptedData = '';
                if (openssl_public_encrypt($row->data, $encryptedData, $publicKey)) {

                    $tempRow[$row->type] = base64_encode($encryptedData);
                }
            } else if ($row->type == 'company_logo') {

                $tempRow[$row->type] = url('/assets/images/logo/logo.png');
            } else if ($row->type == 'web_logo' || $row->type == 'web_placeholder_logo' || $row->type == 'app_home_screen' || $row->type == 'web_footer_logo' || $row->type == 'placeholder_logo' || $row->type == 'favicon_icon') {


                $tempRow[$row->type] = url('/assets/images/logo/') . '/' . $row->data;
            } else {
                $tempRow[$row->type] = $row->data;
            }
        }

        if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
            $loggedInUserId = Auth::guard('sanctum')->user()->id;
            update_subscription($loggedInUserId);

            $customer_data = Customer::find($loggedInUserId);
            if ($customer_data->isActive == 0) {

                $tempRow['is_active'] = false;
            } else {
                $tempRow['is_active'] = true;
            }
            if ($row->type == "seo_settings") {

                $tempRow[$row->type] = $row->data == 1 ? true : false;
            }

            $customer = Customer::select('id', 'subscription', 'is_premium')
                ->where(function ($query) {
                    $query->where('subscription', 1)
                        ->orWhere('is_premium', 1);
                })
                ->find($loggedInUserId);



            if (($customer)) {
                $tempRow['is_premium'] = $customer->is_premium == 1 ? true : ($customer->subscription == 1 ? true : false);

                $tempRow['subscription'] = $customer->subscription == 1 ? true : false;
            } else {

                $tempRow['is_premium'] = false;
                $tempRow['subscription'] = false;
            }
        }
        $language = Language::select('code', 'name')->get();
        $user_data = User::find(1);
        $tempRow['admin_name'] = $user_data->name;
        $tempRow['admin_image'] = url('/assets/images/faces/2.jpg');
        $tempRow['demo_mode'] = env('DEMO_MODE');
        $tempRow['languages'] = $language;
        $tempRow['img_placeholder'] = url('/assets/images/placeholder.svg');


        $tempRow['min_price'] = DB::table('propertys')
            ->selectRaw('MIN(price) as min_price')
            ->value('min_price');


        $tempRow['max_price'] = DB::table('propertys')
            ->selectRaw('MAX(price) as max_price')
            ->value('max_price');

        if (!empty($result)) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $tempRow;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: Get System Setting   *//


    //* START :: user_signup   *//
    // public function user_signup(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'type' => 'required',
    //         'auth_id' => 'required',
    //     ]);

    //     if (!$validator->fails()) {
    //         $type = $request->type;
    //         $auth_id = $request->auth_id;

    //         $user = Customer::where('auth_id', $auth_id)->where('logintype', $type)->get();
    //         if ($user->isEmpty()) {
    //             $saveCustomer = new Customer();
    //             $saveCustomer->name = isset($request->name) ? $request->name : '';
    //             $saveCustomer->email = isset($request->email) ? $request->email : '';
    //             $saveCustomer->mobile = isset($request->mobile) ? $request->mobile : '';
    //             $saveCustomer->slug_id = generateUniqueSlug($request->name, 5);
    //             $saveCustomer->logintype = isset($request->type) ? $request->type : '';
    //             $saveCustomer->address = isset($request->address) ? $request->address : '';
    //             $saveCustomer->auth_id = isset($request->auth_id) ? $request->auth_id : '';
    //             $saveCustomer->about_me = isset($request->about_me) ? $request->about_me : '';
    //             $saveCustomer->facebook_id = isset($request->facebook_id) ? $request->facebook_id : '';
    //             $saveCustomer->twiiter_id = isset($request->twiiter_id) ? $request->twiiter_id : '';
    //             $saveCustomer->instagram_id = isset($request->instagram_id) ? $request->instagram_id : '';
    //             $saveCustomer->youtube_id = isset($request->youtube_id) ? $request->youtube_id : '';
    //             $saveCustomer->latitude = isset($request->latitude) ? $request->latitude : '';
    //             $saveCustomer->longitude = isset($request->longitude) ? $request->longitude : '';
    //             $saveCustomer->notification = 1;
    //             $saveCustomer->about_me = isset($request->about_me) ? $request->about_me : '';
    //             $saveCustomer->facebook_id = isset($request->facebook_id) ? $request->facebook_id : '';
    //             $saveCustomer->twiiter_id = isset($request->twiiter_id) ? $request->twiiter_id : '';
    //             $saveCustomer->instagram_id = isset($request->instagram_id) ? $request->instagram_id : '';
    //             $saveCustomer->isActive = '1';


    //             $destinationPath = public_path('images') . config('global.USER_IMG_PATH');
    //             if (!is_dir($destinationPath)) {
    //                 mkdir($destinationPath, 0777, true);
    //             }
    //             // image upload

    //             if ($request->hasFile('profile')) {
    //                 $profile = $request->file('profile');
    //                 $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
    //                 $profile->move($destinationPath, $imageName);
    //                 $saveCustomer->profile = $imageName;
    //             } else {
    //                 $saveCustomer->profile = $request->profile;
    //             }

    //             $saveCustomer->save();
    //             // Create a new personal access token for the user
    //             $token = $saveCustomer->createToken('token-name');

    //             $start_date =  Carbon::now();
    //             $package = Package::find(1);

    //             if ($package && $package->status == 1) {
    //                 $user_package = new UserPurchasedPackage();
    //                 $user_package->modal()->associate($saveCustomer);
    //                 $user_package->package_id = 1;
    //                 $user_package->start_date = $start_date;
    //                 $user_package->end_date =  Carbon::now()->addDays($package->duration);
    //                 $user_package->save();

    //                 $saveCustomer->subscription = 1;
    //                 $saveCustomer->update();
    //             }


    //             $response['error'] = false;
    //             $response['message'] = 'User Register Successfully';

    //             $credentials = Customer::find($saveCustomer->id);
    //             $credentials = Customer::where('auth_id', $auth_id)->where('logintype', $type)->first();

    //             $response['token'] = $token->plainTextToken;
    //             $response['data'] = $credentials;
    //         } else {
    //             $credentials = Customer::where('auth_id', $auth_id)->where('logintype', $type)->first();
    //             if ($credentials->isActive == 0) {
    //                 $response['error'] = true;
    //                 $response['message'] = 'Account Deactivated by Administrative please connect to them';
    //                 $response['is_active'] = false;
    //                 return response()->json($response);
    //             }
    //             $credentials->update();
    //             $token = $credentials->createToken('token-name');


    //             // Update or add FCM ID in UserToken for Current User
    //             if ($request->has('fcm_id') && !empty($request->fcm_id)) {
    //                 Usertokens::updateOrCreate(
    //                     ['fcm_id' => $request->fcm_id],
    //                     ['customer_id' => $credentials->id]
    //                 );
    //             }
    //             $response['error'] = false;
    //             $response['message'] = 'Login Successfully';
    //             $response['token'] = $token->plainTextToken;
    //             $response['data'] = $credentials;
    //         }
    //     } else {
    //         $response['error'] = true;
    //         $response['message'] = 'Please fill all data and Submit';
    //     }
    //     return response()->json($response);
    // }
    public function user_signup(Request $request)
    {
        // dd($request->all());
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $email = $request->email;
        $password = $request->password;

        // Find user by email
        $user = Customer::where('email', $email)->first();

        // Check if user exists and verify password
        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Check if account is active
        if ($user->isActive == 0) {
            return response()->json([
                'error' => true,
                'message' => 'Account Deactivated by Admin, please contact support',
                'is_active' => false,
            ], 403);
        }

        // Generate auth token
        $token = $user->createToken('token-name')->plainTextToken;

        // Update FCM Token if provided
        if ($request->has('fcm_id') && !empty($request->fcm_id)) {
            Usertokens::updateOrCreate(
                ['customer_id' => $user->id],
                ['fcm_id' => $request->fcm_id]
            );
        }

        return response()->json([
            'error' => false,
            'message' => 'Login Successful',
            'token' => $token,
            'data' => $user, // Sending full user data
        ], 200);
    }
    // User Register
    public function userRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:customers',
            'password' => 'required|min:6',
            're_password' => 'required|same:password',
            'mobile' => 'nullable|unique:customers',
            'account_type' => 'required|in:seeker,provider',
        ]);
        // dd($request->all());

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $customerExists = Customer::where(['email' => $request->email, 'logintype' => 3])->exists();
            if ($customerExists) {
                ResponseService::errorResponse("User Already Exists");
            }

            $customerData = $request->except('re_password');
            $customerData = array_merge($customerData, [
                'auth_id' => Str::uuid()->toString(),
                'slug_id' => generateUniqueSlug($request->name, 5),
                'notification' => 1,
                'isActive' => 1,
                'subscription' => 1,
                'logintype' => 3,
                'account_type' => $request->account_type,
                'mobile' => $request->has('mobile') && !empty($request->mobile) ? $request->mobile : null,
            ]);

            $customer = Customer::create($customerData);
            $customer->password = Hash::make($request->password);
            $customer->save();

            // ✅ Only Send OTP if Mobile is Provided
            if (!empty($customerData['mobile'])) {
                $existingOtp = NumberOtp::where('number', $customerData['mobile'])->first();
                if ($existingOtp) {
                    ResponseService::errorResponse("User Already Exists");
                }

                // Generate a new OTP
                $otp = rand(123456, 999999);
                $expireAt = now()->addMinutes(10);

                // Update or create OTP entry in the database
                NumberOtp::updateOrCreate(
                    ['number' => $customerData['mobile']],
                    ['otp' => $otp, 'expire_at' => $expireAt]
                );
            }

            /** Register Mail */
            $emailTypeData = HelperService::getEmailTemplatesTypes("welcome_mail");
            $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
            $appName = env("APP_NAME", "eBroker");
            $variables = [
                'app_name' => $appName,
                'user_name' => !empty($request->name) ? $request->name : "$appName User",
                'email' => $request->email,
            ];
            if (empty($welcomeEmailTemplateData)) {
                $welcomeEmailTemplateData = "Welcome to $appName";
            }
            $welcomeEmailTemplate = HelperService::replaceEmailVariables($welcomeEmailTemplateData, $variables);

            $data = [
                'email_template' => $welcomeEmailTemplate,
                'email' => $request->email,
                'title' => $emailTypeData['title'],
            ];
            HelperService::sendMail($data);

            /** Send OTP Mail (Only if Mobile is Provided) */
            if (!empty($customerData['mobile'])) {
                $emailTypeData = HelperService::getEmailTemplatesTypes("verify_mail");
                $otpEmailTemplateData = system_setting($emailTypeData['type']);
                $variables = ['app_name' => $appName, 'otp' => $otp];

                if (empty($otpEmailTemplateData)) {
                    $otpEmailTemplateData = "Your OTP :- " . $otp;
                }

                $otpEmailTemplate = HelperService::replaceEmailVariables($otpEmailTemplateData, $variables);
                $data = [
                    'email_template' => $otpEmailTemplate,
                    'email' => $request->email,
                    'title' => $emailTypeData['title'],
                ];
                HelperService::sendMail($data);
            }

            DB::commit();
            ResponseService::successResponse('User Registered Successfully');
        } catch (Exception $e) {
            DB::rollback();

            if (Str::contains($e->getMessage(), ['Failed', 'Mail', 'Mailer', 'MailManager', "Connection could not be established"])) {
                ResponseService::errorResponse("There is an issue with mail configuration, kindly contact admin.");
            } else {
                dd($e->getMessage());
            }
        }
    }


    //* START :: get_slider   *//
    public function getSlider(Request $request)
    {
        $sliderData = Slider::select('id','type', 'image', 'category_id', 'propertys_id','show_property_details','link')->with(['category' => function($query){
            $query->select('id,category')->where('status',1);
        }],'property:id,title,title_image,price,propery_type as property_type')->orderBy('id', 'desc')->get()->map(function($slider){
            if(collect($slider->property)->isNotEmpty()){
                $slider->property->parameters = $slider->property->parameters;
            }
            return $slider;
        });

        if(collect($sliderData)->isNotEmpty()){
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $sliderData;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }

    //* END :: get_slider   *//


    //* START :: get_categories   *//
    public function get_categories(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $categories = Category::select('id', 'category', 'image', 'parameter_types', 'meta_title', 'meta_description', 'meta_keywords', 'slug_id')->where('status', '1');

        if($request->has('has_property') && $request->has_property == true){
            $categories = $categories->clone()->whereHas('properties',function($query){
                $query->where('status',1);
            })->withCount(['properties' => function ($query) {
                    $query->where('status', 1);
                }
            ]);
        }

        if (isset($request->search) && !empty($request->search)) {
            $search = $request->search;
            $categories->where('category', 'LIKE', "%$search%");
        }

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $categories->where('id', $id);
        }
        if (isset($request->slug_id) && !empty($request->slug_id)) {
            $id = $request->slug_id;
            $categories->where('slug_id', $request->slug_id);
        }

        $total = $categories->get()->count();
        $result = $categories->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();

        $result->map(function ($result) {
            $result['meta_image'] = $result->image;
        });


        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            foreach ($result as $row) {
                $parameterData = parameterTypesByCategory($row->id);
                if (collect($parameterData)->isNotEmpty()) {
                    $parameterData = $parameterData->map(function ($item) {
                        unset($item->assigned_parameter);
                        return $item;
                    });
                }
                $row->parameter_types = $parameterData;
            }

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: get_slider   *//







    //* START :: about_meofile   *//
    public function update_profile(Request $request)
    {
        try {
            DB::beginTransaction();
            $currentUser = Auth::user();
            $customer =  Customer::find($currentUser->id);

            if (!empty($customer)) {

                // update the Data passed in payload
                $fieldsToUpdate = $request->only([
                    'name', 'email', 'mobile', 'fcm_id', 'address', 'notification', 'about_me',
                    'facebook_id', 'twiiter_id', 'instagram_id', 'youtube_id', 'latitude', 'longitude',
                    'city', 'state', 'country'
                ]);

                $customer->update($fieldsToUpdate);

                if ($request->has('fcm_id') && !empty($request->fcm_id)) {
                    Usertokens::updateOrCreate(
                        ['fcm_id' => $request->fcm_id],
                        ['customer_id' => $customer->id,]
                    );
                }

                // Update Profile
                if ($request->hasFile('profile')) {
                    $destinationPath = public_path('images') . config('global.USER_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }

                    $old_image = $customer->profile;
                    $profile = $request->file('profile');
                    $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();

                    if ($profile->move($destinationPath, $imageName)) {
                        $customer->profile = $imageName;
                        if ($old_image != '') {
                            if (file_exists(public_path('images') . config('global.USER_IMG_PATH') . $old_image)) {
                                unlink(public_path('images') . config('global.USER_IMG_PATH') . $old_image);
                            }
                        }
                        $customer->update();
                    }
                }

                DB::commit();
                return response()->json(['error' => false, 'data' => $customer]);
            } else {
                return response()->json(['error' => false, 'message' => "No data found!", 'data' => []]);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => true, 'message' => 'Something Went Wrong'], 500);
        }
    }

    //* END :: update_profile   *//


    //* START :: get_user_by_id   *//
    public function getUserData()
    {
        try {
            // Get LoggedIn User Data from Toke
            $userData = Auth::user();
            // Check the User Data is not Empty
            if (collect($userData)->isNotEmpty()) {
                $response['error'] = false;
                $response['data'] = $userData;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }
    //* END :: get_user_by_id   *//


    //* START :: get_property   *//
    public function get_property(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
            $current_user = Auth::guard('sanctum')->user()->id;
        } else {
            $current_user = null;
        }
        $property = Property::with('customer', 'user', 'category:id,category,image,slug_id', 'assignfacilities.outdoorfacilities', 'parameters', 'favourite', 'interested_users')->where('status', 1);

        $max_price = isset($request->max_price) ? $request->max_price : Property::max('price');
        $min_price = isset($request->min_price) ? $request->min_price : 0;
        $totalClicks = 0;

        // If parameter ID passed
        if ($request->has('parameter_id') && !empty($request->parameter_id)) {
            $parameterId = $request->parameter_id;
            $property = $property->whereHas('parameters', function ($q) use ($parameterId) {
                $q->where('parameter_id', $parameterId);
            });
        }

        // If Max Price And Min Price passed
        if (isset($request->max_price) && isset($request->min_price) && (!empty($request->max_price) && !empty($min_price))) {
            $property = $property->whereBetween('price', [$min_price, $max_price]);
        }

        $property_type = $request->property_type;  //0 : Sell 1:Rent
        // If Property Type Passed
        if (isset($property_type) && (!empty($property_type) || $property_type == 0)) {
            $property = $property->where('propery_type', $property_type);
        }

        // If Posted Since 0 or 1 is passed
        if ($request->has('posted_since') && !empty($request->posted_since)) {
            $posted_since = $request->posted_since;
            // 0 - Last Week
            if ($posted_since == 0) {
                $startDateOfWeek = Carbon::now()->subWeek()->startOfWeek();
                $endDateOfWeek = Carbon::now()->subWeek()->endOfWeek();
                $property = $property->whereBetween('created_at', [$startDateOfWeek, $endDateOfWeek]);
            }
            // 1 - Yesterday
            if ($posted_since == 1) {
                $yesterdayDate = Carbon::yesterday();
                $property =  $property->whereDate('created_at', $yesterdayDate);
            }
        }

        // If Category Id is Passed
        if ($request->has('category_id') && !empty($request->category_id)) {
            $property = $property->where('category_id', $request->category_id);
        }

        // If Id is passed
        if ($request->has('id') && !empty($request->id)) {
            $property = $property->where('id', $request->id);
        }

        if ($request->has('category_slug_id') && !empty($request->category_slug_id)) {
            // Get the category date on category slug id
            $category = Category::where('slug_id', $request->category_slug_id)->first();
            // if category data exists then get property on the category id
            if (collect($category)->isNotEmpty()) {
                $property = $property->where('category_id', $category->id);
            }
        }

        // If Property Slug is passed
        if ($request->has('slug_id') && !empty($request->slug_id)) {
            $property = $property->where('slug_id', $request->slug_id);
        }

        // If Country is passed
        if ($request->has('country') && !empty($request->country)) {
            $property = $property->where('country', $request->country);
        }

        // If State is passed
        if ($request->has('state') && !empty($request->state)) {
            $property = $property->where('state', $request->state);
        }

        // If City is passed
        if ($request->has('city') && !empty($request->city)) {
            $property = $property->where('city', $request->city);
        }

        // If promoted is passed then get the properties according to advertisement's data except the advertisement's slider data
        if ($request->has('promoted') && !empty($request->promoted)) {
            $propertiesId = Advertisement::whereNot('type', 'Slider')->where('is_enable', 1)->pluck('property_id');
            $property = $property->whereIn('id', $propertiesId)->inRandomOrder();
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }

        // IF User Promoted Param Passed then show the User's Advertised data
        if ($request->has('users_promoted') && !empty($request->users_promoted)) {
            $propertiesId = Advertisement::where('customer_id', $current_user)->pluck('property_id');
            $property = $property->whereIn('id', $propertiesId);
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }

        // if (isset($request->promoted)) {

        //     if (!($property->Has('advertisement'))) {
        //         $response['error'] = false;
        //         $response['message'] = "No data found!";
        //         $response['data'] = [];
        //         return ($response);
        //     }

        //     $property = $property->with('advertisement');
        // }
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $property = $property->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%$search%")
                    ->orWhere('address', 'LIKE', "%$search%")
                    ->orWhereHas('category', function ($query1) use ($search) {
                        $query1->where('category', 'LIKE', "%$search%");
                    });
            });
        }
        // if (empty($request->search)) {
        //     $property = $property;
        // }

        // If Top Rated passed then show the property data with Order by on Total Click Descending
        if ($request->has('top_rated') && $request->top_rated == 1) {
            $property = $property->orderBy('total_click', 'DESC');
        }

        // IF Most Liked Passed then show the data according to
        if ($request->has('most_liked') && !empty($request->most_liked)) {
            $property = $property->withCount('favourite')->orderBy('favourite_count', 'DESC');
        }

        $total = $property->count();
        $result = $property->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();

        if (!$result->isEmpty()) {
            $property_details  = get_property_details($result, $current_user);

            // Check that Property Details exists or not
            if (isset($property_details) && collect($property_details)->isNotEmpty()) {
                /**
                 * Check that id or slug id passed and get the similar properties data according to param passed
                 * If both passed then priority given to id param
                 * */
                if ((isset($id) && !empty($id))) {
                    $getSimilarPropertiesQueryData = Property::where('id', '!=', $id)->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image')->where('status', 1)->orderBy('id', 'desc')->limit(10)->get();
                    $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $current_user);
                } else if ((isset($request->slug_id) && !empty($request->slug_id))) {
                    $getSimilarPropertiesQueryData = Property::where('slug_id', '!=', $request->slug_id)->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image')->where('status', 1)->orderBy('id', 'desc')->limit(10)->get();
                    $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $current_user);
                }
            }


            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['total_clicks'] = (float)$totalClicks;
            $response['similar_properties'] = $getSimilarProperties ?? array();
            $response['total'] = $total;
            $response['data'] = $property_details;
        } else {

            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return ($response);
    }
    //* END :: get_property   *//



    //* START :: post_property   *//
    public function post_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'             => 'required',
            'description'       => 'required',
            'category_id'       => 'required',
            'property_type'     => 'required',
            'address'           => 'required',
            'title_image'       => 'required|file|max:3000|mimes:jpeg,png,jpg',
            'three_d_image'     => 'nullable|mimes:jpg,jpeg,png,gif|max:3000',
            'documents.*'       => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
            'price'             => ['required', function ($attribute, $value, $fail) {
                if ($value > 1000000000000) {
                    $fail("The Price must not exceed one trillion that is 1000000000000.");
                }
            }],
            'video_link'        => ['nullable', 'url', function ($attribute, $value, $fail) {
                // Get the headers of the URL
                $headers = @get_headers($value, 1);

                // Check if the Content-Type header indicates a video or text/html (common for streaming platforms)
                if ($headers && isset($headers['Content-Type'])) {
                    $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
                    if (strpos($contentType, 'video/') !== 0 && strpos($contentType, 'text/html') !== 0) {
                        $fail("The Video Link must be a valid video URL.");
                    }
                } else {
                    $fail("The Video Link must be a valid URL.");
                }
            }],
        ], [], [
            'documents.*' => 'document :position'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;

            // Get Current Package with checking the property limit
            $currentPackage = $this->getCurrentPackage($loggedInUserId, 1);

            if (!($currentPackage)) {
                $response['error'] = true;
                $response['message'] = 'Package not found';
                return response()->json($response);
            } else {
                $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;
                $saveProperty = new Property();
                $saveProperty->category_id = $request->category_id;
                $saveProperty->slug_id = generateUniqueSlug($slugData, 1);
                $saveProperty->title = $request->title;
                $saveProperty->description = $request->description;
                $saveProperty->address = $request->address;
                $saveProperty->client_address = (isset($request->client_address)) ? $request->client_address : '';
                $saveProperty->propery_type = $request->property_type;
                $saveProperty->price = $request->price;
                $saveProperty->country = (isset($request->country)) ? $request->country : '';
                $saveProperty->state = (isset($request->state)) ? $request->state : '';
                $saveProperty->city = (isset($request->city)) ? $request->city : '';
                $saveProperty->latitude = (isset($request->latitude)) ? $request->latitude : '';
                $saveProperty->longitude = (isset($request->longitude)) ? $request->longitude : '';
                $saveProperty->rentduration = (isset($request->rentduration)) ? $request->rentduration : '';
                $saveProperty->meta_title = (isset($request->meta_title)) ? $request->meta_title : '';
                $saveProperty->meta_description = (isset($request->meta_description)) ? $request->meta_description : '';
                $saveProperty->meta_keywords = (isset($request->meta_keywords)) ? $request->meta_keywords : '';
                $saveProperty->added_by = $loggedInUserId;
                $saveProperty->video_link = (isset($request->video_link)) ? $request->video_link : "";
                $saveProperty->package_id = $request->package_id;
                $saveProperty->post_type = 1;

                $autoApproveStatus = $this->getAutoApproveStatus($loggedInUserId);
                if($autoApproveStatus){
                    $saveProperty->status = 1;
                }else{
                    $saveProperty->status = 0;
                }

                //Title Image
                if ($request->hasFile('title_image')) {
                    $destinationPath = public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    $file = $request->file('title_image');
                    $imageName = microtime(true) . "." . $file->getClientOriginalExtension();
                    $titleImageName = handleFileUpload($request, 'title_image', $destinationPath, $imageName);
                    $saveProperty->title_image = $titleImageName;
                } else {
                    $saveProperty->title_image  = '';
                }

                // Meta Image
                if ($request->hasFile('meta_image')) {
                    $destinationPath = public_path('images') . config('global.PROPERTY_SEO_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    $file = $request->file('meta_image');
                    $imageName = microtime(true) . "." . $file->getClientOriginalExtension();
                    $metaImageName = handleFileUpload($request, 'meta_image', $destinationPath, $imageName);
                    $saveProperty->meta_image = $metaImageName;
                }

                // three_d_image
                if ($request->hasFile('three_d_image')) {
                    $destinationPath = public_path('images') . config('global.3D_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    $file = $request->file('three_d_image');
                    $imageName = microtime(true) . "." . $file->getClientOriginalExtension();
                    $three_dImage = handleFileUpload($request, 'three_d_image', $destinationPath, $imageName);
                    $saveProperty->three_d_image = $three_dImage;
                } else {
                    $saveProperty->three_d_image  = '';
                }


                $saveProperty->is_premium = isset($request->is_premium) ? ($request->is_premium == "true" ? 1 : 0) : 0;
                $saveProperty->save();


                $newPropertyLimitCount = 0;
                // Increment the property limit count
                $newPropertyLimitCount = $currentPackage->used_limit_for_property + 1;
                if ($currentPackage->package->property_limit != null && $newPropertyLimitCount >= $currentPackage->package->property_limit) {
                    $addPropertyStatus = 0;
                } else if($currentPackage->package->property_limit == null){
                    $addPropertyStatus = 1;
                }else {
                    $addPropertyStatus = 1;
                }
                // Update the Limit and status
                UserPurchasedPackage::where('id', $currentPackage->id)->update(['used_limit_for_property' => $newPropertyLimitCount, 'prop_status' => $addPropertyStatus]);

                $destinationPathForParam = public_path('images') . config('global.PARAMETER_IMAGE_PATH');
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                if ($request->facilities) {
                    foreach ($request->facilities as $key => $value) {
                        $facilities = new AssignedOutdoorFacilities();
                        $facilities->facility_id = $value['facility_id'];
                        $facilities->property_id = $saveProperty->id;
                        $facilities->distance = $value['distance'];
                        $facilities->save();
                    }
                }
                if ($request->parameters) {
                    foreach ($request->parameters as $key => $parameter) {
                        if(isset($parameter['value']) && !empty($parameter['value'])){
                            $AssignParameters = new AssignParameters();
                            $AssignParameters->modal()->associate($saveProperty);
                            $AssignParameters->parameter_id = $parameter['parameter_id'];
                            if ($request->hasFile('parameters.' . $key . '.value')) {
                                $profile = $request->file('parameters.' . $key . '.value');
                                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                                $profile->move($destinationPathForParam, $imageName);
                                $AssignParameters->value = $imageName;
                            } else if (filter_var($parameter['value'], FILTER_VALIDATE_URL)) {
                                $ch = curl_init($parameter['value']);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $fileContents = curl_exec($ch);
                                curl_close($ch);
                                $filename = microtime(true) . basename($parameter['value']);
                                file_put_contents($destinationPathForParam . '/' . $filename, $fileContents);
                                $AssignParameters->value = $filename;
                            } else {
                                $AssignParameters->value = $parameter['value'];
                            }
                            $AssignParameters->save();
                        }
                    }
                }

                /// START :: UPLOAD GALLERY IMAGE
                $FolderPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH');
                if (!is_dir($FolderPath)) {
                    mkdir($FolderPath, 0777, true);
                }
                $destinationPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . "/" . $saveProperty->id;
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                if ($request->hasfile('gallery_images')) {
                    foreach ($request->file('gallery_images') as $file) {
                        $name = microtime(true). '.' . $file->extension();
                        $file->move($destinationPath, $name);
                        $gallary_image = new PropertyImages();
                        $gallary_image->image = $name;
                        $gallary_image->propertys_id = $saveProperty->id;
                        $gallary_image->save();
                    }
                }
                /// END :: UPLOAD GALLERY IMAGE


                /// START :: UPLOAD DOCUMENTS
                $FolderPath = public_path('images') . config('global.PROPERTY_DOCUMENT_PATH');
                if (!is_dir($FolderPath)) {
                    mkdir($FolderPath, 0777, true);
                }
                $destinationPath = public_path('images') . config('global.PROPERTY_DOCUMENT_PATH') . "/" . $saveProperty->id;
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                if ($request->hasfile('documents')) {
                    $documentsData = array();
                    foreach ($request->file('documents') as $file) {
                        $name = microtime(true). '.' . $file->extension();
                        $type = $file->extension();
                        $file->move($destinationPath, $name);
                        $documentsData[] = array(
                            'property_id' => $saveProperty->id,
                            'name' => $name,
                            'type' => $type
                        );
                    }

                    if(collect($documentsData)->isNotEmpty()){
                        PropertiesDocument::insert($documentsData);
                    }
                }
                /// END :: UPLOAD DOCUMENTS

                // START :: ADD CITY DATA
                if(isset($request->city) && !empty($request->city)){
                    CityImage::updateOrCreate(array('city' => $request->city));
                }
                // END :: ADD CITY DATA

                $result = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $saveProperty->id)->get();
                $property_details = get_property_details($result);

                DB::commit();
                $response['error'] = false;
                $response['message'] = 'Property Post Successfully';
                $response['data'] = $property_details;
            }
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
        return response()->json($response);
    }

    //* END :: post_property   *//
    //* START :: update_post_property   *//
    /// This api use for update and delete  property
    public function update_post_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action_type'           => 'required',
            'three_d_image'         => 'nullable|mimes:jpg,jpeg,png,gif|max:3000',
            'remove_three_d_image'  => 'nullable|in:0,1',
            'documents.*'           => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
            'price'                 => ['nullable', function ($attribute, $value, $fail) {
                if ($value > 1000000000000) {
                    $fail("The Price must not exceed one trillion that is 1000000000000.");
                }
            }],
            'video_link'            => ['nullable', 'url', function ($attribute, $value, $fail) {
                // Get the headers of the URL
                $headers = @get_headers($value, 1);

                // Check if the Content-Type header indicates a video or text/html (common for streaming platforms)
                if ($headers && isset($headers['Content-Type'])) {
                    $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
                    if (strpos($contentType, 'video/') !== 0 && strpos($contentType, 'text/html') !== 0) {
                        $fail("The Video Link must be a valid video URL.");
                    }
                } else {
                    $fail("The Video Link must be a valid URL.");
                }
            }],
        ], [], [
            'documents.*' => 'document :position'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            DB::beginTransaction();
            $current_user = Auth::user()->id;
            $id = $request->id;
            $action_type = $request->action_type;
            if ($request->slug_id) {
                $property = Property::where('added_by', $current_user)->where('slug_id', $request->slug_id)->first();
                if (!$property) {
                    $property = Property::where('added_by', $current_user)->find($id);
                }
            } else {
                $property = Property::where('added_by', $current_user)->find($id);
            }
            if (($property)) {
                // 0: Update 1: Delete
                if ($action_type == 0) {

                    $destinationPath = public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH');
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }

                    if (isset($request->category_id)) {
                        $property->category_id = $request->category_id;
                    }

                    if (isset($request->title)) {
                        $property->title = $request->title;
                        $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;
                        $property->slug_id = generateUniqueSlug($slugData, 1,null,$id);
                    }

                    if(isset($request->slug_id) && !empty($request->slug_id)){
                        $property->slug_id = generateUniqueSlug($request->slug_id, 1,null,$id);
                    }

                    if (isset($request->description)) {
                        $property->description = $request->description;
                    }

                    if (isset($request->address)) {
                        $property->address = $request->address;
                    }

                    if (isset($request->client_address)) {
                        $property->client_address = $request->client_address;
                    }

                    if (isset($request->property_type)) {
                        $property->propery_type = $request->property_type;
                    }

                    if (isset($request->price)) {
                        $property->price = $request->price;
                    }
                    if (isset($request->country)) {
                        $property->country = $request->country;
                    }
                    if (isset($request->state)) {
                        $property->state = $request->state;
                    }
                    if (isset($request->city)) {
                        $property->city = $request->city;
                    }
                    if (isset($request->status)) {
                        $property->status = $request->status;
                    }
                    if (isset($request->latitude)) {
                        $property->latitude = $request->latitude;
                    }
                    if (isset($request->longitude)) {
                        $property->longitude = $request->longitude;
                    }
                    if (isset($request->rentduration)) {
                        $property->rentduration = $request->rentduration;
                    }

                    if (isset($request->video_link)) {
                        $property->video_link = $request->video_link;
                    }

                    $property->meta_title = $request->meta_title ?? null;
                    $property->meta_description = $request->meta_description ?? null;
                    $property->meta_keywords = $request->meta_keywords ?? null;

                    if (isset($request->is_premium)) {
                        $property->is_premium = $request->is_premium == "true" ? 1 : 0;
                    }

                    if ($request->hasFile('title_image')) {
                        $profile = $request->file('title_image');
                        $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                        $profile->move($destinationPath, $imageName);
                        if ($property->title_image != '') {
                            if (file_exists(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') .  $property->title_image)) {
                                unlink(public_path('images') . config('global.PROPERTY_TITLE_IMG_PATH') . $property->title_image);
                            }
                        }
                        $property->title_image = $imageName;
                    }

                    // if ($request->hasFile('meta_image')) {
                    //     if (!empty($property->meta_image)) {
                    //         $url = $property->meta_image;
                    //         $relativePath = parse_url($url, PHP_URL_PATH);
                    //         if (file_exists(public_path()  . $relativePath)) {
                    //             unlink(public_path()  . $relativePath);
                    //         }
                    //     }
                    //     $destinationPath = public_path('images') . config('global.PROPERTY_SEO_IMG_PATH');
                    //     if (!is_dir($destinationPath)) {
                    //         mkdir($destinationPath, 0777, true);
                    //     }
                    //     $profile = $request->file('meta_image');
                    //     $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                    //     $profile->move($destinationPath, $imageName);
                    //     $property->meta_image = $imageName;
                    // } else {
                    //     if (!empty($property->meta_image)) {
                    //         $url = $property->meta_image;
                    //         $relativePath = parse_url($url, PHP_URL_PATH);
                    //         if (file_exists(public_path()  . $relativePath)) {
                    //             unlink(public_path()  . $relativePath);
                    //         }
                    //     }
                    //     $property->meta_image = null;
                    // }



                    if($request->has('meta_image')){
                        if($request->meta_image != $property->meta_image){
                            if (!empty($request->meta_image && $request->hasFile('meta_image'))) {
                                if (!empty($property->meta_image)) {
                                    $url = $property->meta_image;
                                    $relativePath = parse_url($url, PHP_URL_PATH);
                                    if (file_exists(public_path()  . $relativePath)) {
                                        unlink(public_path()  . $relativePath);
                                    }
                                }
                                $destinationPath = public_path('images') . config('global.PROPERTY_SEO_IMG_PATH');
                                if (!is_dir($destinationPath)) {
                                    mkdir($destinationPath, 0777, true);
                                }
                                $profile = $request->file('meta_image');
                                $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                                $profile->move($destinationPath, $imageName);
                                $property->meta_image = $imageName;
                            } else {
                                if (!empty($property->meta_image)) {
                                    $url = $property->meta_image;
                                    $relativePath = parse_url($url, PHP_URL_PATH);
                                    if (file_exists(public_path()  . $relativePath)) {
                                        unlink(public_path()  . $relativePath);
                                    }
                                }
                                $property->meta_image = null;
                            }
                        }
                    }

                    if($request->has('remove_three_d_image') && $request->remove_three_d_image == 1){
                        $threeDImage = $property->getRawOriginal('three_d_image');
                        if (!empty($threeDImage)) {
                            if (file_exists(public_path('images') . config('global.3D_IMG_PATH') .  $threeDImage)) {
                                unlink(public_path('images') . config('global.3D_IMG_PATH') . $threeDImage);
                            }
                        }
                        $property->three_d_image = null;
                    }

                    if ($request->hasFile('three_d_image')) {
                        $destinationPath1 = public_path('images') . config('global.3D_IMG_PATH');
                        if (!is_dir($destinationPath1)) {
                            mkdir($destinationPath1, 0777, true);
                        }
                        $profile = $request->file('three_d_image');
                        $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                        $profile->move($destinationPath1, $imageName);
                        if ($property->three_d_image != '') {
                            if (file_exists(public_path('images') . config('global.3D_IMG_PATH') .  $property->three_d_image)) {
                                unlink(public_path('images') . config('global.3D_IMG_PATH') . $property->three_d_image);
                            }
                        }
                        $property->three_d_image = $imageName;
                    }

                    if ($request->parameters) {
                        $destinationPathforparam = public_path('images') . config('global.PARAMETER_IMAGE_PATH');
                        if (!is_dir($destinationPath)) {
                            mkdir($destinationPath, 0777, true);
                        }
                        foreach ($request->parameters as $key => $parameter) {
                            $AssignParameters = AssignParameters::where('modal_id', $property->id)->where('parameter_id', $parameter['parameter_id'])->pluck('id');
                            if (count($AssignParameters)) {
                                $update_data = AssignParameters::find($AssignParameters[0]);
                                if ($request->hasFile('parameters.' . $key . '.value')) {
                                    $profile = $request->file('parameters.' . $key . '.value');
                                    $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                                    $profile->move($destinationPathforparam, $imageName);
                                    $update_data->value = $imageName;
                                } else if (filter_var($parameter['value'], FILTER_VALIDATE_URL)) {
                                    $ch = curl_init($parameter['value']);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    $fileContents = curl_exec($ch);
                                    curl_close($ch);
                                    $filename = microtime(true) . basename($parameter['value']);
                                    file_put_contents($destinationPathforparam . '/' . $filename, $fileContents);
                                    $update_data->value = $filename;
                                } else {
                                    $update_data->value = $parameter['value'];
                                }
                                $update_data->save();
                            } else {
                                $AssignParameters = new AssignParameters();
                                $AssignParameters->modal()->associate($property);
                                $AssignParameters->parameter_id = $parameter['parameter_id'];
                                if ($request->hasFile('parameters.' . $key . '.value')) {
                                    $profile = $request->file('parameters.' . $key . '.value');
                                    $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                                    $profile->move($destinationPathforparam, $imageName);
                                    $AssignParameters->value = $imageName;
                                } else if (filter_var($parameter['value'], FILTER_VALIDATE_URL)) {
                                    $ch = curl_init($parameter['value']);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    $fileContents = curl_exec($ch);
                                    curl_close($ch);
                                    $filename = microtime(true) . basename($parameter['value']);
                                    file_put_contents($destinationPathforparam . '/' . $filename, $fileContents);
                                    $AssignParameters->value = $filename;
                                } else {
                                    $AssignParameters->value = $parameter['value'];
                                }
                                $AssignParameters->save();
                            }
                        }
                    }

                    if ($request->id) {
                        $prop_id = $request->id;
                        AssignedOutdoorFacilities::where('property_id', $request->id)->delete();
                    } else {
                        $prop = Property::where('slug_id', $request->slug_id)->first();
                        $prop_id = $prop->id;
                        AssignedOutdoorFacilities::where('property_id', $prop->id)->delete();

                    }
                    // AssignedOutdoorFacilities::where('property_id', $request->id)->delete();
                    if ($request->facilities) {
                        foreach ($request->facilities as $key => $value) {
                            $facilities = new AssignedOutdoorFacilities();
                            $facilities->facility_id = $value['facility_id'];
                            $facilities->property_id = $prop_id;
                            $facilities->distance = $value['distance'];
                            $facilities->save();
                        }
                    }

                    $property->update();
                    $update_property = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $request->id)->get();

                    /// START :: UPLOAD GALLERY IMAGE
                    $FolderPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH');
                    if (!is_dir($FolderPath)) {
                        mkdir($FolderPath, 0777, true);
                    }
                    $destinationPath = public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . "/" . $property->id;
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    if ($request->remove_gallery_images) {
                        foreach ($request->remove_gallery_images as $key => $value) {
                            $gallary_images = PropertyImages::find($value);
                            if (file_exists(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $gallary_images->propertys_id . '/' . $gallary_images->image)) {
                                unlink(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $gallary_images->propertys_id . '/' . $gallary_images->image);
                            }
                            $gallary_images->delete();
                        }
                    }
                    if ($request->hasfile('gallery_images')) {
                        foreach ($request->file('gallery_images') as $file) {
                            $name = microtime(true). '.' . $file->extension();
                            $file->move($destinationPath, $name);
                            PropertyImages::create([
                                'image' => $name,
                                'propertys_id' => $property->id,
                            ]);
                        }
                    }
                    /// END :: UPLOAD GALLERY IMAGE



                    /// START :: UPLOAD DOCUMENTS
                    if ($request->remove_documents) {
                        foreach ($request->remove_documents as $key => $value) {
                            $document = PropertiesDocument::find($value);
                            if (file_exists(public_path('images') . config('global.PROPERTY_DOCUMENT_PATH') . $document->propertys_id . '/' . $document->name)) {
                                unlink(public_path('images') . config('global.PROPERTY_DOCUMENT_PATH') . $document->propertys_id . '/' . $document->name);
                            }
                            $document->delete();
                        }
                    }

                    $FolderPath = public_path('images') . config('global.PROPERTY_DOCUMENT_PATH');
                    if (!is_dir($FolderPath)) {
                        mkdir($FolderPath, 0777, true);
                    }
                    $destinationPath = public_path('images') . config('global.PROPERTY_DOCUMENT_PATH') . "/" . $property->id;
                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    if ($request->hasfile('documents')) {
                        $documentsData = array();
                        foreach ($request->file('documents') as $file) {
                            // $name = time() . rand(1, 100) . '.' . $file->extension();
                            // $type = $file->extension();
                            // $file->move($destinationPath, $name);

                            $type = $file->extension();
                            $name = microtime(true). '.' . $type;
                            $file->move($destinationPath, $name);

                            $documentsData[] = array(
                                'property_id' => $property->id,
                                'name' => $name,
                                'type' => $type
                            );
                        }

                        if(collect($documentsData)->isNotEmpty()){
                            PropertiesDocument::insert($documentsData);
                        }
                    }
                    /// END :: UPLOAD DOCUMENTS

                    // START :: ADD CITY DATA
                    if(isset($request->city) && !empty($request->city)){
                        CityImage::updateOrCreate(array('city' => $request->city));
                    }
                    // END :: ADD CITY DATA


                    $current_user = Auth::user()->id;
                    $property_details = get_property_details($update_property, $current_user);
                    $response['error'] = false;
                    $response['message'] = 'Property Update Succssfully';
                    $response['data'] = $property_details;
                } elseif ($action_type == 1) {
                    if ($property->delete()) {
                        $response['error'] = false;
                        $response['message'] =  'Delete Successfully';
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'something wrong';
                    }
                }
            } else {
                $response['error'] = true;
                $response['message'] = 'No Data Found';
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }

        return response()->json($response);
    }
    //* END :: update_post_property   *//


    //* START :: remove_post_images   *//
    public function remove_post_images(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if (!$validator->fails()) {
            $id = $request->id;
            $getImage = PropertyImages::where('id', $id)->first();
            $image = $getImage->image;
            $propertys_id =  $getImage->propertys_id;

            if (PropertyImages::where('id', $id)->delete()) {
                if (file_exists(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id . "/" . $image)) {
                    unlink(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id . "/" . $image);
                }
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }

            $countImage = PropertyImages::where('propertys_id', $propertys_id)->get();
            if ($countImage->count() == 0) {
                rmdir(public_path('images') . config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id);
            }

            $response['error'] = false;
            $response['message'] = 'Property Post Succssfully';
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }

        return response()->json($response);
    }
    //* END :: remove_post_images   *//

    //* START :: set_property_inquiry   *//




    //* START :: get_notification_list   *//
    public function get_notification_list(Request $request)
    {
        $loggedInUserId = Auth::user()->id;
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $notificationQuery = Notifications::where("customers_id", $loggedInUserId)
            ->orWhere('send_type', '1')
            ->with('property:id,title_image')
            ->select('id', 'title', 'message', 'image', 'type', 'send_type', 'customers_id', 'propertys_id', 'created_at')
            ->orderBy('id', 'DESC');

        $result = $notificationQuery->clone()
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = $notificationQuery->count();

        if (!$result->isEmpty()) {
            $result = $result->map(function ($notification) {
                $notification->created = $notification->created_at->diffForHumans();
                $notification->notification_image = !empty($notification->image) ? $notification->image : (!empty($notification->propertys_id) && !empty($notification->property) ? $notification->property->title_image : "");
                unset($notification->image);
                return $notification;
            });

            $response = [
                'error' => false,
                'total' => $total,
                'data' => $result->toArray(),
            ];
        } else {
            $response = [
                'error' => false,
                'message' => 'No data found!',
                'data' => [],
            ];
        }

        return response()->json($response);
    }
    //* END :: get_notification_list   *//




    //* START :: set_property_total_click   *//
    public function set_property_total_click(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required_without_all:project_id,project_slug_id,property_slug_id',
            'project_id' => 'required_without_all:property_id,project_slug_id,property_slug_id',
            'project_slug_id' => 'required_without_all:property_id,project_id,property_slug_id',
            'property_slug_id' => 'required_without_all:property_id,project_id,project_slug_id',
        ]);

        if (!$validator->fails()) {
            if (isset($request->project_id)) {
                // When project id is there
                $project = Projects::find($request->project_id);
                $project->increment('total_click');
            } else if ($request->property_id) {
                // When property id is there
                $Property = Property::find($request->property_id);
                $Property->increment('total_click');
            }else if (isset($request->project_slug_id)) {
                // When project slug is there
                $project = Projects::where('slug_id', $request->project_slug_id);
                $project->increment('total_click');
            } else if (isset($request->property_slug_id)) {
                // When property slug is there
                $Property = Property::where('slug_id', $request->property_slug_id);
                $Property->increment('total_click');
            }


            $response['error'] = false;
            $response['message'] = 'Update Succssfully';
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }

        return response()->json($response);
    }
    //* END :: set_property_total_click   *//


    //* START :: delete_user   *//
    public function delete_user(Request $request)
    {
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $customer = Customer::find($loggedInUserId);
            if(collect($customer)->isNotEmpty()){
                $customer->delete();
            }
            DB::commit();
            $response['error'] = false;
            $response['message'] = 'Delete Successfully';
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollBack();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }
    //* END :: delete_user   *//
    public function bearerToken($request)
    {
        $header = $request->header('Authorization', '');
        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }
    }
    //*START :: add favoutite *//
    public function add_favourite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'property_id' => 'required',


        ]);

        if (!$validator->fails()) {
            //add favourite
            $current_user = Auth::user()->id;
            if ($request->type == 1) {


                $fav_prop = Favourite::where('user_id', $current_user)->where('property_id', $request->property_id)->get();

                if (count($fav_prop) > 0) {
                    $response['error'] = false;
                    $response['message'] = "Property already add to favourite";
                    return response()->json($response);
                }
                $favourite = new Favourite();
                $favourite->user_id = $current_user;
                $favourite->property_id = $request->property_id;
                $favourite->save();
                $response['error'] = false;
                $response['message'] = "Property add to Favourite add successfully";
            }
            //delete favourite
            if ($request->type == 0) {
                Favourite::where('property_id', $request->property_id)->where('user_id', $current_user)->delete();

                $response['error'] = false;
                $response['message'] = "Property remove from Favourite  successfully";
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }


        return response()->json($response);
    }

    public function get_articles(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $article = Article::with('category:id,category,slug_id')->select('id', 'slug_id', 'image', 'title', 'description', 'meta_title', 'meta_description', 'meta_keywords', 'category_id', 'created_at');

        if (isset($request->category_id)) {
            $category_id = $request->category_id;
            if ($category_id == 0) {
                $article = $article->clone()->where('category_id', '');
            } else {

                $article = $article->clone()->where('category_id', $category_id);
            }
        }

        if (isset($request->id)) {
            $similarArticles = $article->clone()->where('id', '!=', $request->id)->get();
            $article = $article->clone()->where('id', $request->id);
        } else if (isset($request->slug_id)) {
            $category = Category::where('slug_id', $request->slug_id)->first();
            if ($category) {
                $article = $article->clone()->where('category_id', $category->id);
            } else {
                $similarArticles = $article->clone()->where('slug_id', '!=', $request->slug_id)->get();
                $article = $article->clone()->where('slug_id', $request->slug_id);
            }
        }


        $total = $article->clone()->get()->count();
        $result = $article->clone()->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        if (!$result->isEmpty()) {
            $result = $result->toArray();

            foreach ($result as &$item) {
                $item['meta_image'] = $item['image'];
                $item['created_at'] = Carbon::parse($item['created_at'])->diffForHumans();
            }

            $response['data'] = $result;
            $response['similar_articles'] = $similarArticles ?? array();
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['total'] = $total;
            $response['data'] = [];
        }
        return response()->json($response);
    }



    public function store_advertisement(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();
            $current_user = Auth::user()->id;

            $currentPackage = $this->getCurrentPackage($current_user, 2);

            if (!($currentPackage)) {
                $response['error'] = true;
                $response['message'] = 'Package not found';
                return response()->json($response);
            } else {
                $checkAdvertisement = Advertisement::where(['property_id' => $request->property_id,'type' => $request->type])->count();
                if(!empty($checkAdvertisement)){
                    $response['error'] = true;
                    $response['message'] = 'Advertisement Already Exists';
                    return response()->json($response);
                }
                $advertisementData = new Advertisement();

                $advertisementData->start_date = Carbon::now();
                if (isset($request->end_date)) {
                    $advertisementData->end_date = $request->end_date;
                } else {
                    $advertisementData->end_date = Carbon::now()->addDays($currentPackage->package->duration);
                }
                $advertisementData->package_id = $currentPackage->package_id;
                $advertisementData->type = 'HomeScreen';
                $advertisementData->property_id = $request->property_id;
                $advertisementData->customer_id = $current_user;
                $advertisementData->is_enable = false;

                // Check the auto approve and verified user status and make advertisement auto approved or pending and is enable true or false
                $autoApproveStatus = $this->getAutoApproveStatus($current_user);
                if($autoApproveStatus){
                    $advertisementData->status = 0;
                    $advertisementData->is_enable = true;
                }else{
                    $advertisementData->status = 1;
                    $advertisementData->is_enable = false;
                }

                $destinationPath = public_path('images') . config('global.ADVERTISEMENT_IMAGE_PATH');
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                // If Type is Slider then add new slider entry
                if ($request->type == 'Slider') {
                    $destinationPath_slider = public_path('images') . config('global.SLIDER_IMG_PATH');
                    if (!is_dir($destinationPath_slider)) {
                        mkdir($destinationPath_slider, 0777, true);
                    }
                    $slider = new Slider();
                    if ($request->hasFile('image')) {
                        $file = $request->file('image');
                        $name = microtime(true) . '.' . $file->extension();
                        $file->move($destinationPath_slider, $name);
                        $sliderImageName = $name;
                        $slider->image = $sliderImageName;
                    } else {
                        $slider->image = '';
                    }
                    $categoryId = Property::where('id', $request->property_id)->pluck('category_id')->first();
                    $slider->category_id = isset($request->category_id) ? $request->category_id : $categoryId;
                    $slider->propertys_id = $request->property_id;
                    $slider->save();
                }

                $advertisementData->image = "";
                $advertisementData->save();

                $result = Property::with('customer')->with('category:id,category,image')->with('favourite')->with('parameters')->with('interested_users')->where('id', $request->property_id)->get();
                $propertyDetails = get_property_details($result);

                $newAdvertisementLimitCount = 0;
                // Increment the property limit count
                $newAdvertisementLimitCount = $currentPackage->used_limit_for_advertisement + 1;
                if($currentPackage->package->advertisement_limit == null){
                    $addAdvertisementStatus = 1;
                } else if ($newAdvertisementLimitCount >= $currentPackage->package->advertisement_limit) {
                    $addAdvertisementStatus = 0;
                } else {
                    $addAdvertisementStatus = 1;
                }
                // Update the Limit and status
                UserPurchasedPackage::where('id', $currentPackage->id)->update(['used_limit_for_advertisement' => $newAdvertisementLimitCount, 'adv_status' => $addAdvertisementStatus]);

                DB::commit();
                $response['error'] = false;
                $response['message'] = "Advertisement add successfully";
                $response['data'] = $propertyDetails;
            }
            return response()->json($response);
        } catch (\Throwable $th) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    public function get_advertisement(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $article = Article::select('id', 'image', 'title', 'description');
        $date = date('Y-m-d');

        $adv = Advertisement::select('id', 'image', 'category_id', 'property_id', 'type', 'customer_id', 'is_enable', 'status')->with('customer:id,name')->where('end_date', '>', $date);
        if (isset($request->customer_id)) {
            $adv->where('customer_id', $request->customer_id);
        }
        $total = $adv->get()->count();
        $result = $adv->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        if (!$result->isEmpty()) {
            foreach ($adv as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.ADVERTISEMENT_IMAGE_PATH') . $row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }


        return response()->json($response);
    }
    public function get_package(Request $request)
    {
        if ($request->platform == "ios") {
            $packages = Package::where('status', 1)
                ->where('ios_product_id', '!=', '')
                ->orderBy('price', 'ASC')
                ->get();
        } else {
            $packages = Package::where('status', 1)
                ->orderBy('price', 'ASC')
                ->get();
        }

        $packages->transform(function ($item) use ($request) {
            if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                $currentDate = Carbon::now()->format("Y-m-d");

                $loggedInUserId = Auth::guard('sanctum')->user()->id;
                $user_package = UserPurchasedPackage::where('modal_id', $loggedInUserId)->where(function ($query) use ($currentDate) {
                    $query->whereDate('start_date', '<=', $currentDate)
                        ->whereDate('end_date', '>=', $currentDate);
                });

                if ($request->type == 'property') {
                    $user_package->where('prop_status', 1);
                } else if ($request->type == 'advertisement') {
                    $user_package->where('adv_status', 1);
                }

                $user_package = $user_package->where('package_id', $item->id)->first();


                if (!empty($user_package)) {
                    $startDate = new DateTime(Carbon::now());
                    $endDate = new DateTime($user_package->end_date);

                    // Calculate the difference between two dates
                    $interval = $startDate->diff($endDate);

                    // Get the difference in days
                    $diffInDays = $interval->days;

                    $item['is_active'] = 1;
                    $item['type'] = $item->type === "premium_user" ? "premium_user" : "product_listing";

                    if (!($item->type === "premium_user")) {
                        $item['used_limit_for_property'] = $user_package->used_limit_for_property;
                        $item['used_limit_for_advertisement'] = $user_package->used_limit_for_advertisement;
                        $item['property_status'] = $user_package->prop_status;
                        $item['advertisement_status'] = $user_package->adv_status;
                    }

                    $item['start_date'] = $user_package->start_date;
                    $item['end_date'] = $user_package->end_date;
                    $item['remaining_days'] = $diffInDays;
                } else {
                    $item['is_active'] = 0;
                }
            }

            if (!($item->type === "premium_user")) {
                $item['advertisement_limit'] = $item->advertisement_limit == '' ? "unlimited" : ($item->advertisement_limit == 0 ? "not_available" : $item->advertisement_limit);
                $item['property_limit'] = $item->property_limit == '' ? "unlimited" : ($item->property_limit == 0 ? "not_available" : $item->property_limit);
            } else {
                unset($item['property_limit']);
                unset($item['advertisement_limit']);
            }


            return $item;
        });

        // Sort the packages based on is_active flag (active packages first)
        $packages = $packages->sortByDesc('is_active');

        $response = [
            'error' => false,
            'message' => 'Data Fetch Successfully',
            'data' => $packages->values()->all(), // Reset the keys after sorting
        ];

        return response()->json($response);
    }

    //     public function get_package(Request $request)
    //     {

    //  if($request->platform=="ios"){
    //     //  dd("in");
    //               $packages = Package::where('status', 1)->where('ios_product_id','!=','')
    //             ->orderBy('id', 'ASC')

    //             ->get();

    //             }else{
    //                  $packages = Package::where('status', 1)
    //             ->orderBy('id', 'ASC')

    //             ->get();
    //             }

    //         // $packages = Package::where('status', 1)
    //         //     ->orderBy('id', 'ASC')

    //         //     ->get();

    //         $packages->map(function ($item) use ($request) {




    //             // If the user has purchased a package, set "is_active" for that specific package
    //             if ($request->filled('current_user')) {
    //                 $user_package = UserPurchasedPackage::where('modal_id', $request->current_user)->first();
    //                 $is_active = $user_package ? 1 : 0;

    //                 if ($is_active && $item->id == $user_package->package_id) {
    //                     $item['is_active'] = 1;
    //                 } else {
    //                     $item['is_active'] = 0;
    //                 }
    //             }
    //             $item['advertisement_limit'] = $item->advertisement_limit == '' ? "unlimited" : ($item->advertisement_limit == 0 ? "not_available" : $item->advertisement_limit);
    //             $item['property_limit'] = $item->property_limit == '' ? "unlimited" : ($item->property_limit == 0 ? "not_available" : $item->property_limit);

    //             return $item;
    //         });

    // // dd($packages->toArray());

    //         $response = [
    //             'error' => false,
    //             'message' => 'Data Fetch Successfully',
    //             'data' => $packages,
    //         ];

    //         return response()->json($response);
    //     }
    public function user_purchase_package(Request $request)
    {

        $start_date =  Carbon::now();
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
        ]);

        if (!$validator->fails()) {
            $loggedInUserId = Auth::user()->id;
            if (isset($request->flag)) {
                $user_exists = UserPurchasedPackage::where('modal_id', $loggedInUserId)->get();
                if ($user_exists) {
                    UserPurchasedPackage::where('modal_id', $loggedInUserId)->delete();
                }
            }

            $package = Package::find($request->package_id);
            $user = Customer::find($loggedInUserId);
            $data_exists = UserPurchasedPackage::where('modal_id', $loggedInUserId)->get();
            if (count($data_exists) == 0 && $package) {
                $user_package = new UserPurchasedPackage();
                $user_package->modal()->associate($user);
                $user_package->package_id = $request->package_id;
                $user_package->start_date = $start_date;
                $user_package->end_date = $package->duratio != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                $user_package->save();

                $user->subscription = 1;
                $user->update();

                $response['error'] = false;
                $response['message'] = "purchased package  add successfully";
            } else {
                $response['error'] = true;
                $response['message'] = "data already exists or package not found or add flag for add new package";
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function get_favourite_property(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;

        $current_user = Auth::user()->id;

        $favourite = Favourite::where('user_id', $current_user)->select('property_id')->get();
        $arr = array();
        foreach ($favourite as $p) {
            $arr[] =  $p->property_id;
        }

        $property_details = Property::whereIn('id', $arr)->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('parameters');
        $result = $property_details->clone()->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();

        $total = $property_details->clone()->count();

        if (!$result->isEmpty()) {

            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] =  get_property_details($result, $current_user);
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function delete_advertisement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',

        ]);

        if (!$validator->fails()) {
            $adv = Advertisement::find($request->id);
            if (!$adv) {
                $response['error'] = false;
                $response['message'] = "Data not found";
            } else {

                $adv->delete();
                $response['error'] = false;
                $response['message'] = "Advertisement Deleted successfully";
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function interested_users(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
            'type' => 'required'


        ]);
        if (!$validator->fails()) {
            $current_user = Auth::user()->id;

            $interested_user = InterestedUser::where('customer_id', $current_user)->where('property_id', $request->property_id);

            if ($request->type == 1) {

                if (count($interested_user->get()) > 0) {
                    $response['error'] = false;
                    $response['message'] = "already added to interested users ";
                } else {
                    $interested_user = new InterestedUser();
                    $interested_user->property_id = $request->property_id;
                    $interested_user->customer_id = $current_user;
                    $interested_user->save();
                    $response['error'] = false;
                    $response['message'] = "Interested Users added successfully";
                }
            }
            if ($request->type == 0) {

                if (count($interested_user->get()) == 0) {
                    $response['error'] = false;
                    $response['message'] = "No data found to delete";
                } else {
                    $interested_user->delete();

                    $response['error'] = false;
                    $response['message'] = "Interested Users removed  successfully";
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }

    public function user_interested_property(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;

        $current_user = Auth::user()->id;


        $favourite = InterestedUser::where('customer_id', $current_user)->select('property_id')->get();
        $arr = array();
        foreach ($favourite as $p) {
            $arr[] =  $p->property_id;
        }
        $property_details = Property::whereIn('id', $arr)->with('category:id,category')->with('parameters');
        $result = $property_details->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();


        $total = $result->count();

        if (!$result->isEmpty()) {
            foreach ($property_details as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.PROPERTY_TITLE_IMG_PATH') . $row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $result;
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_limits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_type' => 'required',

        ]);
        if (!$validator->fails()) {
            if ($request->package_type == "property") {
                $package_type = "property_limit";
                $status = "prop_status";
                $message = "Post Property";
            } else {
                $package_type = "advertisement_limit";
                $message = "Advertisement";
                $status = "adv_status";
            }

            $currentUserId = Auth::user()->id;
            update_subscription($currentUserId);
            $currentDate = Carbon::now()->format("Y-m-d");

            $current_package = UserPurchasedPackage::where('modal_id', $currentUserId)->where(function ($query) use ($currentDate) {
                $query->whereDate('start_date', '<=', $currentDate)
                    ->whereDate('end_date', '>=', $currentDate);
                })->with(['package' => function ($q) use ($package_type) {
                    $q->select('id', $package_type)->where($package_type, '>', 0)->orWhere($package_type, null);
                }])
                ->whereHas('package', function ($q) use ($package_type) {
                    $q->where($package_type, '>', 0)->orWhere($package_type, null);
                })->where($status, 1)
            ->get();


            $customer = Customer::where('id',$currentUserId)
                ->where(function ($query) {
                    $query->where('subscription', 1)->orWhere('is_premium', 1);
                })
            ->first();


            if (collect($current_package)->isEmpty()) {
                $response['error'] = true;
                $response['message'] = 'Please Subscribe for ' . $message;
            } else {
                $response['error'] = false;
                $response['message'] = "User able to upload";
            }



            if (($customer)) {
                $response['subscription'] = $customer->subscription == 1 ? true : false;
            } else {
                $response['subscription'] = false;
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function get_languages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language_code' => 'required',
        ]);

        if (!$validator->fails()) {
            $language = Language::where('code', $request->language_code)->first();

            if ($language) {
                if ($request->web_language_file) {
                    $json_file_path = public_path('web_languages/' . $request->language_code . '.json');
                } else {
                    $json_file_path = public_path('languages/' . $request->language_code . '.json');
                }

                if (file_exists($json_file_path)) {
                    $json_string = file_get_contents($json_file_path);
                    $json_data = json_decode($json_string);

                    if ($json_data !== null) {
                        $language->file_name = $json_data;
                        $response['error'] = false;
                        $response['message'] = "Data Fetch Successfully";
                        $response['data'] = $language;
                    } else {
                        $response['error'] = true;
                        $response['message'] = "Invalid JSON format in the language file";
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = "Language file not found";
                }
            } else {
                $response['error'] = true;
                $response['message'] = "Language not found";
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }

        return response()->json($response);
    }
    public function get_payment_details(Request $request)
    {
        $current_user = Auth::user()->id;
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $paymentQuery = Payments::where('customer_id', $current_user);
        $total = $paymentQuery->clone()->count();
        $result = $paymentQuery->orderBy('created_at','DESC')->skip($offset)->take($limit)->get();

        if (count($result)) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";

            $response['total'] = $total;

            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }



    public function paypal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
            'amount' => 'required'

        ]);
        if (!$validator->fails()) {
            $current_user = Auth::user()->id;
            $paypal = new Paypal();
            // url('') . config('global.IMG_PATH')

            // $returnURL = url('api/app_payment_status');
            $returnURL = url('api/app_payment_status?error=false');
            $cancelURL = url('api/app_payment_status?error=true');
            $notifyURL = url('webhook/paypal');
            // $package_id = $request->package_id;
            $package_id = $request->package_id;
            // Get product data from the database

            // Get current user ID from the session
            $paypal->add_field('return', $returnURL);
            $paypal->add_field('cancel_return', $cancelURL);
            $paypal->add_field('notify_url', $notifyURL);
            $custom_data = $package_id . ',' . $current_user;

            // // Add fields to paypal form


            $paypal->add_field('item_name', "package");
            $paypal->add_field('custom_id', json_encode($custom_data));

            $paypal->add_field('custom', ($custom_data));

            $paypal->add_field('amount', $request->amount);

            // Render paypal form
            $paypal->paypal_auto_form();
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
    }
    public function app_payment_status(Request $request)
    {
        $paypalInfo = $request->all();
        // Get Web URL
        $webURL = system_setting('web_url') ?? null;
        if (isset($paypalInfo) && !empty($paypalInfo) && isset($paypalInfo['payment_status']) && !empty($paypalInfo['payment_status'])){
            if($paypalInfo['payment_status'] == "Completed") {
                $webWithStatusURL = $webURL.'/payment/success';
                $response['error'] = false;
                $response['message'] = "Your Purchase Package Activate Within 10 Minutes ";
                $response['data'] = $paypalInfo['txn_id'];
            } elseif ($paypalInfo['payment_status'] == "Authorized") {
                $webWithStatusURL = $webURL.'/payment/success';
                $response['error'] = false;
                $response['message'] = "Your payment has been Authorized successfully. We will capture your transaction within 30 minutes, once we process your order. After successful capture Ads wil be credited automatically.";
                $response['data'] = $paypalInfo;
            } else {
                $webWithStatusURL = $webURL.'/payment/fail';
                $response['error'] = true;
                $response['message'] = "Payment Cancelled / Declined ";
                $response['data'] = !empty($paypalInfo) ? $paypalInfo : "";
            }
        }else{
            $webWithStatusURL = $webURL.'/payment/fail';
            $response['error'] = true;
            $response['message'] = "Payment Cancelled / Declined ";
        }

        if($webURL){
            echo "<html>
            <body>
            Redirecting...!
            </body>
            <script>
                window.location.replace('".$webWithStatusURL."');
            </script>
            </html>";
        }else{
            echo "<html>
            <body>
            Redirecting...!
            </body>
            <script>
                console.log('No web url added');
            </script>
            </html>";
        }
        // return (response()->json($response));
    }
    public function get_payment_settings(Request $request)
    {
        $payment_settings = Setting::select('type', 'data')->whereIn('type', ['paypal_business_id', 'sandbox_mode', 'paypal_gateway', 'razor_key', 'razor_secret', 'razorpay_gateway', 'paystack_public_key', 'paystack_secret_key', 'paystack_currency', 'paystack_gateway', 'stripe_publishable_key', 'stripe_currency', 'stripe_gateway', 'stripe_secret_key','flutterwave_status'])->get();
        foreach ($payment_settings as $setting) {
            if ($setting->type === 'stripe_secret_key') {
                $publicKey = file_get_contents(base_path('public_key.pem')); // Load the public key
                $encryptedData = '';
                if (openssl_public_encrypt($setting->data, $encryptedData, $publicKey)) {
                    $setting->data = base64_encode($encryptedData);
                }
            }
        }

        if (count($payment_settings)) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $payment_settings;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return (response()->json($response));
    }
    public function send_message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sender_id' => 'required',
            'receiver_id' => 'required',
            'property_id' => 'required',
            'file' => 'nullable|mimes:png,jpg,jpeg,pdf,doc,docx|max:2024',
            'audio' => 'nullable|mimes:mpeg,m4a,mp3,mp4|max:5024'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::select('id', 'name', 'profile')->with(['usertokens' => function ($q) {
            $q->select('fcm_id', 'id', 'customer_id');
        }])->find($request->receiver_id);
        if(collect($customer)->isNotEmpty()){
            $senderBlockedReciever = BlockedChatUser::where(['by_user_id' => $request->sender_id, 'user_id' => $request->receiver_id])->count();
            if($senderBlockedReciever){
                ResponseService::errorResponse("You have blocked user");
            }
            $recieverBlockedSender = BlockedChatUser::where(['by_user_id' => $request->receiver_id, 'user_id' => $request->sender_id])->count();
            if($recieverBlockedSender){
                ResponseService::errorResponse("You are blocked by user");
            }
        }else{
            $senderBlockedReciever = BlockedChatUser::where(['by_user_id' => $request->sender_id, 'admin' => 1])->count();
            if($senderBlockedReciever){
                ResponseService::errorResponse("You have blocked admin");
            }
            $recieverBlockedSender = BlockedChatUser::where(['by_admin' => 1, 'user_id' => $request->sender_id])->count();
            if($recieverBlockedSender){
                ResponseService::errorResponse("You are blocked by admin");
            }
        }

        $fcm_id = array();
        $chat = new Chats();
        $chat->sender_id = $request->sender_id;
        $chat->receiver_id = $request->receiver_id;
        $chat->property_id = $request->property_id;
        $chat->message = $request->message;

        $destinationPath = public_path('images') . config('global.CHAT_FILE');
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }
        // Files upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = microtime(true) . "." . $file->getClientOriginalExtension();
            $file->move($destinationPath, $fileName);
            $chat->file = $fileName;
        } else {
            $chat->file = '';
        }

        $audiodestinationPath = public_path('images') . config('global.CHAT_AUDIO');
        if (!is_dir($audiodestinationPath)) {
            mkdir($audiodestinationPath, 0777, true);
        }
        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $fileName = microtime(true) . "." . $file->getClientOriginalExtension();
            $file->move($audiodestinationPath, $fileName);
            $chat->audio = $fileName;
        } else {
            $chat->audio = '';
        }
        $chat->save();

        if ($customer) {
            foreach ($customer->usertokens as $usertokens) {
                array_push($fcm_id, $usertokens->fcm_id);
            }
            $username = $customer->name;
        } else {

            $user_data = User::select('fcm_id', 'name')->get();
            $username = "Admin";
            foreach ($user_data as $user) {
                array_push($fcm_id, $user->fcm_id);
            }
        }
        $senderUser = Customer::select('fcm_id', 'name', 'profile')->find($request->sender_id);
        if ($senderUser) {
            $profile = $senderUser->profile;
        } else {
            $profile = "";
        }

        $Property = Property::find($request->property_id);






        $chat_message_type = "";

        if (!empty($request->file('audio'))) {
            $chat_message_type = "audio";
        } else if (!empty($request->file('file')) && $request->message == "") {
            $chat_message_type = "file";
        } else if (!empty($request->file('file')) && $request->message != "") {
            $chat_message_type = "file_and_text";
        } else if (empty($request->file('file')) && $request->message != "" && empty($request->file('audio'))) {
            $chat_message_type = "text";
        } else {
            $chat_message_type = "text";
        }


        $fcmMsg = array(
            'title' => 'Message',
            'message' => $request->message,
            'type' => 'chat',
            'body' => $request->message,
            'sender_id' => $request->sender_id,
            'receiver_id' => $request->receiver_id,
            'file' => $chat->file,
            'username' => $username,
            'user_profile' => $profile,
            'audio' => $chat->audio,
            'date' => $chat->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'sound' => 'default',
            'time_ago' => $chat->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true),
            'property_id' => (string)$Property->id,
            'property_title_image' => $Property->title_image,
            'title' => $Property->title,
            'chat_message_type' => $chat_message_type,
            'created_at' => Carbon::parse($chat->created_at)->toIso8601ZuluString()
        );

        $send = send_push_notification($fcm_id, $fcmMsg);
        $response['error'] = false;
        $response['message'] = "Data Store Successfully";
        $response['id'] = $chat->id;
        // $response['data'] = $send;
        return (response()->json($response));
    }
    public function get_messages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
            'user_id' => 'required'

        ]);
        if (!$validator->fails()) {
            $currentUser = Auth::user();
            $userId = $request->user_id;

            $perPage = $request->per_page ? $request->per_page : 15; // Number of results to display per page
            $page = $request->page ?? 1; // Get the current page from the query string, or default to 1
            $chat = Chats::where('property_id', $request->property_id)
                ->where(function ($query) use ($currentUser, $userId) {
                    $query->where(function ($query) use ($currentUser, $userId) {
                        $query->where('sender_id', $currentUser->id)
                            ->where('receiver_id', $userId);
                    })->orWhere(function ($query) use ($currentUser, $userId) {
                        $query->where('sender_id', $userId)
                            ->where('receiver_id', $currentUser->id);
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage, ['*'], 'page', $page);

            // You can then pass the $chat object to your view to display the paginated results.




            $chat_message_type = "";
            if ($chat) {


                $chat->map(function ($chat) use ($chat_message_type, $currentUser) {
                    if (!empty($chat->audio)) {
                        $chat_message_type = "audio";
                    } else if (!empty($chat->file) && $chat->message == "") {
                        $chat_message_type = "file";
                    } else if (!empty($chat->file) && $chat->message != "") {
                        $chat_message_type = "file_and_text";
                    } else if (empty($chat->file) && !empty($chat->message) && empty($chat->audio)) {
                        $chat_message_type = "text";
                    } else {
                        $chat_message_type = "text";
                    }
                    $chat['chat_message_type'] = $chat_message_type;
                    $chat['user_profile'] = $currentUser->profile;
                    $chat['time_ago'] = $chat->created_at->diffForHumans();
                });

                $response['error'] = false;
                $response['message'] = "Data Fetch Successfully";
                $response['total_page'] = $chat->lastPage();
                $response['data'] = $chat;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }

    public function get_chats(Request $request)
    {
        $current_user = Auth::user()->id;
        $perPage = $request->per_page ? $request->per_page : 15; // Number of results to display per page
        $page = $request->page ?? 1;

        $chat = Chats::with(['sender', 'receiver'])->with('property')
           ->select('id', 'sender_id', 'receiver_id', 'property_id', 'created_at',
                DB::raw('LEAST(sender_id, receiver_id) as user1_id'),
                DB::raw('GREATEST(sender_id, receiver_id) as user2_id')
            )
            ->where(function($query) use ($current_user) {
                $query->where('sender_id', $current_user)
                    ->orWhere('receiver_id', $current_user);
            })
            ->orderBy('id', 'desc')
            ->groupBy('user1_id', 'user2_id', 'property_id')
            ->paginate($perPage, ['*'], 'page', $page);

        if (!$chat->isEmpty()) {

            $rows = array();

            $count = 1;

            $response['total_page'] = $chat->lastPage();

            foreach ($chat as $key => $row) {
                $tempRow = array();
                $tempRow['property_id'] = $row->property_id;
                $tempRow['title'] = $row->property->title;
                $tempRow['title_image'] = $row->property->title_image;
                $tempRow['date'] = $row->created_at;
                $tempRow['property_id'] = $row->property_id;
                if (!$row->receiver || !$row->sender) {
                    $user = Customer::where('id', $row->sender_id)->orWhere('id', $row->receiver_id)->select('id')->first();

                    $isBlockedByMe = false;
                    $isBlockedByUser = false;

                    $blockedByMe = BlockedChatUser::where('by_user_id', $user->id)
                        ->where('admin', 1)
                        ->exists();

                    $blockedByAdmin = BlockedChatUser::where('by_admin', 1)
                        ->where('user_id', $user->id)
                        ->exists();
                    $tempRow['is_blocked_by_me'] = $blockedByMe;
                    $tempRow['is_blocked_by_user'] = $blockedByAdmin;


                    $tempRow['user_id'] = 0;
                    $tempRow['name'] = "Admin";
                    $tempRow['profile'] = url('assets/images/faces/2.jpg');

                    // $tempRow['fcm_id'] = $row->receiver->fcm_id;
                } else {

                    $isBlockedByMe = false;
                    $isBlockedByUser = false;
                    if ($row->sender->id == $current_user) {
                        $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                            ->where('user_id', $row->receiver->id)
                            ->exists();

                        $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->receiver->id)
                            ->where('user_id', $current_user)
                            ->exists();

                        $tempRow['is_blocked_by_me'] = $isBlockedByMe;
                        $tempRow['is_blocked_by_user'] = $isBlockedByUser;

                        $tempRow['user_id'] = $row->receiver->id;
                        $tempRow['name'] = $row->receiver->name;
                        $tempRow['profile'] = $row->receiver->profile;
                        $tempRow['fcm_id'] = $row->receiver->fcm_id;
                    }
                    if ($row->receiver->id == $current_user) {

                        $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                            ->where('user_id', $row->sender->id)
                            ->exists();

                        $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->sender->id)
                            ->where('user_id', $current_user)
                            ->exists();

                        $tempRow['is_blocked_by_me'] = $isBlockedByMe;
                        $tempRow['is_blocked_by_user'] = $isBlockedByUser;

                        $tempRow['user_id'] = $row->sender->id;
                        $tempRow['name'] = $row->sender->name;
                        $tempRow['profile'] = $row->sender->profile;
                        $tempRow['fcm_id'] = $row->sender->fcm_id;
                    }
                }
                $rows[] = $tempRow;
                $count++;
            }


            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";
            $response['data'] = $rows;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_nearby_properties(Request $request)
    {

        // Create Property Query
        $propertyQuery = Property::with('category')->where('status', 1);

        // Check the city and state params and query the params according to it
        if (isset($request->city) || isset($request->state)) {
            $propertyQuery->where(function ($query) use ($request) {
                $query->where('state', 'LIKE', "%{$request->state}%")
                    ->orWhere('city', 'LIKE', "%{$request->city}%");
            });
        }

        // Check the type params and query the params according to it
        if (isset($request->type)) {
            $propertyQuery->where('propery_type', $request->type);
        }

        // Get Final Data
        $propertiesData = $propertyQuery->select(
            'id', 'slug_id', 'category_id', 'title', 'title_image', 'price',
            'latitude', 'longitude', 'city', 'state', 'country',
            'propery_type as property_type'
        )->get();

        // Pass data as json
        if (collect($propertiesData)->isNotEmpty()) {
            $response['error'] = false;
            $response['data'] = $propertiesData;
        } else {
            $response['error'] = false;
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function update_property_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'property_id' => 'required'

        ]);
        if (!$validator->fails()) {
            $property = Property::find($request->property_id);
            $property->propery_type = $request->status;
            $property->save();
            $response['error'] = false;
            $response['message'] = "Data updated Successfully";
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function getCitiesData(Request $request)
    {
        // Get Offset and Limit from payload request
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $city_arr = array();
        $citiesQuery = CityImage::where('status',1);
        $citiesData = $citiesQuery->clone()->withCount(['property' => function ($query) {
            $query->where('status', 1);
        }])->orderBy('property_count','DESC')->skip($offset)->take($limit)->get();
        $totalData = $citiesQuery->clone()->count();
        foreach ($citiesData as $city) {
            if (!empty($city->getRawOriginal('image'))) {
                $url = $city->image;
                $relativePath = parse_url($url, PHP_URL_PATH);
                if (file_exists(public_path()  . $relativePath)) {
                    array_push($city_arr, ['City' => $city->city, 'Count' => $city->property_count, 'image' => $city->image]);
                    continue;
                }
            }
            $resultArray = $this->getUnsplashData($city);
            array_push($city_arr, $resultArray);
        }
        $response['error'] = false;
        $response['data'] = $city_arr;
        $response['total'] = $totalData;
        $response['message'] = "Data Fetched Successfully";

        return response()->json($response);
    }

    public function get_facilities(Request $request)
    {
        $facilities = OutdoorFacilities::query();

        // if (isset($request->search) && !empty($request->search)) {
        //     $search = $request->search;
        //     $facilities->where('category', 'LIKE', "%$search%");
        // }

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $facilities->where('id', '=', $id);
        }
        $total = $facilities->clone()->count();
        $result = $facilities->clone()->get();


        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_report_reasons(Request $request)
    {
        $report_reason = report_reasons::query();

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $report_reason->where('id', '=', $id);
        }
        $result = $report_reason->clone()->get();

        $total = $report_reason->clone()->count();

        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function add_reports(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason_id' => 'required',
            'property_id' => 'required',



        ]);
        $current_user = Auth::user()->id;
        if (!$validator->fails()) {
            $report_count = user_reports::where('property_id', $request->property_id)->where('customer_id', $current_user)->get();
            if (!count($report_count)) {
                $report_reason = new user_reports();
                $report_reason->reason_id = $request->reason_id ? $request->reason_id : 0;
                $report_reason->property_id = $request->property_id;
                $report_reason->customer_id = $current_user;
                $report_reason->other_message = $request->other_message ? $request->other_message : '';



                $report_reason->save();


                $response['error'] = false;
                $response['message'] = "Report Submited Successfully";
            } else {
                $response['error'] = false;
                $response['message'] = "Already Reported";
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Please fill all data and Submit";
        }
        return response()->json($response);
    }
    public function delete_chat_message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try{
            // Get Customer IDs

            // Get FCM IDs
            $fcmId = Usertokens::select('fcm_id')->where('customer_id', $request->receiver_id)->pluck('fcm_id')->toArray();

            if (isset($request->message_id)) {
                $chat = Chats::find($request->message_id);
                if ($chat) {
                    if (!empty($fcmId)) {
                        $registrationIDs = array_filter($fcmId);
                        $fcmMsg = array(
                            'title' => "Delete Chat Message",
                            'message' => "Message Deleted Successfully",
                            "image" => '',
                            'type' => 'delete_message',
                            'message_id' => $request->message_id,
                            'body' => 'Message Deleted Successfully',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',

                        );
                        send_push_notification($registrationIDs, $fcmMsg, 1);
                    }
                    $chat->delete();
                    ResponseService::successResponse("Message Deleted Successfully");
                }else{
                    ResponseService::errorResponse("No data found");
                }
            }else if (isset($request->sender_id) && isset($request->receiver_id) && isset($request->property_id)) {

                $user_chat = Chats::where('property_id', $request->property_id)
                    ->where(function ($query) use ($request) {
                        $query->where('sender_id', $request->sender_id)
                            ->orWhere('receiver_id', $request->receiver_id);
                    })
                    ->orWhere(function ($query) use ($request) {
                        $query->where('sender_id', $request->receiver_id)
                            ->orWhere('receiver_id', $request->sender_id);
                    });
                if (count($user_chat->get())) {

                    $user_chat->delete();
                    ResponseService::successResponse("chat deleted successfully");
                } else {
                    ResponseService::errorResponse("No data found");
                }
            } else {
                ResponseService::errorResponse("No data found");
            }
        } catch(Exception $e){
            ResponseService::errorResponse("Something went wrong");
        }
    }
    public function get_user_recommendation(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $current_user = Auth::user()->id;


        $user_interest = UserInterest::where('user_id', $current_user)->first();
        if (collect($user_interest)->isNotEmpty()) {

            $property = Property::with('customer')->with('user')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('status', 1);


            $property_type = $request->property_type;
            if ($user_interest->category_ids != '') {

                $category_ids = explode(',', $user_interest->category_ids);

                $property = $property->whereIn('category_id', $category_ids);
            }

            if ($user_interest->price_range != '') {

                $max_price = explode(',', $user_interest->price_range)[1];

                $min_price = explode(',', $user_interest->price_range)[0];

                if (isset($max_price) && isset($min_price)) {
                    $min_price = floatval($min_price);
                    $max_price = floatval($max_price);

                    $property = $property->where(function ($query) use ($min_price, $max_price) {
                        $query->whereRaw("CAST(price AS DECIMAL(10, 2)) >= ?", [$min_price])
                            ->whereRaw("CAST(price AS DECIMAL(10, 2)) <= ?", [$max_price]);
                    });
                }
            }


            if ($user_interest->city != '') {
                $city = $user_interest->city;
                $property = $property->where('city', $city);
            }
            if ($user_interest->property_type != '') {
                $property_type = explode(',',  $user_interest->property_type);
            }
            if ($user_interest->outdoor_facilitiy_ids != '') {


                $outdoor_facilitiy_ids = explode(',', $user_interest->outdoor_facilitiy_ids);
                $property = $property->whereHas('assignfacilities.outdoorfacilities', function ($q) use ($outdoor_facilitiy_ids) {
                    $q->whereIn('id', $outdoor_facilitiy_ids);
                });
            }



            if (isset($property_type)) {
                if (count($property_type) == 2) {
                    $property_type = $property->where(function ($query) use ($property_type) {
                        $query->where('propery_type', $property_type[0])->orWhere('propery_type', $property_type[1]);
                    });
                } else {
                    if (isset($property_type[0])  &&  $property_type[0] == 0) {

                        $property = $property->where('propery_type', $property_type[0]);
                    }
                    if (isset($property_type[0])  &&  $property_type[0] == 1) {

                        $property = $property->where('propery_type', $property_type[0]);
                    }
                }
            }



            $total = $property->get()->count();

            $result = $property->skip($offset)->take($limit)->get();
            $property_details = get_property_details($result, $current_user);

            if (!empty($result)) {
                $response['error'] = false;
                $response['message'] = "Data Fetch Successfully";
                $response['total'] = $total;
                $response['data'] = $property_details;
            } else {

                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return ($response);
    }
    public function contct_us(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'subject' => 'required',
            'message' => 'required',




        ]);

        if (!$validator->fails()) {

            $contactrequest = new Contactrequests();
            $contactrequest->first_name = $request->first_name;
            $contactrequest->last_name = $request->last_name;
            $contactrequest->email = $request->email;
            $contactrequest->subject = $request->subject;
            $contactrequest->message = $request->message;
            $contactrequest->save();
            $response['error'] = false;
            $response['message'] = "Contact Request Send successfully";
        } else {


            $response['error'] = true;
            $response['message'] =  $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'package_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $limit = isset($request->limit) ? $request->limit : 10;
            $current_user = Auth::user()->id;

            $secret_key = system_setting('stripe_secret_key');

            $stripe_currency = system_setting('stripe_currency');
            $package = Package::find($request->package_id);

            $data = [
                'amount' => ((int)($package['price'])) * 100,
                'currency' => $stripe_currency,
                'description' => $request->description,


                'payment_method_types[]' => $request->payment_method,
                'metadata' => [
                    'userId' => $current_user,
                    'packageId' => $request->package_id,
                ],
                'shipping' => [
                    'name' => $request['shipping']['name'], // Replace with the actual name
                    'address' => [
                        'line1' => !empty($request['shipping']['address']['line1']) ? $request['shipping']['address']['line1'] : '',
                        'line2' => !empty($request['shipping']['address']['line2']) ? $request['shipping']['address']['line2'] : '',
                        'postal_code' => !empty($request['shipping']['address']['postal_code']) ? $request['shipping']['address']['postal_code'] : '',
                        'city' => !empty($request['shipping']['address']['city']) ? $request['shipping']['address']['city'] : '',
                        'state' => !empty($request['shipping']['address']['state']) ? $request['shipping']['address']['state'] : '',
                        'country' => !empty($request['shipping']['address']['country']) ? $request['shipping']['address']['country'] : '',
                    ],
                ],
            ];
            $headers = [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $response = Http::withHeaders($headers)->asForm()->post('https://api.stripe.com/v1/payment_intents', $data);
            $responseData = $response->json();
            return response()->json([
                'data' => $responseData,
                'message' => 'Intent created.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while processing the payment.',
            ], 500);
        }
    }
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paymentIntentId' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {

            $secret_key = system_setting('stripe_secret_key');
            $headers = [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $response = Http::withHeaders($headers)
                ->get("https://api.stripe.com/v1/payment_intents/{$request->paymentIntentId}");
            $responseData = $response->json();
            $statusOfTransaction = $responseData['status'];
            if ($statusOfTransaction == 'succeeded') {
                return response()->json([
                    'message' => 'Transaction successful',
                    'success' => true,
                    'status' => $statusOfTransaction,
                ]);
            } elseif ($statusOfTransaction == 'pending' || $statusOfTransaction == 'captured') {
                return response()->json([
                    'message' => 'Transaction pending',
                    'success' => true,
                    'status' => $statusOfTransaction,
                ]);
            } else {
                return response()->json([
                    'message' => 'Transaction failed',
                    'success' => false,
                    'status' => $statusOfTransaction,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while processing the payment.',
            ], 500);
        }
    }
    public function delete_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            DB::beginTransaction();
            Property::find($request->id)->delete();
            DB::commit();
            $response['error'] = false;
            $response['message'] =  'Delete Successfully';
            return response()->json($response);
        }catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }
    public function assign_package(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
            'product_id' => 'required_if:in_app,true',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        $current_user = Auth::user()->id;

        $user = Customer::find($current_user);

        $start_date =  Carbon::now();

        if ($request->in_app == 'true' || $request->in_app === true) {
            $package = Package::where('ios_product_id', $request->product_id)->first();
        } else {
            $package = Package::find($request->package_id);
        }

        if ($package) {
            if ($package->type == "premium_user") {
                UserPurchasedPackage::where('modal_id', $current_user)->where('package_id', $package->id)->delete();
            }
            $user_package = new UserPurchasedPackage();

            $user_package->modal()->associate($user);
            $user_package->package_id = $request->package_id;
            $user_package->start_date = $start_date;
            $user_package->end_date = $package->duration != 0 ? Carbon::now()->addDays($package->duration) : NULL;
            $user_package->save();

            if ($package->type == "premium_user") {
                $user->is_premium = 1;
            } else {

                $user->subscription = 1;
            }
            $user->update();
            $response['error'] = false;
            $response['message'] =  'Package Purchased Successfully';
        } else {
            $response['error'] = true;
            $response['message'] =  'Package Not Found';
        }
        return response()->json($response);
    }
    // public function assign_package(Request $request)
    // {

    //     $validator = Validator::make($request->all(), [
    //         'package_id' => 'required',
    //         'product_id' => 'required_if:in_app,true',


    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'error' => true,
    //             'message' => $validator->errors()->first(),
    //         ]);
    //     }
    //     $payload = JWTAuth::getPayload($this->bearerToken($request));
    //     $current_user = ($payload['customer_id']);
    //     $user = Customer::find($current_user);
    //     $start_date =  Carbon::now();
    //     if ($request->in_app) {
    //         $package = Package::where('ios_product_id', $request->product_id)->find($request->package_id);
    //     } else {

    //         $package = Package::where('price', 0)->find($request->package_id);
    //     }
    //     $data_exists = UserPurchasedPackage::where('modal_id', $current_user)->get();

    //     if ($package) {

    //         $user_package = new UserPurchasedPackage();

    //         $user_package->modal()->associate($user);
    //         $user_package->package_id = $request->package_id;
    //         $user_package->start_date = $start_date;
    //         $user_package->end_date = $package->duration != 0 ? Carbon::now()->addDays($package->duration) : NULL;
    //         $user_package->save();
    //         if ($data_exists) {
    //             UserPurchasedPackage::where('modal_id', $current_user)->where('id', '!=', $user_package->id)->delete();
    //         }
    //         $user->subscription = 1;
    //         $user->update();
    //         $response['error'] = false;
    //         $response['message'] =  'Package Purchased Successfully';
    //     } else {
    //         $response['error'] = false;
    //         $response['message'] =  'Package Not Found';
    //     }
    //     return response()->json($response);
    // }
    public function get_app_settings(Request $request)
    {
        $result =  Setting::select('type', 'data')->whereIn('type', ['app_home_screen', 'placeholder_logo', 'light_tertiary', 'light_secondary', 'light_primary', 'dark_tertiary', 'dark_secondary', 'dark_primary'])->get();


        $tempRow = [];
 
        if (($request->user_id) != "") {
            update_subscription($request->user_id);

            $customer_data = Customer::find($request->user_id);
            if ($customer_data) {
                if ($customer_data->isActive == 0) {

                    $tempRow['is_active'] = false;
                } else {
                    $tempRow['is_active'] = true;
                }
            }
        }



        foreach ($result as $row) {
            $tempRow[$row->type] = $row->data;

            if ($row->type == 'app_home_screen' || $row->type == "placeholder_logo") {

                $tempRow[$row->type] = url('/assets/images/logo/') . '/' . $row->data;
            }
        }

        $response['error'] = false;
        $response['data'] = $tempRow;
        return response()->json($response);
    }
    public function get_seo_settings(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $seo_settings = SeoSettings::select('id', 'page', 'image', 'title', 'description', 'keywords');


        if (isset($request->page) && !empty($request->page)) {

            $seo_settings->where('page', 'LIKE', "%$request->page%");
        } else {
            $seo_settings->where('page', 'LIKE', "%homepage%");
        }

        $total = $seo_settings->count();
        $result = $seo_settings->skip($offset)->take($limit)->get();


        // $seo_settingsWithCount = Category::withCount('properties')->get();
        $rows = array();
        $count = 0;
        if (!$result->isEmpty()) {

            foreach ($result as $key => $row) {
                $tempRow['id'] = $row->id;
                $tempRow['page'] = $row->page;
                $tempRow['meta_image'] = $row->image;

                if ($row->page == "properties-city") {
                    $tempRow['meta_title'] = "[Your City]'s Finest:" . $row->title;
                    $tempRow['meta_description'] = "Discover the charm of living near [Your City]." . $row->description;
                } else {

                    $tempRow['meta_title'] = $row->title;
                    $tempRow['meta_description'] = $row->description;
                }
                $tempRow['meta_keywords'] = $row->keywords;

                $rows[] = $tempRow;

                $count++;
            }
        }


        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = "Data Fetch Successfully";


            $response['total'] = $total;
            $response['data'] = $rows;
        } else {
            $response['error'] = false;
            $response['message'] = "No data found!";
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function getInterestedUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'property_id' => 'required_without:slug_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->errors()->first(),
                ]);
            }

            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            if (isset($request->slug_id)) {
                $property = Property::where('slug_id', $request->slug_id)->first();
                $property_id = $property->id;
            } else {
                $property_id = $request->property_id;
            }

            $interestedUserQuery = InterestedUser::has('customer')->with('customer:id,name,profile,email,mobile')->where('property_id', $property_id);
            $totalData = $interestedUserQuery->clone()->count();
            $interestedData = $interestedUserQuery->take($limit)->skip($offset)->get();
            if(collect($interestedData)->isNotEmpty()){
                $data = $interestedData->pluck('customer');
                ResponseService::successResponse("Data Fetched Successfully",$data,['total' => $totalData]);
            }else{
                ResponseService::errorResponse("No Data Found");
            }
        } catch (Exception $e) {
            ResponseService::errorResponse("Something Went Wrong");
        }
    }
    public function post_project(Request $request)
    {
        if ($request->has('id')) {
            $validator = Validator::make($request->all(), [
                'title' => 'required',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'title'         => 'required',
                'description'   => 'required',
                'image'         => 'required|file|max:3000|mimes:jpeg,png,jpg',
                'category_id'   => 'required',
                'city'          => 'required',
                'state'         => 'required',
                'country'       => 'required',
            ]);
        }
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            DB::beginTransaction();
            $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;

            $currentUser = Auth::user()->id;
            if (!(isset($request->id))) {
                $currentPackage = $this->getCurrentPackage($currentUser, 1);
                if (!($currentPackage)) {
                    $response['error'] = true;
                    $response['message'] = 'Package not found';
                    return response()->json($response);
                }
                $project = new Projects();

                // Check the auto approve and verified user status and make project auto enable or disable
                $autoApproveStatus = $this->getAutoApproveStatus($currentUser);
                if($autoApproveStatus){
                    $project->status = 1;
                }else{
                    $project->status = 0;
                }
            } else {
                $project = Projects::where('added_by', $currentUser)->find($request->id);
                if (!$project) {
                    $response['error'] = false;
                    $response['message'] = 'Project Not Found ';
                }
            }

            if ($request->category_id) {
                $project->category_id = $request->category_id;
            }
            if ($request->description) {
                $project->description = $request->description;
            }
            if ($request->location) {
                $project->location = $request->location;
            }
            if ($request->meta_title) {
                $project->meta_title = $request->meta_title;
            }
            if ($request->meta_description) {
                $project->meta_description = $request->meta_description;
            }
            if ($request->meta_keywords) {
                $project->meta_keywords = $request->meta_keywords;
            }
            $project->added_by = $currentUser;
            if ($request->country) {
                $project->country = $request->country;
            }
            if ($request->state) {
                $project->state = $request->state;
            }
            if ($request->city) {
                $project->city = $request->city;
            }
            if ($request->latitude) {
                $project->latitude = $request->latitude;
            }
            if ($request->longitude) {
                $project->longitude = $request->longitude;
            }
            if ($request->video_link) {
                $project->video_link = $request->video_link;
            }
            if ($request->type) {
                $project->type = $request->type;
            }
            if ($request->id) {
                if ($project->title !== $request->title) {
                    $title = !empty($request->title) ? $request->title : $project->title;
                    $project->title = $title;
                } else {
                    $title = $request->title;
                    $project->title = $title;
                }
                $project->slug_id = generateUniqueSlug($slugData, 4, null, $request->id);
                if ($request->hasFile('image')) {
                    $project->image = store_image($request->file('image'), 'PROJECT_TITLE_IMG_PATH');
                }

                if($request->has('meta_image')){
                    if($request->meta_image != $project->meta_image){
                        if (!empty($request->meta_image && $request->hasFile('meta_image'))) {
                            if (!empty($project->meta_image)) {
                                $url = $project->meta_image;
                                $relativePath = parse_url($url, PHP_URL_PATH);
                                if (file_exists(public_path()  . $relativePath)) {
                                    unlink(public_path()  . $relativePath);
                                }
                            }
                            $destinationPath = public_path('images') . config('global.PROJECT_SEO_IMG_PATH');
                            if (!is_dir($destinationPath)) {
                                mkdir($destinationPath, 0777, true);
                            }
                            $profile = $request->file('meta_image');
                            $imageName = microtime(true) . "." . $profile->getClientOriginalExtension();
                            $profile->move($destinationPath, $imageName);
                            $project->meta_image = $imageName;
                        } else {
                            if (!empty($project->meta_image)) {
                                $url = $project->meta_image;
                                $relativePath = parse_url($url, PHP_URL_PATH);
                                if (file_exists(public_path()  . $relativePath)) {
                                    unlink(public_path()  . $relativePath);
                                }
                            }
                            $project->meta_image = null;
                        }
                    }
                }
                // if ($request->hasFile('meta_image')) {
                //     if ($project->meta_image) {
                //         unlink_image($project->meta_image);
                //     }
                //     $project->meta_image = store_image($request->file('meta_image'), 'PROJECT_SEO_IMG_PATH');
                // }else{
                //     if ($request->has('image')){
                //         if(!empty($request->meta_image)) {
                //             $url = $project->meta_image;
                //             $relativePath = parse_url($url, PHP_URL_PATH);
                //             if (file_exists(public_path()  . $relativePath)) {
                //                 unlink(public_path()  . $relativePath);
                //             }
                //         }
                //         $project->meta_image = null;
                //     }
                // }
            } else {
                $project->title = $request->title;
                $project->image = $request->hasFile('image') ? store_image($request->file('image'), 'PROJECT_TITLE_IMG_PATH') : '';
                $project->meta_image = $request->hasFile('meta_image') ? store_image($request->file('meta_image'), 'PROJECT_SEO_IMG_PATH') : '';
                $title = $request->title;
                $project->slug_id = generateUniqueSlug($slugData, 4);
            }

            $project->save();

            if ($request->remove_gallery_images) {
                $remove_gallery_images = explode(',', $request->remove_gallery_images);
                foreach ($remove_gallery_images as $key => $value) {
                    $gallary_images = ProjectDocuments::find($value);
                    unlink_image($gallary_images->name);
                    $gallary_images->delete();
                }
            }

            if ($request->remove_documents) {
                $remove_documents = explode(',', $request->remove_documents);
                foreach ($remove_documents as $key => $value) {
                    $gallary_images = ProjectDocuments::find($value);
                    unlink_image($gallary_images->name);
                    $gallary_images->delete();
                }
            }

            if ($request->hasfile('gallery_images')) {
                foreach ($request->file('gallery_images') as $file) {
                    $gallary_image = new ProjectDocuments();
                    $gallary_image->name = store_image($file, 'PROJECT_DOCUMENT_PATH');
                    $gallary_image->project_id = $project->id;
                    $gallary_image->type = 'image';
                    $gallary_image->save();
                }
            }

            if ($request->hasfile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $project_documents = new ProjectDocuments();
                    $project_documents->name = store_image($file, 'PROJECT_DOCUMENT_PATH');
                    $project_documents->project_id = $project->id;
                    $project_documents->type = 'doc';
                    $project_documents->save();
                }
            }

            if ($request->plans) {
                foreach ($request->plans as $key => $plan) {
                    if (isset($plan['id']) && $plan['id'] != '') {
                        $project_plans =  ProjectPlans::find($plan['id']);
                    } else {
                        $project_plans = new ProjectPlans();
                    }
                    if (isset($plan['document'])) {
                        $project_plans->document = store_image($plan['document'], 'PROJECT_DOCUMENT_PATH');
                    }
                    $project_plans->title = $plan['title'];
                    $project_plans->project_id = $project->id;
                    $project_plans->save();
                }
            }


            if ($request->remove_plans) {
                $remove_plans = explode(',', $request->remove_plans);
                foreach ($remove_plans as $key => $value) {
                    $project_plans = ProjectPlans::find($value);
                    unlink_image($project_plans->document);
                    $project_plans->delete();
                }
            }
            if (!(isset($request->id))) {
                $newPropertyLimitCount = 0;
                // Increment the property limit count
                $newPropertyLimitCount = $currentPackage->used_limit_for_property + 1;
                if($currentPackage->package->property_limit == null){
                    $addPropertyStatus = 1;
                }else if ($newPropertyLimitCount >= $currentPackage->package->property_limit) {
                    $addPropertyStatus = 0;
                } else {
                    $addPropertyStatus = 1;
                }
                // Update the Limit and status
                UserPurchasedPackage::where('id', $currentPackage->id)->update(['used_limit_for_property' => $newPropertyLimitCount, 'prop_status' => $addPropertyStatus]);
            }
            $result = Projects::with('customer')->with('gallary_images')->with('documents')->with('plans')->with('category:id,category,image')->where('id', $project->id)->get();

            DB::commit();
            $response['error'] = false;
            $response['message'] = isset($request->id) ? 'Project Updated Successfully' : 'Project Post Succssfully';
            $response['data'] = $result;
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }
    public function delete_project(Request $request)
    {
        $current_user = Auth::user()->id;

        $validator = Validator::make($request->all(), [

            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        $project = Projects::where('added_by', $current_user)->with('gallary_images')->with('documents')->with('plans')->find($request->id);

        if ($project) {
            foreach ($project->gallary_images as $row) {
                if ($project->title_image != '') {
                    unlink_image($row->title_image);
                }
                $gallary_image = ProjectDocuments::find($row->id);
                if ($gallary_image) {
                    if ($row->name != '') {

                        unlink_image($row->name);
                    }
                }
            }

            foreach ($project->documents as $row) {

                $project_documents = ProjectDocuments::find($row->id);
                if ($project_documents) {
                    if ($row->name != '') {

                        unlink_image($row->name);
                    }
                    $project_documents->delete();
                }
            }
            foreach ($project->plans as $row) {

                $project_plans = ProjectPlans::find($row->id);
                if ($project_plans) {
                    if ($row->name != '') {

                        unlink_image($row->document);
                    }
                    $project_plans->delete();
                }
            }
            $project->delete();
            $response['error'] = false;
            $response['message'] =  'Project Delete Successfully';
        } else {
            $response['error'] = true;
            $response['message'] = 'Data not found';
        }
        return response()->json($response);
    }

    public function getUserPersonalisedInterest(Request $request)
    {
        try {
            // Get Current User's ID From Token
            $loggedInUserId = Auth::user()->id;
            $data = array();

            // Get User Interest Data on the basis of current User
            $userInterest = UserInterest::where('user_id', $loggedInUserId)->first();
            if(collect($userInterest)->isNotEmpty()){
                // Get Data
                $categoriesIds = !empty($userInterest->category_ids) ? explode(',', $userInterest->category_ids) : '';
                $priceRange = $userInterest->property_type != null ? explode(',', $userInterest->price_range) : '';
                $propertyType = $userInterest->property_type == 0 || $userInterest->property_type == 1 ? explode(',', $userInterest->property_type) : '';
                $outdoorFacilitiesIds = !empty($userInterest->outdoor_facilitiy_ids) ? explode(',', $userInterest->outdoor_facilitiy_ids) : '';
                $city = !empty($userInterest->city) ?  $userInterest->city : '';
                // Custom Data Array
                $data = array(
                    'user_id'               => $loggedInUserId,
                    'category_ids'          => $categoriesIds,
                    'price_range'           => $priceRange,
                    'property_type'         => $propertyType,
                    'outdoor_facilitiy_ids' => $outdoorFacilitiesIds,
                    'city'                  => $city,
                );
            }
            $response = array(
                'error' => false,
                'data' => $data,
                'message' => 'Data fetched Successfully'
            );


            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    public function storeUserPersonalisedInterest(Request $request)
    {
        try {
            DB::beginTransaction();
            // Get Current User's ID From Token
            $loggedInUserId = Auth::user()->id;

            // Get User Interest
            $userInterest = UserInterest::where('user_id', $loggedInUserId)->first();

            // If data Exists then update or else insert new data
            if (collect($userInterest)->isNotEmpty()) {
                $response['error'] = false;
                $response['message'] = "Data Updated Successfully";
            } else {
                $userInterest = new UserInterest();
                $response['error'] = false;
                $response['message'] = "Data Store Successfully";
            }

            // Change the values
            $userInterest->user_id = $loggedInUserId;
            $userInterest->category_ids = (isset($request->category_ids) && !empty($request->category_ids)) ? $request->category_ids : "";
            $userInterest->outdoor_facilitiy_ids = (isset($request->outdoor_facilitiy_ids) && !empty($request->outdoor_facilitiy_ids)) ? $request->outdoor_facilitiy_ids : null;
            $userInterest->price_range = (isset($request->price_range) && !empty($request->price_range)) ? $request->price_range : "";
            $userInterest->city = (isset($request->city) && !empty($request->city)) ? $request->city : "";
            $userInterest->property_type = isset($request->property_type) && ($request->property_type == 0 || $request->property_type == 1) ? $request->property_type : "0,1";
            $userInterest->save();

            DB::commit();

            // Get Datas
            $categoriesIds = !empty($userInterest->category_ids) ? explode(',', $userInterest->category_ids) : '';
            $priceRange = !empty($userInterest->price_range) ? explode(',', $userInterest->price_range) : '';
            $propertyType = explode(',', $userInterest->property_type);
            $outdoorFacilitiesIds = !empty($userInterest->outdoor_facilitiy_ids) ? explode(',', $userInterest->outdoor_facilitiy_ids) : '';
            $city = !empty($userInterest->city) ?  $userInterest->city : '';

            // Custom Data Array
            $data = array(
                'user_id'               => $userInterest->user_id,
                'category_ids'          => $categoriesIds,
                'price_range'           => $priceRange,
                'property_type'         => $propertyType,
                'outdoor_facilitiy_ids' => $outdoorFacilitiesIds,
                'city'                  => $city,
            );
            $response['data'] = $data;

            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    public function deleteUserPersonalisedInterest(Request $request)
    {
        try {
            DB::beginTransaction();
            // Get Current User From Token
            $loggedInUserId = Auth::user()->id;

            // Get User Interest
            UserInterest::where('user_id', $loggedInUserId)->delete();
            DB::commit();
            $response = array(
                'error' => false,
                'message' => 'Data Deleted Successfully'
            );
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    public function removeAllPackages(Request $request)
    {
        try {
            DB::beginTransaction();

            $loggedInUserId = Auth::user()->id;

            // Delete All Packages
            UserPurchasedPackage::where('modal_id', $loggedInUserId)->delete();

            // Make subscription and is premium status 0 in customer table
            $customerData = Customer::find($loggedInUserId);
            $customerData->subscription = 0;
            $customerData->is_premium = 0;
            $customerData->save();

            DB::commit();
            $response = array(
                'error' => false,
                'message' => 'Data Deleted Successfully'
            );
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }


    public function getAddedProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_type' => 'nullable|in:0,1,2,3',
            'is_promoted' => 'nullable|in:1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Get Logged In User data
            $loggedInUserData = Auth::user();
            // Get Current Logged In User ID
            $loggedInUserID = $loggedInUserData->id;

            // when is_promoted is passed then show only property who has been featured (advertised)
            if($request->has('is_promoted') && $request->is_promoted == 1){
                // Create Advertisement Query which has Property Data
                $advertisementQuery = Advertisement::whereHas('property',function($query) use($loggedInUserID){
                    $query->where(['post_type' => 1, 'added_by' => $loggedInUserID]);
                })->with('property:id,category_id,slug_id,title,propery_type,city,state,country,price,title_image','property.category:id,category,image');

                // Get Total Advertisement Data
                $advertisementTotal = $advertisementQuery->clone()->count();

                // Get Advertisement Data with custom Data
                $advertisementData = $advertisementQuery->clone()->skip($offset)->take($limit)->orderBy('id','DESC')->get()->map(function($advertisement){
                    if(collect($advertisement->property)->isNotEmpty()){
                        $otherData = array();
                        $otherData['id'] = $advertisement->property->id;
                        $otherData['slug_id'] = $advertisement->property->slug_id;
                        $otherData['property_type'] = $advertisement->property->propery_type;
                        $otherData['title'] = $advertisement->property->title;
                        $otherData['city'] = $advertisement->property->city;
                        $otherData['state'] = $advertisement->property->state;
                        $otherData['country'] = $advertisement->property->country;
                        $otherData['price'] = $advertisement->property->price;
                        $otherData['title_image'] = $advertisement->property->title_image;
                        $otherData['advertisement_id'] = $advertisement->id;
                        $otherData['advertisement_status'] = $advertisement->status;
                        $otherData['advertisement_type'] = $advertisement->type;
                        $otherData['category'] = $advertisement->property->category;
                        unset($advertisement); // remove the original data
                        return $otherData; // return custom created data
                    }
                });
                $response = array(
                    'error' => false,
                    'data' => $advertisementData,
                    'total' => $advertisementTotal,
                    'message' => 'Data fetched Successfully'
                );
            }else{
                // Check the property's post is done by customer and added by logged in user
                $propertyQuery = Property::where(['post_type' => 1, 'added_by' => $loggedInUserID])
                    // When property type is passed in payload show data according property type that is sell or rent
                    ->when($request->filled('property_type'), function ($query) use ($request) {
                        return $query->where('propery_type', $request->property_type);
                    })
                    ->when($request->filled('id'), function ($query) use ($request) {
                        return $query->where('id', $request->id);
                    })
                    ->when($request->filled('slug_id'), function ($query) use ($request) {
                        return $query->where('slug_id', $request->slug_id);
                    })
                    // Pass the Property Data with Category and Advertisement Relation Data
                    ->with('category', 'advertisement');

                // Get Total Views by Sum of total click of each property
                $totalViews = $propertyQuery->sum('total_click');

                // Get total properties
                $totalProperties = $propertyQuery->count();

                // Get the property data with extra data and changes :- is_premium, post_created and promoted
                $propertyData = $propertyQuery->skip($offset)->take($limit)->orderBy('id','DESC')->get()->map(function ($property) use ($loggedInUserData) {
                    $property->is_premium = $property->is_premium == 1 ? true : false;
                    $property->property_type = $property->propery_type;
                    $property->post_created = $property->created_at->diffForHumans();
                    $property->promoted = $property->advertisement->isNotEmpty();
                    $property->parameters = $property->parameters;
                    $property->assign_facilities = $property->assign_facilities;
                    $property->is_feature_available = $property->is_feature_available;

                    // Add User's Details
                    $property->customer_name = $loggedInUserData->name;
                    $property->email = $loggedInUserData->email;
                    $property->mobile = $loggedInUserData->mobile;
                    $property->profile = $loggedInUserData->profile;
                    return $property;
                });

                $response = array(
                    'error' => false,
                    'data' => $propertyData,
                    'total' => $totalProperties,
                    'total_views' => $totalViews,
                    'message' => 'Data fetched Successfully'
                );
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }


    /**
     * Homepage Data API
     * Params :- None
     */
    public function homepageData()
    {
        try {
            //Faq Data
            $faqsData = Faq::select('id','question','answer')->where('status',1)->orderBy('id','DESC')->limit(5)->get();

            // Project Data
            $projectsData = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location','category_id','added_by')->where('status',1)->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile')->orderBy('id', 'desc')->limit(12)->get();

            // Categories Data
            $categoriesData = Category::select('id', 'category', 'image', 'slug_id')->whereHas('properties',function($query){
                $query->where('status',1);
            })->withCount(['properties' => function ($query) {
                    $query->where('status', 1);
                }
            ])->limit(12)->get();

            // Article Data
            $articlesData = Article::select('id', 'slug_id', 'category_id', 'title', 'description', 'image', 'created_at')->with('category:id,slug_id,image,category')->limit(5)->get();

            //Slider Data
            $slidersData = Slider::select('id','type', 'image', 'category_id', 'propertys_id','show_property_details','link')->with(['category' => function($query){
                $query->where('status',1)->select('id','slug_id','category');
            },'property' => function($query){
                $query->whereIn('propery_type',array(0,1))->where('status',1)->select('id','slug_id','title','title_image','price','propery_type as property_type');
            }])->orderBy('id', 'desc')->get()->map(function($slider) {
                $slider->slider_type = $slider->getRawOriginal('type');
                if ($slider->getRawOriginal('type') == 2) {
                    if (collect($slider->category)->isNotEmpty()) {
                        return $slider;
                    }
                }
                else if ($slider->getRawOriginal('type') == 3) {
                    if (collect($slider->property)->isNotEmpty() && collect($slider->property)->isNotEmpty()) {
                        $slider->property->parameters = $slider->property->parameters;
                        return $slider;
                    }
                } else {
                    return $slider;
                }
            })
            ->filter()->values(); // This will remove null values from the collection

            // Agent Data
            $agentsData = Customer::select('id','name','email', 'profile', 'slug_id')->withCount([
                'projects' => function ($query) {
                    $query->where('status', 1);
                },
                'property' => function ($query) {
                    $query->where('status', 1);
                }
            ])
            ->where('isActive', 1)
            ->get()
            ->map(function ($customer) {
                $customer->is_verified = $customer->is_user_verified;
                $customer->total_count = $customer->projects_count + $customer->property_count;
                $customer->is_admin = false;
                return $customer;
            })
            ->filter(function ($customer) {
                return $customer->projects_count > 0 || $customer->property_count > 0;
            })
            ->sortByDesc(function ($customer) {
                return [$customer->is_verified, $customer->total_count];
            })
            ->values() // This line resets the array keys
            ->take(12);

            // Get admin List
            $adminEmail = system_setting('company_email');
            $adminData = array();
            $adminPropertiesCount = Property::where(['added_by' => 0,'status' => 1])->count();
            $adminProjectsCount = Projects::where(['is_admin_listing' => 1,'status' => 1])->count();
            $totalCount = $adminPropertiesCount + $adminProjectsCount;

            $adminQuery = User::where('type',0)->select('id','slug_id')->first();
            if($adminQuery){
                $adminData = array(
                    'id' => $adminQuery->id,
                    'name' => 'Admin',
                    'slug_id' => $adminQuery->slug_id,
                    'email' => !empty($adminEmail) ? $adminEmail : "",
                    'property_count' => $adminPropertiesCount,
                    'projects_count' => $adminProjectsCount,
                    'total_count' => $totalCount,
                    'is_verified' => true,
                    'profile' => url('assets/images/faces/2.jpg'),
                    'is_admin' => true
                );
            }
            // Insert Admin Data at the Beginning
            $agentsData->prepend((object) $adminData);


            // Product Data Query and added extra data with its in every section
            $propertyDataQuery = Property::select('id', 'slug_id', 'category_id','city', 'state', 'country', 'price', 'propery_type', 'title', 'title_image', 'is_premium', 'address', 'rentduration')->with('category:id,slug_id,image,category')->where('status',1)->whereIn('propery_type',array(0,1));

            // Get Featured Section with the data having advertisement whose data is enabled and not the type of slider and extra data of properties
            $featuredSection = $propertyDataQuery->clone()->where(function($query){
                $query->whereHas('advertisement',function($subQuery){
                    $subQuery->where(['is_enable' => 1, 'status' => 0])->whereNot('type', 'Slider');
                });
            })->orderBy('id', 'DESC')->limit(12)->get()->map(function($propertyData){
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->parameters = $propertyData->parameters;
                $propertyData->is_premium = $propertyData->is_premium == 1 ? true : false;
                return $propertyData;
            });

            // Get Most Liked Properties Section with Favourite Count and extra data of properties
            $mostLikedProperties = $propertyDataQuery->clone()->withCount('favourite')->orderBy('favourite_count', 'DESC')->limit(12)->get()->map(function($propertyData){
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->parameters = $propertyData->parameters;
                $propertyData->is_premium = $propertyData->is_premium == 1 ? true : false;
                return $propertyData;
            });;

            // Get Most Viewed Properties Section and extra data of properties
            $mostViewedProperties = $propertyDataQuery->clone()->orderBy('total_click', 'DESC')->limit(12)->get()->map(function($propertyData){
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->parameters = $propertyData->parameters;
                $propertyData->is_premium = $propertyData->is_premium == 1 ? true : false;
                return $propertyData;
            });;

            // Add Data in Homepage Array Data
            $homepageData = array(
                'featured_section' => $featuredSection,
                'most_liked_properties' => $mostLikedProperties,
                'most_viewed_properties' => $mostViewedProperties,
                'project_section' => $projectsData,
                'slider_section' => $slidersData,
                'categories_section' => $categoriesData,
                'article_section' => $articlesData,
                'agents_list' => $agentsData,
                'faq_section' => $faqsData
            );

            // Get The data only on the Auth Data exists
            if (Auth::guard('sanctum')->check()) {
                $loggedInUserId = Auth::guard('sanctum')->user()->id;
                $cityOfUser = Auth::guard('sanctum')->user()->city;
                if (collect($cityOfUser)->isNotEmpty()) {

                    // Get Nearby Properties on the basis of city of user selected and with extra data of properties
                    $nearByProperties = $propertyDataQuery->clone()->where('city', $cityOfUser)->orderBy('id', 'DESC')->limit(12)->get()->map(function($propertyData){
                        $propertyData->promoted = $propertyData->is_promoted;
                        $propertyData->property_type = $propertyData->propery_type;
                        $propertyData->parameters = $propertyData->parameters;
                        $propertyData->is_premium = $propertyData->is_premium == 1 ? true : false;
                        return $propertyData;
                    });

                    // Add Nearby properties data in homepage data array
                    $homepageData['nearby_properties'] = $nearByProperties;
                }
                // Get User Recommendation Data
                $userInterestData = UserInterest::where('user_id', $loggedInUserId)->first();
                if(collect($userInterestData)->isNotEmpty()){
                    // User Recommendation Query
                    $userRecommendationQuery = $propertyDataQuery->clone();

                    // Check the User's Interested Category Ids
                    if (!empty($userInterestData->category_ids)) {
                        $categoryIds = explode(',', $userInterestData->category_ids);
                        $userRecommendationQuery = $userRecommendationQuery->whereIn('category_id', $categoryIds);
                    }

                    // Check User's Interested Price Range
                    if (!empty($userInterestData->price_range)) {
                        $minPrice = explode(',', $userInterestData->price_range)[0]; // Get User's Minimum Price
                        $maxPrice = explode(',', $userInterestData->price_range)[1]; // Get User's Maximum Price

                        if (isset($maxPrice) && isset($minPrice)) {
                            $minPrice = floatval($minPrice);
                            $maxPrice = floatval($maxPrice);
                            $userRecommendationQuery = $userRecommendationQuery->where(function ($query) use ($minPrice, $maxPrice) {
                                $query->whereRaw("CAST(price AS DECIMAL(10, 2)) >= ?", [$minPrice])
                                    ->whereRaw("CAST(price AS DECIMAL(10, 2)) <= ?", [$maxPrice]);
                            });
                        }
                    }

                    // Check User's Interested City
                    if (!empty($userInterestData->city)) {
                        $city = $userInterestData->city;
                        $userRecommendationQuery = $userRecommendationQuery->where('city', $city);
                    }

                    // Check User's Interested Property Types
                    if (!empty($userInterestData->property_type) || $userInterestData->property_type == 0) {
                        $propertyType = explode(',',  $userInterestData->property_type);
                        $userRecommendationQuery = $userRecommendationQuery->whereIn('propery_type', $propertyType);
                    }

                    // Check User's Interested Outdoor Facilities
                    if (!empty($userInterestData->outdoor_facilitiy_ids)) {
                        $outdoorFacilityIds = explode(',', $userInterestData->outdoor_facilitiy_ids);
                        $userRecommendationQuery = $userRecommendationQuery->whereHas('assignfacilities.outdoorfacilities', function ($q) use ($outdoorFacilityIds) {
                            $q->whereIn('id', $outdoorFacilityIds);
                        });
                    }

                    // Get the user recommended Properties according to its Personalised Data
                    $userRecommendationData = $userRecommendationQuery->orderBy('id', 'DESC')->limit(12)->get()->map(function($propertyData){
                        $propertyData->promoted = $propertyData->is_promoted;
                        $propertyData->property_type = $propertyData->propery_type;
                        $propertyData->parameters = $propertyData->parameters;
                        $propertyData->is_premium = $propertyData->is_premium == 1 ? true : false;
                        return $propertyData;
                    });

                    // Add user recommendation properties data in homepage data array
                }
                $homepageData['user_recommendation'] = $userRecommendationData ?? array();
            }


            $response = array(
                'error' => false,
                'data' => $homepageData,
                'message' => 'Data fetched Successfully'
            );
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    /**
     * Agent List API
     * Params :- limit and offset
     */
    public function getAgentList(Request $request)
    {
        try {
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;

            // if there is limit in request then have to do less by one so that to manage total data count with admin
            $limit = isset($request->limit) && !empty($request->limit) ? ($request->limit - 1) : 10;


            if(!empty($request->limit)){
                $agentsListQuery = Customer::select('id','name','email','profile','slug_id')->where(function($query) {
                    $query->where('isActive', 1);
                })
                ->where(function($query) {
                    $query->whereHas('projects', function ($query) {
                        $query->where('status', 1);
                    })->orWhereHas('property', function ($query) {
                        $query->where('status', 1);
                    });
                })
                ->withCount([
                    'projects' => function ($query) {
                        $query->where('status', 1);
                    },
                    'property' => function ($query) {
                        $query->where('status', 1);
                    }
                ]);

                $agentListCount = $agentsListQuery->clone()->count();

                $agentListData = $agentsListQuery->clone()
                    ->get()
                    ->map(function ($customer) {
                        $customer->is_verified = $customer->is_user_verified;
                        $customer->total_count = $customer->projects_count + $customer->property_count;
                        $customer->is_admin = false;
                        return $customer;
                    })
                    ->filter(function ($customer) {
                        return $customer->projects_count > 0 || $customer->property_count > 0;
                    })
                    ->sortByDesc(function ($customer) {
                        return [$customer->is_verified, $customer->total_count];
                    })
                    ->skip($offset)
                    ->take($limit)
                    ->values(); // This line resets the array keys




                // Get admin List

                $adminEmail = system_setting('company_email');
                $adminData = array();
                $adminPropertiesCount = Property::where(['added_by' => 0,'status' => 1])->count();
                $adminProjectsCount = Projects::where(['is_admin_listing' => 1,'status' => 1])->count();
                $totalCount = $adminPropertiesCount + $adminProjectsCount;

                $adminQuery = User::where('type',0)->select('id','slug_id')->first();
                if($adminQuery){
                    $adminData = array(
                        'id' => $adminQuery->id,
                        'name' => 'Admin',
                        'slug_id' => $adminQuery->slug_id,
                        'email' => !empty($adminEmail) ? $adminEmail : "",
                        'property_count' => $adminPropertiesCount,
                        'projects_count' => $adminProjectsCount,
                        'total_count' => $totalCount,
                        'is_verified' => true,
                        'profile' => url('assets/images/faces/2.jpg'),
                        'is_admin' => true
                    );
                }
                if($offset == 0){
                    $agentListData->prepend((object) $adminData);
                }
            }

            $response = array(
                'error' => false,
                'total' => $agentListCount ?? 0,
                'data' => $agentListData ?? array(),
                'message' => 'Data fetched Successfully'
            );

            return response()->json($response);

        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    /**
     * Agent Properties API
     * Params :- id or slug_id, limit, offset and is_project
     */
    public function getAgentProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug_id' => 'required_without_all:id,is_admin',
            'is_projects' => 'nullable|in:1',
            'is_admin' => 'nullable|in:1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            if (Auth::guard('sanctum')->check()) {
                $user = Auth::guard('sanctum')->user();
                $userIsPremium = $user->is_premium == 1 ? true : ($user->subscription == 1 ? true : false);
                if ($userIsPremium == false) {
                    ResponseService::errorResponse("You are not premium user");
                }
            }else{
                ResponseService::errorResponse("You are not premium user");
            }
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            $is_admin_listing = false;

            if($request->has('is_admin') && $request->is_admin == 1){
                $addedBy = 0;
                $is_admin_listing = true;
                $adminEmail = system_setting('company_email');
                $adminCompanyTel1 = system_setting('company_tel1');
                $customerData = array();
                $adminPropertiesCount = Property::where(['added_by' => 0,'status' => 1])->count();
                $adminProjectsCount = Projects::where(['is_admin_listing' => 1,'status' => 1])->count();
                $totalCount = $adminPropertiesCount + $adminProjectsCount;

                $adminQuery = User::where('type',0)->select('id','slug_id')->first();
                if($adminQuery){
                    $customerData = array(
                        'id' => $adminQuery->id,
                        'name' => 'Admin',
                        'slug_id' => $adminQuery->slug_id,
                        'email' => !empty($adminEmail) ? $adminEmail : "",
                        'mobile' => !empty($adminCompanyTel1) ? $adminCompanyTel1 : "",
                        'property_count' => $adminPropertiesCount,
                        'projects_count' => $adminProjectsCount,
                        'total_count' => $totalCount,
                        'is_verify' => true,
                        'profile' => url('assets/images/faces/2.jpg')
                    );
                }
            }else{
                // Customer Query
                $customerQuery = Customer::select('id','slug_id','name','profile','mobile','email','address','city','country','state','facebook_id','twiiter_id as twitter_id','youtube_id','instagram_id','about_me')->where(function($query){
                    $query->where('isActive', 1);
                })->withCount(['projects' => function($query){
                    $query->where('status',1);
                }, 'property' => function($query){
                    $query->where('status',1);
                }]);
                // Check if id exists or slug id on the basis of get agent id
                if($request->has('id') && !empty($request->id)){
                    $addedBy = $request->id;
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('id',$request->id)->first();
                    $addedBy = !empty($customerData) ? $customerData->id : "";
                }else if($request->has('slug_id')){
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('slug_id',$request->slug_id)->first();
                    $addedBy = !empty($customerData) ? $customerData->id : "";
                }
                // Add Is User Verified Status in Customer Data
                !empty($customerData) ? $customerData->is_verify = $customerData->is_user_verified : "";
            }

            // if there is agent id then only get properties of it
            if(!empty($addedBy) || $addedBy == 0){

                if(($request->has('is_projects') && !empty($request->is_projects) && $request->is_projects == 1)){
                    $projectQuery = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location','category_id');
                    if($is_admin_listing == true){
                        $projectQuery = $projectQuery->clone()->where(['status' => 1, 'is_admin_listing' => 1]);
                    }else{
                        $projectQuery = $projectQuery->clone()->where(['status' => 1, 'added_by' => $addedBy]);
                    }
                    $totalProjects = $projectQuery->clone()->count();
                    $projectData = $projectQuery->clone()->with('gallary_images','category:id,slug_id,image,category')->skip($offset)->take($limit)->get();
                    $totalData = $totalProjects;
                }else{
                    $propertiesQuery = Property::select('id', 'slug_id', 'city', 'state', 'category_id','country', 'price', 'propery_type', 'title', 'title_image', 'is_premium', 'address', 'added_by')->where(['status' => 1, 'added_by' => $addedBy])->with('category:id,slug_id,image,category');
                    $totalProperties = $propertiesQuery-> clone()->count();
                    $propertiesData = $propertiesQuery-> clone()->orderBy('id','DESC')->skip($offset)->take($limit)->get()->map(function($property){
                        $property->property_type = $property->propery_type;
                        $property->parameters = $property->parameters;
                        $property->promoted = $property->is_promoted;
                        unset($property->propery_type);
                        return $property;
                    });
                    $totalData = $totalProperties;
                }
            }

            $response = array(
                'error' => false,
                'total' => $totalData ?? 0,
                'data' => array(
                    'customer_data' => $customerData ?? array(),
                    'properties_data' => $propertiesData ?? array(),
                    'projects_data' => $projectData ?? array(),
                ),
                'message' => 'Data fetched Successfully'
            );
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }


    public function getWebSettings(Request $request){
        try{
            // Types for web requirement only
            $types = array('company_name', 'currency_symbol', 'default_language', 'number_with_suffix', 'web_maintenance_mode', 'company_tel', 'company_tel2', 'system_version', 'web_logo', 'web_footer_logo', 'web_placeholder_logo', 'company_email', 'latitude', 'longitude', 'company_address', 'system_color', 'svg_clr', 'iframe_link', 'facebook_id', 'instagram_id', 'twitter_id', 'youtube_id', 'playstore_id', 'sell_background', 'appstore_id', 'category_background', 'web_maintenance_mod','seo_settings','company_tel1','place_api_key','stripe_publishable_key','paystack_public_key','sell_web_color','sell_web_background_color','rent_web_color','rent_web_background_color','about_us','terms_conditions','privacy_policy','number_with_otp_login','social_login','distance_option','otp_service_provider','text_property_submission','auto_approve');

            // Query the Types to Settings Table to get its data
            $result =  Setting::select('type', 'data')->whereIn('type',$types)->get();

            // Check the result data is not empty
            if(collect($result)->isNotEmpty()){
                $settingsData = array();

                // Loop on the result data
                foreach ($result as $row) {
                    // Change data according to conditions
                    if ($row->type == 'company_logo') {
                        // Add logo image with its url
                        $settingsData[$row->type] = url('/assets/images/logo/logo.png');
                    } else if ($row->type == 'seo_settings') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } else if ($row->type == 'web_logo' || $row->type == 'web_placeholder_logo' || $row->type == 'web_footer_logo') {
                        // Add Full URL to the specified type
                        $settingsData[$row->type] = url('/assets/images/logo/') . '/' . $row->data;
                    } else if ($row->type == 'place_api_key') {
                        // Add Full URL to the specified type
                        $publicKey = file_get_contents(base_path('public_key.pem')); // Load the public key
                        $encryptedData = '';
                        if (openssl_public_encrypt($row->data, $encryptedData, $publicKey)) {
                            $settingsData[$row->type] = base64_encode($encryptedData);
                        }else{
                            $settingsData[$row->type] = "";
                        }
                    }else{
                        // add the data as it is in array
                        $settingsData[$row->type] = $row->data;
                    }
                }

                $user_data = User::find(1);
                $settingsData['admin_name'] = $user_data->name;
                $settingsData['admin_image'] = url('/assets/images/faces/2.jpg');
                $settingsData['demo_mode'] = env('DEMO_MODE');
                $settingsData['img_placeholder'] = url('/assets/images/placeholder.svg');

                // if Token is passed of current user.
                if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                    $loggedInUserId = Auth::guard('sanctum')->user()->id;
                    update_subscription($loggedInUserId);

                    $checkVerifiedStatus = VerifyCustomer::where('user_id', $loggedInUserId)->first();
                    if(!empty($checkVerifiedStatus)){
                        $settingsData['verification_status'] = $checkVerifiedStatus->status;
                    }else{
                        $settingsData['verification_status'] = 'initial';
                    }

                    $customerDataQuery = Customer::select('id', 'subscription', 'is_premium', 'isActive');
                    $customerData = $customerDataQuery->clone()->find($loggedInUserId);

                    // Check Active of current User
                    if (collect($customerData)->isNotEmpty()){
                        $settingsData['is_active'] = $customerData->isActive == 1 ? true : false;
                    } else {
                        $settingsData['is_active'] = false;
                    }

                    // Check the subscription
                    if (collect($customerData)->isNotEmpty()) {
                        $settingsData['is_premium'] = $customerData->is_premium == 1 ? true : ($customerData->subscription == 1 ? true : false);
                        $settingsData['subscription'] = $customerData->subscription == 1 ? true : false;
                    } else {
                        $settingsData['is_premium'] = false;
                        $settingsData['subscription'] = false;
                    }

                }


                // Check the min_price and max_price
                $settingsData['min_price'] = DB::table('propertys')->selectRaw('MIN(price) as min_price')->value('min_price');
                $settingsData['max_price'] = DB::table('propertys')->selectRaw('MAX(price) as max_price')->value('max_price');

                // Get Languages Data
                $language = Language::select('id', 'code', 'name')->get();
                $settingsData['languages'] = $language;

                $response['error'] = false;
                $response['message'] = "Data Fetch Successfully";
                $response['data'] = $settingsData;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }


    public function getAppSettings(Request $request){
        try{
          $types = array('company_name', 'currency_symbol', 'ios_version', 'default_language', 'force_update', 'android_version', 'number_with_suffix', 'maintenance_mode', 'company_tel1', 'company_tel2', 'company_email', 'company_address', 'place_api_key', 'svg_clr', 'playstore_id', 'sell_background', 'appstore_id', 'show_admob_ads', 'android_banner_ad_id', 'ios_banner_ad_id', 'android_interstitial_ad_id', 'ios_interstitial_ad_id', 'android_native_ad_id', 'ios_native_ad_id', 'demo_mode', 'min_price', 'max_price','privacy_policy', 'terms_conditions','about_us','number_with_otp_login','social_login','distance_option','otp_service_provider','app_home_screen','placeholder_logo','light_tertiary','light_secondary','light_primary','dark_tertiary','dark_secondary','dark_primary','text_property_submission','auto_approve');

            // Query the Types to Settings Table to get its data
            $result =  Setting::select('type', 'data')->whereIn('type',$types)->get();

            // Check the result data is not empty
            if(collect($result)->isNotEmpty()){
                $settingsData = array();

                // Loop on the result data
                foreach ($result as $row) {
                    if ($row->type == "place_api_key") {
                        $publicKey = file_get_contents(base_path('public_key.pem')); // Load the public key
                        $encryptedData = '';
                        if (openssl_public_encrypt($row->data, $encryptedData, $publicKey)) {
                            $settingsData[$row->type] = base64_encode($encryptedData);
                        }
                    } else if ($row->type == 'default_language'){
                        // Add Code in Data
                        $settingsData[$row->type] = $row->data;

                        // Add Default language's name
                        $languageData = Language::where('code',$row->data)->first();
                        if(collect($languageData)->isNotEmpty()){
                            $settingsData['default_language_name'] = $languageData->name;
                            $settingsData['default_language_rtl'] = $languageData->rtl == 1 ? 1 : 0;
                        }else{
                            $settingsData['default_language_name'] = "";
                            $settingsData['default_language_rtl'] = 0;
                        }
                    } else if ($row->type == 'app_home_screen' || $row->type == "placeholder_logo") {
                        $settingsData[$row->type] = url('/assets/images/logo/') . '/' . $row->data;
                    } else{
                        // add the data as it is in array
                        $settingsData[$row->type] = $row->data;
                    }
                }

                $settingsData['demo_mode'] = env('DEMO_MODE');
                // if Token is passed of current user.
                if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                    $loggedInUserId = Auth::guard('sanctum')->user()->id;
                    update_subscription($loggedInUserId);


                    $checkVerifiedStatus = VerifyCustomer::where('user_id', $loggedInUserId)->first();
                    if(!empty($checkVerifiedStatus)){
                        $settingsData['verification_status'] = $checkVerifiedStatus->status;
                    }else{
                        $settingsData['verification_status'] = 'initial';
                    }

                    $customerDataQuery = Customer::select('id', 'subscription', 'is_premium', 'isActive');
                    $customerData = $customerDataQuery->clone()->find($loggedInUserId);

                    // Check Active of current User
                    if (collect($customerData)->isNotEmpty()){
                        $settingsData['is_active'] = $customerData->isActive == 1 ? true : false;
                    } else {
                        $settingsData['is_active'] = false;
                    }

                    // Check the subscription
                    if (collect($customerData)->isNotEmpty()) {
                        $settingsData['is_premium'] = $customerData->is_premium == 1 ? true : ($customerData->subscription == 1 ? true : false);
                        $settingsData['subscription'] = $customerData->subscription == 1 ? true : false;
                    } else {
                        $settingsData['is_premium'] = false;
                        $settingsData['subscription'] = false;
                    }

                }

                // Check the min_price and max_price
                $settingsData['min_price'] = DB::table('propertys')->selectRaw('MIN(price) as min_price')->value('min_price');
                $settingsData['max_price'] = DB::table('propertys')->selectRaw('MAX(price) as max_price')->value('max_price');

                // Get Languages Data
                $language = Language::select('id', 'code', 'name')->get();
                $settingsData['languages'] = $language;

                $response['error'] = false;
                $response['message'] = "Data Fetch Successfully";
                $response['data'] = $settingsData;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    public function getLanguagesData(){
        try {
            $languageData = Language::select('id', 'code', 'name')->get();
            if(collect($languageData)->isNotEmpty()){
                $response['error'] = false;
                $response['message'] = "Data Fetch Successfully";
                $response['data'] = $languageData;
            } else {
                $response['error'] = false;
                $response['message'] = "No data found!";
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    /**
     * Faq API
     * Params :- Limit and offset
     */
    public function getFaqData(Request $request)
    {
        try {
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            $faqsQuery = Faq::where('status',1);
            $totalData = $faqsQuery->clone()->count();
            $faqsData = $faqsQuery->clone()->select('id','question','answer')->orderBy('id','DESC')->skip($offset)->take($limit)->get();
            $response = array(
                'error' => false,
                'total' => $totalData ?? 0,
                'data' => $faqsData,
                'message' => 'Data fetched Successfully'
            );
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    /**
     * beforeLogout API
     */
    public function beforeLogout(Request $request){
        try {
            if($request->has('fcm_id')){
                Usertokens::where(['fcm_id' => $request->fcm_id, 'customer_id' => $request->user()->id])->delete();
            }
            $response = array(
                'error' => false,
                'message' => 'Data Processed Successfully'
            );
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => 'Something Went Wrong'
            );
            return response()->json($response, 500);
        }
    }

    public function getOtp(Request $request){
        $validator = Validator::make($request->all(), [
            'number' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $requestNumber = $request->number; // Get data from Request
            $trimmedNumber = ltrim($requestNumber,'+'); // remove + from starting if exists
            $toNumber = "+".(string)$trimmedNumber; // Add + starting of number

            // Initialize empty array
            $dbData = array();

            // make an array of types for database query and get data from settings table
            $twilioCredentialsTypes = array('twilio_account_sid','twilio_auth_token','twilio_my_phone_number');
            $twilioCredentialsDB = Setting::select('type','data')->whereIn('type',$twilioCredentialsTypes)->get();

            // Loop the db result in such a way that type becomes key of array and data becomes its value in new array
            foreach ($twilioCredentialsDB as $value) {
                $dbData[$value->type] = $value->data;
            }

            // Get Twilio credentials
            $sid = $dbData['twilio_account_sid'];
            $token = $dbData['twilio_auth_token'];
            $fromNumber = $dbData['twilio_my_phone_number'];

            // Instance Created of Twilio client with Twilio SID and token
            $client = new TwilioRestClient($sid, $token);

            // Validate phone number using Twilio Lookup API
            try {
                $client->lookups->v1->phoneNumbers($toNumber)->fetch();
            } catch (RestException $e) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid phone number.',
                ]);
            }

            // Check if OTP already exists and is still valid
            $existingOtp = NumberOtp::where('number', $toNumber)->first();

            if ($existingOtp && now()->isBefore($existingOtp->expire_at)) {
                // OTP is still valid
                $otp = $existingOtp->otp;
            } else {
                // Generate a new OTP
                $otp = rand(123456, 999999);
                $expireAt = now()->addMinutes(3); // Set OTP expiry time

                // Update or create OTP entry in the database
                NumberOtp::updateOrCreate(
                    ['number' => $toNumber],
                    ['otp' => $otp, 'expire_at' => $expireAt]
                );
            }

            // Use the Client to make requests to the Twilio REST API
            $client->messages->create(
                // The number you'd like to send the message to
                $toNumber,
                [
                    // A Twilio phone number you purchased at https://console.twilio.com
                    'from' => $fromNumber,
                    // The body of the text message you'd like to send
                    'body' => "Here is the OTP: ".$otp.". It expires in 3 minutes."
                ]
            );

            /** Note :- While using Trial accounts cannot send messages to unverified numbers, or purchase a Twilio number to send messages to unverified numbers.*/

            // Return success response
            return response()->json([
                'error' => false,
                'message' => 'OTP sent successfully!',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request) {
        $validator = Validator::make($request->all(), [
            'number' => 'required',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $requestNumber = $request->number; // Get data from Request
            $trimmedNumber = ltrim($requestNumber,'+'); // remove + from starting if exists
            $toNumber = "+".(string)$trimmedNumber; // Add + starting of number
            $userOtp = $request->otp;

            // Fetch the OTP record from the database
            $otpRecord = NumberOtp::where('number', $toNumber)->first();

            if (!$otpRecord) {
                return response()->json([
                    'error' => true,
                    'message' => 'OTP not found.',
                ]);
            }

            // Check if the OTP is valid and not expired
            if ($otpRecord->otp == $userOtp && now()->isBefore($otpRecord->expire_at)) {
                // Check the number and login type exists in user table
                $user = Customer::where('mobile', $trimmedNumber)->where('logintype',1)->first();
                if(collect($user)->isNotEmpty()){
                    $authId = $user->auth_id;
                }else{
                    // Generate a unique identifier
                    $authId = Str::uuid()->toString();
                }
                return response()->json([
                    'error' => false,
                    'message' => 'OTP verified successfully!',
                    'auth_id' => $authId
                ]);
            } else if ($otpRecord->otp != $userOtp){
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid OTP.',
                ]);
            } else if (now()->isAfter($otpRecord->expire_at)){
                return response()->json([
                    'error' => true,
                    'message' => 'OTP expired.',
                ]);
            } else{
                return response()->json([
                    'error' => true,
                    'message' => 'Something Went Wrong',
                ]);
            }

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getPropertyList(Request $request){
        try {
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Create a property query
            $propertyQuery = Property::whereIn('propery_type',[0,1])->where('status',1);

            // If Property Type Passed
            $property_type = $request->property_type;  //0 : Sell 1:Rent
            if (isset($property_type) && (!empty($property_type) || $property_type == 0)) {
                $propertyQuery = $propertyQuery->clone()->where('propery_type', $property_type);
            }

            // If Category Id is Passed
            if ($request->has('category_id') && !empty($request->category_id)) {
                $propertyQuery = $propertyQuery->clone()->where('category_id', $request->category_id);
            }

            // If parameter id passed 
            if ($request->has('parameter_id') && !empty($request->parameter_id)) {
                $parametersId = explode(",",$request->parameter_id);
                $propertyQuery = $propertyQuery->clone()->whereHas('assignParameter',function($query) use($parametersId){
                    $query->whereIn('parameter_id',$parametersId)->whereNotNull('value');
                });
            }

            // If Category Slug is Passed
            if ($request->has('category_slug_id') && !empty($request->category_slug_id)) {
                $categorySlugId = $request->category_slug_id;
                $propertyQuery = $propertyQuery->clone()->whereHas('category',function($query)use($categorySlugId){
                    $query->where('slug_id',$categorySlugId);
                });
            }

            // If Country is passed
            if ($request->has('country') && !empty($request->country)) {
                $propertyQuery = $propertyQuery->clone()->where('country', $request->country);
            }

            // If State is passed
            if ($request->has('state') && !empty($request->state)) {
                $propertyQuery = $propertyQuery->clone()->where('state', $request->state);
            }

            // If City is passed
            if ($request->has('city') && !empty($request->city)) {
                $propertyQuery = $propertyQuery->clone()->where('city', $request->city);
            }

            // If Max Price And Min Price passed
            if ($request->has('min_price') && !empty($request->min_price)) {
                $minPrice = $request->min_price;
                $propertyQuery = $propertyQuery->clone()->where('price','>=',$minPrice);
            }

            if (isset($request->max_price) && !empty($request->max_price)) {
                $maxPrice = $request->max_price;
                $propertyQuery = $propertyQuery->clone()->where('price','<=',$maxPrice);
            }

            // If Posted Since 0 or 1 is passed
            if ($request->has('posted_since')) {
                $posted_since = $request->posted_since;

                // 0 - Last Week (from today back to the same day last week)
                if ($posted_since == 0) {
                    $oneWeekAgo = Carbon::now()->subWeek()->startOfDay();
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$oneWeekAgo, $today]);
                }
                // 1 - Yesterday
                if ($posted_since == 1) {
                    $yesterdayDate = Carbon::yesterday();
                    $propertyQuery =  $propertyQuery->clone()->whereDate('created_at', $yesterdayDate);
                }
            }

            // Search the property
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $propertyQuery = $propertyQuery->clone()->where(function ($query) use ($search) {
                    $query->where('title', 'LIKE', "%$search%")
                        ->orWhere('address', 'LIKE', "%$search%")
                        ->orWhereHas('category', function ($query1) use ($search) {
                            $query1->where('category', 'LIKE', "%$search%");
                        });
                });
            }

            // If Top Rated passed then show the property data with Order by on Total Click Descending
            if ($request->has('most_viewed') && $request->most_viewed == 1) {
                $propertyQuery = $propertyQuery->clone()->orderBy('total_click', 'DESC');
            }

            // IF Most Liked Passed then show the data according to
            if ($request->has('most_liked') && $request->most_liked == 1) {
                $propertyQuery = $propertyQuery->clone()->orderBy('favourite_count', 'DESC');
            }

            // IF Promoted Passed then show the data according to
            if ($request->has('promoted') && $request->promoted == 1) {
                $propertyQuery = $propertyQuery->clone()->whereHas('advertisement',function($query){
                    $query->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // Get total properties
            $totalProperties = $propertyQuery->clone()->count();

            // Get properties list data
            $propertiesData = $propertyQuery->clone()->with('category:id,category,image,slug_id')->select('id','slug_id','propery_type','title_image','category_id','title','price','city','state','country','rentduration','added_by')->withCount('favourite')->orderBy('id', 'DESC')->skip($offset)->take($limit)->get()->map(function($property){
                $property->promoted = $property->is_promoted;
                $property->is_premium = $property->is_premium == 1 ? true : false;
                $property->property_type = $property->propery_type;
                $property->assign_facilities = $property->assign_facilities;
                $property->parameters = $property->parameters;
                unset($property->propery_type);
                return $property;
            });

            // Sort properties based on the promoted attribute
            $propertiesData = $propertiesData->sortByDesc(function ($property) {
                return $property->promoted;
            })->values()->filter();

            $response = array(
                'error' => false,
                'total' => $totalProperties,
                'data' => $propertiesData,
                'message' => 'Data fetched Successfully'
            );
            return response()->json($response);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // public function getPropertyDetail(Request $request){
    //     $validator = Validator::make($request->all(), [
    //         'id'        => 'required_without:slug_id',
    //         'slug_id'   => 'required_without:id',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'error' => true,
    //             'message' => $validator->errors()->first(),
    //         ]);
    //     }
    //     try{
    //         $propertyQuery = Property::where('status', 1)->whereIn('propery_type',array(0,1))->with(['customer','user', 'category:id,category,image,slug_id','favourite','interested_users' => function ($query){
    //             $query->has('customer')->with('customer:id,name');
    //         }]);

    //         if($request->has('id') && !empty($request->id)){
    //             $propertyQuery = $propertyQuery->clone()->where(['id' => $request->id,'status' => 1]);
    //         } else {
    //             $propertyQuery = $propertyQuery->clone()->where(['slug_id' => $request->slug_id,'status' => 1]);
    //         }
    //         $propertyData = $propertyQuery->first();

    //         if ($propertyData) {
    //             $propertyData->is_premium = $propertyData->is_premium == 1 ? true : false;
    //             $propertyData->property_type = $propertyData->propery_type;
    //             $propertyData->assign_facilities = $propertyData->assign_facilities;
    //             $propertyData->parameters = $propertyData->parameters;
    //             unset($propertyData->propery_type);
    //         }

    //         $response = array(
    //             'error' => false,
    //             'data' => $propertyData,
    //             'message' => 'Data fetched Successfully'
    //         );
    //         return response()->json($response);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'error' => true,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function getAgentVerificationFormFields(Request $request){
        $data = VerifyCustomerForm::where('status','active')->with('form_fields_values:id,verify_customer_form_id,value')->select('id','name','field_type')->get();

        if (collect($data)->isNotEmpty()) {
            ResponseService::successResponse("Data Fetched Successfully",$data,array(),200);
        } else {
            ResponseService::successResponse("No data found!");
        }
    }

    public function getAgentVerificationFormValues(Request $request){
        $data = VerifyCustomer::where('user_id', Auth::user()->id)->with(['user' => function($query){
            $query->select('id', 'name', 'profile')->withCount(['property', 'projects']);
        }])->with(['verify_customer_values' => function($query){
            $query->with('verify_form:id,name,field_type','verify_form.form_fields_values:id,verify_customer_form_id,value')->select('id','verify_customer_id','verify_customer_form_id','value');
        }])->first();

        if (collect($data)->isNotEmpty()) {
            ResponseService::successResponse("Data Fetched Successfully",$data,array(),200);
        } else {
            ResponseService::successResponse("No data found!");
        }
    }

    public function applyAgentVerification(Request $request) {
        $validator = Validator::make($request->all(), [
            'form_fields'           => 'required|array',
            'form_fields.*.id'      => 'required|exists:verify_customer_forms,id',
            'form_fields.*.value'   => 'required',
        ], [
            'form_fields.*.id'      => ':positionth Form Field id is not valid',
            'form_fields.*.value'   => ':positionth Form Field Value is not valid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            // If Payload is empty then show Payload is empty
            if (empty($request->form_fields)) {
                ResponseService::errorResponse('Payload is empty');
            }

            // Update the status of Customer (User) to pending
            $verifyCustomer = VerifyCustomer::updateOrCreate(['user_id' => Auth::user()->id], ['status' => 'pending']);
            $addCustomerValues = array();

            // Loop on request data of form_fields
            foreach ($request->form_fields as $key => $form_fields) {
                if (isset($form_fields['value']) && !empty($form_fields['value'])) {
                    // Check the Value is File upload or not
                    if ($request->hasFile('form_fields.' . $key . '.value')) {
                        $file = $request->file('form_fields.' . $key . '.value'); // Get Request File
                        $allowedImageExtensions = ['jpg', 'jpeg', 'png']; // Allowed Images Extensions
                        $allowedDocumentExtensions = ['doc', 'docx', 'pdf', 'txt']; // Allowed Documentation Extensions
                        $extension = $file->getClientOriginalExtension(); // Get Extension
                        // Check the extension and verify with allowed images or documents extensions
                        if (in_array($extension, $allowedImageExtensions) || in_array($extension, $allowedDocumentExtensions)) {
                            // Get Old form value
                            $oldFormValue = VerifyCustomerValue::where(['verify_customer_id' => $verifyCustomer->id, 'verify_customer_form_id' => $form_fields['id']])->with('verify_form:id,field_type')->first();
                            if (!empty($oldFormValue)) {
                                unlink_image($oldFormValue->value);
                            }
                            // Upload the new file
                            $destinationPath = public_path('images') . config('global.AGENT_VERIFICATION_DOC_PATH');
                            if (!is_dir($destinationPath)) {
                                mkdir($destinationPath, 0777, true);
                            }
                            $imageName = microtime(true) . "." . $extension;
                            $file->move($destinationPath, $imageName);
                            $value = $imageName;
                        } else {
                            ResponseService::errorResponse('Invalid file type. Allowed types are: jpg, jpeg, png, doc, docx, pdf, txt');
                        }
                    } else {
                        // Check the value other than File Upload
                        $formFieldQueryData = VerifyCustomerForm::where('id', $form_fields['id'])->first();
                        if ($formFieldQueryData->field_type == 'radio' || $formFieldQueryData->field_type == 'dropdown') {
                            // IF Field Type is Radio or Dropdown, then check its value with database stored options
                            $checkValueExists = VerifyCustomerFormValue::where(['verify_customer_form_id' => $form_fields['id'], 'value' => $form_fields['value']])->first();
                            if (collect($checkValueExists)->isEmpty()) {
                                ResponseService::errorResponse('No Form Value Found');
                            }
                            $value = $form_fields['value'];
                        } else if ($formFieldQueryData->field_type == 'checkbox') {
                            // IF Field Type is Checkbox
                            $submittedValue = explode(',', $form_fields['value']); // Explode the Comma Separated Values
                            // Loop on the values and check its value with database stored options
                            foreach ($submittedValue as $key => $value) {
                                $checkValueExists = VerifyCustomerFormValue::where(['verify_customer_form_id' => $form_fields['id'], 'value' => $value])->first();
                                if (collect($checkValueExists)->isEmpty()) {
                                    ResponseService::errorResponse('No Form Value Found');
                                }
                            }
                            // Convert the value into json encode
                            $value = json_encode($form_fields['value']);
                        } else {
                            // Get Value as it is for other field types
                            $value = $form_fields['value'];
                        }
                    }
                    // Create an array to upsert data
                    $addCustomerValues[] = array(
                        'verify_customer_id'        => $verifyCustomer->id,
                        'verify_customer_form_id'   => $form_fields['id'],
                        'value'                     => $value,
                        'created_at'                => now(),
                        'updated_at'                => now()
                    );
                }
            }

            // If array is not empty then update or create in bulk
            if (!empty($addCustomerValues)) {
                VerifyCustomerValue::upsert($addCustomerValues, ['verify_customer_id', 'verify_customer_form_id'], ['value']);
            }


            // Send Notification to Admin
            $fcm_id = array();
            $user_data = User::select('fcm_id', 'name')->get();
            foreach ($user_data as $user) {
                array_push($fcm_id, $user->fcm_id);
            }

            if (!empty($fcm_id)) {
                $registrationIDs = $fcm_id;
                $fcmMsg = array(
                    'title' => 'Agent Verification Form Submitted',
                    'message' => 'Agent Verification Form Submitted',
                    'type' => 'agent_verification',
                    'body' => 'Agent Verification Form Submitted',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default'
                );
                send_push_notification($registrationIDs, $fcmMsg);
            }

            // Commit the changes and return response
            DB::commit();
            ResponseService::successResponse("Data Submitted Successfully");
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, $e->getMessage(), 'Something Went Wrong');
        }
    }

    public function calculateMortgageCalculator(Request $request) {
        $validator = Validator::make($request->all(), [
            'down_payment' => 'nullable|lt:loan_amount',
            'show_all_details' => 'nullable|in:1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $loanAmount = $request->loan_amount; // Loan amount
            $downPayment = $request->down_payment; // Down payment
            $interestRate = $request->interest_rate; // Annual interest rate in percentage
            $loanTermYear = $request->loan_term_years; // Loan term in years
            $showAllDetails = 0;
            if($request->show_all_details == 1){
                if (Auth::guard('sanctum')->check()) {
                    $user = Auth::guard('sanctum')->user();
                    $userIsPremium = $user->is_premium == 1 ? true : ($user->subscription == 1 ? true : false);
                    if ($userIsPremium == true) {
                        $showAllDetails = 1;
                    }
                }
            }

            $schedule = $this->mortgageCalculation($loanAmount, $downPayment, $interestRate, $loanTermYear, $showAllDetails);
            ResponseService::successResponse('Data Fetched Successfully',$schedule,[],200);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, $e->getMessage(), 'Something Went Wrong');
        }
    }
    public function getProjectDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug_id' => 'required_without:id',
            'get_similar' => 'nullable|in:1'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Auth::user();
            $getSimilarProjects = array();
            $project = Projects::with('customer:id,name,profile,email,mobile,address,slug_id')
                ->with('gallary_images')
                ->with('documents')
                ->with('plans')
                ->with('category:id,category,image');

            if ($request->get_similar == 1) {
                if($request->has('id') && !empty($request->id)){
                    $getSimilarProjects = $project->clone()->where('id', '!=', $request->id)->where('status',1)->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location','category_id','added_by')->where('status',1)->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile')->get();
                }else if($request->has('slug_id') && !empty($request->slug_id)){
                    $getSimilarProjects = $project->clone()->where('slug_id', '!=', $request->slug_id)->where('status',1)->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location','category_id','added_by')->where('status',1)->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile')->get();
                }
            }

            if ($request->id) {
                $project = $project->clone()->where(function($query) use($user){
                    $query->where('status', 1)->orWhere('added_by',$user->id);
                })->where('id',$request->id);
            }

            if ($request->slug_id) {
                $project = $project->clone()->where(function($query) use($user){
                    $query->where('status', 1)->orWhere('added_by',$user->id);
                })->where('slug_id',$request->slug_id);
            }

            $total = $project->clone()->count();
            $data = $project->first();

            ResponseService::successResponse(
                "Data Fetch Successfully",
                $data,
                array(
                    'total' => $total,
                    'similar_projects' => $getSimilarProjects
                )
            );
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getAddedProjects(Request $request){
        try{
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            $user = Auth::user();

            // Query
            $projectsQuery = Projects::where('added_by',$user->id)
                        ->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile')
                        ->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location','status','category_id','added_by','created_at');

            // Get Total
            $total = $projectsQuery->clone()->count();

            // Get Data
            $data = $projectsQuery->clone()->take($limit)->skip($offset)->get();
            ResponseService::successResponse("Data Fetched Successfully",$data,array('total' => $total));
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getProjects(Request $request){
        try{
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Query
            $projectsQuery = Projects::where('status',1)
                        ->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile,slug_id')
                        ->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'status', 'location','category_id','added_by','is_admin_listing');

            $postedSince = $request->posted_since;
            if (isset($postedSince)) {
                // 0: last_week   1: yesterday
                if ($postedSince == 0) {
                    $projectsQuery = $projectsQuery->clone()->whereBetween(
                        'created_at',
                        [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                    );
                }
                if ($postedSince == 1) {
                    $projectsQuery =  $projectsQuery->clone()->whereDate('created_at', Carbon::yesterday());
                }
            }

            // Get Total
            $total = $projectsQuery->clone()->count();

            // Get Admin Company Details
            $adminCompanyTel1 = system_setting('company_tel1');
            $adminEmail = system_setting('company_email');
            $adminUser = User::where('id',1)->select('id','slug_id')->first();

            // Get Data
            $data = $projectsQuery->clone()->take($limit)->skip($offset)->get()->map(function($project) use($adminCompanyTel1,$adminEmail,$adminUser){
                // Check if listing is by admin then add admin details in customer
                if ($project->is_admin_listing == true) {
                    unset($project->customer);
                    $project->customer = array(
                        'name' => "Admin",
                        'email' => $adminEmail,
                        'mobile' => $adminCompanyTel1,
                        'slug_id' => $adminUser->slug_id
                    );
                }
                return $project;
            });
            ResponseService::successResponse("Data Fetched Successfully",$data,array('total' => $total));
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function flutterwave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $currentUser = Auth::user();
            $currentUserId = $currentUser->id;
            $currentUserName = $currentUser->name ?? null;
            $currentUserEmail = $currentUser->email ?? null;
            $currentUserNumber = $currentUser->mobile ?? null;
            $packageId = $request->package_id;
            $amount = Package::where('id',$packageId)->pluck('price'); // Get price data from the database
            $currencySymbol = system_setting('flutterwave_currency');

            $reference = Flutterwave::generateReference(); //This generates a payment reference

            // Enter the details of the payment
            $data = [
                'payment_options' => 'card,banktransfer',
                'amount' => $amount,
                'email' => $currentUserEmail,
                'tx_ref' => $reference,
                'currency' => $currencySymbol,
                'redirect_url' => URL::to('api/flutterwave-payment-status'),
                'customer' => [
                    'email' => $currentUserEmail,
                    "phone_number" => $currentUserNumber,
                    "name" => $currentUserName
                ],
                "meta" => [
                    "package_id" => $packageId,
                    "user_id" => $currentUserId
                ]
            ];

            $payment = Flutterwave::initializePayment($data);

            if ($payment['status'] !== 'success') {
                ResponseService::errorResponse("Payment Failed");
            }else{
                ResponseService::successResponse("Data Fetched Successfully",$payment);
            }
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function flutterwavePaymentStatus(Request $request)
    {
        $flutterwavePaymentInfo = $request->all();
        // Get Web URL
        $webURL = system_setting('web_url') ?? null;
        if (isset($flutterwavePaymentInfo) && !empty($flutterwavePaymentInfo) && isset($flutterwavePaymentInfo['status']) && !empty($flutterwavePaymentInfo['status'])){
            if($flutterwavePaymentInfo['status'] == "successful") {
                $webWithStatusURL = $webURL.'/payment/success';
                $response['error'] = false;
                $response['message'] = "Your Purchase Package Activate Within 10 Minutes ";
                $response['data'] = $flutterwavePaymentInfo;
            } else {
                $webWithStatusURL = $webURL.'/payment/fail';
                $response['error'] = true;
                $response['message'] = "Payment Cancelled / Declined ";
                $response['data'] = !empty($flutterwavePaymentInfo) ? $flutterwavePaymentInfo : "";
            }
        }else{
            $webWithStatusURL = $webURL.'/payment/fail';
            $response['error'] = true;
            $response['message'] = "Payment Cancelled / Declined ";
        }

        if($webURL){
            echo "<html>
            <body>
            Redirecting...!
            </body>
            <script>
                window.location.replace('".$webWithStatusURL."');
            </script>
            </html>";
        }else{
            echo "<html>
            <body>
            Redirecting...!
            </body>
            <script>
                console.log('No web url added');
            </script>
            </html>";
        }
        // return (response()->json($response));
    }

    public function blockChatUser(Request $request) {
        $userId = Auth::user()->id;

        $validator = Validator::make($request->all(),[
            'to_user_id' => [
                'required_without:to_admin',
                'exists:customers,id',
                function ($attribute, $value, $fail) use ($userId) {
                    if ($value == $userId) {
                        $fail('You cannot block yourself.');
                    }
                }
            ],
            'to_admin' => 'required_without:to_user_id|in:1',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $blockUserData = array(
                'by_user_id' => $userId,
                'reason' => $request->reason ?? null
            );
            if($request->has('to_user_id') && !empty($request->to_user_id)){
                $ifExtryExists = BlockedChatUser::where(['by_user_id' => $userId,'user_id' => $request->to_user_id])->count();
                if($ifExtryExists){
                    ResponseService::errorResponse("User Already Blocked");
                }
                $blockUserData['user_id'] = $request->to_user_id;
            } else if($request->has('to_admin') && $request->to_admin == 1){
                $ifExtryExists = BlockedChatUser::where(['by_user_id' => $userId,'admin' => 1])->count();
                if($ifExtryExists){
                    ResponseService::errorResponse("Admin Already Blocked");
                }
                $blockUserData['admin'] = 1;
            }else{
                ResponseService::errorResponse("Something Went Wrong in API");
            }

            BlockedChatUser::create($blockUserData);
            ResponseService::successResponse("User Blocked Successfully");
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function unBlockChatUser(Request $request){
        $userId = Auth::user()->id;
        $validator = Validator::make($request->all(),[
            'to_user_id' => [
                'required_without:to_admin',
                'exists:customers,id',
                function ($attribute, $value, $fail) use ($userId) {
                    if ($value == $userId) {
                        $fail('You cannot unblock yourself.');
                    }
                }
            ],
            'to_admin' => 'required_without:to_user_id|in:1',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            if($request->has('to_user_id') && !empty($request->to_user_id)){
                $blockedUserQuery = BlockedChatUser::where(['by_user_id' => $userId,'user_id' => $request->to_user_id]);
                $ifExtryExists = $blockedUserQuery->clone()->count();
                if(!$ifExtryExists){
                    ResponseService::errorResponse("No Blocked User Found");
                }
                $blockedUserQuery->delete();
            } else if($request->has('to_admin') && $request->to_admin == 1){
                $blockedUserQuery = BlockedChatUser::where(['by_user_id' => $userId,'user_id' => $request->to_user_id]);
                $ifExtryExists = $blockedUserQuery->count();
                if(!$ifExtryExists){
                    ResponseService::errorResponse("No Blocked User Found");
                }
                $blockedUserQuery->delete();
            }else{
                ResponseService::errorResponse("Something Went Wrong in API");
            }
            ResponseService::successResponse("User Unblocked Successfully");
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getFacilitiesForFilter(Request $request){
        try {
            $parameters = parameter::get();
            $data = [
                'error' => false,
                'message' => 'Data Fetched Successfully',
                'data' => $parameters,
                'code' => 200
            ];
            return response()->json($data);
            ResponseService::successResponse("Data Fetched Successfully",$parameters);
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    /*****************************************************************************************************************************************
     * Functions
     *****************************************************************************************************************************************
    */

    /**
     * Get Current Package
     * Params :- user id, package type 1 - property / project and 2 - advertisement
     */
    function getCurrentPackage($userId, $packageType)
    {
        $currentDate = Carbon::now()->format("Y-m-d");
        if ($packageType == 1) {
            $currentPackage = UserPurchasedPackage::where(['modal_id' => $userId, 'prop_status' => 1])->whereDate('end_date', '>=', $currentDate)->whereHas('package', function ($q) {
                $q->where('property_limit', '>', 0)->orWhere('property_limit', null);
            })->with('package:id,property_limit')->first();
        } else {
            $currentPackage = UserPurchasedPackage::where(['modal_id' => $userId, 'adv_status' => 1])->whereDate('end_date', '>=', $currentDate)->whereHas('package', function ($q) {
                $q->where('advertisement_limit', '>', 0)->orWhere('advertisement_limit', null);
            })->with('package:id,advertisement_limit,duration')->first();
        }
        return $currentPackage;
    }


    function getUnsplashData($cityData){
        $apiKey = env('UNSPLASH_API_KEY');
        $query = $cityData->city;
        $apiUrl = "https://api.unsplash.com/search/photos/?query=$query";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID ' . $apiKey,
        ]);
        $unsplashResponse = curl_exec($ch);

        curl_close($ch);

        $unsplashData = json_decode($unsplashResponse, true);
        // Check if the response contains data
        if (isset($unsplashData['results'])) {
            $results = $unsplashData['results'];

            // Initialize the image URL
            $imageUrl = '';

            // Loop through the results and get the first image URL
            foreach ($results as $result) {
                $imageUrl = $result['urls']['regular'];
                break; // Stop after getting the first image URL
            }
            if ($imageUrl != "") {
                return array('City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => $imageUrl);
            }
        }
        return array('City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => "");
    }

    public function getAutoApproveStatus($loggedInUserId){
        // Check auto approve is on and is user is verified or not
        $autoApproveSettingStatus = system_setting('auto_approve');
        $autoApproveStatus = false;
        if($autoApproveSettingStatus == 1){
            $userData = Customer::where('id', $loggedInUserId)->first();
            $autoApproveStatus = $userData->is_user_verified ? true : false;
        }

        return $autoApproveStatus;
    }
    function roundArrayValues($array,$pointsValue) {
        return array_map(function($item) use($pointsValue){
            if (is_array($item)) {
                return $this->roundArrayValues($item,$pointsValue); // Recursive call
            }
            return is_numeric($item) ? round($item, $pointsValue) : $item; // Base Case
        }, $array);
    }


    function mortgageCalculation($loanAmount, $downPayment, $interestRate, $loanTermYear, $showAllDetails) {
        if ($downPayment > 0) {
            $downPayment = (int)$downPayment;
            $loanAmount = $loanAmount - $downPayment;
        }

        // Convert annual interest rate to monthly interest rate
        $monthlyInterestRate = ($interestRate / 100) / 12;

        // Convert loan term in years to months
        $loanTermMonths = $loanTermYear * 12;

        // Calculate monthly payment
        $monthlyPayment = $loanAmount * ($monthlyInterestRate * pow(1 + $monthlyInterestRate, $loanTermMonths)) / (pow(1 + $monthlyInterestRate, $loanTermMonths) - 1);

        // Initialize an array to store the mortgage schedule
        $schedule = [];
        $schedule['main_total'] = array();

        // Initialize main totals
        $mainTotal = [
            'principal_amount' => $loanAmount,
            'down_payment' => $downPayment,
            'payable_interest' => 0,
            'monthly_emi' => $monthlyPayment,
            'total_amount' => 0,
        ];

        // Get current year and month
        $currentYear = date('Y');
        $currentMonth = date('n');

        // Initialize the remaining balance
        $remainingBalance = $loanAmount;

        // Loop through each month
        for ($i = 0; $i < $loanTermMonths; $i++) {
            $month = ($currentMonth + $i) % 12; // Ensure month wraps around by using modulo 12, so it does not exceed 12
            $year = $currentYear + floor(($currentMonth + $i - 1) / 12); // Calculate the year by incrementing when months exceed December

            // Correct month format
            $month = $month === 0 ? 12 : $month;

            // Calculate interest and principal
            $interest = $remainingBalance * $monthlyInterestRate;
            $principal = $monthlyPayment - $interest;
            $remainingBalance -= $principal;

            // Ensure remaining balance is not negative
            if ($remainingBalance < 0) {
                $remainingBalance = 0;
            }

            // Update yearly totals
            if ($showAllDetails && !isset($schedule['yearly_totals'][$year])) {
                $schedule['yearly_totals'][$year] = [
                    'year' => $year,
                    'monthly_emi' => 0,
                    'principal_amount' => 0,
                    'interest_paid' => 0,
                    'remaining_balance' => $remainingBalance,
                    'monthly_totals' => []
                ];
            }

            if ($showAllDetails) {
                $schedule['yearly_totals'][$year]['interest_paid'] += $interest;
                $schedule['yearly_totals'][$year]['principal_amount'] += $principal;

                // Store monthly totals
                $schedule['yearly_totals'][$year]['monthly_totals'][] = [
                    'month' => strtolower(date('F', mktime(0, 0, 0, $month, 1, $year))),
                    'principal_amount' => $principal,
                    'payable_interest' => $interest,
                    'remaining_balance' => $remainingBalance
                ];
            }

            // Update main total
            $mainTotal['payable_interest'] += $interest;
        }

        // Re-index the year totals array index, year used as index
        if ($showAllDetails) {
            $schedule['yearly_totals'] = array_values($schedule['yearly_totals']);
        }else{
            $schedule['yearly_totals'] = array();
        }

        // Calculate the total amount by addition of principle amount and total payable_interest
        $mainTotal['total_amount'] = $mainTotal['principal_amount'] + $mainTotal['payable_interest'];

        // Add Main Total in Schedule Variable
        $schedule['main_total'] = $mainTotal;

        // Round off values for display
        $schedule['main_total'] = $this->roundArrayValues($schedule['main_total'],2);
        $schedule['yearly_totals'] = $this->roundArrayValues($schedule['yearly_totals'],0);

        // Return the mortgage schedule
        return $schedule;
    }

}