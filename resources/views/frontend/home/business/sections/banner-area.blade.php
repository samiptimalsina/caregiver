<section class="slider__area home4_slider__area">
    <div class="swiper-container slider__active">
        <div class="swiper-wrapper">
            @for ($slideNumber = 1; $slideNumber <= 4; $slideNumber++)
                @php
                    $slideName = ['one', 'two', 'three', 'four'][$slideNumber - 1];
                    $imageKey = 'image_' . $slideName;
                    $shortTitleKey = 'short_title_' . $slideName;
                    $titleKey = 'title_' . $slideName;
                    $subTitleKey = 'sub_title_' . $slideName;
                    $slideImage = $slider?->global_content?->{$imageKey};
                @endphp
                @if ($slideImage)
                    <div class="swiper-slide slider__single">
                        <div class="slider__bg" data-background="{{ asset($slideImage) }}">
                            <div class="container">
                                <div class="row">
                                    <div class="col-xl-6 col-lg-7">
                                        <div class="slider__content">
                                            <span class="sub-title">{{ $slider?->translation_content?->{$shortTitleKey} }}</span>
                                            <h2 class="title">{!! clean(processText($slider?->translation_content?->{$titleKey})) !!}</h2>
                                            <p>{!! clean(processText($slider?->translation_content?->{$subTitleKey})) !!}</p>
                                            <div class="slider__search">
                                                <form action="{{ route('courses') }}" class="slider__search-form">
                                                    <input type="text" name="search"
                                                        placeholder="{{ __('Search here') }} . . .">
                                                    <button type="submit">{{ __('Find Courses') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endfor
        </div>
    </div>
</section>
