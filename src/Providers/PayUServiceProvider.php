<?php

namespace FriendsOfBotble\PayU\Providers;

use Botble\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Support\ServiceProvider;

class PayUServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public const MODULE_NAME = 'payu';

    public function boot(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        $this->setNamespace('plugins/payu')
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->publishAssets()
            ->loadRoutes();

        $this->app->booted(function () {
            $this->app->register(HookServiceProvider::class);
        });
    }
}
