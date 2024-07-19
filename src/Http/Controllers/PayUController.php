<?php

namespace FriendsOfBotble\PayU\Http\Controllers;

use FriendsOfBotble\PayU\Providers\PayUServiceProvider;
use FriendsOfBotble\PayU\Services\PayUService;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Repositories\Interfaces\PaymentInterface;
use Botble\Payment\Supports\PaymentHelper;
use Illuminate\Http\Request;

class PayUController extends BaseController
{
    public function callback(Request $request, BaseHttpResponse $response): BaseHttpResponse
    {
        $metadata = json_decode(html_entity_decode($request->input('udf1')), true);

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

        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'order_id' => $metadata['order_id'],
            'amount' => $request->input('amount'),
            'charge_id' => $request->input('mihpayid'),
            'payment_channel' => PayUServiceProvider::MODULE_NAME,
            'status' => $status,
            'customer_id' => $metadata['customer_id'],
            'customer_type' => $metadata['customer_type'],
            'payment_type' => 'direct',
        ], $request);

        return $response
            ->setNextUrl(PaymentHelper::getRedirectURL($metadata['token']))
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

        switch ($response['data']['status']) {
            case 'success':
                $status = PaymentStatusEnum::COMPLETED;

                break;

            case 'failure':
                $status = PaymentStatusEnum::FAILED;

                break;
            default:
                $status = PaymentStatusEnum::PENDING;

                break;
        }

        if (! in_array($payment->status, [PaymentStatusEnum::COMPLETED, PaymentStatusEnum::FAILED, $status])) {
            $payment->status = $status;
            $payment->save();
        }
    }
}
