@extends('frontend.layouts.master')

@section('meta_title', $seo_setting['faq_page']['seo_title'] ?? __('Frequently Asked Questions'))
@section('meta_description', $seo_setting['faq_page']['seo_description'] ?? __('Answers to common questions about our courses and learning platform.'))

@section('contents')
    <x-frontend.breadcrumb :title="__('Frequently Asked Questions')" :links="[['url' => route('home'), 'text' => __('Home')], ['url' => '', 'text' => __('FAQ')]]" />

    <section class="section-py-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    @forelse ($faqs as $faq)
                        @if ($faq->translation)
                            <details class="border rounded p-4 mb-3">
                                <summary class="h5 mb-0">{{ $faq->translation->question }}</summary>
                                <div class="mt-3">{!! clean($faq->translation->answer) !!}</div>
                            </details>
                        @endif
                    @empty
                        <p>{{ __('Frequently asked questions will be published here soon.') }}</p>
                    @endforelse

                    <div class="mt-4">{{ $faqs->links() }}</div>
                </div>
            </div>
        </div>
    </section>
@endsection
