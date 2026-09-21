<?php

use App\Models\FileManager;

function getOption($option_key, $default = '')
{
    $system_settings = config('settings');

    if ($option_key && isset($system_settings[$option_key])) {
        return $system_settings[$option_key];
    } else {
        return $default;
    }
}

function getSettingImage($option_key): string
{
    $system_settings = config('settings');

    if ($option_key && isset($system_settings[$option_key])) {
        $fileManager = FileManager::find($system_settings[$option_key]);
        if ($fileManager) {
            $destinationPath = 'files/AppSetting' . '/' . $fileManager->file_name;
            if (Storage::disk(config('app.STORAGE_DRIVER'))->exists($destinationPath)) {
                return asset('storage/' . $destinationPath);
            }
        }
    }
    return '';
}

function settingImageStoreUpdate($option_id, $requestFile)
{
    if ($requestFile) {
        $new_file = FileManager::where('origin_type', 'App\Models\AppSetting')->where('origin_id', $option_id)->first();

        if ($new_file) {
            $new_file->removeFile();
            $upload = $new_file->updateUpload($new_file->id, 'AppSetting', $requestFile);
        } else {
            $new_file = new FileManager();
            $upload = $new_file->upload('AppSetting', $requestFile);
        }

        if (@$upload->code != 100) {
            $upload->origin_id = $option_id;
            $upload->origin_type = "App\Models\AppSetting";
            $upload->save();
        }

        return $upload->id;
    }

    return null;
}

function imageStoreUpdate($modelName, $image, $id)
{
    // Build the fully qualified model class string
    $originType = "App\\Models\\$modelName";

    // Find existing file
    $existingFile = FileManager::where('origin_type', $originType)->where('origin_id', $id)->first();

    if ($existingFile) {
        // Remove old file
        $existingFile->removeFile();

        // Update the file
        $upload = $existingFile->updateUpload($existingFile->id, $modelName, $image);
    } else {
        $fileManager = new FileManager();
        $upload = $fileManager->upload($modelName, $image);
    }

    // Save origin information if upload is successful
    if ($upload->code != 100) {
        $upload->origin_id = $id;
        $upload->origin_type = $originType;
        $upload->save();
    }

    return $upload;
}
function getApiCredential($provider, $name, $default = null)
{
    $user = Auth::user();
    
    // 1. Try to find organization-specific credential first
    if ($user && $user->organization_id) {
        $credential = \App\Models\ApiCredential::withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('provider', $provider)
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
            
        if ($credential && $credential->key) {
            return $credential->key;
        }
    }

    // 2. Fallback to system-wide credential (where organization_id is null)
    $credential = \App\Models\ApiCredential::withoutGlobalScopes()
        ->whereNull('organization_id')
        ->where('provider', $provider)
        ->where('name', $name)
        ->where('is_active', true)
        ->first();

    return ($credential && $credential->key) ? $credential->key : $default;
}
