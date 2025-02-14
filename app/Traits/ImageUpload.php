<?php
namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\File;

trait ImageUpload
{
    public function uploadAndCompressImage($image, $img_for,$directory, $width = 800, $height = 600)
    {
        // Create a unique filename
        $filename = $img_for . '_' . time() .'.'.$image->getClientOriginalExtension();

        // Define the storage path
        $path = $directory . '/' . $filename;

        // Compress the image
        $imageResized = Image::make($image->getRealPath())->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode($image->getClientOriginalExtension(), 75);

        // Store the image in the public disk
       Storage::disk('public')->put($path, $imageResized);
       
       return $filename;

    }

    public function getUrl($org_id,$img_name){
        $file_name = $img_name===null ? 'null' : $img_name;
        $file_path = 'design/'.$org_id.'/'.$file_name;
        if(Storage::disk('public')->exists($file_path)){
            $root_path = url('storage/'.$file_path);
        }
        else{
            $root_path=url('storage/design/no-img.png'); 
        }
        
        return $root_path;
    }

    public function getLogo($img_name){
        $file_name = $img_name===null ? 'null' : $img_name;
        $file_path = 'logo/'.$file_name;
        if(Storage::disk('public')->exists($file_path)){
            $root_path = url('storage/'.$file_path);
        }
        else{
            $root_path=url('storage/design/no-img.png'); 
        }
        
        return $root_path;
    }

}