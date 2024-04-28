<?php

namespace DigitalTunnel\Cart\Contracts;

use DigitalTunnel\Cart\Cart;

/**
 * The CartNode interface.
 */
interface CartNode
{
    /**
     * Check if the parent node can be found.
     */
    public function hasKnownParentNode(): bool;

    /**
     * Get parent node instance that this instance is belong to.
     */
    public function getParentNode(): object;

    /**
     * Get the cart instance that this node belongs to.
     */
    public function getCart(): Cart;

    /**
     * Determines which values to filter.
     */
    public function getFilterValues(): array;

    /**
     * Get config of the cart instance thet this node belongs to.
     *
     * @param  null|string  $name  The config name
     * @param  mixed  $default  The return value if the config does not exist
     */
    public function getConfig(?string $name = null, mixed $default = null): mixed;

    /**
     * Get the cart node's original attribute values.
     *
     * @param  null|string  $attribute  The attribute
     * @param  mixed  $default  The return value if attribute does not exist
     */
    public function getOriginal(?string $attribute = null, mixed $default = null): mixed;

    /**
     * Dynamic attribute getter.
     *
     * @param  string  $attribute  The attribute
     * @param  mixed  $default  The return value if attribute does not exist
     */
    public function get(string $attribute, mixed $default = null): mixed;
}
