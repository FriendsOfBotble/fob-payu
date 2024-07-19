<li class="list-group-item">
    <input class="magic-radio js_payment_method" type="radio" name="payment_method" id="payment_{{ $moduleName }}" value="{{ $moduleName }}" @checked($selecting === $moduleName)>
    <label for="payment_{{ $moduleName }}">{{ get_payment_setting('name', $moduleName) }}</label>
    <div @class(['payment_' . $moduleName . '_wrap payment_collapse_wrap collapse', 'show' => $selecting === $moduleName])>
        <p>{!! get_payment_setting('description', $moduleName) !!}</p>
    </div>
</li>
