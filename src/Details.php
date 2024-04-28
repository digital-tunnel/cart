<?php

namespace DigitalTunnel\Cart;

use DigitalTunnel\Cart\Exceptions\InvalidAssociatedException;
use DigitalTunnel\Cart\Exceptions\InvalidModelException;
use Illuminate\Support\Collection;
use Illuminate\Support\HigherOrderCollectionProxy;

/**
 * The Details class.
 */
class Details extends Collection
{
    /**
     * Dynamically access item from a collection.
     *
     * @param  string  $key
     * @return mixed
     *
     * @throws InvalidAssociatedException
     * @throws InvalidModelException
     */
    public function __get($key)
    {
        if (class_exists('\Illuminate\Support\HigherOrderCollectionProxy') && in_array($key, static::$proxies)) {
            return new HigherOrderCollectionProxy($this, $key);
        }

        return $this->get($key);
    }

    /**
     * Determine if an item exists in the collection by key.
     */
    public function has(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (! array_key_exists($value, $this->items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get an item from the collection by key.
     *
     *
     * @throws InvalidAssociatedException
     * @throws InvalidModelException
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        if ($key === 'model') {
            if ($this->has(['id', 'associated_class'])) {
                $id = $this->get('id');
                $associatedClass = $this->get('associated_class');

                if (! class_exists($associatedClass)) {
                    throw new InvalidAssociatedException('The ['.$associatedClass.'] class does not exist.');
                }

                $model = with(new $associatedClass)->findById($id);

                if (! $model) {
                    throw new InvalidModelException('The supplied associated model from ['.$associatedClass.'] does not exist.');
                }

                return $model;
            }

            return $default;
        }

        return parent::get($key, $default);
    }
}
