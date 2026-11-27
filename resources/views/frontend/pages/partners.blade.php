@extends('frontend.layouts.master')

@section('meta_title', $seo_setting['partners_page']['seo_title'] ?? __('Our Partners'))
@section('meta_description', $seo_setting['partners_page']['seo_description'] ?? __('Organizations and partners working with us.'))

@section('contents')
    <x-frontend.breadcrumb :title="__('Our Partners')" :links="[['url' => route('home'), 'text' => __('Home')], ['url' => '', 'text' => __('Our Partners')]]" />

    <section class="section-py-120">
        <div class="container">
            <div class="row g-4">
                @forelse ($partners as $partner)
                    <div class="col-6 col-md-4 col-lg-3">
                        <a class="d-flex h-100 flex-column align-items-center justify-content-center border rounded p-4 text-center"
                            href="{{ str_starts_with($partner->url, 'http://') || str_starts_with($partner->url, 'https://') ? $partner->url : url($partner->url) }}"
                            target="_blank" rel="noopener noreferrer">
                            <img src="{{ asset($partner->image) }}" alt="{{ $partner->name }}" class="img-fluid mb-3"
                                loading="lazy">
                            <span>{{ $partner->name }}</span>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <p>{{ __('Our partners will be listed here soon.') }}</p>
                    </div>
                @endforelse
            </div>
            <div class="mt-4">{{ $partners->links() }}</div>
        </div>
    </section>
@endsection
