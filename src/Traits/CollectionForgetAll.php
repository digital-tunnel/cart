<?php

namespace DigitalTunnel\Cart\Traits;

/**
 * The CollectionForgetAll traits.
 */
trait CollectionForgetAll
{
    /**
     * Remove all items out of the collection.
     *
     * @return $this
     */
    public function forgetAll(): static
    {
        parent::__construct();

        return $this;
    }
}
