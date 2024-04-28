<?php

namespace DigitalTunnel\Cart;

use Illuminate\Support\ServiceProvider;

/**
 * The CartServiceProvider class.
 */
class CartServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     */
    protected bool $defer = false;

    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        $packageConfigPath = __DIR__.'/Config/config.php';
        $appConfigPath = config_path('cart.php');

        $this->mergeConfigFrom($packageConfigPath, 'cart');

        $this->publishes([
            $packageConfigPath => $appConfigPath,
        ], 'config');
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->bind('cart', 'DigitalTunnel\Cart\Cart');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'cart',
        ];
    }
}
