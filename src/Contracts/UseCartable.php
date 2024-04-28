<?php

namespace DigitalTunnel\Cart\Contracts;

use DigitalTunnel\Cart\Cart;
use DigitalTunnel\Cart\Item;
use Illuminate\Support\Collection;

/**
 * The UseCartable interface.
 */
interface UseCartable
{
    /**
     * Get the identifier of the UseCartable item.
     */
    public function getUseCartableId(): int|string;

    /**
     * Get the title of the UseCartable item.
     */
    public function getUseCartableTitle(): string;

    /**
     * Get the price of the UseCartable item.
     */
    public function getUseCartablePrice(): float;

    /**
     * Add the UseCartable item to the cart.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     * @param  array  $attributes  The additional attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function addToCart(Cart|string $cartOrName, array $attributes = [], bool $withEvent = true): ?Item;

    /**
     * Determines the UseCartable item has in the cart.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     * @param  array  $filter  Array of additional filter
     */
    public function hasInCart(Cart|string $cartOrName, array $filter = []): bool;

    /**
     * Get all the UseCartable item in the cart.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     */
    public function allFromCart(Cart|string $cartOrName): array;

    /**
     * Get the UseCartable items in the cart with given additional filter.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     * @param  array  $filter  Array of additional filter
     */
    public function searchInCart(Cart|string $cartOrName, array $filter = []): array;

    /**
     * Find a model by its identifier.
     *
     * @param  int  $id  The identifier of model
     */
    public function findById(int $id): null|Collection|static;
}
