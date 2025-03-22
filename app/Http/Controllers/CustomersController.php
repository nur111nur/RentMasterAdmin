<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Usertokens;
use Illuminate\Http\Request;
use App\Models\InterestedUser;
use App\Services\ResponseService;
use Illuminate\Support\Facades\Auth;

class CustomersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'customer')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        return view('customer.index');
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (!has_permissions('update', 'customer')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Customer::where('id', $request->id)->update(['isActive' => $request->status]);
            $fcm_ids = array();

            $customer_id = Customer::where(['id' => $request->id,'notification' => 1])->count();
            if ($customer_id) {
                $user_token = Usertokens::where('customer_id', $request->id)->pluck('fcm_id')->toArray();
                $fcm_ids[] = $user_token;
            }


            $msg = "";
            if (!empty($fcm_ids)) {
                $msg = $request->status == 1 ? 'Activate now by Adminstrator ' : 'Deactive now by Adminstrator ';
                $type = $request->status == 1 ? 'account_activated' : 'account_deactivated';
                $full_msg = $request->status == 1 ? 'Your Account' . $msg : 'Please Contact to Administrator';
                $registrationIDs = $fcm_ids[0];

                $fcmMsg = array(
                    'title' =>  'Your Account' . $msg,
                    'message' => $full_msg,
                    'type' => $type,
                    'body' => 'Your Account'  . $msg,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',

                );
                send_push_notification($registrationIDs, $fcmMsg);
            }
            ResponseService::successResponse($request->status ? "Customer Activated Successfully" : "Customer Deactivated Successfully");
        }
    }




    public function customerList(Request $request)
    {
        if (!has_permissions('read', 'customer')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');


        if (isset($_GET['property_id'])) {
            $interested_users =  InterestedUser::select('customer_id')->where('property_id', $_GET['property_id'])->pluck('customer_id');

            $sql = Customer::whereIn('id', $interested_users)->orderBy($sort, $order);
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'];
                $sql->where(function($query) use($search){
                    $query->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('mobile', 'LIKE', "%$search%");
                });
            }
        } else {

            $sql = Customer::orderBy($sort, $order);
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'];
                $sql->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('mobile', 'LIKE', "%$search%");
            }
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
        foreach ($res as $row) {
            $tempRow = $row->toArray();

            // Mask Details in Demo Mode
            $tempRow['mobile'] = (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( $row->mobile ) : '****************************' ) : ( $row->mobile ));
            $tempRow['email'] = (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( $row->email ) : '****************************' ) : ( $row->email ));
            $tempRow['address'] = (env('DEMO_MODE') ? ( env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ( $row->address ) : '****************************' ) : ( $row->address ));

            $tempRow['edit_status_url'] = 'customerstatus';
            $tempRow['total_properties'] =  '<a href="' . url('property') . '?customer=' . $row->id . '">' . $row->total_properties . '</a>';
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
