<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Modules\Brand\app\Models\Brand;
use Modules\Faq\app\Models\Faq;

class PublicPagesController extends Controller
{
    public function faq()
    {
        $faqs = Faq::active()->with('translation')->orderBy('id')->paginate(15);

        return view('frontend.pages.faq', compact('faqs'));
    }

    public function partners()
    {
        $partners = Brand::where('status', true)->orderBy('name')->paginate(15);

        return view('frontend.pages.partners', compact('partners'));
    }
}
