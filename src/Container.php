<?php

namespace DigitalTunnel\Cart;

use DigitalTunnel\Cart\Exceptions\InvalidHashException;
use DigitalTunnel\Cart\Traits\BackToCreator;
use DigitalTunnel\Cart\Traits\CollectionForgetAll;
use DigitalTunnel\Cart\Traits\FireEvent;
use Illuminate\Support\Collection;

/**
 * The Container class.
 */
class Container extends Collection
{
    use BackToCreator;
    use CollectionForgetAll;
    use FireEvent;

    /**
     * Create a new container.
     *
     * @param  mixed  $items
     * @return void
     */
    public function __construct($items = [])
    {
        $this->storeCreator();

        parent::__construct($items);
    }

    /**
     * Get details information of this container as a collection.
     */
    public function getDetails(): Details
    {
        $details = new Details;
        $allActions = $this->all();

        foreach ($allActions as $key => $value) {
            $details->put($key, $value->getDetails());
        }

        return $details;
    }

    /**
     * Check for the existence of the hash string.
     *
     * @param  string  $hash  The hash string
     *
     * @throws InvalidHashException
     */
    protected function throwInvalidHashException(string $hash): void
    {
        throw new InvalidHashException('Could not find any action with hash '.$hash.' in the actions container.');
    }
}
