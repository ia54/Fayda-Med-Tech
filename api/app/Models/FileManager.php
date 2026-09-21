<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Storage;

class FileManager extends Model
{
    use HasFactory;
    public function upload($to,$file)
    {
        try {
            $file_name = time().rand(1000,9999).$file->getClientOriginalName();
            $filename = pathinfo($file_name, PATHINFO_FILENAME);
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $file_name = preg_replace('/\s+/', '', $filename);
            $file_name=  preg_replace('/[^A-Za-z0-9\-]/', '', $file_name);
            $file_name = $file_name.'.'.$extension;
            Storage::disk(config('app.STORAGE_DRIVER')) -> put('files/'.$to.'/'.$file_name, file_get_contents($file -> getRealPath()));
            $store = new self();
            $store->folder_name = 'files/'.$to;
            $store->file_name = $file_name;
            $store->save();
            return $store;
        }catch (\Exception $exception){
            $str = new \stdClass();
            $str->code = 100;
            return $str;
        }
    }

    public function updateUpload($id,$to,$file)
    {
        try {
            $file_name = time().$file->getClientOriginalName();
            $filename = pathinfo($file_name, PATHINFO_FILENAME);
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $file_name = preg_replace('/\s+/', '', $filename);
            $file_name=  preg_replace('/[^A-Za-z0-9\-]/', '', $file_name);
            $file_name = $file_name.'.'.$extension;
            Storage::disk(config('app.STORAGE_DRIVER')) -> put('files/'.$to.'/'.$file_name, file_get_contents($file -> getRealPath()));
            $store = FileManager::find($id);
            $store->folder_name = 'files/'.$to;
            $store->file_name = $file_name;
            $store->save();
            return $store;
        }catch (\Exception $exception){
            $str = new \stdClass();
            $str->code = 100;
            return $str;
        }
    }

    public function  getFileUrlAttribute()
    {
        $destinationPath = $this->folder_name.'/'.$this->file_name;
        if(Storage::disk(config('app.STORAGE_DRIVER'))->exists($this->FileDir)){
            return asset('storage/'.$destinationPath);
        }
        return asset('assets/images/no-image.jpg');
    }

    public function  getFileDirAttribute()
    {
        $destinationPath = $this->folder_name.'/'.$this->file_name;
        return $destinationPath;
    }

    public function removeFile(){
        $destinationPath = $this->folder_name.'/'.$this->file_name;
        if(Storage::disk(config('app.STORAGE_DRIVER'))->exists($this->FileDir)){
            Storage::disk(config('app.STORAGE_DRIVER'))->delete($destinationPath);
            return 100;
        }
        return 200;
    }

    public function origin()
    {
        return $this->morphTo();
    }
}
