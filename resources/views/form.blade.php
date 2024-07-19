<form action="{{ $action }}" method="post">
    @foreach($data as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}"/>
    @endforeach
    <button type="submit" style="display: none">{{ __('Submit') }}</button>
</form>

<p>{{ __('Redirecting to PayU...') }}</p>

<script>
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelector('form').submit();
    });
</script>
