<?php

namespace FriendsOfBotble\PayU\Services;

use Botble\Payment\Services\Traits\PaymentErrorTrait;
use Botble\Support\Services\ProduceServiceInterface;
use Exception;
use Illuminate\Http\Request;

abstract class PaymentServiceAbstract implements ProduceServiceInterface
{
    use PaymentErrorTrait;

    protected string $currency;

    public function __construct()
    {
        $this->currency = config('plugins.payment.payment.currency');
    }

    abstract public function refund(string $chargeId, float $amount): array;

    abstract public function isSupportRefundOnline(): bool;

    public function execute(Request $request): void
    {
        try {
            $this->makePayment($request->input());
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);
        }
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSupportRefundOnline(): bool
    {
        return $this->isSupportRefundOnline();
    }

    public function refundOrder(string $chargeId, float $amount): array
    {
        return $this->refund($chargeId, $amount);
    }

    public function makePayment(array $data): void
    {
    }
}
