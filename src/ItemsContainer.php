<?php

namespace DigitalTunnel\Cart;

use Closure;
use DigitalTunnel\Cart\Exceptions\InvalidHashException;
use DigitalTunnel\Cart\Exceptions\UnknownCreatorException;

/**
 * The ItemsContainer class
 * This is a container used to store cart items.
 *
 *
 * @author  Jackie Do <anhvudo@gmail.com>
 */
class ItemsContainer extends Container
{
    /**
     * The name of the accepted class is the creator.
     */
    protected array $acceptedCreators = [
        Cart::class,
    ];

    /**
     * Get details information of this container as a collection.
     *
     * @param  bool  $withActions  Include details of applied actions in the result
     */
    public function getDetails(bool $withActions = true): Details
    {
        $details = new Details;
        $allItems = $this->all();

        foreach ($allItems as $hash => $item) {
            $details->put($hash, $item->getDetails($withActions));
        }

        return $details;
    }

    /**
     * Add an item into this container.
     *
     * @param  array  $attributes  The item attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function addItem(array $attributes = [], bool $withEvent = true): ?Item
    {
        $item = new Item($attributes);

        if ($withEvent) {
            $event = $this->fireEvent('cart.item.adding', [$item]);

            if ($event === false) {
                return null;
            }
        }

        $itemHash = $item->getHash();

        if ($this->has($itemHash)) {
            // If item is already exists in this container, we will increase quantity of item
            $newQuantity = $this->get($itemHash)->getQuantity() + $item->getQuantity();

            return $this->updateItem($itemHash, ['quantity' => $newQuantity], $withEvent);
        }

        // If an item does not exist in this container, we will put it to container
        $this->put($itemHash, $item);

        if ($withEvent) {
            $this->fireEvent('cart.item.added', [$item]);
        }

        return $item;
    }

    /**
     * Update item attributes of an item in this container.
     *
     * @param  string  $itemHash  The unique identifier of item
     * @param  array  $attributes  The new item attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function updateItem(string $itemHash, array $attributes = [], bool $withEvent = true): ?Item
    {
        if (! is_array($attributes)) {
            $attributes = ['quantity' => $attributes];
        }

        if (array_key_exists('quantity', $attributes) && intval($attributes['quantity']) <= 0) {
            $this->removeItem($itemHash, $withEvent);

            return null;
        }

        $item = $this->getItem($itemHash);

        if ($withEvent) {
            $event = $this->fireEvent('cart.item.updating', [&$attributes, $item]);

            if ($event === false) {
                return null;
            }
        }

        $item->update($attributes);

        $newHash = $item->getHash();

        if ($newHash != $itemHash) {
            $this->forget($itemHash);

            if ($this->has($newHash)) {
                $existingQty = $this->get($newHash)->getQuantity();
                $attributes = array_merge($attributes, ['quantity' => $item->getQuantity() + $existingQty]);
                $item = $this->updateItem($newHash, $attributes, $withEvent);
            } else {
                $this->put($newHash, $item);
            }
        }

        if ($withEvent) {
            $this->fireEvent('cart.item.updated', [$item]);
        }

        return $item;
    }

    /**
     * Remove an item from this container.
     *
     * @param  string  $itemHash  The unique identifier of item
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws InvalidHashException
     * @throws UnknownCreatorException
     */
    public function removeItem(string $itemHash, bool $withEvent = true): static
    {
        $item = $this->getItem($itemHash);

        if ($withEvent) {
            $event = $this->fireEvent('cart.item.removing', [$item]);

            if ($event === false) {
                return $this;
            }
        }

        $cart = $item->getCart();
        $this->forget($itemHash);

        if ($withEvent) {
            $this->fireEvent('cart.item.removed', [$itemHash, clone $cart]);
        }

        return $this;
    }

    /**
     * Clear all items in this container.
     *
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws UnknownCreatorException
     */
    public function clearItems(bool $withEvent = true): static
    {
        $cart = $this->getCreator();

        if ($withEvent) {
            $event = $this->fireEvent('cart.item.clearing', [$cart]);

            if ($event === false) {
                return $this;
            }
        }

        $this->forgetAll();

        if ($withEvent) {
            $this->fireEvent('cart.item.cleared', [$cart]);
        }

        return $this;
    }

    /**
     * Get an item in this container by given hash.
     *
     * @param  string  $itemHash  The unique identifier of item
     *
     * @throws InvalidHashException
     */
    public function getItem(string $itemHash): Item
    {
        if (! $this->has($itemHash)) {
            $this->throwInvalidHashException($itemHash);
        }

        return $this->get($itemHash);
    }

    /**
     * Get all items in this container that match the given filter.
     *
     * @param  mixed|null  $filter  The search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function getItems(mixed $filter = null, bool $complyAll = true): array
    {
        // If there is no filter, return all items
        if (is_null($filter)) {
            return $this->all();
        }

        // If filter is a closure
        if ($filter instanceof \Closure) {
            return $this->filter($filter)->all();
        }

        // If filter is an array
        if (is_array($filter)) {
            // If filter is not an associative array
            if (! isAssocArray($filter)) {
                $filtered = $this->filter(function ($item) use ($filter) {
                    return in_array($item->getHash(), $filter);
                });

                return $filtered->all();
            }

            // If filter is an associative
            if (! $complyAll) {
                $filtered = $this->filter(function ($item) use ($filter) {
                    $intersects = array_intersect_assoc_recursive($item->getFilterValues(), $filter);

                    return ! empty($intersects);
                });
            } else {
                $filtered = $this->filter(function ($item) use ($filter) {
                    $diffs = array_diff_assoc_recursive($item->getFilterValues(), $filter);

                    return empty($diffs);
                });
            }

            return $filtered->all();
        }

        return [];
    }

    /**
     * Count the number of items in this container that match the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function countItems(mixed $filter = null, bool $complyAll = true): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        return count($this->getItems($filter, $complyAll));
    }

    /**
     * Count the quantities of all items in this container that match the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function sumQuantity(mixed $filter = null, bool $complyAll = true): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        $allItems = $this->getItems($filter, $complyAll);

        return array_reduce($allItems, function ($carry, $item) {
            return $carry + $item->getQuantity();
        }, 0);
    }

    /**
     * Sum the subtotal of all items in this container that match the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function sumSubtotal(mixed $filter = null, bool $complyAll = true): float
    {
        if ($this->isEmpty()) {
            return 0;
        }

        $allItems = $this->getItems($filter, $complyAll);

        return array_reduce($allItems, function ($carry, $item) {
            return $carry + $item->getSubtotal();
        }, 0);
    }

    /**
     * Sum the taxable number of all items in this container that match the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function sumTaxableAmount(mixed $filter = null, bool $complyAll = true): float
    {
        if ($this->isEmpty()) {
            return 0;
        }

        $allItems = $this->getItems($filter, $complyAll);

        return array_reduce($allItems, function ($carry, $item) {
            return $carry + $item->getTaxableAmount();
        }, 0);
    }
}
