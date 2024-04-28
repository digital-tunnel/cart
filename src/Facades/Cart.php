<?php

namespace DigitalTunnel\Cart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The Cart facade.
 *
 *
 * @author  Jackie Do <anhvudo@gmail.com>
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
