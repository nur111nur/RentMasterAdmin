<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Setting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Projects;
use App\Models\parameter;
use App\Models\Usertokens;
use App\Models\ProjectPlans;
use Illuminate\Http\Request;
use App\Models\Notifications;
use App\Models\ProjectDocuments;
use App\Models\OutdoorFacilities;
use App\Services\ResponseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'project')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        $category = Category::all();
        return view('project.index',compact('category'));
    }

    public function create(){
        if (!has_permissions('create', 'project')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        $category = Category::where('status', '1')->get();
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
        return view('project.create', compact('category', 'currency_symbol'));
    }

    public function store(Request $request){
        if (!has_permissions('create', 'project')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'title'         => 'required',
            'description'   => 'required',
            'image'         => 'required|file|max:3000|mimes:jpeg,png,jpg',
            'category_id'   => 'required',
            'city'          => 'required',
            'state'         => 'required',
            'country'       => 'required',
            'video_link'    => ['nullable', 'url', function ($attribute, $value, $fail) {
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
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;

            $project = new Projects();
            $project->title = $request->title;
            $project->slug_id = generateUniqueSlug($slugData, 4);
            $project->category_id = $request->category_id;
            $project->description = $request->description;
            $project->location = $request->address;
            $project->meta_title = $request->meta_title ?? null;
            $project->meta_description = $request->meta_description ?? null;
            $project->meta_keywords = $request->meta_keywords ?? null;
            $project->added_by = null;
            $project->is_admin_listing = true;
            $project->country = $request->country;
            $project->state = $request->state;
            $project->city = $request->city;
            $project->latitude = $request->latitude;
            $project->longitude = $request->longitude;
            $project->video_link = $request->video_link;
            $project->type = $request->project_type;
            if ($request->hasFile('image')) {
                $project->image = store_image($request->file('image'), 'PROJECT_TITLE_IMG_PATH');
            }
            if ($request->hasFile('meta_image')) {
                $project->meta_image = store_image($request->file('meta_image'), 'PROJECT_SEO_IMG_PATH');
            }

            $project->save();

            if ($request->hasfile('gallery_images')) {
                $gallaryImages = array();
                foreach ($request->file('gallery_images') as $file) {
                    $gallaryImages[] = array(
                        'project_id' => $project->id,
                        'name' => store_image($file, 'PROJECT_DOCUMENT_PATH'),
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($gallaryImages)){
                    ProjectDocuments::insert($gallaryImages);
                }
            }

            if ($request->hasfile('documents')) {
                $projectDocuments = array();
                foreach ($request->file('documents') as $file) {
                    $projectDocuments[] = array(
                        'project_id' => $project->id,
                        'name' => store_image($file, 'PROJECT_DOCUMENT_PATH'),
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($projectDocuments)){
                    ProjectDocuments::insert($projectDocuments);
                }
            }

            if ($request->floor_data) {
                $projectPlan = array();
                foreach ($request->floor_data as $key => $planArray) {
                    $plan = (object)$planArray;
                    $projectPlan[] = array(
                        'title' => $plan->title,
                        'project_id' => $project->id,
                        'document' => !empty($plan->floor_image) ? store_image($plan->floor_image, 'PROJECT_DOCUMENT_PATH') : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }

                if(!empty($projectPlan)){
                    ProjectPlans::insert($projectPlan);
                }
            }

            DB::commit();
            ResponseService::successResponse("Data Created Successfully");
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse("Something Went Wrong");
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!has_permissions('read', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');


        $sql = Projects::with('category')->with('gallary_images')->with('documents')->with('plans')->with('customer')->orderBy($sort, $order);

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('location', 'LIKE', "%$search%")->orwhereHas('category', function ($query) use ($search) {
                $query->where('category', 'LIKE', "%$search%");
            })->orWhereHas('customer', function ($query) use ($search) {
                $query->where('name', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%");
            });
        }

        if ($_GET['status'] != '' && isset($_GET['status'])) {
            $status = $_GET['status'];
            $sql = $sql->where('status', $status);
        }


        if ($_GET['category'] != '' && isset($_GET['category'])) {
            $category_id = $_GET['category'];
            $sql = $sql->where('category_id', $category_id);
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
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();

        foreach ($res as $row) {
            $documentsButtonCustomClasses = ["btn","icon","btn-primary","btn-sm","rounded-pill","documents-btn"];
            $documentsButtonCustomAttributes = ["id" => $row->id, "title" => trans('Documents'), "data-toggle" => "modal", "data-bs-target" => "#documentsModal", "data-bs-toggle" => "modal"];
            $documentAction = BootstrapTableService::button('bi bi-eye-fill', '',$documentsButtonCustomClasses,$documentsButtonCustomAttributes);

            $operate = null;
            if($row->is_admin_listing == true){
                if (has_permissions('update', 'project')) {
                    $operate = BootstrapTableService::editButton(route('project.edit', $row->id), false);
                }
                if (has_permissions('delete', 'project')) {
                    $operate .= BootstrapTableService::deleteAjaxButton(route('project.destroy', $row->id));
                }
            }

            $tempRow = $row->toArray();
            $tempRow['owner_name'] = $row->is_admin_listing == true ? "Admin" : $row->customer->name;
            $tempRow['edit_status_url'] = 'updateProjectStatus';

            $tempRow['price'] = $currency_symbol . '' . $row->price . '/' . (!empty($row->rentduration) ? $row->rentduration : 'Month');
            $tempRow['document_action'] = $documentAction;
            $tempRow['action'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }


        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id){
        if (!has_permissions('update', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        // $project = Projects::where('id',$id)->with('gallary_images','documents','plans')->first();
        $project = Projects::where('id',$id)->with('plans')->first();
        $project['gallary_images'] = $project->gallary_images_directly->get();
        $project['documents'] = $project->documents_directly->get();
        $category = Category::where('status', '1')->get();
        return view('project.edit',compact('project','category'));
    }

    public function update($id,Request $request){
        if (!has_permissions('create', 'project')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'title'         => 'required',
            'description'   => 'required',
            'image'         => 'nullable|file|max:3000|mimes:jpeg,png,jpg',
            'category_id'   => 'required',
            'city'          => 'required',
            'state'         => 'required',
            'country'       => 'required',
            'video_link'    => ['nullable', 'url', function ($attribute, $value, $fail) {
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
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;

            $project = Projects::find($id);
            $project->title = $request->title;
            $project->slug_id = generateUniqueSlug($slugData, 4,null,$id);
            $project->category_id = $request->category_id;
            $project->description = $request->description;
            $project->location = $request->address;
            $project->meta_title = $request->meta_title ?? null;
            $project->meta_description = $request->meta_description ?? null;
            $project->meta_keywords = $request->meta_keywords ?? null;
            $project->added_by = null;
            $project->is_admin_listing = true;
            $project->country = $request->country;
            $project->state = $request->state;
            $project->city = $request->city;
            $project->latitude = $request->latitude;
            $project->longitude = $request->longitude;
            $project->video_link = $request->video_link;
            $project->type = $request->project_type;
            if ($request->hasFile('image')) {
                $project->image = store_image($request->file('image'), 'PROJECT_TITLE_IMG_PATH');
            }
            if ($request->hasFile('meta_image')) {
                $project->meta_image = store_image($request->file('meta_image'), 'PROJECT_SEO_IMG_PATH');
            }

            $project->save();

            if ($request->hasfile('gallery_images')) {
                $gallaryImages = array();
                foreach ($request->file('gallery_images') as $file) {
                    $gallaryImages[] = array(
                        'project_id' => $project->id,
                        'name' => store_image($file, 'PROJECT_DOCUMENT_PATH'),
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($gallaryImages)){
                    ProjectDocuments::insert($gallaryImages);
                }
            }

            if ($request->hasfile('documents')) {
                $projectDocuments = array();
                foreach ($request->file('documents') as $file) {
                    $projectDocuments[] = array(
                        'project_id' => $project->id,
                        'name' => store_image($file, 'PROJECT_DOCUMENT_PATH'),
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($projectDocuments)){
                    ProjectDocuments::insert($projectDocuments);
                }
            }

            if ($request->floor_data) {
                $projectPlan = array();
                foreach ($request->floor_data as $key => $planArray) {
                    $plan = (object)$planArray;
                    $projectPlan[$key] = array(
                        'id' => $plan->id ?? null,
                        'title' => $plan->title,
                        'project_id' => $project->id,
                    );
                    if(!empty($plan->floor_image)){
                        $projectPlan[$key]['document'] = store_image($plan->floor_image, 'PROJECT_DOCUMENT_PATH');
                    }
                }

                if(!empty($projectPlan)){
                    if(!empty($plan->floor_image)){
                        ProjectPlans::upsert($projectPlan,['id'],['title','project_id','document']);
                    }else{
                        ProjectPlans::upsert($projectPlan,['id'],['title','project_id']);
                    }
                }
            }

            DB::commit();
            ResponseService::successResponse("Data Updated Successfully");
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse("Something Went Wrong");
        }
    }

    public function destroy($id){
        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            DB::beginTransaction();
            $property = Projects::find($id);

            DB::commit();
            if ($property->delete()) {
                ResponseService::successResponse("Data Deleted Successfully");
            }else{
                ResponseService::errorResponse("Something Went Wrong");
            }
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse("Something Went Wrong");
        }
    }

    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Projects::where('id', $request->id)->update(['status' => $request->status]);
            $project = Projects::with('customer')->find($request->id);

            if ($project->customer) {

                $fcm_ids = array();
                if ($project->customer->isActive == 1 && $project->customer->notification == 1) {
                    $user_token = Usertokens::where('customer_id', $project->customer->id)->pluck('fcm_id')->toArray();
                }

                $fcm_ids[] = $user_token;

                $msg = "";
                if (!empty($fcm_ids)) {
                    $msg = $project->status == 1 ? 'Activate now by Administrator ' : 'Deactivated now by Administrator ';
                    $registrationIDs = $fcm_ids[0];

                    $fcmMsg = array(
                        'title' =>  $project->name . 'Project Updated',
                        'message' => 'Your Project Post ' . $msg,
                        'type' => 'project_inquiry',
                        'body' => 'Your Project Post ' . $msg,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => (string)$project->id,
                    );
                    send_push_notification($registrationIDs, $fcmMsg);
                }
                //END ::  Send Notification To Customer

                Notifications::create([
                    'title' => $project->name . 'Project Updated',
                    'message' => 'Your Project Post ' . $msg,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $project->customer->id,
                    'projects_id' => $project->id
                ]);
            }
            $response['error'] = false;
            ResponseService::successResponse($request->status ? "Project Activated Successfully" : "Project Deactivated Successfully");
        }
    }
    public function generateAndCheckSlug(Request $request){
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        // Generate the slug or throw exception
        try {
            $title = $request->title;
            $id = $request->has('id') && !empty($request->id) ? $request->id : null;
            if($id){
                $slug = generateUniqueSlug($title,4,null,$id);
            }else{
                $slug = generateUniqueSlug($title,4);
            }
            ResponseService::successResponse("",$slug);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, "Project Slug Generation Error", "Something Went Wrong");
        }
    }

    public function removeGalleryImage(Request $request)
    {

        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;

            $getImage = ProjectDocuments::where('id', $id)->first();


            $image = $getImage->getRawOriginal('name');
            if (ProjectDocuments::where('id', $id)->delete()) {
                if (file_exists(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" . $image)) {
                    unlink(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" . $image);
                }
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }
            return response()->json($response);
        }
    }
    public function removeDocument(Request $request)
    {

        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;

            $getDocument = ProjectDocuments::where('id', $id)->first();


            $file = $getDocument->getRawOriginal('name');
            if (ProjectDocuments::where('id', $id)->delete()) {
                if (file_exists(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" . $file)) {
                    unlink(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" . $file);
                }
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }
            return response()->json($response);
        }
    }

    public function removeFloorPlan($id){
        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            try {
                $getDocument = ProjectPlans::where('id', $id)->first();

                $file = $getDocument->getRawOriginal('document');
                if (ProjectPlans::where('id', $id)->delete()) {
                    if (file_exists(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" . $file)) {
                        unlink(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" . $file);
                    }
                    ResponseService::successResponse("Data Deleted Sucessfully");
                } else {
                    ResponseService::errorResponse("Something Went Wrong");
                }
            } catch (Exception $e) {
                ResponseService::errorResponse("Something Went Wrong");
            }
        }
    }
}
