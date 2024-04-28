<?php

namespace DigitalTunnel\Cart\Traits;

use DigitalTunnel\Cart\Cart;
use DigitalTunnel\Cart\Exceptions\UnknownCreatorException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The CanBeCartNode traits.
 */
trait CanBeCartNode
{
    use BackToCreator { getCreator as protected; }

    /**
     * Dynamically handle calls to the class.
     *
     * @param  string  $method  The method name
     * @param  array  $parameters  The input parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (strlen($method) > 3 && substr($method, 0, 3) == 'get') {
            $attribute = Str::snake(substr($method, 3));

            if (array_key_exists($attribute, $this->attributes)) {
                return $this->attributes[$attribute];
            }
        }

        throw new \BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }

    /**
     * Check if the parent node can be found.
     *
     *
     * @throws UnknownCreatorException
     */
    public function hasKnownParentNode(): bool
    {
        return $this->hasKnownCreator() && $this->getCreator()->hasKnownCreator();
    }

    /**
     * Get parent node instance that this instance is belong to.
     *
     *
     * @throws UnknownCreatorException
     */
    public function getParentNode(): object
    {
        return $this->getCreator()->getCreator();
    }

    /**
     * Get the cart instance that this node belongs to.
     *
     *
     * @throws UnknownCreatorException
     */
    public function getCart(): Cart
    {
        $parentNode = $this->getParentNode();

        if ($parentNode instanceof Cart) {
            return $parentNode;
        }

        return $parentNode->getCart();
    }

    /**
     * Get config of the cart instance thet this node belongs to.
     *
     * @param  null|string  $name  The config name
     * @param  mixed  $default  The return value if the config does not exist
     */
    public function getConfig(?string $name = null, mixed $default = null): mixed
    {
        try {
            return $this->getCart()->getConfig($name, $default);
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Get the cart node's original attribute values.
     *
     * @param  null|string  $attribute  The attribute
     * @param  mixed  $default  The return value if attribute does not exist
     */
    public function getOriginal(?string $attribute = null, mixed $default = null): mixed
    {
        if ($attribute) {
            return Arr::get($this->attributes, $attribute, $default);
        }

        return $this->attributes;
    }

    /**
     * Dynamic attribute getter.
     *
     * @param  null|string  $attribute  The attribute
     * @param  mixed  $default  The return value if attribute does not exist
     */
    public function get(?string $attribute, mixed $default = null): mixed
    {
        if (! empty($attribute)) {
            $getMethod = Str::camel('get_'.$attribute);

            if (method_exists($this, $getMethod)) {
                $methodReflection = new \ReflectionMethod($this, $getMethod);
                $isMethodPublic = $methodReflection->isPublic();
                $numberOfRequiredParams = $methodReflection->getNumberOfRequiredParameters();

                if ($isMethodPublic && $numberOfRequiredParams == 0) {
                    return $this->{$getMethod}();
                }
            }

            return $this->getOriginal($attribute, $default);
        }

        return $default;
    }

    /**
     * Get the value of one or some extended information of the current node
     * using "dot" notation.
     *
     * @param  null|array|string  $information  The information want to get
     */
    public function getExtraInfo(null|array|string $information = null, mixed $default = null): mixed
    {
        $extraInfo = $this->attributes['extra_info'];

        if (is_null($information)) {
            return $extraInfo;
        }

        if (is_array($information)) {
            return Arr::only($extraInfo, $information);
        }

        return Arr::get($extraInfo, $information, $default);
    }

    /**
     * Set the value for an attribute of this node.
     *
     * @param  string  $attribute  The attribute want to set
     * @param  mixed  $value  The value of attribute
     */
    protected function setAttribute(string $attribute, mixed $value): void
    {
        if (! empty($attribute)) {
            $setter = Str::camel('set_'.$attribute);

            if (method_exists($this, $setter)) {
                $this->{$setter}($value);
            } else {
                $this->attributes[$attribute] = $value;
            }
        }
    }

    /**
     * Set value for the attributes of this node.
     */
    protected function setAttributes(array $attributes = []): void
    {
        foreach ($attributes as $attribute => $value) {
            $this->setAttribute($attribute, $value);
        }
    }

    /**
     * Set value for extended information of the current node.
     * Can use "dot" notation with each information.
     */
    protected function setExtraInfo(array $informations = []): void
    {
        if (empty($informations)) {
            $this->attributes['extra_info'] = [];
        }

        foreach ($informations as $key => $value) {
            $key = trim($key, '.');

            if (! empty($key)) {
                Arr::set($this->attributes['extra_info'], $key, $value);
            }
        }
    }
}
