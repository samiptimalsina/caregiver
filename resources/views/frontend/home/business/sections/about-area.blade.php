<section class="choose__area-four tg-motion-effects section-py-140">
    <div class="container">
        <div class="row align-items-center justify-content-center">
            <div class="col-lg-6 col-md-10">
                <div class="choose__img-four">
                    <div class="left__side">
                        <img src="{{ asset($aboutSection?->global_content?->image) }}" alt="Caregiver training at Nirvan" data-aos="fade-down" data-aos-delay="200">
                        <img src="{{ asset($aboutSection?->global_content?->image_two) }}" alt="Nirvan students supporting a care recipient" data-aos="fade-up" data-aos-delay="200">
                    </div>
                    <div class="right__side" data-aos="fade-left" data-aos-delay="400">
                        <img src="{{ asset($aboutSection?->global_content?->image_three) }}" alt="Healthcare training session at Nirvan">
                        @if($aboutSection?->global_content?->video_url)
                            <a href="{{$aboutSection?->global_content?->video_url}}" class="popup-video" aria-label="Watch introductory video"><i class="fas fa-play"></i></a>
                        @endif
                    </div>
                    <img src="{{ asset('frontend/img/others/h7_choose_shape01.svg') }}" alt="shape" class="shape shape-one tg-motion-effects4">
                    <img src="{{ asset('frontend/img/others/h7_choose_shape02.svg') }}" alt="shape" class="shape shape-two alltuchtopdown">
                    <img src="{{ asset('frontend/img/others/h7_choose_shape03.svg') }}" alt="shape" class="shape shape-three tg-motion-effects7">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="choose__content-four">
                    <div class="section__title mb-20">
                        <span class="sub-title">{{ $aboutSection?->translation_content?->short_title }}</span>
                        <h2 class="title bold">{!! clean(processText($aboutSection?->translation_content?->title)) !!}</h2>
                    </div>
                    @php
                        $description = trim(preg_replace(
                            '/\s+/u',
                            ' ',
                            html_entity_decode(strip_tags(data_get($aboutSection?->translation_content, 'description', '')), ENT_QUOTES | ENT_HTML5),
                        ) ?? '');
                        $benefits = [
                            data_get($aboutSection?->translation_content, 'benefit_one'),
                            data_get($aboutSection?->translation_content, 'benefit_two'),
                            data_get($aboutSection?->translation_content, 'benefit_three'),
                        ];
                    @endphp
                    @if ($description !== '')
                        <p>{{ \Illuminate\Support\Str::limit($description, 260) }}</p>
                    @endif
                    @if (array_filter($benefits))
                        <ul class="about__info-list">
                            @foreach ($benefits as $benefit)
                                @if ($benefit)
                                    <li class="about__info-list-item">
                                        <p>{{ $benefit }}</p>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ $aboutSection?->global_content?->button_url }}" class="btn arrow-btn btn-four">{{ $aboutSection?->translation_content?->button_text }} <img src="{{ asset('frontend/img/icons/right_arrow.svg') }}" alt="" class="injectable"></a>
                </div>
            </div>
        </div>
    </div>
</section>