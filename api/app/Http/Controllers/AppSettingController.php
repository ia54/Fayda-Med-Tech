<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * @OA\Tag(
 *     name="Application Settings",
 *     description="API Endpoints for Global Configuration and System Environment"
 * )
 */
class AppSettingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/get-setting-values",
     *     summary="Retrieve global UI settings",
     *     description="Get logos, favicons, and other platform branding configurations",
     *     operationId="getSettingValues",
     *     tags={"Application Settings"},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function getSettingValues()
    {
        $settings = AppSetting::all();
        $settingArray = [];

        foreach ($settings as $setting) {
            if ($setting->type == 1) {
                $settingArray[$setting->option_key] = getSettingImage($setting->option_key);
            } else {
                $settingArray[$setting->option_key] = $setting->option_value;
            }
        }
        $response['settings'] = $settingArray;
        return response()->json($response,200);
    }

    /**
     * @OA\Get(
     *     path="/api/get-env-values",
     *     summary="Retrieve environment variables",
     *     description="Get mail and stripe configurations (Admin only)",
     *     operationId="getEnvValues",
     *     tags={"Application Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function getEnvValues()
    {
        $settingArray = array(
            'MAIL_MAILER' => env('MAIL_MAILER'),
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_PORT' => env('MAIL_PORT'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_PASSWORD' => env('MAIL_PASSWORD'),
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
            'STRIPE_MODE' => env('STRIPE_MODE'),
            'STRIPE_KEY' => env('STRIPE_KEY'),
            'STRIPE_SECRET' => env('STRIPE_SECRET'),
            'STRIPE_WEBHOOK_SECRET' => env('STRIPE_WEBHOOK_SECRET'),
        );
        $response['env_values'] = $settingArray;

        return response()->json($response,200);
    }

    /**
     * @OA\Post(
     *     path="/api/setting-update",
     *     summary="Update branding settings",
     *     operationId="updateBrandingSettings",
     *     tags={"Application Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="logo", type="string", format="binary")))),
     *     @OA\Response(response=200, description="Settings updated")
     * )
     */
    public function settingUpdate(Request $request)
    {
        $inputs = Arr::except($request->all(), ['_token']);
        $keys = [];

        foreach ($inputs as $k => $v) {
            $keys[$k] = $k;
        }

        foreach ($inputs as $key => $value) {
            $option = AppSetting::firstOrCreate(['option_key' => $key]);

            if ($request->hasFile('logo') && $key == 'logo') {
                $request->validate([
                    'logo' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->logo);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('favicon') && $key == 'favicon') {
                $request->validate([
                    'favicon' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->favicon);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('favicon_16') && $key == 'favicon_16') {
                $request->validate([
                    'favicon_16' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->favicon_16);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('favicon_32') && $key == 'favicon_32') {
                $request->validate([
                    'favicon_32' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->favicon_32);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('favicon_64') && $key == 'favicon_64') {
                $request->validate([
                    'favicon_64' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->favicon_64);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('apple_touch_icon') && $key == 'apple_touch_icon') {
                $request->validate([
                    'apple_touch_icon' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->apple_touch_icon);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('homeBannerBg') && $key == 'homeBannerBg') {
                $request->validate([
                    'homeBannerBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->homeBannerBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            }  elseif ($request->hasFile('homeWhyNowLeftImage') && $key == 'homeWhyNowLeftImage') {
                $request->validate([
                    'homeWhyNowLeftImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->homeWhyNowLeftImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('aboutBannerBg') && $key == 'aboutBannerBg') {
                $request->validate([
                    'aboutBannerBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->aboutBannerBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('aboutAboutUsLeftImage') && $key == 'aboutAboutUsLeftImage') {
                $request->validate([
                    'aboutAboutUsLeftImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->aboutAboutUsLeftImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            }  elseif ($request->hasFile('aboutWhoWeServeRightImage') && $key == 'aboutWhoWeServeRightImage') {
                $request->validate([
                    'aboutWhoWeServeRightImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->aboutWhoWeServeRightImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('aboutOurStoryLeftImage') && $key == 'aboutOurStoryLeftImage') {
                $request->validate([
                    'aboutOurStoryLeftImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->aboutOurStoryLeftImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('aboutOurMissionRightImage') && $key == 'aboutOurMissionRightImage') {
                $request->validate([
                    'aboutOurMissionRightImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->aboutOurMissionRightImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('aboutProblemWeSolveLeftImage') && $key == 'aboutProblemWeSolveLeftImage') {
                $request->validate([
                    'aboutProblemWeSolveLeftImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->aboutProblemWeSolveLeftImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('solutionsBannerBg') && $key == 'solutionsBannerBg') {
                $request->validate([
                    'solutionsBannerBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->solutionsBannerBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('solutionsSmarterClaimProcessingRightImage') && $key == 'solutionsSmarterClaimProcessingRightImage') {
                $request->validate([
                    'solutionsSmarterClaimProcessingRightImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->solutionsSmarterClaimProcessingRightImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('pricingBannerBg') && $key == 'pricingBannerBg') {
                $request->validate([
                    'pricingBannerBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->pricingBannerBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('pricingPlanQuoteImage') && $key == 'pricingPlanQuoteImage') {
                $request->validate([
                    'pricingPlanQuoteImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->pricingPlanQuoteImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('contactBannerBg') && $key == 'contactBannerBg') {
                $request->validate([
                    'contactBannerBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->contactBannerBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('privacyPolicyBannerBg') && $key == 'privacyPolicyBannerBg') {
                $request->validate([
                    'privacyPolicyBannerBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->privacyPolicyBannerBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('termsConditionBannerBg') && $key == 'termsConditionBannerBg') {
                $request->validate([
                    'termsConditionBannerBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->termsConditionBannerBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('homeAppIntegrationRightImage') && $key == 'homeAppIntegrationRightImage') {
                $request->validate([
                    'homeAppIntegrationRightImage' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->homeAppIntegrationRightImage);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } elseif ($request->hasFile('commonCtaBg') && $key == 'commonCtaBg') {
                $request->validate([
                    'commonCtaBg' => 'file'
                ]);
                $upload = settingImageStoreUpdate($option->id, $request->commonCtaBg);
                $option->option_value = $upload;
                $option->type = 1;
                $option->save();
            } else {
                $option->option_value = $value;
                $option->save();
            }
        }

        return response()->json(['message' => 'Settings updated successfully'],200);
    }

    /**
     * @OA\Post(
     *     path="/api/setting-env-update",
     *     summary="Update environment variables",
     *     description="Updates .env values for mail, stripe, and other system services (Admin only)",
     *     operationId="updateEnvValues",
     *     tags={"Application Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             description="Key-value pairs for environment variables",
     *             example={"MAIL_HOST": "smtp.mailtrap.io", "STRIPE_KEY": "pk_test_..."}
     *         )
     *     ),
     *     @OA\Response(response=200, description="Env updated successfully")
     * )
     */
    public function settingEnvUpdate(Request $request)
    {
        $inputs = Arr::except($request->all(), ['_token']);
        $keys = [];

        foreach ($inputs as $k => $v) {
            $keys[$k] = $k;
        }

        foreach ($inputs as $key => $value) {

            $oldValue = env($key);
            $newValue = str_replace(' ', '', $value);

            $path = base_path('.env');
            if (file_exists($path)) {
                file_put_contents(
                    $path, str_replace($key . '=' . $oldValue, $key . '=' . $newValue, file_get_contents($path))
                );
            }
        }
        return response()->json(['message' => 'Env Updated successfully'],200);
    }
}
