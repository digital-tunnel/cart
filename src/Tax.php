<?php

namespace DigitalTunnel\Cart;

use DigitalTunnel\Cart\Contracts\CartNode;
use DigitalTunnel\Cart\Exceptions\InvalidArgumentException;
use DigitalTunnel\Cart\Exceptions\UnknownCreatorException;
use DigitalTunnel\Cart\Traits\CanBeCartNode;
use Illuminate\Support\Arr;

/**
 * The Tax class.
 */
class Tax implements CartNode
{
    use CanBeCartNode;

    /**
     * The attributes of tax.
     *
     * @var array
     */
    protected $attributes = [
        'id' => null,
        'title' => null,
        'rate' => null,
        'extra_info' => [],
    ];

    /**
     * The name of the accepted class is the creator.
     */
    protected array $acceptedCreators = [
        TaxesContainer::class,
    ];

    /**
     * Create a new tax.
     *
     * @param  array  $attributes  The tax attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        // Stores the creator
        $this->storeCreator();

        // Initialize attributes
        $this->initAttributes($attributes);
    }

    /**
     * Update attributes of this tax instance.
     *
     * @param  array  $attributes  The new attributes
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws UnknownCreatorException
     */
    public function update(array $attributes = [], bool $withEvent = true): static
    {
        // Determines the caller that called this method
        $caller = getCaller();
        $callerClass = Arr::get($caller, 'class');
        $creator = $this->getCreator();

        // If the caller is not the creator of this instance
        if ($callerClass !== get_class($creator)) {
            return $creator->updateTax($this->getHash(), $attributes, $withEvent);
        }

        // Filter the allowed attributes to be updated
        $attributes = Arr::only($attributes, ['title', 'rate', 'extra_info']);

        // Validate the input
        $this->validate($attributes);

        // Stores the input into attributes
        $this->setAttributes($attributes);

        return $this;
    }

    /**
     * Get details of the tax as a collection.
     */
    public function getDetails(): Details
    {
        $details = [
            'hash' => $this->getHash(),
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'rate' => $this->getRate(),
            'amount' => $this->getAmount(),
            'extra_info' => new Details($this->getExtraInfo()),
        ];

        return new Details($details);
    }

    /**
     * Determines which values to filter.
     */
    public function getFilterValues(): array
    {
        return array_merge([
            'hash' => $this->getHash(),
        ], $this->attributes);
    }

    /**
     * Get the unique identifier of the tax.
     */
    public function getHash(): string
    {
        return 'tax_'.md5($this->attributes['id']);
    }

    /**
     * Get the calculated amount of this tax.
     */
    public function getAmount(): float
    {
        try {
            $cartInstance = $this->getCart();
            $taxableAmount = $cartInstance->getTaxableAmount();

            return $taxableAmount * ($this->getRate() / 100);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Initialize the attributes.
     *
     * @param  array  $attributes  The tax attributes
     * @return $this
     */
    protected function initAttributes(array $attributes = []): static
    {
        // Define default value for attributes
        $this->attributes['rate'] = $this->getConfig('default_tax_rate', 0);

        // Add the missing attributes with default attributes
        $attributes = array_merge($this->attributes, Arr::only($attributes, array_keys($this->attributes)));

        // Validate the input
        $this->validate($attributes);

        // Stores the input into attributes
        $this->setAttributes($attributes);

        return $this;
    }

    /**
     * Set value for the attributes of this instance.
     */
    protected function setAttributes(array $attributes = []): void
    {
        foreach ($attributes as $attribute => $value) {
            switch ($attribute) {
                case 'rate':
                    $this->setRate($value);
                    break;

                case 'extra_info':
                    $this->setExtraInfo($value);
                    break;

                default:
                    $this->attributes[$attribute] = $value;
                    break;
            }
        }
    }

    /**
     * Set value for the rate attribute.
     */
    protected function setRate(mixed $value): void
    {
        $this->attributes['rate'] = floatval($value);
    }

    /**
     * Validate the tax attributes.
     *
     * @param  array  $attributes  The tax attributes
     *
     * @throws InvalidArgumentException
     */
    protected function validate(array $attributes): void
    {
        if (array_key_exists('id', $attributes) && empty($attributes['id'])) {
            throw new InvalidArgumentException('The id attribute of the tax is required.');
        }

        if (array_key_exists('title', $attributes) && (! is_string($attributes['title']) || empty($attributes['title']))) {
            throw new InvalidArgumentException('The title attribute of the tax is required and must be a non-empty string.');
        }

        if (array_key_exists('rate', $attributes) && ! preg_match('/^\d+(\.{0,1}\d+)?$/', $attributes['rate'])) {
            throw new InvalidArgumentException('The rate attribute of the tax is required and must be a float number greater than or equal to 0.');
        }

        if (array_key_exists('extra_info', $attributes) && ! is_array($attributes['extra_info'])) {
            throw new InvalidArgumentException('The extra_info attribute of the tax must be an array.');
        }
    }
}
