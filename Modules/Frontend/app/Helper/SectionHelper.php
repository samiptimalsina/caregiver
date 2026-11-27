<?php

namespace Modules\Frontend\app\Helper;

use Illuminate\Support\Facades\Cache;
use Modules\Frontend\app\Models\Section;

class SectionHelper {
    public static function getAll($theme_name, $hours = 24) {
        $lang = getSessionLanguage();
        $cacheKey = "{$theme_name}_home_sections_{$lang}";

        return Cache::remember($cacheKey, now()->addHours($hours), function () use ($lang, $theme_name) {
            $sections = Section::whereHas("home", function ($q) use ($theme_name) {
                $q->where('slug', $theme_name);
            })->select('sections.id', 'sections.name', 'sections.global_content', 'sections.home_id')
                ->selectRaw("t.content as translation_content")
                ->leftJoin('section_translations as t', function ($q) {
                    $q->on('t.section_id', '=', 'sections.id')
                        ->where('t.lang_code', getSessionLanguage());
                })->get();

            $sections = $sections->map(function ($section) {
                if ($section->translation_content) {
                    $section->translation_content = json_decode($section->translation_content);
                }
                return $section;
            });
            return $sections;
        });
    }
}
