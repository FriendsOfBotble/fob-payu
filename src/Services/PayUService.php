<?php

namespace FriendsOfBotble\PayU\Services;

use FriendsOfBotble\PayU\Providers\PayUServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayUService
{
    protected string $merchantKey;

    protected string $saltKey;

    protected array $processUrls = [
        'test' => 'https://test.payu.in',
        'production' => 'https://secure.payu.in',
    ];

    protected array $data = [];

    public function __construct()
    {
        $this->merchantKey = get_payment_setting('merchant_key', PayUServiceProvider::MODULE_NAME);
        $this->saltKey = get_payment_setting('salt_key', PayUServiceProvider::MODULE_NAME);
    }

    public function withData(array $data): self
    {
        $this->data = $data;

        $this->withAdditionalData();

        return $this;
    }

    public function redirectToCheckoutPage(): void
    {
        echo view('plugins/payu::form', [
            'data' => $this->data,
            'action' => $this->getPaymentUrl(),
        ]);

        exit();
    }

    public function refund(string $chargeId, float $amount): array
    {
        $data = $this->sendPostService('cancel_refund_transaction', [
            'var1' => $chargeId,
            'var2' => Str::random(10),
            'var3' => $amount,
        ]);

        return [
            'error' => isset($data['status']) && $data['status'] != 1,
            'message' => $data['msg'] ?? null,
        ];
    }

    public function verifyPayment(string $chargeId): array
    {
        $data = $this->sendPostService('check_payment', [
            'var1' => $chargeId,
        ]);

        return [
            'error' => isset($data['status']) && $data['status'] != 1,
            'message' => $data['msg'] ?? null,
            'data' => $data['transaction_details'] ?? [],
        ];
    }

    public function transactionId(): string
    {
        return Str::random(10);
    }

    public function getMerchantKey(): string
    {
        return $this->merchantKey;
    }

    public function getSaltKey(): string
    {
        return $this->saltKey;
    }

    protected function getPaymentHash(): string
    {
        return hash(
            'sha512',
            "{$this->getMerchantKey()}|{$this->data['txnid']}|{$this->data['amount']}|{$this->data['productinfo']}|{$this->data['firstname']}|{$this->data['email']}|{$this->data['udf1']}||||||||||{$this->getSaltKey()}"
        );
    }

    protected function getPostServiceHash(string $command, array $vars): string
    {
        $payUId = Arr::first($vars);

        return hash(
            'sha512',
            "{$this->getMerchantKey()}|$command|{$payUId}|{$this->getSaltKey()}"
        );
    }

    protected function withAdditionalData(): void
    {
        $this->data = array_merge($this->data, [
            'key' => $this->getMerchantKey(),
            'hash' => $this->getPaymentHash(),
        ]);
    }

    protected function getProcessUrl(string $uri = ''): string
    {
        return $this->processUrls[
            get_payment_setting('environment', PayUServiceProvider::MODULE_NAME) ?: 'test'
        ] . $uri;
    }

    protected function getPaymentUrl(): string
    {
        return $this->getProcessUrl('/_payment');
    }

    protected function getPostServiceUrl(): string
    {
        return $this->getProcessUrl('/merchant/postservice?form=2');
    }

    protected function sendPostService(string $command, array $vars = []): array
    {
        $response = Http::withoutVerifying()->asForm()->post($this->getPostServiceUrl(), array_merge([
            'key' => $this->getMerchantKey(),
            'command' => $command,
            'hash' => $this->getPostServiceHash($command, $vars),
        ], $vars));

        if (! $response->ok()) {
            return [
                'error' => false,
                'message' => $response->reason(),
            ];
        }

        return $response->json();
    }
}
