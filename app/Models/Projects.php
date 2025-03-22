<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
    use Carbon\Carbon;

class Projects extends Model
{
    use HasFactory;
    protected $fillable = array(
        'title',
        'slug_id',
        'category_id',
        'description',
        'location',
        'added_by',
        'is_admin_listing',
        'country',
        'state',
        'city',
        'latitude',
        'longitude',
        'video_link',
        'type',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'meta_image',
        'status'
    );
    protected static function boot() {
        parent::boot();
        static::deleting(static function ($project) {
            if(collect($project)->isNotEmpty()){
                // before delete() method call this

                // Delete Title Image
                if ($project->getRawOriginal('image') != '') {
                    $url = $project->image;
                    $relativePath = parse_url($url, PHP_URL_PATH);
                    if (file_exists(public_path()  . $relativePath)) {
                        unlink(public_path()  . $relativePath);
                    }
                }

                // Delete Gallery Image
                if(isset($project->gallery) && collect($project->gallery)->isNotEmpty()){
                    foreach ($project->gallery as $row) {
                        if (ProjectDocuments::where('id', $row->id)->delete()) {
                            $image = $row->getRawOriginal('name');
                            if (file_exists(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" .$image)) {
                                unlink(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" .$image );
                            }
                        }
                    }
                }

                // Delete Documents
                if(isset($project->documents) && collect($project->documents)->isNotEmpty()){
                    foreach ($project->documents as $row) {
                        if (ProjectDocuments::where('id', $row->id)->delete()) {
                            $file = $row->getRawOriginal('name');
                            if (file_exists(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" .$file)) {
                                unlink(public_path('images') . config('global.PROJECT_DOCUMENT_PATH') . "/" .$file );
                            }
                        }
                    }
                }

                // Delete Floor Plans
                if(isset($project->floor_plans) && collect($project->floor_plans)->isNotEmpty()){
                    foreach ($project->floor_plans as $row) {
                        unlink_image($row->document);
                        ProjectPlans::where('id', $row->id)->delete();
                    }
                }
            }
        });
    }

    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'category_id')->select('id', 'category', 'parameter_types', 'image');
    }
    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'added_by');
    }
    public function project_documetns(){
        return $this->hasMany(ProjectDocuments::class,'project_id');
    }
    public function gallary_images()
    {
        return $this->hasMany(ProjectDocuments::class, 'project_id')->where('type', 'image');
    }
    public function documents()
    {
        return $this->hasMany(ProjectDocuments::class, 'project_id')->where('type', 'doc');
    }
    public function plans()
    {
        return $this->hasMany(ProjectPlans::class, 'project_id');
    }
    public function getImageAttribute($image, $fullUrl = true)
    {
        if ($fullUrl) {
            return $image != '' ? url('') . config('global.IMG_PATH') . config('global.PROJECT_TITLE_IMG_PATH') . $image : '';
        } else {
            return $image;
        }
    }
    public function getMetaImageAttribute($image, $fullUrl = true) {
        if ($fullUrl) {
            return $image != '' ? url('') . config('global.IMG_PATH') . config('global.PROJECT_SEO_IMG_PATH') . $image : '';
        } else {
            return $image;
        }
    }

    public function getCreatedAtAttribute($date){
        // Assuming $date is a string representing a date
        $carbonDate = new Carbon($date);

        return $carbonDate->diffForHumans();
    }

    public function getGallaryImagesDirectlyAttribute(){
        return $this->project_documetns()->where('type','image');
    }
    public function getDocumentsDirectlyAttribute(){
        return $this->project_documetns()->where('type','doc');
    }
}

