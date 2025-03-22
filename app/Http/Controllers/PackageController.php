<?php

namespace App\Http\Controllers;

use DateTime;
use DateInterval;
use App\Models\Slider;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Services\ResponseService;
use App\Models\UserPurchasedPackage;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'package')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();

        return view('packages.index', compact('currency_symbol'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!has_permissions('create', 'package')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'ios_product_id' => 'nullable|unique:packages,ios_product_id',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $package = new Package();

            $package->name = $request->name;
            $package->duration = isset($request->duration) ? $request->duration : 0;
            $package->price = $request->price;
            if (isset($request->typep)) {
                $package->property_limit =  $request->property_limit == NULL ? NULL : $request->property_limit;
            } else {
                $package->property_limit = 0;
            }

            if (isset($request->typel)) {
                $package->advertisement_limit =  $request->advertisement_limit == NULL ? NULL : $request->advertisement_limit;
            } else {
                $package->advertisement_limit = 0;
            }
            $package->ios_product_id = $request->ios_product_id;
            $package->type = $request->package_type;
            $package->save();

            ResponseService::successRedirectResponse("Data Created Successfully");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!has_permissions('read', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');

        $sql = Package::orderBy($sort, $order);

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('duration', 'LIKE', "%$search%");
        }

        $total = $sql->count();
        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;
        $operate = '';
        $tempRow['type'] = '';

        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['property_limit'] = $row->property_limit == '' ?  "unlimited" : ($row->property_limit == 0 ? "Not Available" : $row->property_limit);
            $tempRow['advertisement_limit'] = $row->advertisement_limit == '' ? "unlimited" : ($row->advertisement_limit == 0 ? "Not Available" : $row->advertisement_limit);
            $operate = BootstrapTableService::editButton('', true, null, null, $row->id);
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     *
     */
    public function update($id, Request $request)
    {

        if (!has_permissions('update', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'edit_id'           => 'required',
                'ios_product_id'    => 'nullable|unique:packages,ios_product_id,'.$request->edit_id.'id',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $id = $request->edit_id;
            $name =  $request->edit_name;
            $duration =  $request->edit_duration;
            $package = Package::find($id);
            $package->name = $name;
            $package->duration = $duration;
            $package->status = $request->status;
            $package->ios_product_id = $request->edit_ios_product_id;
            $package->update();
            ResponseService::successResponse('Data Updated Successfully');
        }
    }
    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Package::where('id', $request->id)->update(['status' => $request->status]);
            $response['error'] = false;
            return response()->json($response);
        }
    }

    public function userPackageIndex(){
        if (!has_permissions('read', 'user_package')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        return view('packages.users_packages');
    }
    public function get_user_package_list(Request $request)
    {
        if (!has_permissions('read', 'user_package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');

        $sql = UserPurchasedPackage::with('package')->with('customer')->orderBy($sort, $order);
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwherehas('customer', function ($q1) use ($search) {
                $q1->where('name', 'LIKE', "%$search%");
            })->orwherehas('package', function ($q1) use ($search) {
                $q1->where('name', 'LIKE', "%$search%")->orwhere('duration', 'LIKE', "%$search%");
            });
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;

        $tempRow['type'] = '';

        foreach ($res as $row) {
            if($row->customer){
                $tempRow['id'] = $row->id;
                $tempRow['start_date'] = date('d-m-Y', strtotime($row->start_date));
                $tempRow['end_date'] = date('d-m-Y', strtotime($row->end_date));
                $tempRow['subscription'] = $row->customer->subscription == 1 ? 'On' : 'Off';
                $tempRow['name'] = $row->package->name;
                $tempRow['customer_name'] = !empty($row->customer) ? $row->customer->name : '';
                $rows[] = $tempRow;
                $count++;
            }
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
