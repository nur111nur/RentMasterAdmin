<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Slider extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'image',
        'sequence',
        'category_id',
        'propertys_id',
        'show_property_details',
        'link',
        'default_data'
    ];

    protected static function boot() {
        parent::boot();
        static::deleting(static function ($slider) {
            if(collect($slider)->isNotEmpty()){
                // before delete() method call this

                // Delete Image
                if ($slider->getRawOriginal('image') != '') {
                    $image = $slider->getRawOriginal('image');
                    if (file_exists(public_path('images') . config('global.SLIDER_IMG_PATH') . $image)) {
                        unlink(public_path('images') . config('global.SLIDER_IMG_PATH') . $image);
                    }
                }
            }
        });
    }


    protected $hidden = [
        'created_at',
        'updated_at'
    ];
    protected $casts = [
        'type' => 'string',
        'sequence' => 'integer',
    ];



    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'category_id')->select('id', 'category');
    }

    public function property()
    {
        return $this->hasOne(Property::class, 'id', 'propertys_id');
    }

    public function getImageAttribute($image)
    {
        if (file_exists(public_path('images') . config('global.SLIDER_IMG_PATH') . $image)) {
            return $image != "" ? url('') . config('global.IMG_PATH') . config('global.SLIDER_IMG_PATH') . $image : '';
        }
        return url('assets/images/logo/slider-default.png');

    }

    public function getTypeAttribute($value)
    {
        switch($value) {
            case '1':
                return trans('Only Image');
                break;
            case '2':
                return trans('Category');
                break;
            case '3':
                return trans('Property');
                break;
            case '4':
                return trans('Other Link');
                break;
            default:
                return trans('Invalid');
                break;
        }
    }
}

