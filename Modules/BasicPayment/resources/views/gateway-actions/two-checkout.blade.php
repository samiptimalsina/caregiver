@php
    $sellerId = $paymentService->getGatewayDetails($paymentMethod)->two_checkout_sellerId;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>2checkout</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset($setting->favicon) }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/main.min.css') }}?v={{ $setting?->version }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/frontend.min.css') }}?v={{ $setting?->version }}">
</head>

<body>
    <section
        class="about-area-three section-py-120 vh-100 d-flex align-items-center justify-content-center position-relative">
        <div class="preloader-two d-none">
            <div class="loader-icon-two"><img src="{{ asset(Cache::get('setting')->preloader) }}" alt="Preloader"></div>
        </div>
        <div class="container d-flex justify-content-center">
            <div class="col-md-7">
                <div class="text-center mb-3">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset($setting?->logo) }}" alt="{{ $setting?->app_name }}" width="220">
                    </a>
                </div>
                <div class="card">
                    <div class="card-body">
                        <form
                            action="{{ isset($token) ? route('payment-api.bank-webview', ['bearer_token' => $token, 'order_id' => $order_id]) : route('pay-via-two-checkout') }}"
                            method="post" id="payment-form">
                            @csrf

                            <!-- Name -->
                            <div class="my-1 form-group pe-4">
                                <label for="name">{{ __('Name') }}</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    placeholder="{{ __('Your name') }}">
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Account Number -->
                            <div class="my-1 form-group mt-3">
                                <div class="col-12" id="card-element"></div>
                            </div>

                            <button class="mt-2 btn btn-primary" id="submitPayment">{{ __('Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @php
        $failedUrl = isset($token) ? route('payment-api.webview-failed-payment') : route('payment-failed');
    @endphp
    <script src="{{ asset('global/js/jquery-3.7.1.min.js') }}"></script>
    <script type="text/javascript" src="https://2pay-js.2checkout.com/v1/2pay.js"></script>
    <script>
        window.addEventListener('load', function() {
            var jsPaymentClient = new TwoPayClient("{{ $sellerId }}"),
            component = jsPaymentClient.components.create('card');
            component.mount('#card-element');

            let csrf_token = $('meta[name="csrf-token"]').attr("content");

            var doAjaxRequest = function(fparams) {
                $.ajax({
                    url: fparams.formAction,
                    type: "POST",
                    dataType: "json",
                    data: {
                        ess_token: fparams.token,
                        useCore: false,
                        refno: fparams.refno,
                        _token: "{{ csrf_token() }}"
                    },
                    xhrFields: {
                        withCredentials: true
                    },
                    success: (response) => {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }else {
                            window.location.href = "{{ $failedUrl }}";
                        }
                    },
                    error: function(xhr, status, error) {
                        window.location.href = "{{ $failedUrl }}";
                    },
                });
            };

            var getFormParamsObj = function(formId) {
                return {
                    formAction: $('#' + formId).attr('action'),
                    refno: ''
                }
            }
            //for placing orders with 2pay.js
            $('body').on('click', '#submitPayment', function(e) {
                e.preventDefault();
                $(".preloader-two").removeClass("d-none");
                var billingDetails = {
                    name: document.querySelector('#name').value,
                };
                var params = getFormParamsObj('payment-form');
                jsPaymentClient.tokens.generate(component, billingDetails).then(function(response) {
                    params.token = response.token;
                    doAjaxRequest(params);
                }).catch(function(error) {
                    $(".preloader-two").addClass("d-none");
                });
            });

        });
    </script>
</body>

</html>
