<?php

namespace Database\Seeders;

use App\Enums\ThemeList;
use App\Models\Admin;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Blog\app\Models\Blog;
use Modules\Blog\app\Models\BlogCategory;
use Modules\Blog\app\Models\BlogCategoryTranslation;
use Modules\Blog\app\Models\BlogTranslation;
use Modules\Course\app\Models\CourseCategory;
use Modules\Course\app\Models\CourseCategoryTranslation;
use Modules\Frontend\app\Models\FeaturedCourseSection;
use Modules\Frontend\app\Models\Section;
use Modules\Frontend\app\Models\SectionTranslation;
use Modules\Faq\app\Models\Faq;
use Modules\Faq\app\Models\FaqTranslation;
use Modules\Language\app\Models\Language;
use Modules\GlobalSetting\app\Models\Setting;
use Modules\PageBuilder\app\Models\CustomPage;
use Modules\PageBuilder\app\Models\CustomPageTranslation;
use Modules\Testimonial\app\Models\Testimonial;
use Modules\Testimonial\app\Models\TestimonialTranslation;
use Mews\Purifier\Facades\Purifier;
use RuntimeException;

class NirvanCaregiverContentSeeder extends Seeder
{
    private const INSTRUCTOR_EMAIL = 'nirvan-catalog@nirvancaregiver.invalid';

    public function run(): void
    {
        $snapshotPath = database_path('seeders/data/nirvan-caregiver-content.json');

        if (! File::exists($snapshotPath)) {
            throw new RuntimeException("Nirvan content snapshot was not found at [{$snapshotPath}].");
        }

        $snapshot = json_decode(File::get($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
        $this->validateSnapshot($snapshot);

        DB::transaction(function () use ($snapshot): void {
            $locale = Language::query()->where('is_default', true)->value('code') ?: 'en';
            $instructor = $this->instructor();
            $courseCategories = $this->seedCourseCategories(
                $snapshot['course_categories'],
                $snapshot['courses'],
                $locale,
            );

            $this->seedCourses($snapshot['courses'], $courseCategories, $instructor, (float) $snapshot['dummy_course_price']);

            $blogCategories = $this->seedBlogCategories($snapshot['blog_categories'], $locale);
            $this->seedPosts($snapshot['posts'], $snapshot['blog_tags'], $blogCategories, $locale);
            $this->seedPages($snapshot['pages'], $snapshot['courses'], $locale);
            $this->seedHomepage($snapshot, $courseCategories, $locale);
            $this->seedBrandLogo($snapshot['brand']['logo']);
        });

        Cache::forget('setting');
    }

    private function validateSnapshot(array $snapshot): void
    {
        foreach (['course_categories', 'courses', 'blog_categories', 'blog_tags', 'posts', 'pages', 'brand'] as $key) {
            if (! isset($snapshot[$key]) || ! is_array($snapshot[$key])) {
                throw new InvalidArgumentException("Nirvan content snapshot is missing a valid [{$key}] collection.");
            }
        }

        if (! isset($snapshot['brand']['logo']['path'], $snapshot['brand']['logo']['source_url'])) {
            throw new InvalidArgumentException('Nirvan content snapshot must define its logo source and local path.');
        }

        if (! isset($snapshot['dummy_course_price']) || ! is_numeric($snapshot['dummy_course_price'])) {
            throw new InvalidArgumentException('Nirvan content snapshot must define a numeric dummy course price.');
        }
    }

    private function instructor(): User
    {
        $instructor = User::query()->firstOrCreate(
            ['email' => self::INSTRUCTOR_EMAIL],
            [
                'name' => 'Nirvan Caregiver',
                'password' => Hash::make(Str::random(64)),
                'role' => 'instructor',
                'status' => 'active',
                'is_banned' => 'no',
                'short_bio' => 'Course catalog instructor for Nirvan Caregiver.',
                'bio' => 'Imported courses from nirvancaregiver.com.',
            ],
        );

        if ($instructor->role !== 'instructor') {
            $instructor->role = 'instructor';
            $instructor->save();
        }

        return $instructor;
    }

    private function seedCourseCategories(array $records, array $courses, string $locale): array
    {
        $categories = [];

        CourseCategory::query()
            ->where('show_at_trending', true)
            ->whereNotIn('slug', array_column($records, 'slug'))
            ->update(['show_at_trending' => false]);

        foreach ($records as $record) {
            $course = collect($courses)->firstWhere('category_slug', $record['slug']);
            $icon = $course['thumbnail'] ?? null;

            if ($icon && ! $this->isLocalMedia($icon)) {
                throw new InvalidArgumentException("Category [{$record['slug']}] has an invalid local image.");
            }

            $category = CourseCategory::query()->updateOrCreate(
                ['slug' => $record['slug']],
                [
                    'status' => true,
                    'show_at_trending' => true,
                    'icon' => $icon ? ltrim($icon, '/') : null,
                ],
            );

            CourseCategoryTranslation::query()->updateOrCreate(
                ['course_category_id' => $category->id, 'lang_code' => $locale],
                ['name' => $record['name']],
            );

            $categories[$record['slug']] = $category;
        }

        return $categories;
    }

    private function seedCourses(array $records, array $categories, User $instructor, float $dummyPrice): void
    {
        foreach ($records as $record) {
            $category = $record['category_slug'] ? ($categories[$record['category_slug']] ?? null) : null;

            if ($record['category_slug'] && ! $category) {
                throw new RuntimeException("Course category [{$record['category_slug']}] was not found in the snapshot.");
            }

            $sourcePrice = $record['source_price'] ?? null;
            $hasListedPrice = is_numeric($sourcePrice) && (float) $sourcePrice > 0;
            $course = Course::withTrashed()->firstOrNew(['slug' => $record['slug']]);
            $course->fill([
                'instructor_id' => $instructor->id,
                'category_id' => $category?->id,
                'type' => 'course',
                'title' => $record['title'],
                'seo_description' => $this->plainText($record['seo_description'] ?: $record['excerpt'], 255),
                'thumbnail' => $record['thumbnail'],
                'description' => $this->cleanHtml($record['content']),
                'price' => $hasListedPrice ? (float) $sourcePrice : $dummyPrice,
                'discount' => null,
                'certificate' => false,
                'downloadable' => false,
                'partner_instructor' => false,
                'qna' => false,
                'message_for_reviewer' => $hasListedPrice
                    ? null
                    : 'Placeholder price from Nirvan content import. Replace with the actual course price before taking payment.',
                'status' => 'active',
                'is_approved' => 'approved',
            ]);

            if ($course->exists && $course->trashed()) {
                $course->restore();
            } else {
                $course->save();
            }
        }
    }

    private function seedBlogCategories(array $records, string $locale): array
    {
        $categories = [];

        foreach ($records as $record) {
            $category = BlogCategory::query()->updateOrCreate(
                ['slug' => $record['slug']],
                ['status' => true],
            );

            $translation = BlogCategoryTranslation::query()->firstOrNew([
                'blog_category_id' => $category->id,
                'lang_code' => $locale,
            ]);
            $translation->blog_category_id = $category->id;
            $translation->lang_code = $locale;
            $translation->title = $record['name'];
            $translation->short_description = $this->plainText($record['description'], 255);
            $translation->save();

            $categories[$record['slug']] = $category;
        }

        return $categories;
    }

    private function seedPosts(array $records, array $tagRecords, array $categories, string $locale): void
    {
        $tagsBySlug = collect($tagRecords)->keyBy('slug');
        $adminId = Admin::query()->value('id') ?? 0;

        foreach ($records as $record) {
            $categorySlug = $record['categories'][0] ?? 'uncategorized';
            $category = $categories[$categorySlug] ?? $categories['uncategorized'] ?? null;

            if (! $category) {
                throw new RuntimeException("Blog category [{$categorySlug}] was not found in the snapshot.");
            }

            $post = Blog::query()->updateOrCreate(
                ['slug' => $record['slug']],
                [
                    'admin_id' => $adminId,
                    'blog_category_id' => $category->id,
                    'image' => $record['thumbnail'],
                    'views' => 0,
                    'show_homepage' => true,
                    'is_popular' => false,
                    'tags' => collect($record['tags'])
                        ->map(fn (string $slug): string => $tagsBySlug->get($slug)['name'] ?? $slug)
                        ->implode(', '),
                    'status' => true,
                ],
            );

            BlogTranslation::query()->updateOrCreate(
                ['blog_id' => $post->id, 'lang_code' => $locale],
                [
                    'title' => $record['title'],
                    'description' => $this->cleanHtml($record['content']),
                    'seo_title' => $record['seo_title'] ?: $record['title'],
                    'seo_description' => $this->plainText($record['seo_description'] ?: $record['excerpt'], 1000),
                ],
            );
        }
    }

    private function seedPages(array $records, array $courses, string $locale): void
    {
        foreach ($records as $record) {
            $page = CustomPage::query()->updateOrCreate(
                ['slug' => $record['slug']],
                ['fixed_header' => false, 'status' => true],
            );

            CustomPageTranslation::query()->updateOrCreate(
                ['custom_page_id' => $page->id, 'lang_code' => $locale],
                [
                    'name' => $record['title'],
                    'content' => $this->cleanHtml($this->localizeContentLinks(
                        $this->renderPageContent($record),
                        $records,
                        $courses,
                    )),
                    'seo_title' => $record['seo_title'] ?: $record['title'],
                    'seo_description' => $this->plainText($record['seo_description'] ?: $record['excerpt'], 1000),
                ],
            );
        }
    }

    private function seedHomepage(array $snapshot, array $categories, string $locale): void
    {
        $homepage = collect($snapshot['pages'])->firstWhere('slug', 'home');
        $slides = $homepage['slider'] ?? [];

        if (count($slides) !== 4) {
            throw new InvalidArgumentException('Nirvan homepage snapshot must contain all four carousel slides.');
        }

        $sliderContent = [];
        $sliderImages = [];

        foreach ($slides as $index => $slide) {
            $number = $index + 1;
            $numberName = ['one', 'two', 'three', 'four'][$index];
            $image = ltrim($slide['image'] ?? '', '/');

            if (! $this->isLocalMedia($image)) {
                throw new InvalidArgumentException("Homepage slide [{$number}] has an invalid local image.");
            }

            $sliderImages["image_{$numberName}"] = $image;
            $sliderContent["short_title_{$numberName}"] = $slide['label'] ?? '';
            $sliderContent["title_{$numberName}"] = trim(($slide['heading'] ?? '') . ' ' . ($slide['heading_accent'] ?? ''));
            $sliderContent["sub_title_{$numberName}"] = $slide['subheading'] ?? '';
        }

        $featureContent = [
            'title_one' => 'Caregiver Training',
            'sub_title_one' => 'Industry-led learning for essential caregiving skills.',
            'title_two' => 'Global Career Pathways',
            'sub_title_two' => 'Prepare for healthcare opportunities at home and abroad.',
            'title_three' => 'Hands-On Practice',
            'sub_title_three' => 'Build confidence through practical, clinical training.',
            'title_four' => 'Certification Support',
            'sub_title_four' => 'Get guidance for certification and your next career step.',
        ];

        $video = $homepage['video'] ?? [];
        $videoPath = ltrim($video['src'] ?? '', '/');

        if (($video['mime_type'] ?? null) !== 'video/mp4' || ! $this->isLocalMedia($videoPath)) {
            throw new InvalidArgumentException('Nirvan homepage snapshot must contain a local MP4 video.');
        }

        $aboutContent = [
            'short_title' => 'Why Choose Nirvan',
            'title' => 'Professional Caregiver Training Led by Healthcare Experts',
            'description' => '<p>Nirvan is a government-endorsed training center with healthcare professionals, practical classes and internship opportunities to help learners build caregiving skills.</p>',
            'benefit_one' => 'Learn from experienced healthcare professionals',
            'benefit_two' => 'Practice essential caregiving and clinical skills',
            'benefit_three' => 'Get guidance for certification and career pathways',
            'button_text' => 'Explore Courses',
        ];
        $aboutGlobal = [
            'image' => ltrim($slides[1]['image'], '/'),
            'image_two' => ltrim($slides[2]['image'], '/'),
            'image_three' => ltrim($slides[3]['image'], '/'),
            'video_url' => '/' . $videoPath,
            'button_url' => '/courses',
            'year_experience' => null,
            'course_success' => null,
            'phone_number' => null,
        ];
        $faqContent = [
            'short_title' => 'Need Help?',
            'title' => 'Caregiver Training FAQs',
            'description' => 'Answers to common questions about Nirvan courses, practical training and enrollment.',
        ];
        $faqGlobal = [
            'image' => ltrim($slides[2]['image'], '/'),
            'image_two' => ltrim($slides[3]['image'], '/'),
        ];

        $themeNames = array_map(fn (ThemeList $theme): string => $theme->value, ThemeList::cases());

        foreach ($themeNames as $themeName) {
            if ($themeName === ThemeList::BUSINESS->value) {
                $this->updateThemeSection($themeName, 'slider_section', $sliderContent, $sliderImages, $locale);
            } else {
                $leadSlide = $slides[0];
                $backgroundSlide = $slides[1];
                $heroContent = [
                    'short_title' => $leadSlide['label'] ?? '',
                    'title' => trim(($leadSlide['heading'] ?? '') . '\\' . ($leadSlide['heading_accent'] ?? '')),
                    'sub_title' => $leadSlide['subheading'] ?? '',
                    'action_button_text' => 'Explore Caregiver Courses',
                    'video_button_text' => 'Watch Our Training Video',
                    'total_student' => null,
                    'total_instructor' => null,
                    'total_courses' => count($snapshot['courses']),
                    'average_reviews' => null,
                ];
                $heroGlobal = [
                    'banner_image' => ltrim($leadSlide['image'], '/'),
                    'hero_background' => ltrim($backgroundSlide['image'], '/'),
                    'action_button_url' => '/courses',
                    'video_button_url' => '/' . $videoPath,
                ];

                $this->updateThemeSection($themeName, 'hero_section', $heroContent, $heroGlobal, $locale);
            }

            $this->updateThemeSection($themeName, 'our_features_section', $featureContent, [], $locale);
            $this->updateThemeSection($themeName, 'about_section', $aboutContent, $aboutGlobal, $locale);
            $this->updateThemeSection($themeName, 'faq_section', $faqContent, $faqGlobal, $locale);
        }

        $this->seedFaqs($locale);
        $this->seedTestimonials($locale);
        $this->seedFeaturedCourses($snapshot['courses'], $categories);
    }

    private function seedFaqs(string $defaultLocale): void
    {
        $records = [
            [
                'legacy_question' => 'What kind of courses do you offer?',
                'en' => ['What caregiver courses does Nirvan offer?', 'Nirvan offers caregiver and healthcare training. Browse the course catalog for current programs and availability.'],
                'hi' => ['निर्वाण कौन-से केयरगिवर पाठ्यक्रम प्रदान करता है?', 'निर्वाण केयरगिवर और स्वास्थ्य-सेवा प्रशिक्षण प्रदान करता है। उपलब्ध पाठ्यक्रमों और जानकारी के लिए कोर्स सूची देखें।'],
                'ar' => ['ما دورات رعاية المرضى التي تقدمها نيرفان؟', 'تقدم نيرفان دورات في رعاية المرضى والتدريب الصحي. راجع قائمة الدورات للاطلاع على البرامج المتاحة.'],
            ],
            [
                'legacy_question' => 'Who are your instructors?',
                'en' => ['Who teaches Nirvan caregiver courses?', 'Nirvan courses are taught by healthcare professionals, including doctors, nurses and social workers.'],
                'hi' => ['निर्वाण के केयरगिवर पाठ्यक्रम कौन पढ़ाते हैं?', 'निर्वाण के पाठ्यक्रम डॉक्टरों, नर्सों और सामाजिक कार्यकर्ताओं सहित स्वास्थ्य-सेवा पेशेवरों द्वारा पढ़ाए जाते हैं।'],
                'ar' => ['من يدرّس دورات رعاية المرضى في نيرفان؟', 'يقدم دورات نيرفان متخصصون في الرعاية الصحية، بمن فيهم الأطباء والممرضون والأخصائيون الاجتماعيون.'],
            ],
            [
                'legacy_question' => 'How much do your courses cost?',
                'en' => ['How can I check course fees and apply?', 'Course fees and enrollment details can vary by program. Open a course page or contact Nirvan for current information.'],
                'hi' => ['कोर्स की फीस और आवेदन की जानकारी कैसे मिलेगी?', 'कोर्स के अनुसार फीस और प्रवेश की जानकारी अलग हो सकती है। नवीनतम जानकारी के लिए कोर्स पेज देखें या निर्वाण से संपर्क करें।'],
                'ar' => ['كيف يمكنني معرفة الرسوم والتقديم؟', 'قد تختلف الرسوم وإجراءات التسجيل حسب البرنامج. راجع صفحة الدورة أو تواصل مع نيرفان للحصول على المعلومات الحالية.'],
            ],
            [
                'legacy_question' => 'How does the online learning platform work?',
                'en' => ['Do Nirvan courses include practical training or internships?', 'Nirvan training combines theoretical classes with practical skills and internship opportunities. Details depend on the selected program.'],
                'hi' => ['क्या निर्वाण के पाठ्यक्रमों में व्यावहारिक प्रशिक्षण या इंटर्नशिप शामिल है?', 'निर्वाण प्रशिक्षण में सैद्धांतिक कक्षाओं के साथ व्यावहारिक कौशल और इंटर्नशिप के अवसर शामिल हैं। विवरण चुने गए कार्यक्रम पर निर्भर करते हैं।'],
                'ar' => ['هل تشمل دورات نيرفان تدريبًا عمليًا أو فرص تدريب ميداني؟', 'يجمع تدريب نيرفان بين الدروس النظرية والمهارات العملية وفرص التدريب الميداني. وتختلف التفاصيل حسب البرنامج المختار.'],
            ],
            [
                'legacy_question' => null,
                'en' => ['What career paths can caregiver training support?', 'Caregiver training can help prepare learners for healthcare roles locally and internationally. Hiring and licensing requirements depend on the employer and destination.'],
                'hi' => ['केयरगिवर प्रशिक्षण से कौन-से करियर विकल्प मिल सकते हैं?', 'केयरगिवर प्रशिक्षण स्थानीय और अंतरराष्ट्रीय स्वास्थ्य-सेवा भूमिकाओं के लिए तैयारी में मदद कर सकता है। भर्ती और लाइसेंस की आवश्यकताएँ नियोक्ता और देश के अनुसार अलग होती हैं।'],
                'ar' => ['ما المسارات المهنية التي يمكن أن يدعمها تدريب رعاية المرضى؟', 'يساعد التدريب على الاستعداد لوظائف الرعاية الصحية محليًا ودوليًا. وتختلف متطلبات التوظيف والترخيص حسب جهة العمل والبلد.'],
            ],
        ];
        $locales = Language::query()->where('status', true)->pluck('code')->all();

        if (! in_array($defaultLocale, $locales, true)) {
            $locales[] = $defaultLocale;
        }

        foreach ($records as $record) {
            $questions = array_filter([$record['legacy_question'], $record['en'][0]]);
            $translation = FaqTranslation::query()
                ->where('lang_code', $defaultLocale)
                ->whereIn('question', $questions)
                ->first();
            $faq = $translation?->faq ?? new Faq();
            $faq->status = true;
            $faq->save();

            foreach ($locales as $locale) {
                [$question, $answer] = $record[$locale] ?? $record['en'];
                FaqTranslation::query()->updateOrCreate(
                    ['faq_id' => $faq->id, 'lang_code' => $locale],
                    ['question' => $question, 'answer' => $answer],
                );
            }
        }
    }

    private function seedTestimonials(string $defaultLocale): void
    {
        $records = [
            [
                'name' => 'Apek Shya',
                'comment' => 'Environment is very friendly. Sir/mam is so sweet and friendly 🫶. Here I feel so happy.🙂',
            ],
            [
                'name' => 'Richa Bhujel',
                'comment' => 'Good caregiver center in nepal',
            ],
            [
                'name' => 'Apsu Rumdali',
                'comment' => 'GOOD CAREGIVER CENTER IN NEPAL',
            ],
            [
                'name' => 'Samsara Maharjan',
                'comment' => 'Very friendly and good environmonet for studies😻😻😻😻',
            ],
        ];
        $locales = Language::query()->where('status', true)->pluck('code')->all();

        if (! in_array($defaultLocale, $locales, true)) {
            $locales[] = $defaultLocale;
        }

        foreach ($records as $record) {
            $translation = TestimonialTranslation::query()
                ->where('lang_code', $defaultLocale)
                ->where('name', $record['name'])
                ->first();
            $testimonial = $translation?->testimonial ?? new Testimonial();
            $testimonial->image = 'uploads/website-images/default-avatar.png';
            $testimonial->rating = 5;
            $testimonial->status = true;
            $testimonial->save();

            foreach ($locales as $locale) {
                TestimonialTranslation::query()->updateOrCreate(
                    ['testimonial_id' => $testimonial->id, 'lang_code' => $locale],
                    [
                        'name' => $record['name'],
                        'designation' => 'Google Reviewer',
                        'comment' => $record['comment'],
                    ],
                );
            }
        }
    }

    private function seedBrandLogo(array $logo): void
    {
        $path = ltrim($logo['path'], '/');

        if (! $this->isLocalMedia($path)) {
            throw new InvalidArgumentException('Nirvan brand logo must point to a downloaded local image.');
        }

        Setting::query()->updateOrCreate(
            ['key' => 'logo'],
            ['value' => $path],
        );
    }

    private function updateThemeSection(
        string $themeName,
        string $name,
        array $content,
        array $globalContent,
        string $locale,
    ): void
    {
        $section = Section::getByName($name, $themeName);
        $section->global_content = array_merge((array) $section->global_content, $globalContent);
        $section->save();

        $translation = SectionTranslation::query()->firstOrNew([
            'section_id' => $section->id,
            'lang_code' => $locale,
        ]);
        $translation->content = array_merge((array) $translation->content, $content);
        $translation->save();
    }

    private function seedFeaturedCourses(array $courseRecords, array $categories): void
    {
        $coursesBySlug = Course::query()
            ->whereIn('slug', array_column($courseRecords, 'slug'))
            ->get(['id', 'slug', 'category_id'])
            ->keyBy('slug');
        $allCourseIds = $coursesBySlug->pluck('id')->values()->all();
        $featuredContent = [
            'all_category_ids' => json_encode($allCourseIds, JSON_THROW_ON_ERROR),
            'all_category_status' => true,
        ];

        $categorySlugs = array_keys($categories);
        $categoryNames = ['one', 'two', 'three', 'four', 'five'];

        for ($number = 1; $number <= 5; $number++) {
            $category = $categories[$categorySlugs[$number - 1] ?? ''] ?? null;
            $categoryCourseIds = $category
                ? $coursesBySlug->filter(fn (Course $course): bool => $course->category_id === $category->id)->pluck('id')->values()->all()
                : [];
            $categoryName = $categoryNames[$number - 1];
            $featuredContent["category_{$categoryName}"] = $category?->id;
            $featuredContent["category_{$categoryName}_ids"] = json_encode($categoryCourseIds, JSON_THROW_ON_ERROR);
            $featuredContent["category_{$categoryName}_status"] = $category !== null;
        }

        FeaturedCourseSection::query()->updateOrCreate(['id' => 1], $featuredContent);
    }

    private function renderPageContent(array $record): string
    {
        $content = $record['content'];

        if (! empty($record['slider'])) {
            $content = $this->renderSlider($content, $record['slider']);
        }

        if (! empty($record['video'])) {
            $video = $record['video'];
            $source = $video['src'] ?? '';

            if (! $this->isLocalMedia($source) || ($video['mime_type'] ?? null) !== 'video/mp4') {
                throw new InvalidArgumentException("Page [{$record['slug']}] has an invalid local video source.");
            }

            $videoMarkup = '<div id="media-container"><video class="wc-video" controls preload="metadata"><source src="'
                .e('/' . ltrim($source, '/'))
                .'" type="video/mp4"></video></div>';
            $content = preg_replace(
                '/<div\b(?=[^>]*\bid=["\']media-container["\'])[^>]*>\s*<\/div>/i',
                $videoMarkup,
                $content,
                1,
                $replacements,
            );

            if ($content === null || $replacements !== 1) {
                throw new RuntimeException("Page [{$record['slug']}] is missing its expected media container.");
            }
        }

        return $content;
    }

    private function renderSlider(string $content, array $slides): string
    {
        if ($slides === []) {
            throw new InvalidArgumentException('Homepage carousel must contain at least one slide.');
        }

        $slideMarkup = '';
        $textMarkup = '';
        $dotMarkup = '';

        foreach ($slides as $index => $slide) {
            $image = $slide['image'] ?? '';

            if (! $this->isLocalMedia($image)) {
                throw new InvalidArgumentException("Homepage slide [{$index}] has an invalid local image.");
            }

            $active = $index === 0;
            $slideMarkup .= '<div class="hero-slide' . ($active ? ' active' : '')
                .'" style="background-image:url(&quot;' .e($image) .'&quot;)" role="img" aria-label="'
                .e('Slide ' . ($index + 1) . ': ' . ($slide['label'] ?? ''))
                .'"></div>';
            $textMarkup .= '<div class="hero-text-item' . ($active ? ' active' : '')
                .'"><span class="hero-label">' .e($slide['label'] ?? '') .'</span><h1 class="hero-heading">'
                .e($slide['heading'] ?? '') .'<br><span class="accent">' .e($slide['heading_accent'] ?? '')
                .'</span></h1><p class="hero-subheading">' .e($slide['subheading'] ?? '') .'</p></div>';
            $dotMarkup .= '<button class="dot' . ($active ? ' active' : '')
                .'" type="button" aria-label="' .e('Go to slide ' . ($index + 1))
                .'" aria-selected="' .($active ? 'true' : 'false') .'"></button>';
        }

        $content = preg_replace_callback(
            '/<button\b(?=[^>]*\bid=["\'](?:prevSlide|nextSlide)["\'])[^>]*>.*?<\/button>/is',
            fn (array $matches): string => preg_replace('/<svg\b.*?<\/svg>/is', '', $matches[0]) ?? $matches[0],
            $content,
        ) ?? $content;
        $content = preg_replace_callback(
            '/<div\b(?=[^>]*\bid=["\']heroSlider["\'])[^>]*>/i',
            fn (array $matches): string => $matches[0] . $slideMarkup,
            $content,
            1,
            $sliderReplacements,
        );
        $content = preg_replace_callback(
            '/(<div\b(?=[^>]*\bid=["\']heroTextWrapper["\'])[^>]*>).*?<\/div>/is',
            fn (array $matches): string => substr($matches[0], 0, strpos($matches[0], '>') + 1) . $textMarkup . '</div>',
            $content ?? '',
            1,
            $textReplacements,
        );
        $content = preg_replace_callback(
            '/(<div\b(?=[^>]*\bid=["\']sliderDots["\'])[^>]*>).*?<\/div>/is',
            fn (array $matches): string => substr($matches[0], 0, strpos($matches[0], '>') + 1) . $dotMarkup . '</div>',
            $content ?? '',
            1,
            $dotReplacements,
        );

        if ($content === null || $sliderReplacements !== 1 || $textReplacements !== 1 || $dotReplacements !== 1) {
            throw new RuntimeException('Homepage carousel containers were not found in the content snapshot.');
        }

        return $content;
    }

    private function localizeContentLinks(string $content, array $pages, array $courses): string
    {
        foreach ($courses as $course) {
            $sourceUrl = $course['source_url'] ?? null;

            if ($sourceUrl) {
                $content = str_replace(
                    [rtrim($sourceUrl, '/') . '/', rtrim($sourceUrl, '/')],
                    '/course/' .rawurlencode($course['slug']),
                    $content,
                );
            }
        }

        foreach ($pages as $page) {
            $sourceUrl = $page['source_url'] ?? null;

            if ($sourceUrl) {
                $localUrl = $page['slug'] === 'home' ? '/' : '/page/' .rawurlencode($page['slug']);

                if ($page['slug'] === 'home') {
                    $content = str_replace(
                        ['href="' .e($sourceUrl) .'"', "href='" .e($sourceUrl) ."'"],
                        ['href="' .e($localUrl) .'"', "href='" .e($localUrl) ."'"],
                        $content,
                    );

                    continue;
                }

                $content = str_replace($sourceUrl, $localUrl, $content);
            }
        }

        $content = preg_replace_callback(
            '/href=(["\'])https:\/\/nirvancaregiver\.com\/([^"\']*)\1/i',
            function (array $matches): string {
                $path = parse_url(html_entity_decode('/' . $matches[2], ENT_QUOTES | ENT_HTML5), PHP_URL_PATH) ?: '/';
                $localPath = match (true) {
                    str_starts_with($path, '/profile/') => '/all-instructors',
                    str_starts_with($path, '/course-category/') => '/courses',
                    str_starts_with($path, '/student-public-account') => '/register',
                    str_starts_with($path, '/dashboard/retrieve-password') => '/forgot-password',
                    default => null,
                };

                return $localPath ? 'href=' . $matches[1] . $localPath . $matches[1] : $matches[0];
            },
            $content,
        ) ?? $content;

        return $content;
    }

    private function cleanHtml(?string $html): string
    {
        $html = preg_replace_callback('/<(img|video|source)\b[^>]*>/i', function (array $matches): string {
            $tag = $matches[0];
            $tagName = strtolower($matches[1]);

            if (in_array($tagName, ['img', 'source'], true)) {
                if (! preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $tag, $source)) {
                    return '';
                }

                $sourceUrl = html_entity_decode($source[2], ENT_QUOTES | ENT_HTML5);

                return $this->isLocalMedia($sourceUrl) ? $tag : '';
            }

            foreach (['src', 'poster'] as $attribute) {
                if (! preg_match('/\b' . $attribute . '\s*=\s*(["\'])(.*?)\1/i', $tag, $media)) {
                    continue;
                }

                $mediaUrl = html_entity_decode($media[2], ENT_QUOTES | ENT_HTML5);

                if (! $this->isLocalMedia($mediaUrl)) {
                    if ($attribute === 'src') {
                        return '';
                    }

                    $tag = preg_replace('/\s+poster\s*=\s*(["\']).*?\1/i', '', $tag) ?? $tag;
                }
            }

            return $tag;
        }, $html ?? '') ?? '';

        return Purifier::clean($html, 'nirvan_import');
    }

    private function isLocalMedia(string $path): bool
    {
        $relativePath = ltrim($path, '/');

        return str_starts_with($relativePath, 'uploads/nirvan-caregiver/')
            && ! str_contains($relativePath, '..')
            && File::isFile(public_path($relativePath));
    }

    private function plainText(?string $html, int $limit): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html ?? '')) ?? '');

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

}
