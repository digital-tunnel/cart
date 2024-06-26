<?php

namespace DigitalTunnel\Cart;

use DigitalTunnel\Cart\Contracts\CartNode;
use DigitalTunnel\Cart\Contracts\UseCartable;
use DigitalTunnel\Cart\Exceptions\InvalidArgumentException;
use DigitalTunnel\Cart\Exceptions\InvalidAssociatedException;
use DigitalTunnel\Cart\Exceptions\InvalidModelException;
use DigitalTunnel\Cart\Exceptions\UnknownCreatorException;
use DigitalTunnel\Cart\Traits\CanApplyAction;
use DigitalTunnel\Cart\Traits\CanBeCartNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The Item class.
 *
 *
 * @author  Digital Tunnel <hey@digitaltunnel.net>
 */
class Item implements CartNode
{
    use CanApplyAction;
    use CanBeCartNode;

    /**
     * The attributes of item.
     *
     * @var array
     */
    protected $attributes = [
        'associated_class' => null,
        'id' => null,
        'title' => null,
        'quantity' => 1,
        'price' => 0,
        'taxable' => true,
        'options' => [],
        'extra_info' => [],
    ];

    /**
     * The name of the accepted class is the creator.
     */
    protected array $acceptedCreators = [
        ItemsContainer::class,
    ];

    /**
     * Stores applied actions.
     */
    protected Collection $appliedActions;

    /**
     * Indicates whether this item belongs to a commercial cart.
     */
    protected bool $inCommercialCart = false;

    /**
     * Indicates whether this item belongs to a taxable cart.
     */
    protected bool $enabledBuiltinTax = false;

    /**
     * The constructor.
     *
     * @param  array  $attributes  The item attributes
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $attributes = [])
    {
        // Store the creator
        $this->storeCreator(0, function ($creator, $caller) {
            $cart = $creator->getCreator();
            $this->inCommercialCart = $cart->isCommercialCart();
            $this->enabledBuiltinTax = $cart->isEnabledBuiltinTax();
        });

        // Validate and initialize properties
        $this->initAttributes($attributes);
    }

    /**
     * Update information of cart item.
     *
     * @param  array  $attributes  The new attributes
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws UnknownCreatorException
     * @throws InvalidArgumentException
     */
    public function update(array $attributes = [], bool $withEvent = true): static
    {
        // Determines the caller that called this method
        $caller = getCaller();
        $callerClass = Arr::get($caller, 'class');
        $creator = $this->getCreator();

        // If the caller is not the creator of this instance
        if ($callerClass !== get_class($creator)) {
            return $creator->updateItem($this->getHash(), $attributes, $withEvent);
        }

        // Filter the allowed attributes to be updated
        if ($this->inCommercialCart) {
            $attributes = Arr::only($attributes, ['title', 'quantity', 'price', 'taxable', 'options', 'extra_info']);
        } else {
            $attributes = Arr::only($attributes, ['title', 'extra_info']);
        }

        // Validate input
        $this->validate($attributes);

        // Stores input into attributes
        $this->setAttributes($attributes);

        return $this;
    }

    /**
     * Get details of the item as a collection.
     *
     * @param  bool  $withActions  Include details of applied actions in the result
     */
    public function getDetails(bool $withActions = true): Details
    {
        $details = [
            'hash' => $this->getHash(),
            'associated_class' => $this->getAssociatedClass(),
            'id' => $this->getId(),
            'title' => $this->getTitle(),
        ];

        if ($this->inCommercialCart) {
            $details['quantity'] = $this->getQuantity();
            $details['price'] = $this->getPrice();
            $details['taxable'] = $this->isTaxable();
            $details['total_price'] = $this->getTotalPrice();
            $details['actions_count'] = $this->countActions();
            $details['actions_amount'] = $this->sumActionsAmount();
            $details['subtotal'] = $this->getSubtotal();

            if ($this->enabledBuiltinTax) {
                $details['taxable_amount'] = $this->getTaxableAmount();
            }

            $details['options'] = new Details($this->getOptions());

            if ($withActions) {
                $details['applied_actions'] = $this->getActionsContainer()->getDetails();
            }
        }

        $details['extra_info'] = new Details($this->getExtraInfo());

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
     * Get the unique identifier of item in the cart.
     */
    public function getHash(): string
    {
        $id = $this->attributes['id'];
        $price = $this->attributes['price'];
        $associatedClass = $this->attributes['associated_class'];
        $options = $this->attributes['options'];

        ksort_recursive($options);

        return 'item_'.md5($id.serialize($price).serialize($associatedClass).serialize($options));
    }

    /**
     * Get the model instance to which this item is associated.
     *
     *
     * @throws InvalidAssociatedException|InvalidModelException
     */
    public function getModel(): ?Model
    {
        $id = $this->attributes['id'];
        $associatedClass = $this->attributes['associated_class'];

        if (! class_exists($associatedClass)) {
            throw new InvalidAssociatedException('The ['.$associatedClass.'] class does not exist.');
        }

        $model = with(new $associatedClass)->findById($id);

        if (! $model) {
            throw new InvalidModelException('The supplied associated model from ['.$associatedClass.'] does not exist.');
        }

        return $model;
    }

    /**
     * Calculate the total price of item based on the quantity and unit price of item.
     */
    public function getToTalPrice(): float
    {
        return $this->attributes['quantity'] * $this->attributes['price'];
    }

    /**
     * Get the subtotal information of item in the cart.
     */
    public function getSubtotal(): float
    {
        return $this->getTotalPrice() + $this->sumActionsAmount();
    }

    /**
     * Calculate taxable amount based on total price and total taxable action amounts.
     */
    public function getTaxableAmount(): float
    {
        if (! $this->enabledBuiltinTax || ! $this->isTaxable()) {
            return 0;
        }

        return $this->getTotalPrice() + $this->sumActionsAmount([
            'rules' => [
                'taxable' => true,
            ],
        ]);
    }

    /**
     * Get the value of one or some options of the item
     * using "dot" notation.
     *
     * @param  null|array|string  $options  The option want to get
     */
    public function getOptions(null|array|string $options = null, mixed $default = null): mixed
    {
        $optionsAttribute = $this->attributes['options'];

        if (is_null($options)) {
            return $optionsAttribute;
        }

        if (is_array($options)) {
            return Arr::only($optionsAttribute, $options);
        }

        return Arr::get($optionsAttribute, $options, $default);
    }

    /**
     * Determines whether this is a taxable item.
     */
    public function isTaxable(): bool
    {
        return $this->attributes['taxable'];
    }

    /**
     * Get Item attributes
     */
    public function getAttributes(): array
    {

        return $this->attributes;
    }

    /**
     * Initialize attributes for cart item.
     *
     * @param  array  $attributes  The cart item attributes
     * @return $this;
     *
     * @throws InvalidArgumentException
     */
    protected function initAttributes(array $attributes = []): static
    {
        // Disallow the associated_class key in the input attributes
        unset($attributes['associated_class']);

        // If UseCartable exists in the input attributes
        if (($model = Arr::get($attributes, 'model')) instanceof UseCartable) {
            $exceptAttrs = array_values(array_intersect(['title', 'price'], array_keys($attributes)));
            $attributes = array_merge($attributes, $this->parseUseCartable($model, $exceptAttrs));
        }

        // Filter the attributes that allow initialization
        if ($this->inCommercialCart) {
            $attributes = Arr::only($attributes, ['id', 'title', 'quantity', 'price', 'taxable', 'options', 'extra_info', 'associated_class']);
        } else {
            $attributes = Arr::only($attributes, ['id', 'title', 'extra_info', 'associated_class']);
            $attributes['taxable'] = false;
        }

        // Add the missing attributes with default attributes
        $attributes = array_merge($this->attributes, $attributes);

        // Validate the attributes
        $this->validate($attributes);

        // Stores the input into attributes
        $this->setAttributes($attributes);

        // Creates the action container
        $this->appliedActions = new ActionsContainer;

        return $this;
    }

    /**
     * Parse data from UseCartable model to retrieve attributes.
     *
     * @param  object  $model  The UseCartable model
     * @param  array  $except  The attrobutes will be excepted
     */
    protected function parseUseCartable(object $model, array $except = []): array
    {
        $attributes = [
            'id' => $model->getUseCartableId(),
            'title' => $model->getUseCartableTitle(),
            'price' => $model->getUseCartablePrice(),
            'associated_class' => get_class($model),
        ];

        foreach ($except as $attribute) {
            unset($attributes[$attribute]);
        }

        return $attributes;
    }

    /**
     * Set value for the attributes of this instance.
     */
    protected function setAttributes(array $attributes = []): void
    {
        foreach ($attributes as $attribute => $value) {
            switch ($attribute) {
                case 'quantity':
                    $this->setQuantity($value);
                    break;

                case 'price':
                    $this->setPrice($value);
                    break;

                case 'options':
                    $this->setOptions($value);
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
     * Set value for the quantity attribute.
     */
    protected function setQuantity(mixed $value): void
    {
        $this->attributes['quantity'] = intval($value);
    }

    /**
     * Set value for the price attribute.
     */
    protected function setPrice(mixed $value): void
    {
        $this->attributes['price'] = floatval($value);
    }

    /**
     * Set value for the option attribute.
     */
    protected function setOptions(array $options = []): void
    {
        if (empty($options)) {
            $this->attributes['options'] = [];
        }

        foreach ($options as $key => $value) {
            $key = trim($key, '.');

            if (! empty($key)) {
                Arr::set($this->attributes['options'], $key, $value);
            }
        }
    }

    /**
     * Return the action container.
     */
    protected function getActionsContainer(): ActionsContainer|Collection
    {
        if ($this->inCommercialCart) {
            return $this->appliedActions;
        }

        return new ActionsContainer;
    }

    /**
     * Indicates whether this instance can apply cart.
     */
    protected function canApplyAction(): bool
    {
        if ($this->inCommercialCart) {
            return true;
        }

        return false;
    }

    /**
     * Validate item attributes.
     *
     * @param  array  $attributes  The item attributes
     *
     * @throws InvalidArgumentException
     */
    protected function validate(array $attributes = []): void
    {
        if (array_key_exists('id', $attributes) && empty($attributes['id'])) {
            throw new InvalidArgumentException('The id attribute of the item is required.');
        }

        if (array_key_exists('title', $attributes) && (! is_string($attributes['title']) || empty($attributes['title']))) {
            throw new InvalidArgumentException('The title attribute of the item is required.');
        }

        if (array_key_exists('quantity', $attributes) && (! is_numeric($attributes['quantity']) || intval($attributes['quantity']) < 1)) {
            throw new InvalidArgumentException('The quantity attribute of the item is required and must be an integer type greater than 1.');
        }

        if (array_key_exists('price', $attributes) && (! is_numeric($attributes['price']) || floatval($attributes['price']) < 0)) {
            throw new InvalidArgumentException('The price attribute of the item must be an float type greater than or equal to 0.');
        }

        if (array_key_exists('taxable', $attributes) && ! is_bool($attributes['taxable'])) {
            throw new InvalidArgumentException('The taxable attribute of the item must be a boolean type.');
        }

        if (array_key_exists('options', $attributes) && ! is_array($attributes['options'])) {
            throw new InvalidArgumentException('The options attribute of the item must be an array.');
        }

        if (array_key_exists('extra_info', $attributes) && ! is_array($attributes['extra_info'])) {
            throw new InvalidArgumentException('The extra_info attribute of the item must be an array.');
        }
    }
}
