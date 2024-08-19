<?php

namespace FriendsOfBotble\PayU\Providers;

use FriendsOfBotble\PayU\Services\PayUPaymentService;
use FriendsOfBotble\PayU\Services\PayUService;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Models\Payment;
use Html;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use PaymentMethods;
use Throwable;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, function (string|null $settings) {
            return $settings . view('plugins/payu::settings')->render();
        }, 999);

        add_filter(BASE_FILTER_ENUM_ARRAY, function (array $values, string $class): array {
            if ($class === PaymentMethodEnum::class) {
                $values['PAYU'] = PayUServiceProvider::MODULE_NAME;
            }

            return $values;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class): string {
            if ($class === PaymentMethodEnum::class && $value === PayUServiceProvider::MODULE_NAME) {
                $value = 'PayU';
            }

            return $value;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function (string $value, string $class): string {
            if ($class === PaymentMethodEnum::class && $value === PayUServiceProvider::MODULE_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )->toHtml();
            }

            return $value;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, function (string|null $html, array $data): string|null {
            if (get_payment_setting('status', PayUServiceProvider::MODULE_NAME)) {
                $payUService = new PayUService();

                if (! $payUService->getMerchantKey() || ! $payUService->getSaltKey()) {
                    return $html;
                }

                PaymentMethods::method(PayUServiceProvider::MODULE_NAME, [
                    'html' => view(
                        'plugins/payu::method',
                        $data,
                        ['moduleName' => PayUServiceProvider::MODULE_NAME]
                    )->render(),
                ]);
            }

            return $html;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, function (string|null $data, string $value): string|null {
            if ($value === PayUServiceProvider::MODULE_NAME) {
                $data = PayUPaymentService::class;
            }

            return $data;
        }, 20, 2);

        add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, function (array $data, Request $request): array {
            if ($data['type'] !== PayUServiceProvider::MODULE_NAME) {
                return $data;
            }

            $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

            try {
                $payUService = new PayUService();

                $payUService->withData([
                    'txnid' => $payUService->transactionId(),
                    'productinfo' => $paymentData['description'],
                    'amount' => $paymentData['amount'],
                    'email' => $paymentData['address']['email'],
                    'firstname' => Str::of($paymentData['address']['name'])->before(' ')->toString(),
                    'lastname' => Str::of($paymentData['address']['name'])->after(' ')->toString(),
                    'surl' => route('payment.payu.callback'),
                    'furl' => route('payment.payu.callback'),
                    'phone' => $paymentData['address']['phone'],
                    'address1' => $paymentData['address']['address'],
                    'city' => $paymentData['address']['city'],
                    'state' => $paymentData['address']['state'],
                    'country' => $paymentData['address']['country'],
                    'zipcode' => $paymentData['address']['zip_code'] ?? $paymentData['address']['zip'],
                    'udf1' => json_encode([
                        'order_id' => $paymentData['order_id'],
                        'currency' => $paymentData['currency'],
                        'customer_id' => $paymentData['customer_id'],
                        'customer_type' => addslashes($paymentData['customer_type']),
                        'token' => $paymentData['checkout_token'],
                    ]),
                ]);

                $payUService->redirectToCheckoutPage();
            } catch (Throwable $exception) {
                $data['error'] = true;
                $data['message'] = json_encode($exception->getMessage());
            }

            return $data;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_PAYMENT_INFO_DETAIL, function (string $data, Payment $payment) {
            if ($payment->payment_channel == PayUServiceProvider::MODULE_NAME) {
                $detail = (new PayUService())->verifyPayment($payment->charge_id);
                if ($payment->metadata) {
                    $detail['data']['refunds'] = $payment->metadata['refunds'];
                }

                $data = view('plugins/payu::detail', ['payment' => $detail['data']])->render() . $data;
            }

            return $data;
        }, 999, 2);
    }
}
