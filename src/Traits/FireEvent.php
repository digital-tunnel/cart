<?php

namespace DigitalTunnel\Cart\Traits;

/**
 * The FireEvent traits.
 *
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
trait FireEvent
{
    /**
     * Fire an event and call the listeners.
     */
    protected function fireEvent(object|string $event, mixed $payload = [], bool $halt = true): ?array
    {
        return event($event, $payload, $halt);
    }
}
