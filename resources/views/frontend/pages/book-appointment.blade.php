@extends('frontend.layouts.master')

@section('meta_title', __('Book an Appointment'))
@section('meta_description', __('Request an appointment with Nirvan Caregiver and Vocational Training Center.'))

@section('contents')
    <x-frontend.breadcrumb :title="__('Book an Appointment')" :links="[['url' => route('home'), 'text' => __('Home')], ['url' => '', 'text' => __('Book an Appointment')]]" />

    <section class="section-py-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    @if (session('appointment_submitted'))
                        <div class="alert alert-success" role="status">{{ session('appointment_submitted') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('appointment.store') }}" method="POST" class="border rounded p-4">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="appointment-name" class="form-label">{{ __('Name') }}</label>
                                <input id="appointment-name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label for="appointment-email" class="form-label">{{ __('Email') }}</label>
                                <input id="appointment-email" name="email" type="email" class="form-control" value="{{ old('email') }}" required maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label for="appointment-phone" class="form-label">{{ __('Phone') }}</label>
                                <input id="appointment-phone" name="phone" type="tel" class="form-control" value="{{ old('phone') }}" required maxlength="30">
                            </div>
                            <div class="col-md-3">
                                <label for="appointment-date" class="form-label">{{ __('Preferred date') }}</label>
                                <input id="appointment-date" name="appointment_date" type="date" class="form-control" min="{{ now()->toDateString() }}" value="{{ old('appointment_date') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label for="appointment-time" class="form-label">{{ __('Preferred time') }}</label>
                                <input id="appointment-time" name="appointment_time" type="time" class="form-control" value="{{ old('appointment_time') }}" required>
                            </div>
                            <div class="col-12">
                                <label for="appointment-message" class="form-label">{{ __('Message (optional)') }}</label>
                                <textarea id="appointment-message" name="message" class="form-control" rows="4" maxlength="1000">{{ old('message') }}</textarea>
                            </div>
                            @if (Cache::get('setting')?->recaptcha_status === 'active')
                                <div class="col-12">
                                    <div class="g-recaptcha" data-sitekey="{{ Cache::get('setting')->recaptcha_site_key }}"></div>
                                </div>
                            @endif
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">{{ __('Request Appointment') }}</button>
                                <p class="mt-2 mb-0">{{ __('Your preferred time is a request. Our team will contact you to confirm availability.') }}</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
