<?php

namespace Modules\Course\app\Helper;

use Illuminate\Support\Facades\Cache;
use Modules\Course\app\Models\CourseCategory;

class CourseCategoryHelper {
    public static function getAll($hours = 24) {
        $lang = getSessionLanguage();
        $cacheKey = "course_categories_{$lang}";

        return Cache::remember($cacheKey, now()->addHours($hours), function () use ($lang) {
            return CourseCategory::select('course_categories.*')
                ->selectRaw('t.name as translation_name')
                ->leftJoin('course_category_translations as t', function ($q) use ($lang) {
                    $q->on('t.course_category_id', '=', 'course_categories.id')->where('t.lang_code', $lang);
                })
                ->where('status', 1)
                ->get();
        });
    }
    public static function getTrendingCategories($hours = 24) {
        $lang = getSessionLanguage();
        $cacheKey = "trending_categories_{$lang}";

        return Cache::remember($cacheKey, now()->addHours($hours), function () use ($lang) {
            return CourseCategory::select('course_categories.*')
            ->selectRaw('(
                    SELECT COUNT(*) FROM course_categories as sub
                    INNER JOIN courses ON courses.category_id = sub.id
                    WHERE sub.parent_id = course_categories.id AND courses.status = "active"
                ) as total_courses')
            ->selectRaw("t.name as translation_name")
            ->leftJoin('course_category_translations as t', function ($q) use ($lang) {
                $q->on('t.course_category_id', '=', 'course_categories.id')
                    ->where('t.lang_code', $lang);
            })
            ->whereNull('parent_id')
            ->where('status', 1)
            ->where('show_at_trending', 1)
            ->get();
        });
    }
}