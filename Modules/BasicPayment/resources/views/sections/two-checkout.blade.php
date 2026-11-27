<div class="tab-pane fade" id="two_checkout_tab" role="tabpanel">
    <form action="{{ route('admin.two-checkout-update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="form-group col-md-6">
                <label for="two_checkout_account_mode">{{ __('Account Mode') }}</label>
                <select name="two_checkout_account_mode" id="two_checkout_account_mode" class="form-control">
                    <option {{ $basic_payment?->two_checkout_account_mode == 'sandbox' ? 'selected' : '' }} value="sandbox">
                        {{ __('Sandbox') }}</option>
                    <option {{ $basic_payment?->two_checkout_account_mode == 'live' ? 'selected' : '' }} value="live">
                        {{ __('Live') }}</option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label for="two_checkout_charge">{{ __('Gateway charge (%)') }}</label>
                <input type="text" class="form-control" name="two_checkout_charge" id="two_checkout_charge"
                    value="{{ $basic_payment?->two_checkout_charge }}">
            </div>
            <div class="form-group col-md-6">
                <label for="two_checkout_sellerId">{{ __('Seller ID') }}</label>
                <input type="text" class="form-control" name="two_checkout_sellerId" id="two_checkout_sellerId"
                    value="{{ $basic_payment?->two_checkout_sellerId }}">
            </div>
            <div class="form-group col-md-6">
                <label for="two_checkout_secretKey">{{ __('Secret key') }}</label>
                <input type="text" class="form-control" name="two_checkout_secretKey" id="two_checkout_secretKey"
                    value="{{ $basic_payment?->two_checkout_secretKey }}">
            </div>
            <div class="form-group col-md-12">
                <label for="two_checkout_buyLinkSecretWord">{{ __('Buy Link Secret Word') }}</label>
                <input type="text" class="form-control" name="two_checkout_buyLinkSecretWord" id="two_checkout_buyLinkSecretWord"
                    value="{{ $basic_payment?->two_checkout_buyLinkSecretWord }}">
            </div>
            <div class="form-group col-md-12">
                <label>{{ __('New Image') }} <code>({{ __('Recommended') }}: 210X100 PX)</code></label>
                <div id="two_checkout_image_preview" class="image-preview">
                    <label for="two_checkout_image_upload" id="two_checkout_image_label">{{ __('Image') }}</label>
                    <input type="file" name="two_checkout_image" id="two_checkout_image_upload">
                </div>
            </div>
            <div class="form-group col-md-12">
                <label class="d-flex align-items-center">
                    <input type="hidden" value="inactive" name="two_checkout_status" class="custom-switch-input">
                    <input type="checkbox" value="active" name="two_checkout_status" class="custom-switch-input"
                        {{ $basic_payment?->two_checkout_status == 'active' ? 'checked' : '' }}>
                    <span class="custom-switch-indicator"></span>
                    <span class="custom-switch-description">{{ __('Status') }}</span>
                </label>
            </div>
        </div>

        <button class="btn btn-primary">{{ __('Update') }}</button>
    </form>
</div>
