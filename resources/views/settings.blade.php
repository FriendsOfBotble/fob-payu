@php
    $name = 'PayU';
    $description = trans('plugins/payu::payu.description');
    $link = 'https://payu.in';
    $image = asset('vendor/core/plugins/payu/images/payu.png');
    $moduleName = \FriendsOfBotble\PayU\Providers\PayUServiceProvider::MODULE_NAME;
    $status = (bool) get_payment_setting('status', $moduleName);
@endphp

<table class="table payment-method-item">
    <tbody>
    <tr class="border-pay-row">
        <td class="border-pay-col">
            <i class="fa fa-theme-payments"></i>
        </td>
        <td style="width: 20%">
            <img class="filter-black" src="{{ $image }}" alt="{{ $name }}">
        </td>
        <td class="border-right">
            <ul>
                <li>
                    <a href="{{ $link }}" target="_blank">{{ $name }}</a>
                    <p>{{ $description }}</p>
                </li>
            </ul>
        </td>
    </tr>
    <tr class="bg-white">
        <td colspan="3">
            <div class="float-start" style="margin-top: 5px;">
                <div @class(['payment-name-label-group', 'hidden' => ! $status])>
                    <span class="payment-note v-a-t">{{ trans('plugins/payment::payment.use') }}:</span>
                    <label class="ws-nm inline-display method-name-label">{{ get_payment_setting('name', $moduleName) }}</label>
                </div>
            </div>
            <div class="float-end">
                <a @class(['btn btn-secondary toggle-payment-item edit-payment-item-btn-trigger', 'hidden' => ! $status])>{{ trans('plugins/payment::payment.edit') }}</a>
                <a @class(['btn btn-secondary toggle-payment-item save-payment-item-btn-trigger', 'hidden' => $status])>{{ trans('plugins/payment::payment.settings') }}</a>
            </div>
        </td>
    </tr>
    <tr class="paypal-online-payment payment-content-item hidden">
        <td class="border-left" colspan="3">
            <form>
                <input type="hidden" name="type" value="{{ $moduleName }}" class="payment_type">

                <div class="row">
                    <div class="col-sm-6">
                        <ul>
                            <li>
                                <label>{{ trans('plugins/payment::payment.configuration_instruction', ['name' => $name]) }}</label>
                            </li>
                            <li class="payment-note">
                                <p>{{ trans('plugins/payment::payment.configuration_requirement', ['name' => $name]) }}:</p>
                                <ul class="m-md-l" style="list-style-type:decimal">
                                    <li style="list-style-type:decimal">
                                        <a href="https://dashboard.stripe.com/register" target="_blank">
                                            {{ trans('plugins/payment::payment.service_registration', ['name' => $name]) }}
                                        </a>
                                    </li>
                                    <li style="list-style-type:decimal">
                                        <p>{{ trans('plugins/payment::payment.stripe_after_service_registration_msg', ['name' => $name]) }}</p>
                                    </li>
                                    <li style="list-style-type:decimal">
                                        <p>{{ trans('plugins/payment::payment.stripe_enter_client_id_and_secret') }}</p>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="col-sm-6">
                        <div class="well bg-white">
                            <x-core-setting::text-input
                                name="payment_payu_name"
                                :label="trans('plugins/payment::payment.method_name')"
                                :value="get_payment_setting('name', $moduleName, trans('plugins/payment::payment.pay_online_via', ['name' => $name]))"
                                data-counter="400"
                            />

                            <x-core-setting::form-group>
                                <label class="text-title-field" for="payment_payu_description">{{ trans('core/base::forms.description') }}</label>
                                <textarea class="next-input" name="payment_payu_description" id="payment_payu_description">{{ get_payment_setting('description', $moduleName, __('Payment with PayU')) }}</textarea>
                            </x-core-setting::form-group>

                            <x-core-setting::text-input
                                :name="'payment_' . $moduleName . '_merchant_key'"
                                :label="trans('plugins/payu::payu.merchant_key')"
                                :value="get_payment_setting('merchant_key', $moduleName)"
                                placeholder="xxxxxx"
                            />

                            <x-core-setting::text-input
                                :name="'payment_' . $moduleName . '_salt_key'"
                                :label="trans('plugins/payu::payu.salt_key')"
                                :value="get_payment_setting('salt_key', $moduleName)"
                                placeholder="xxxxxxxx"
                            />

                            <x-core-setting::select
                                :name="'payment_' . $moduleName . '_environment'"
                                :label="trans('plugins/payu::payu.environment')"
                                :options="[
                                    'test' => trans('plugins/payu::payu.test'),
                                    'production' => trans('plugins/payu::payu.production'),
                                ]"
                                :value="get_payment_setting('environment', $moduleName)"
                            />

                            {!! apply_filters(PAYMENT_METHOD_SETTINGS_CONTENT, null, $moduleName) !!}
                        </div>
                    </div>
                </div>

                <div class="col-12 bg-white text-end">
                    <button @class(['btn btn-warning disable-payment-item', 'hidden' => ! $status]) type="button">{{ trans('plugins/payment::payment.deactivate') }}</button>
                    <button @class(['btn btn-info save-payment-item btn-text-trigger-save', 'hidden' => $status]) type="button">{{ trans('plugins/payment::payment.activate') }}</button>
                    <button @class(['btn btn-info save-payment-item btn-text-trigger-update', 'hidden' => ! $status]) type="button">{{ trans('plugins/payment::payment.update') }}</button>
                </div>
            </form>
        </td>
    </tr>
    </tbody>
</table>
