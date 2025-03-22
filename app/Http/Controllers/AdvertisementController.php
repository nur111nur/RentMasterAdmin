<?php

namespace App\Http\Controllers;

use App\Models\Usertokens;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\Notifications;
use App\Services\ResponseService;

class AdvertisementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'advertisement')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        return view('advertisement.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!has_permissions('read', 'advertisement')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        $sql = Advertisement::with('customer','property:id,title_image')->orderBy($sort, $order);

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orWhereHas('customer', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
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
        $status = '';


        $operate = '';
        foreach ($res as $row) {
            $operate = '<a  id="' . $row->id . '"  class="btn icon btn-primary btn-sm rounded-pill edit_btn"  data-status="' . $row->status . '" data-oldimage="' . $row->image . '" data-types="' . $row->id . '" data-bs-toggle="modal" data-bs-target="#editModal"  onclick="setValue(this.id);" title="Edit"><i class="fa fa-edit edit_icon"></i></a>';
            $tempRow = $row->toArray();


            $tempRow['edit_status_url'] = route('featured_properties.update-advertisement-status');

            if ($row->status == 0) {
                $status = trans('Approved');
            }
            if ($row->status == 1) {
                $status = trans('Pending');
            }
            if ($row->status == 2) {
                $status = trans('Rejected');
            }
            if ($row->status == 3) {
                $status = trans('Expired');
            }
            $tempRow['status'] = $status;

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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (!has_permissions('update', 'advertisement')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        } else {
            Advertisement::find($request->id)->update(['status' => $request->edit_adv_status]);

            $adv = Advertisement::with('customer')->find($request->id);
            $status = $adv->status;
            if ($adv->customer->notification == 1) {
                if ($status == '0') {
                    $status_text  = 'Approved';
                } else if ($status == '1') {
                    $status_text  = 'Pending';
                } else if ($status == '2') {
                    $status_text  = 'Rejected';
                }
                $user_token = Usertokens::where('customer_id', $adv->customer->id)->pluck('fcm_id')->toArray();
                //START :: Send Notification To Customer
                $fcm_ids = array();
                $fcm_ids = $user_token;
                if (!empty($fcm_ids)) {
                    $registrationIDs = $fcm_ids;
                    $fcmMsg = array(
                        'title' => 'Advertisement Request',
                        'message' => 'Advertisement Request Is ' . $status_text,
                        'type' => 'advertisement_request',
                        'body' => 'Advertisement Request Is ' . $status_text,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => (string)$adv->id,
                    );
                    send_push_notification($registrationIDs, $fcmMsg);
                }
                //END ::  Send Notification To Customer

                Notifications::create([
                    'title' => 'Property Inquiry Updated',
                    'message' => 'Your Advertisement Request is ' . $status_text,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $adv->customer->id,
                    'propertys_id' => $adv->id
                ]);
            }

            ResponseService::successRedirectResponse('Advertisement status update Successfully');
        }
    }

    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'advertisement')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Advertisement::where('id', $request->id)->update(['is_enable' => $request->status]);
            ResponseService::successResponse($request->status ? "Advertisement Activated Successfully" : "Advertisement Deactivated Successfully");
        }
    }
}
