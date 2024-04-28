<?php

namespace DigitalTunnel\Cart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The Cart facade.
 *
 * @mixin \DigitalTunnel\Cart\Cart
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
