<?php

namespace FriendsOfBotble\PayU\Services;

use Botble\Payment\Repositories\Interfaces\PaymentInterface;
use Exception;

class PayUPaymentService extends PaymentServiceAbstract
{
    public function isSupportRefundOnline(): bool
    {
        return true;
    }

    public function refund(string $chargeId, float $amount): array
    {
        try {
            $payment = app(PaymentInterface::class)->getFirstBy([
                'charge_id' => $chargeId,
            ]);

            if (! $payment) {
                return [
                    'error' => true,
                    'message' => __('Payment not found.'),
                ];
            }

            return (new PayUService())->refund($chargeId, $amount);
        } catch (Exception $exception) {
            return [
                'error' => true,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
