<?php

namespace DigitalTunnel\Cart\Facades;

use DigitalTunnel\Cart\CartServiceProvider;
use Illuminate\Support\Facades\Facade;

/**
 * The Cart facade.
 *
 * @mixin CartServiceProvider
 */
class Cart extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cart';
    }
}
