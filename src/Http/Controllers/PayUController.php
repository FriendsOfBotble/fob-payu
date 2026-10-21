<?php

namespace FriendsOfBotble\PayU\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Hotel\Models\Booking;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Botble\Payment\Repositories\Interfaces\PaymentInterface;
use Botble\Payment\Supports\PaymentHelper;
use FriendsOfBotble\PayU\Providers\PayUServiceProvider;
use FriendsOfBotble\PayU\Services\PayUService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PayUController extends BaseController
{
    public function callback(Request $request, BaseHttpResponse $response, PayUService $payUService): BaseHttpResponse
    {
        if (! $payUService->verifyResponseHash($request->input())) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage(__('Invalid payment response.'));
        }

        $metadata = json_decode(html_entity_decode((string) $request->input('udf1')), true);

        if (! is_array($metadata) || ! isset($metadata['order_id'], $metadata['token'])) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage(__('Invalid payment response.'));
        }

        $status = match ($request->input('status')) {
            'success' => PaymentStatusEnum::COMPLETED,
            'failure' => PaymentStatusEnum::FAILED,
            default => PaymentStatusEnum::PENDING,
        };

        if ($status === PaymentStatusEnum::FAILED) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL($metadata['token']))
                ->setMessage($request->input('error_message'));
        }

        $alreadyCompleted = Payment::query()
            ->where('charge_id', $request->input('mihpayid'))
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->exists();

        // A replayed callback must not process the same PayU transaction twice.
        if (! $alreadyCompleted) {
            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'order_id' => $metadata['order_id'],
                'currency' => $metadata['currency'],
                'amount' => $request->input('amount'),
                'charge_id' => $request->input('mihpayid'),
                'payment_channel' => PayUServiceProvider::MODULE_NAME,
                'status' => $status,
                'customer_id' => $metadata['customer_id'],
                'customer_type' => $metadata['customer_type'],
                'payment_type' => 'direct',
            ], $request);
        }

        if (is_plugin_active('hotel')) {
            $booking = Booking::query()
                ->select('transaction_id')
                ->find(Arr::first($metadata['order_id']));

            if (! $booking) {
                return $response
                    ->setNextUrl(PaymentHelper::getCancelURL())
                    ->setMessage(__('Checkout failed!'));
            }

            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL($booking->transaction_id))
                ->setMessage(__('Checkout successfully!'));
        }

        $nextUrl = PaymentHelper::getRedirectURL($metadata['token']);

        if (is_plugin_active('job-board') || is_plugin_active('real-estate')) {
            $nextUrl = $nextUrl . '?charge_id=' . $request->input('mihpayid');
        }

        return $response
            ->setNextUrl($nextUrl)
            ->setMessage(__('Checkout successfully!'));
    }

    public function webhook(Request $request, PaymentInterface $paymentRepository, PayUService $payUService): void
    {
        if (! ($request->has('mihpayid') && $request->input('status') === 'success')) {
            abort(404);
        }

        $response = $payUService->verifyPayment($request->input('mihpayid'));

        if ($response['error']) {
            return;
        }

        $payment = $paymentRepository->getFirstBy([
            'charge_id' => $request->input('mihpayid'),
        ]);

        if (! $payment) {
            return;
        }

        $status = match (Arr::get($response, 'data.status')) {
            'success' => PaymentStatusEnum::COMPLETED,
            'failure' => PaymentStatusEnum::FAILED,
            default => PaymentStatusEnum::PENDING,
        };

        if (! in_array($payment->status, [PaymentStatusEnum::COMPLETED, PaymentStatusEnum::FAILED, $status])) {
            $payment->status = $status;
            $payment->save();
        }
    }
}
