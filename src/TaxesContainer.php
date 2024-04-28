<?php

namespace DigitalTunnel\Cart;

use Closure;
use DigitalTunnel\Cart\Exceptions\InvalidHashException;
use DigitalTunnel\Cart\Exceptions\UnknownCreatorException;

/**
 * The TaxesContainer class
 * This is a container used to hold tax items.
 */
class TaxesContainer extends Container
{
    /**
     * The name of the accepted class is the creator.
     */
    protected array $acceptedCreators = [
        Cart::class,
    ];

    /**
     * Add a tax instance into this container.
     *
     * @param  array  $attributes  The tax attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function addTax(array $attributes = [], bool $withEvent = true): ?Tax
    {
        $tax = new Tax($attributes);

        if ($withEvent) {
            $event = $this->fireEvent('cart.tax.applying', [$tax]);

            if ($event === false) {
                return null;
            }
        }

        $taxHash = $tax->getHash();

        if ($this->has($taxHash)) {
            // If the tax is already exists in this container, we will update that tax
            return $this->updateTax($taxHash, $attributes, $withEvent);
        }

        // If the tax doesn't exist yet, put it to container
        $this->put($taxHash, $tax);

        if ($withEvent) {
            $this->fireEvent('cart.tax.applied', [$tax]);
        }

        return $tax;
    }

    /**
     * Update a tax in taxes container.
     *
     * @param  string  $taxHash  The unique identifier of tax
     * @param  array  $attributes  The new attributes
     * @param  bool  $withEvent  Enable firing the event
     *
     * @throws InvalidHashException|Exceptions\UnknownCreatorException
     */
    public function updateTax(string $taxHash, array $attributes = [], bool $withEvent = true): ?Tax
    {
        $tax = $this->getTax($taxHash);

        if ($withEvent) {
            $event = $this->fireEvent('cart.tax.updating', [&$attributes, $tax]);

            if ($event === false) {
                return null;
            }
        }

        $tax->update($attributes);

        $newHash = $tax->getHash();

        if ($newHash != $taxHash) {
            $this->forget($taxHash);
            $this->put($newHash, $tax);
        }

        if ($withEvent) {
            $this->fireEvent('cart.tax.updated', [$tax]);
        }

        return $tax;
    }

    /**
     * Get a tax instance in this container by given hash.
     *
     * @param  string  $taxHash  The unique identifier of tax instance
     *
     * @throws InvalidHashException
     */
    public function getTax(string $taxHash): Tax
    {
        if (! $this->has($taxHash)) {
            $this->throwInvalidHashException($taxHash);
        }

        return $this->get($taxHash);
    }

    /**
     * Get all tax instances in this container that matches the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function getTaxes(mixed $filter = null, bool $complyAll = true): array
    {
        // If there is no filter, return all taxes
        if (is_null($filter)) {
            return $this->all();
        }

        // If filter is a closure
        if ($filter instanceof Closure) {
            return $this->filter($filter)->all();
        }

        // If filter is an array
        if (is_array($filter)) {
            // If filter is not an associative array
            if (! isAssocArray($filter)) {
                $filtered = $this->filter(function ($tax) use ($filter) {
                    return in_array($tax->getHash(), $filter);
                });

                return $filtered->all();
            }

            // If filter is associative
            if (! $complyAll) {
                $filtered = $this->filter(function ($tax) use ($filter) {
                    $intersects = array_intersect_assoc_recursive($tax->getFilterValues(), $filter);

                    return ! empty($intersects);
                });
            } else {
                $filtered = $this->filter(function ($tax) use ($filter) {
                    $diffs = array_diff_assoc_recursive($tax->getFilterValues(), $filter);

                    return empty($diffs);
                });
            }

            return $filtered->all();
        }

        return [];
    }

    /**
     * Remove a tax instance from this container.
     *
     * @param  string  $taxHash  The unique identifier of the tax instance
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws UnknownCreatorException
     * @throws InvalidHashException
     */
    public function removeTax(string $taxHash, bool $withEvent = true): static
    {
        $tax = $this->getTax($taxHash);

        if ($withEvent) {
            $event = $this->fireEvent('cart.tax.removing', [$tax]);

            if ($event === false) {
                return $this;
            }
        }

        $cart = $tax->getCart();
        $this->forget($taxHash);

        if ($withEvent) {
            $this->fireEvent('cart.tax.removed', [$taxHash, clone $cart]);
        }

        return $this;
    }

    /**
     * Remove all tax instances from this container.
     *
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws UnknownCreatorException
     */
    public function clearTaxes(bool $withEvent = true): static
    {
        $cart = $this->getCreator();

        if ($withEvent) {
            $event = $this->fireEvent('cart.tax.clearing', [$cart]);

            if ($event === false) {
                return $this;
            }
        }

        $this->forgetAll();

        if ($withEvent) {
            $this->fireEvent('cart.tax.cleared', [$cart]);
        }

        return $this;
    }

    /**
     * Count all tax instances in this container that match the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function countTaxes(mixed $filter = null, bool $complyAll = true): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        return count($this->getTaxes($filter, $complyAll));
    }

    /**
     * Get the sum of tax rate for all tax instances in this container that match the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function sumRate(mixed $filter = null, bool $complyAll = true): float
    {
        if ($this->isEmpty()) {
            return 0;
        }

        $allTaxes = $this->getTaxes($filter, $complyAll);

        return array_reduce($allTaxes, function ($carry, $tax) {
            return $carry + $tax->getRate();
        }, 0);
    }

    /**
     * Get the sum of tax amount for all tax instances in this container that match the given filter.
     *
     * @param  mixed|null  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function sumAmount(mixed $filter = null, bool $complyAll = true): float
    {
        if ($this->isEmpty()) {
            return 0;
        }

        $allTaxes = $this->getTaxes($filter, $complyAll);

        return array_reduce($allTaxes, function ($carry, $tax) {
            return $carry + $tax->getAmount();
        }, 0);
    }
}
