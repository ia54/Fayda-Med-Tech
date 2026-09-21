<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema; // Add this line
use Illuminate\Support\ServiceProvider;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\CaseModel::observe(\App\Observers\CaseObserver::class);
        \App\Models\Document::observe(\App\Observers\DocumentObserver::class);
        \App\Models\CaseSettlement::observe(\App\Observers\SettlementObserver::class);
        \App\Models\CaseParty::observe(\App\Observers\CasePartyObserver::class);
        \App\Models\DemandLetter::observe(\App\Observers\DemandLetterObserver::class);
        \App\Models\CaseTask::observe(\App\Observers\TaskObserver::class);
        \App\Models\MedicalRecordRequest::observe(\App\Observers\MedicalRecordRequestObserver::class);
        \App\Models\HipaaAuthorization::observe(\App\Observers\HipaaAuthorizationObserver::class);
        Schema::defaultStringLength(191); // Add this line
        try {
            $connection = DB::connection()->getPdo();
            if ($connection){
                $allOptions = [];
                $allOptions['settings'] = AppSetting::all()->pluck('option_value', 'option_key')->toArray();
                config($allOptions);
            }
        } catch (\Exception $e) {
            //
        }
    }
}
