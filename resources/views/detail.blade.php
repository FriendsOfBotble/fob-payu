@php
    $metadata = json_decode(html_entity_decode($payment['udf1']), true);
    if (is_plugin_active('ecommerce')) {
        $address = app(\Botble\Ecommerce\Repositories\Interfaces\OrderAddressInterface::class)->getFirstBy([
            'order_id' => Arr::first($metadata['order_id'] ?? []),
        ]);
    }
@endphp

<div class="alert alert-success mt-4" role="alert">
    <p class="mb-2">{{ trans('plugins/payment::payment.payment_id') }}: <strong>{{ $payment['mihpayid'] }}</strong></p>

    <p class="mb-2">
        {{ trans('plugins/payment::payment.details') }}:
        <strong>
            {{ $payment['productinfo'] }}
        </strong>
    </p>

    @if(is_plugin_active('ecommerce') && $address)
        <p class="mb-2">{{ trans('plugins/payment::payment.payer_name') }}: {{ $address->name }}</p>
        <p class="mb-2">{{ trans('plugins/payment::payment.email') }}: {{ $address->email }}</p>

        @if ($address->phone)
            <p class="mb-2">{{ trans('plugins/payment::payment.phone')  }}: {{ $address->phone }}</p>
        @endif

        <p class="mb-0">
            {{ trans('plugins/payment::payment.shipping_address') }}:
            {{ $address->name }}, {{ $address->city }}, {{ $address->state }}, {{ $address->country }} {{ $address->zipcode }}
        </p>
    @endif
</div>

@if (isset($payment['refunds']))
<div class="alert alert-warning">
    <h6 class="alert-heading">{{ trans('plugins/payment::payment.refunds.title') . ' (' . count($payment['refunds']) . ')'}}</h6>

    @foreach ($payment['refunds'] as $refund)
        <hr class="m-0 mb-4">
        @php
            $refund = $refund['_data_request'];
        @endphp
        <p>{{ trans('plugins/payment::payment.amount') }}: {{ $refund['refund_amount'] }} {{ strtoupper($refund['currency']) }}</p>
        <p>{{ trans('plugins/payment::payment.refunds.create_time') }}: {{ Carbon\Carbon::now()->parse($refund['created_at']) }}</p>
    @endforeach
</div>
@endif

@include('plugins/payment::partials.view-payment-source')
