<section class="features__area-seven grey-bg-two home-nirvan-features">
    <div class="container">
        <div class="features__item-wrap-four">
            <div class="row">
                <div class="col-xl-3 col-lg-3 col-md-6">
                    <div class="features__item-six">
                        <div class="features__icon-six">
                            <img src="{{ asset($ourFeatures?->global_content?->image_one) }}" alt="">
                        </div>
                        <div class="features__content-six">
                            <h4 class="title">{{ $ourFeatures?->translation_content?->title_one }}</h4>
                            <span>{{ $ourFeatures?->translation_content?->sub_title_one }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6">
                    <div class="features__item-six">
                        <div class="features__icon-six">
                            <img src="{{ asset($ourFeatures?->global_content?->image_two) }}" alt="">
                        </div>
                        <div class="features__content-six">
                            <h4 class="title">{{ $ourFeatures?->translation_content?->title_two }}</h4>
                            <span>{{ $ourFeatures?->translation_content?->sub_title_two }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6">
                    <div class="features__item-six">
                        <div class="features__icon-six">
                            <img src="{{ asset($ourFeatures?->global_content?->image_three) }}" alt="">
                        </div>
                        <div class="features__content-six">
                            <h4 class="title">{{ $ourFeatures?->translation_content?->title_three }}</h4>
                            <span>{{ $ourFeatures?->translation_content?->sub_title_three }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6">
                    <div class="features__item-six">
                        <div class="features__icon-six">
                            <img src="{{ asset($ourFeatures?->global_content?->image_four) }}" alt="">
                        </div>
                        <div class="features__content-six">
                            <h4 class="title">{{ $ourFeatures?->translation_content?->title_four }}</h4>
                            <span>{{ $ourFeatures?->translation_content?->sub_title_four }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('styles')
    <style>
        .home-nirvan-features .features__item-wrap-four {
            padding: 34px 30px 14px;
            border-color: #e8eaf2;
            border-radius: 22px;
            background: linear-gradient(135deg, #fff 0%, #fbfcff 100%);
            box-shadow: 0 18px 50px rgba(27, 38, 74, 0.09);
        }

        .home-nirvan-features .features__item-six {
            align-items: flex-start;
            gap: 14px;
            min-height: 92px;
            margin-bottom: 20px;
        }

        .home-nirvan-features .features__icon-six {
            width: 58px;
            height: 58px;
            background: #f0efff;
        }

        .home-nirvan-features .features__icon-six img {
            max-width: 30px;
            max-height: 30px;
            object-fit: contain;
        }

        .home-nirvan-features .features__content-six {
            min-width: 0;
        }

        .home-nirvan-features .features__content-six .title {
            margin: 2px 0 7px;
            font-size: 16px;
            line-height: 1.35;
            letter-spacing: -0.01em;
        }

        .home-nirvan-features .features__content-six span {
            color: #687087;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.5;
        }

        @media (max-width: 767.98px) {
            .home-nirvan-features .features__item-wrap-four {
                padding: 28px 22px 10px;
                border-radius: 18px;
            }

            .home-nirvan-features .features__item-six {
                min-height: 0;
            }
        }
    </style>
@endpush