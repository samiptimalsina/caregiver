<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Course\app\Helper\CourseCategoryHelper;
use Modules\Course\app\Models\CourseCategory;
use Modules\Course\app\Models\CourseLanguage;
use Modules\Course\app\Models\CourseLevel;

class CoursePageController extends Controller {
    public function index(): View {
        $categories = CourseCategoryHelper::getAll()->whereNull('parent_id');
        $languages = CourseLanguage::where('status', 1)->get();
        $levels = CourseLevel::where('status', 1)->with('translation')->get();
        return view('frontend.pages.course', compact('categories', 'languages', 'levels'));
    }

    public function fetchCourses(Request $request) {
        $query = Course::query();
        $query->where('is_approved', 'approved')
            ->where('courses.status', 'active');
        $query->whereHas('category.parentCategory', function ($q) use ($request) {
            $q->where('status', 1);
        });
        $query->whereHas('category', function ($q) use ($request) {
            $q->where('status', 1);
        });

        $query->when($request->search, function ($q) use ($request) {
            $q->where('title', 'like', '%' . $request->search . '%');
        });
        $query->when($request->main_category, function ($q) use ($request) {
            $q->whereHas('category', function ($q) use ($request) {
                $q->whereHas('parentCategory', function ($q) use ($request) {
                    $q->where('slug', $request->main_category);
                });
            });
        });
        $query->when($request->category && $request->filled('category'), function ($q) use ($request) {
            $categoriesIds = explode(',', $request->category);
            $q->whereIn('category_id', $categoriesIds);
        });
        $query->when($request->language && $request->filled('language'), function ($q) use ($request) {
            $languagesIds = explode(',', $request->language);
            $q->whereHas('languages', function ($q) use ($languagesIds) {
                $q->whereIn('language_id', $languagesIds);
            });
        });

        $query->when($request->price, function ($q) use ($request) {
            if ($request->price == 'paid') {
                $q->where('price', '>', 0);
            } else {
                $q->where('price', 0)->orWhere('price', null);
            }
        });

        $query->when($request->level, function ($q) use ($request) {
            $levelsIds = explode(',', $request->level);
            $q->whereHas('levels', function ($q) use ($levelsIds) {
                $q->whereIn('level_id', $levelsIds);
            });
        });

        $query->with(['instructor:id,name', 'enrollments']);

        // join category translation to include translated category name in the query results
        $query->leftJoin('course_categories', 'courses.category_id', '=', 'course_categories.id')
            ->leftJoin('course_category_translations as t', function ($join) {
                $join->on('t.course_category_id', '=', 'course_categories.id')
                    ->where('t.lang_code', getSessionLanguage());
            })
            ->select('courses.id','courses.slug','courses.title','courses.created_at','courses.instructor_id','courses.category_id','courses.thumbnail','courses.discount','courses.price','courses.capacity','t.name as category_translation_name',
                DB::raw('(SELECT AVG(course_reviews.rating) FROM course_reviews WHERE course_reviews.course_id = courses.id AND course_reviews.status = 1) as reviews_avg_rating')
            );

        $query->orderBy('created_at', $request->order && $request->filled('order') ? $request->order : 'desc');
        $courses = $query->paginate(9);

        $lastPage = $courses->lastPage();
        $page = $request->page ?? 1;
        $itemCount = $courses->count();
        $data = [
            'items'       => view('frontend.partials.course-card', compact('courses'))->render(),
            'lastPage'    => $lastPage,
            'currentPage' => $page,
            'itemCount'   => $itemCount,
        ];

        // if main category is selected then show sub category card
        if ($request->main_category && $request->filled('main_category')) {
            $subCategories = CourseCategory::whereHas('parentCategory', function ($q) use ($request) {
                $q->where('slug', $request->main_category);
            })->selectRaw('course_categories.*, t.name as translation_name')
                ->leftJoin('course_category_translations as t', function ($q) {
                    $q->on('t.course_category_id', '=', 'course_categories.id')->where('t.lang_code', getSessionLanguage());
                })->get();
            $categoriesIds = explode(',', $request->category);
            $data['sidebar_items'] = view('frontend.partials.course-sidebar-item', compact('subCategories', 'categoriesIds'))->render();
        }

        return response()->json($data);
    }

    public function show(string $slug) {
        $course = Course::query()
            ->with(['chapters' => function ($query) {
                $query->orderBy('order', 'asc')->with(['chapterItems', 'chapterItems.lesson', 'chapterItems.quiz']);
            }, 'instructor:id,name,image,job_title,short_bio,facebook,twitter,linkedin,github,website', 'partnerInstructors.instructor', 'levels.level.translation', 'languages.language', 'enrollments'])
            ->withCount(['reviews' => function ($query) {
                $query->where('status', 1)->whereHas('course')->whereHas('user');
            }])
            ->withCount(['lessons', 'quizzes'])
            ->leftJoin('course_categories', 'courses.category_id', '=', 'course_categories.id')
            ->leftJoin('course_category_translations as t', function ($join) {
                $join->on('t.course_category_id', '=', 'course_categories.id')
                    ->where('t.lang_code', getSessionLanguage());
            })
            ->select('courses.*','t.name as category_translation_name',
                DB::raw('(SELECT AVG(course_reviews.rating) FROM course_reviews WHERE course_reviews.course_id = courses.id AND course_reviews.status = 1) as reviews_avg_rating')
            )
            ->where('courses.status', 'active')
            ->where('courses.slug', $slug)
            ->whereNull('courses.deleted_at')
            ->firstOrFail();

        // Optimize: get all rating stats (average + counts by star) in one query
        $ratingStats = CourseReview::where('course_id', $course->id)
            ->where('status', 1)
            ->whereHas('course')
            ->whereHas('user')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get();

        // Build counts array
        $ratingCounts = [];
        $avgRating = 0;
        $totalReviews = 0;

        foreach ($ratingStats as $stat) {
            $ratingCounts[$stat->rating] = $stat->count;
            $totalReviews += $stat->count;
        }

        $fiveStar = $ratingCounts[5] ?? 0;
        $fourStar = $ratingCounts[4] ?? 0;
        $threeStar = $ratingCounts[3] ?? 0;
        $twoStar = $ratingCounts[2] ?? 0;
        $oneStar = $ratingCounts[1] ?? 0;

        $reviews = CourseReview::with('user:id,name,image')
            ->where('course_id', $course->id)
            ->where('status', 1)
            ->whereHas('course')
            ->whereHas('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('frontend.pages.course-details', compact('course', 'reviews', 'fiveStar', 'fourStar', 'threeStar', 'twoStar', 'oneStar'));
    }
}
