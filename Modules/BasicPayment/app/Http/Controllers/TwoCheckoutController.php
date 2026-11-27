<?php

namespace Modules\BasicPayment\app\Http\Controllers;

require_once base_path('/Modules/BasicPayment/plugin/2checkout-php-sdk/autoloader.php');

use App\Http\Controllers\Controller;
use App\Traits\GetGlobalInformationTrait;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tco\Examples\Common;
use Tco\TwocheckoutFacade;

class TwoCheckoutController extends Controller {
    use GetGlobalInformationTrait;
    private $basic_payment;
    public function __construct() {
        $this->basic_payment = $this->get_basic_payment_info();
        $this->middleware(function (Request $request, Closure $next) {
            if (session()->has('order')) {
                return $next($request);
            }
            return redirect()->back()->with(['messege' => __('Not Found!'), 'alert-type' => 'error']);
        });
    }

    /**
     * Get 2Checkout API configuration.
     *
     * @return array
     */
    private function getTcoConfig(): array {
        return [
            'sellerId'          => $this->basic_payment?->two_checkout_sellerId,
            'secretKey'         => $this->basic_payment?->two_checkout_secretKey,
            'buyLinkSecretWord' => $this->basic_payment?->two_checkout_buyLinkSecretWord,
            'jwtExpireTime'     => 30,
            'curlVerifySsl'     => true,
        ];
    }

    /**
     * Generate dynamic order array.
     *
     * @param array $overrides
     * @return array
     */
    private function generateOrderArray(array $overrides = []): array {
        $user = userAuth();
        $order = session()->get('order');
        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        $defaultOrder = [
            'Currency'          => $payable_currency,
            'CustomerIP'        => request()->ip(),
            'ExternalReference' => 'CustOrd' . now()->timestamp,
            'Language'          => 'en',
            'BillingDetails'    => [
                'Address1'    => $user?->address ?? 'Street 1',
                'City'        => $user?->city ?? 'Cleveland',
                'State'       => $user?->state ?? 'Ohio',
                'CountryCode' => 'US',
                'Email'       => $user?->email,
                'FirstName'   => $user?->name ?? 'John',
                'LastName'    => 'Doe',
                'Zip'         => '20034',
            ],
            'Items'             => [
                [
                    'Name'         => 'Order Payment',
                    'Description'  => "Payment for order #" . $order?->invoice_id,
                    'Quantity'     => 1,
                    'IsDynamic'    => true,
                    'Tangible'     => false,
                    'PurchaseType' => 'PRODUCT',  //always PRODUCT
                    'Price'        => [
                        'Amount' => $paid_amount,
                        'Type'   => 'CUSTOM',
                    ],
                ],
            ],
            'PaymentDetails'    => [
                'Currency'      => $payable_currency,
                'CustomerIP'    => request()->ip(),
                'PaymentMethod' => [
                    'RecurringEnabled' => false,
                ],
            ],
        ];

        return array_replace_recursive($defaultOrder, $overrides);
    }

    /**
     * Handles 3DS payment flow with 2PayJs token and places the order.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processPayment(Request $request) {
        $validated = $request->validate([
            'ess_token' => 'required|string',
            'useCore'   => 'required',
        ]);

        $tco = new TwocheckoutFacade($this->getTcoConfig());

        try {
            $predefinedDynamicOrderParams = $this->generateOrderArray([
                'PaymentDetails' => $this->getPaymentDetailsWith3DsUrls(
                    $validated['ess_token'],
                    ($this->basic_payment?->two_checkout_account_mode ?? 'sandbox') === 'sandbox'
                ),
            ]);

            $response = $validated['useCore']
            ? $tco->apiCore()->call('/orders/', $predefinedDynamicOrderParams)
            : $tco->order()->place($predefinedDynamicOrderParams);

            if (isset($response['Errors']) && !empty($response['Errors'])) {
                return $this->errorResponse(implode(PHP_EOL, $response['Errors']));
            }

            $redirectTo = isset($response['PaymentDetails']['PaymentMethod']['Authorize3DS'])
            ? $this->extract3DSUrl($response['PaymentDetails']['PaymentMethod']['Authorize3DS'])
            : route('two-checkout-success-payment', ['refno' => $response['RefNo']]);

            return response()->json([
                'success'  => true,
                'refno'    => $response['RefNo'],
                'redirect' => $redirectTo,
            ]);
        } catch (Exception $exception) {
            info($exception->getMessage());
            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Generates 3DS callback URLs.
     *
     * @param string $action
     * @param int|null $cartId
     * @return string
     */
    private function get3DsCallbackUrl(string $action, ?int $cartId = null): string {
        $params = [
            'action' => $action,
            'cartId' => $cartId ?? now()->timestamp,
        ];

        return Common\Helpers\MyHelper::generateUrl('Redirect3ds', $params);
    }

    /**
     * Gets payment details with 3DS URLs.
     *
     * @param string $token
     * @param bool $testMode
     * @return array
     */
    private function getPaymentDetailsWith3DsUrls(string $token, bool $testMode): array {
        return [
            'Type'          => $testMode ? 'TEST' : 'EES_TOKEN_PAYMENT',
            'PaymentMethod' => [
                'EesToken'           => $token,
                'Vendor3DSReturnURL' => $this->get3DsCallbackUrl('success'),
                'Vendor3DSCancelURL' => $this->get3DsCallbackUrl('cancel'),
            ],
        ];
    }

    /**
     * Extracts the 3DS authorization URL.
     *
     * @param array $has3ds
     * @return string|null
     */
    private function extract3DSUrl(array $has3ds): ?string {
        return $has3ds['Href'] . '?avng8apitoken=' . ($has3ds['Params']['avng8apitoken'] ?? '');
    }

    /**
     * Error response helper.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    private function errorResponse(string $message) {
        return response()->json([
            'success' => false,
            'errors'  => $message,
        ]);
    }

    /**
     * Handles payment success callback.
     *
     * @param Request $request
     */
    public function success(Request $request) {
        try {
            $response = (new TwocheckoutFacade($this->getTcoConfig()))
                ->order()->getOrder(['RefNo' => $request->refno]);

            if (($response['ApproveStatus'] ?? null) === 'OK') {
                Session::put([
                    'after_success_transaction' => $request->refno,
                    'payment_details'           => $response['PaymentDetails'] ?? null,
                ]);
                return redirect()->route('payment-success');
            }
        } catch (Exception $e) {
            info($e->getMessage());
        }

        return redirect()->route('payment-failed');
    }
}