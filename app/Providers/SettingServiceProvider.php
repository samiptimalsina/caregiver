<?php

namespace App\Providers;

use App\Enums\ThemeList;
use App\Services\InstructorPayoutService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\GlobalSetting\app\Models\MarketingSetting;
use Modules\GlobalSetting\app\Models\SeoSetting;
use Modules\GlobalSetting\app\Models\Setting;

class SettingServiceProvider extends ServiceProvider {
    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        try {
            /** Cache settings */
            $setting = Cache::rememberForever('setting', fn() => (object) Setting::pluck('value', 'key')->all());
            $marketing_setting = Cache::rememberForever('marketing_setting', fn() => (object) MarketingSetting::pluck('value', 'key')->all());
            $seo_setting = Cache::rememberForever('seo_setting', fn() => (object) SeoSetting::all()->groupBy('page_name')->mapWithKeys(function ($group, $pageName) {
                return [$pageName => $group->first()];
            }));

            if ($setting) {
                set_wasabi_config();
                set_aws_config();
                config(['auth.max_devices' => $setting?->max_login_devices]);

                if ($setting->is_queable != 'active' && Schema::hasTable('instructor_earnings_holds')) {
                    $lastRun = Cache::get('instructor_payout_last_run');

                    if (!$lastRun || now()->diffInHours($lastRun) >= 24) {
                        $instructorPayoutReleaseService = new InstructorPayoutService();
                        $instructorPayoutReleaseService->releaseHeldAmounts();

                        Cache::put('instructor_payout_last_run', now());
                    }
                }
            }
        } catch (\Throwable $th) {
            info($th);
            $setting = (object) ['timezone' => config('app.timezone'), 'site_theme' => ThemeList::MAIN->value];
            $marketing_setting = (object) [];
            $seo_setting = (object) [];
        }

        /** Share settings to all views */
        View::composer('*', function ($view) use ($setting, $marketing_setting, $seo_setting) {
            $view->with(['setting' => $setting, 'marketing_setting' => $marketing_setting, 'seo_setting' => $seo_setting]);
        });

        // Set timezone
        date_default_timezone_set($setting->timezone ?? config('app.timezone'));

        // Define default homepage based on site_theme from setting, with fallback
        if (!defined('DEFAULT_HOMEPAGE')) {
            define('DEFAULT_HOMEPAGE', $setting?->site_theme ?? ThemeList::MAIN->value);
        }
    }
}
