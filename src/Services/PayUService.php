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
        $this->merchantKey = (string) get_payment_setting('merchant_key', PayUServiceProvider::MODULE_NAME);
        $this->saltKey = (string) get_payment_setting('salt_key', PayUServiceProvider::MODULE_NAME);
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
            'error' => ($data['status'] ?? 0) != 1,
            'message' => $data['msg'] ?? $data['message'] ?? null,
        ];
    }

    public function verifyPayment(string $chargeId): array
    {
        $data = $this->sendPostService('check_payment', [
            'var1' => $chargeId,
        ]);

        return [
            'error' => ($data['status'] ?? 0) != 1,
            'message' => $data['msg'] ?? $data['message'] ?? null,
            'data' => $data['transaction_details'] ?? [],
        ];
    }

    /**
     * Verify the reverse hash PayU posts back to surl/furl, so the status, amount and udf1
     * (order metadata) of a callback can't be forged.
     */
    public function verifyResponseHash(array $data): bool
    {
        $postedHash = (string) Arr::get($data, 'hash');

        if (! $postedHash || ! $this->getMerchantKey() || ! $this->getSaltKey()) {
            return false;
        }

        // PayU may send udf1 back HTML-encoded, so accept the hash of either form.
        $udf1Candidates = array_unique([
            (string) Arr::get($data, 'udf1'),
            html_entity_decode((string) Arr::get($data, 'udf1')),
        ]);

        foreach ($udf1Candidates as $udf1) {
            $udfs = [];

            for ($i = 10; $i >= 2; $i--) {
                $udfs[] = (string) Arr::get($data, "udf$i");
            }

            $udfs[] = $udf1;

            $hashString = implode('|', [
                $this->getSaltKey(),
                (string) Arr::get($data, 'status'),
                ...$udfs,
                (string) Arr::get($data, 'email'),
                (string) Arr::get($data, 'firstname'),
                (string) Arr::get($data, 'productinfo'),
                (string) Arr::get($data, 'amount'),
                (string) Arr::get($data, 'txnid'),
                $this->getMerchantKey(),
            ]);

            if ($additionalCharges = Arr::get($data, 'additionalCharges')) {
                $hashString = $additionalCharges . '|' . $hashString;
            }

            if (hash_equals(hash('sha512', $hashString), strtolower($postedHash))) {
                return true;
            }
        }

        return false;
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
        $response = Http::asForm()->post($this->getPostServiceUrl(), array_merge([
            'key' => $this->getMerchantKey(),
            'command' => $command,
            'hash' => $this->getPostServiceHash($command, $vars),
        ], $vars));

        if ($response->failed()) {
            return [
                'error' => false,
                'message' => $response->reason(),
            ];
        }

        return $response->json() ?: [
            'error' => false,
            'message' => $response->reason() ?: 'Unknown error',
        ];
    }
}
