<?php

namespace DigitalTunnel\Cart\Traits;

use DigitalTunnel\Cart\Cart;
use DigitalTunnel\Cart\Facades\Cart as CartFacade;
use DigitalTunnel\Cart\Item;
use Illuminate\Support\Collection;

/**
 * The CanUseCart traits.
 */
trait CanUseCart
{
    /**
     * Add the UseCartable item to the cart.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     * @param  array  $attributes  The additional attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function addToCart(Cart|string $cartOrName, array $attributes = [], bool $withEvent = true): ?Item
    {
        $cart = ($cartOrName instanceof Cart) ? $cartOrName : CartFacade::newInstance($cartOrName);

        return $cart->addItem(array_merge($attributes, ['model' => $this]), $withEvent);
    }

    /**
     * Determines the UseCartable item has in the cart.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     * @param  array  $filter  Array of additional filter
     */
    public function hasInCart(Cart|string $cartOrName, array $filter = []): bool
    {
        $foundInCart = $this->searchInCart($cartOrName, $filter);

        return ! empty($foundInCart);
    }

    /**
     * Get all the UseCartable item in the cart.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     */
    public function allFromCart(Cart|string $cartOrName): array
    {
        return $this->searchInCart($cartOrName);
    }

    /**
     * Get the UseCartable items in the cart with given additional options.
     *
     * @param  Cart|string  $cartOrName  The cart instance or the name of the cart
     * @param  array  $filter  Array of additional filter
     */
    public function searchInCart(Cart|string $cartOrName, array $filter = []): array
    {
        $cart = ($cartOrName instanceof Cart) ? $cartOrName : CartFacade::newInstance($cartOrName);
        $filter = array_merge($filter, [
            'id' => $this->getUseCartableId(),
            'associated_class' => __CLASS__,
        ]);

        return $cart->getItems($filter, true);
    }

    /**
     * Get the identifier of the UseCartable item.
     */
    public function getUseCartableId(): int|string
    {
        return method_exists($this, 'getKey') ? $this->getKey() : $this->id;
    }

    /**
     * Get the title of the UseCartable item.
     */
    public function getUseCartableTitle(): string
    {
        if (property_exists($this, 'title')) {
            return $this->title;
        }

        if (property_exists($this, 'cartTitleField')) {
            return $this->getAttribute($this->cartTitleField);
        }

        return 'Unknown';
    }

    /**
     * Get the price of the UseCartable item.
     */
    public function getUseCartablePrice(): float
    {
        if (property_exists($this, 'price')) {
            return $this->price;
        }

        if (property_exists($this, 'cartPriceField')) {
            return $this->getAttribute($this->cartPriceField);
        }

        return 0;
    }

    /**
     * Find a model by its identifier.
     *
     * @param  int  $id  The identifier of model
     */
    public function findById(int $id): null|Collection|static
    {
        return $this->find($id);
    }
}
