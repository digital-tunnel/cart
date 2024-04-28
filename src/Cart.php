<?php

namespace DigitalTunnel\Cart;

use DigitalTunnel\Cart\Exceptions\InvalidAssociatedException;
use DigitalTunnel\Cart\Exceptions\InvalidCartNameException;
use DigitalTunnel\Cart\Exceptions\InvalidHashException;
use DigitalTunnel\Cart\Exceptions\InvalidModelException;
use DigitalTunnel\Cart\Exceptions\UnknownCreatorException;
use DigitalTunnel\Cart\Traits\CanApplyAction;
use DigitalTunnel\Cart\Traits\FireEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The Cart class.
 *
 * Used to manage the cart data in session.
 */
class Cart
{
    use CanApplyAction;
    use FireEvent;

    /**
     * The root session name.
     */
    protected string $rootSessionName;

    /**
     * The default cart name.
     */
    protected string $defaultCartName = 'default';

    /**
     * The name of current cart instance.
     */
    protected string $cartName;

    /**
     * Create cart instance.
     *
     * @throws InvalidCartNameException
     */
    public function __construct()
    {
        $this->rootSessionName = '_'.md5(config('app.name').__NAMESPACE__);
        $defaultCartName = config('cart.default_cart_name');

        if (is_string($defaultCartName) && ! empty($defaultCartName)) {
            $this->defaultCartName = $defaultCartName;
        }

        $this->name();
    }

    /**
     * Select a cart to work with.
     *
     * @param  null|string  $name  The cart name
     * @return $this
     *
     * @throws InvalidCartNameException
     */
    public function name(?string $name = null): static
    {
        $this->cartName = $this->rootSessionName.'.'.$this->standardizeCartName($name);

        $this->initSessions();

        return $this;
    }

    /**
     * Create a cart instance with the specific name.
     *
     * @param  null|string  $name  The cart name
     * @return $this
     *
     * @throws InvalidCartNameException
     */
    public function newInstance(?string $name = null): static
    {
        $name = $this->standardizeCartName($name);

        if ($name === $this->getName()) {
            return clone $this;
        }

        $newInstance = new static;

        $newInstance->name($name);

        return $newInstance;
    }

    /**
     * Determines whether this cart has been grouped.
     */
    public function hasBeenGrouped(): bool
    {
        return Str::contains($this->getName(), ['.']);
    }

    /**
     * Determines whether this cart is in the specific group.
     *
     * @param  string|null  $groupName  The specific group name
     */
    public function isInGroup(?string $groupName): bool
    {
        if (is_null($groupName)) {
            return false;
        }

        $currentGroupName = $this->getGroupName();

        if (is_null($currentGroupName)) {
            return false;
        }

        return Str::startsWith($currentGroupName, $groupName);
    }

    /**
     * Get the group name of the cart.
     */
    public function getGroupName(): ?string
    {
        if (! $this->hasBeenGrouped()) {
            return null;
        }

        $splitParts = explode('.', $this->getName());
        array_pop($splitParts);

        return implode('.', $splitParts);
    }

    /**
     * Get the current cart name.
     */
    public function getName(): string
    {
        return substr($this->cartName, strlen($this->rootSessionName) + 1);
    }

    /**
     * Get config of this cart.
     *
     * @param  null|string  $name  The config name
     * @param  mixed  $default  The return value if the config does not exist
     */
    public function getConfig(?string $name = null, mixed $default = null): mixed
    {
        if ($name) {
            return session($this->getSessionPath('config.'.$name), $default);
        }

        return session($this->getSessionPath('config'), $default);
    }

    /**
     * Change whether the cart status is used for commercial or not.
     *
     * @return $this
     */
    public function useForCommercial(bool $status = true): static
    {
        if ($this->isEmpty()) {
            $status = (bool) $status;

            $this->setConfig('use_for_commercial', $status);

            if ($status) {
                session()->put($this->getSessionPath('applied_actions'), new ActionsContainer);

                if ($this->getConfig('use_builtin_tax')) {
                    session()->put($this->getSessionPath('applied_taxes'), new TaxesContainer);
                } else {
                    session()->forget($this->getSessionPath('applied_taxes'));
                }
            } else {
                session()->forget($this->getSessionPath('applied_actions'));
                session()->forget($this->getSessionPath('applied_taxes'));
            }
        }

        return $this;
    }

    /**
     * Enable or disable the built-in tax system for the cart.
     * This is only possible if the cart is empty.
     *
     * @return $this
     */
    public function useBuiltinTax(bool $status = true): static
    {
        if ($this->isEmpty()) {
            $status = (bool) $status;

            $this->setConfig('use_builtin_tax', $status);

            if ($status && $this->getConfig('use_for_commercial', false)) {
                session()->put($this->getSessionPath('applied_taxes'), new TaxesContainer);
            } else {
                session()->forget($this->getSessionPath('applied_taxes'));
            }
        }

        return $this;
    }

    /**
     * Set default action rules for the cart.
     * This is only possible if the cart is empty.
     *
     * @param  array  $rules  The default action rules
     */
    public function setDefaultActionRules(array $rules = []): static
    {
        if ($this->isEmpty()) {
            $this->setConfig('default_action_rules', $rules);
        }

        return $this;
    }

    /**
     * Set action groups order for the cart.
     *
     * @param  array  $order  The action groups order
     * @return $this
     */
    public function setActionGroupsOrder(array $order = []): static
    {
        $this->setConfig('action_groups_order', $order);

        return $this;
    }

    /**
     * Determines if the cart is empty.
     *
     * @return bool returns true if the cart has no items, no taxes,
     *              and no action has been applied yet
     */
    public function isEmpty(): bool
    {
        return $this->hasNoItems() && $this->hasNoActions() && $this->hasNoTaxes();
    }

    /**
     * Determines if the cart has no items.
     */
    public function hasNoItems(): bool
    {
        return $this->getItemsContainer()->isEmpty();
    }

    /**
     * Determines if the cart has no actions.
     */
    public function hasNoActions(): bool
    {
        return $this->getActionsContainer()->isEmpty();
    }

    /**
     * Determines if the cart has no taxes.
     */
    public function hasNoTaxes(): bool
    {
        return $this->getTaxesContainer()->isEmpty();
    }

    /**
     * Determines if current cart is used for commcercial.
     */
    public function isCommercialCart(): bool
    {
        return $this->getConfig('use_for_commercial', false);
    }

    /**
     * Determines if current cart is enabled built-in tax system.
     */
    public function isEnabledBuiltinTax(): bool
    {
        if (! $this->getConfig('use_for_commercial', false)) {
            return false;
        }

        return $this->getConfig('use_builtin_tax', false);
    }

    /**
     * Remove cart from session.
     *
     * @param  bool  $withEvent  Enable firing the event
     */
    public function destroy(bool $withEvent = true): bool
    {
        if ($withEvent) {
            $eventResponse = $this->fireEvent('cart.destroying', clone $this);

            if ($eventResponse === false) {
                return false;
            }
        }

        session()->forget($this->getSessionPath());

        if ($withEvent) {
            $this->fireEvent('cart.destroyed');
        }

        return true;
    }

    /**
     * Add an item into the item container.
     *
     * @param  array  $attributes  The item attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function addItem(array $attributes = [], bool $withEvent = true): ?Item
    {
        return $this->getItemsContainer()->addItem($attributes, $withEvent);
    }

    /**
     * Update an item in the item container.
     *
     * @param  string  $itemHash  The unique identifier of the item
     * @param  array|int  $attributes  New quantity of item or array of new attributes to update
     * @param  bool  $withEvent  Enable firing the event
     */
    public function updateItem(string $itemHash, array|int $attributes = [], bool $withEvent = true): ?Item
    {
        return $this->getItemsContainer()->updateItem($itemHash, $attributes, $withEvent);
    }

    /**
     * Remove an item out of the item container.
     *
     * @param  string  $itemHash  The unique identifier of the item
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     */
    public function removeItem(string $itemHash, bool $withEvent = true): static
    {
        $this->getItemsContainer()->removeItem($itemHash, $withEvent);

        return $this;
    }

    /**
     * Delete all items in the item container.
     *
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws UnknownCreatorException
     */
    public function clearItems(bool $withEvent = true): static
    {
        $this->getItemsContainer()->clearItems($withEvent);

        return $this;
    }

    /**
     * Get an item in the item container.
     *
     * @param  string  $itemHash  The unique identifier of the item
     *
     * @throws InvalidHashException
     */
    public function getItem(string $itemHash): Item
    {
        return $this->getItemsContainer()->getItem($itemHash);
    }

    /**
     * Get all items in the item container that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function getItems(mixed $filter = null, bool $complyAll = true): array
    {
        return $this->getItemsContainer()->getItems($filter, $complyAll);
    }

    /**
     * Count the number of items in the item container that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function countItems(mixed $filter = null, bool $complyAll = true): int
    {
        return $this->getItemsContainer()->countItems($filter, $complyAll);
    }

    /**
     * Sum the quantities of all items in the item container that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function sumItemsQuantity(mixed $filter = null, bool $complyAll = true): int
    {
        return $this->getItemsContainer()->sumQuantity($filter, $complyAll);
    }

    /**
     * Determines if the item exists in the item container.
     *
     * @param  string  $itemHash  The unique identifier of the item
     */
    public function hasItem(string $itemHash): bool
    {
        return $this->getItemsContainer()->has($itemHash);
    }

    /**
     * Set value for one or some extended information of the cart.
     *
     * @param  array|string  $information  The information want to set
     * @param  mixed  $value  The value of information
     * @return $this
     */
    public function setExtraInfo(array|string $information, mixed $value = null): static
    {
        return $this->setGroupExtraInfo($this->getName(), $information, $value);
    }

    /**
     * Get the value of one or some extended information of the cart
     * using "dot" notation.
     *
     * @param  null|array|string  $information  The information want to get
     * @param  mixed  $default  The return value if information does not exist
     */
    public function getExtraInfo(null|array|string $information = null, mixed $default = null): mixed
    {
        return $this->getGroupExtraInfo($this->getName(), $information, $default);
    }

    /**
     * Remove one or some extended information of the cart
     * using "dot" notation.
     *
     * @param  null|array|string  $information  The information want to remove
     * @return $this
     */
    public function removeExtraInfo(null|array|string $information = null): static
    {
        return $this->removeGroupExtraInfo($this->getName(), $information);
    }

    /**
     * Set value for one or some extended information of the group
     * using "dot" notation.
     *
     * @param  string  $groupName  The name of the cart group
     * @param  array|string  $information  The information want to set
     * @param  mixed  $value  The value of information
     * @return $this
     */
    public function setGroupExtraInfo(string $groupName, array|string $information, mixed $value = null): static
    {
        $groupName = trim($groupName, '.');

        if ($groupName) {
            if (! is_array($information)) {
                $information = [
                    $information => $value,
                ];
            }

            foreach ($information as $key => $value) {
                $key = trim($key, '.');

                if (! empty($key)) {
                    session()->put($this->rootSessionName.'.'.$groupName.'.extra_info.'.$key, $value);
                }
            }
        }

        return $this;
    }

    /**
     * Get the value of one or some extended information of the group
     * using "dot" notation.
     *
     * @param  string  $groupName  The name of the cart group
     * @param  null|array|string  $information  The information want to get
     * @param  mixed  $default  The return value if information does not exist
     */
    public function getGroupExtraInfo(string $groupName, null|array|string $information = null, mixed $default = null): mixed
    {
        $groupName = trim($groupName, '.');

        if ($groupName) {
            $extraInfo = session($this->rootSessionName.'.'.$groupName.'.extra_info', []);

            if (is_null($information)) {
                return $extraInfo;
            }

            if (is_array($information)) {
                return Arr::only($extraInfo, $information);
            }

            return Arr::get($extraInfo, $information, $default);
        }

        return $default;
    }

    /**
     * Remove one or some extended information of the group
     * using "dot" notation.
     *
     * @param  string  $groupName  The name of the cart group
     * @param  null|array|string  $information  The information want to remove
     * @return $this
     */
    public function removeGroupExtraInfo(string $groupName, null|array|string $information = null): static
    {
        $groupName = trim($groupName, '.');

        if ($groupName) {
            if (is_null($information)) {
                session()->put($this->rootSessionName.'.'.$groupName.'.extra_info', []);

                return $this;
            }

            $informations = (array) $information;

            foreach ($informations as $key) {
                $key = trim($key, '.');

                if (! empty($key)) {
                    session()->forget($this->rootSessionName.'.'.$groupName.'.extra_info.'.$key);
                }
            }
        }

        return $this;
    }

    /**
     * Add a tax into the tax container of this cart.
     *
     * @param  array  $attributes  The tax attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function applyTax(array $attributes = [], bool $withEvent = true): ?Tax
    {
        if (! $this->isEnabledBuiltinTax()) {
            return null;
        }

        return $this->getTaxesContainer()->addTax($attributes, $withEvent);
    }

    /**
     * Update a tax in the tax container.
     *
     * @param  string  $taxHash  The unique identifire of the tax instance
     * @param  array  $attributes  The new attributes
     * @param  bool  $withEvent  Enable firing the event
     *
     * @throws InvalidHashException
     * @throws UnknownCreatorException
     */
    public function updateTax(string $taxHash, array $attributes = [], bool $withEvent = true): ?Tax
    {
        return $this->getTaxesContainer()->updateTax($taxHash, $attributes, $withEvent);
    }

    /**
     * Get an applied tax in the tax container of this cart.
     *
     * @param  string  $taxHash  The unique identifire of the tax instance
     *
     * @throws InvalidHashException
     */
    public function getTax(string $taxHash): Tax
    {
        return $this->getTaxesContainer()->getTax($taxHash);
    }

    /**
     * Get all tax instances in the tax container of this cart that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function getTaxes(mixed $filter = null, bool $complyAll = true): array
    {
        return $this->getTaxesContainer()->getTaxes($filter, $complyAll);
    }

    /**
     * Count all taxes in the action container that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function countTaxes(mixed $filter = null, bool $complyAll = true): int
    {
        return $this->getTaxesContainer()->countTaxes($filter, $complyAll);
    }

    /**
     * Determines if the tax exists in the tax container of this cart.
     *
     * @param  string  $taxHash  The unique identifier of the tax
     */
    public function hasTax(string $taxHash): bool
    {
        return $this->getTaxesContainer()->has($taxHash);
    }

    /**
     * Remove an applied tax from the tax container of this cart.
     *
     * @param  string  $taxHash  The unique identifier of the tax instance
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws InvalidHashException
     * @throws UnknownCreatorException
     */
    public function removeTax(string $taxHash, bool $withEvent = true): static
    {
        $this->getTaxesContainer()->removeTax($taxHash, $withEvent);

        return $this;
    }

    /**
     * Remove all apllied taxes from the taxes container of this cart.
     *
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     *
     * @throws UnknownCreatorException
     */
    public function clearTaxes(bool $withEvent = true): static
    {
        $this->getTaxesContainer()->clearTaxes($withEvent);

        return $this;
    }

    /**
     * Get the subtotal number of all items in the item container.
     */
    public function getItemsSubtotal(): float
    {
        return $this->getItemsContainer()->sumSubtotal();
    }

    /**
     * Get the sum number of all items subtotal amount and all-actions amount.
     */
    public function getSubtotal(): float
    {
        $enabledActionsAmount = $this->getActionsContainer()->sumAmount();

        return $this->getItemsSubtotal() + $enabledActionsAmount;
    }

    /**
     * Calculate total taxable amounts include the taxable amount of cart and all items.
     */
    public function getTaxableAmount(): float
    {
        if (! $this->isEnabledBuiltinTax()) {
            return 0;
        }

        $itemsTaxableAmount = $this->getItemsContainer()->sumTaxableAmount();
        $cartTaxableAmount = $this->getActionsContainer()->sumAmount([
            'rules' => [
                'taxable' => true,
            ],
        ]);

        return $itemsTaxableAmount + $cartTaxableAmount;
    }

    /**
     * Get the total tax rate applied to the current cart.
     */
    public function getTaxRate(): float
    {
        if (! $this->isEnabledBuiltinTax()) {
            return 0;
        }

        return $this->getTaxesContainer()->sumRate();
    }

    /**
     * Get the total tax amount applied to the current cart.
     */
    public function getTaxAmount(): float
    {
        if (! $this->isEnabledBuiltinTax()) {
            return 0;
        }

        return $this->getTaxesContainer()->sumAmount();
    }

    /**
     * Get the total amount of the current cart.
     */
    public function getTotal(): float
    {
        return $this->getSubtotal() + $this->getTaxAmount();
    }

    /**
     * Get all information of cart as a collection.
     *
     * @param  bool  $withItems  Include details of added items in the result
     * @param  bool  $withActions  Include details of applied actions in the result
     * @param  bool  $withTaxes  Include details of applied taxes in the result
     */
    public function getDetails(bool $withItems = true, bool $withActions = true, bool $withTaxes = true): Details
    {
        $details = new Details;
        $isCommercialCart = $this->isCommercialCart();
        $enabledBuiltinTax = $this->isEnabledBuiltinTax();
        $itemsContainer = $this->getItemsContainer();

        $details->put('type', 'cart');
        $details->put('name', $this->getName());
        $details->put('commercial_cart', $isCommercialCart);
        $details->put('enabled_builtin_tax', $enabledBuiltinTax);
        $details->put('items_count', $this->countItems());
        $details->put('quantities_sum', $this->sumItemsQuantity());

        if ($isCommercialCart) {
            $actionsContainer = $this->getActionsContainer();

            $details->put('items_subtotal', $this->getItemsSubtotal());
            $details->put('actions_count', $this->countActions());
            $details->put('actions_amount', $this->sumActionsAmount());

            if ($enabledBuiltinTax) {
                $taxesContainer = $this->getTaxesContainer();

                $details->put('subtotal', $this->getSubtotal());
                $details->put('taxes_count', $this->countTaxes());
                $details->put('taxable_amount', $this->getTaxableAmount());
                $details->put('tax_rate', $this->getTaxRate());
                $details->put('tax_amount', $this->getTaxAmount());
                $details->put('total', $this->getTotal());

                if ($withItems) {
                    $details->put('items', $itemsContainer->getDetails($withActions));
                }

                if ($withActions) {
                    $details->put('applied_actions', $actionsContainer->getDetails());
                }

                if ($withTaxes) {
                    $details->put('applied_taxes', $taxesContainer->getDetails());
                }
            } else {
                $details->put('total', $this->getSubtotal());

                if ($withItems) {
                    $details->put('items', $itemsContainer->getDetails($withActions));
                }

                if ($withActions) {
                    $details->put('applied_actions', $actionsContainer->getDetails());
                }
            }
        } else {
            if ($withItems) {
                $details->put('items', $itemsContainer->getDetails($withActions));
            }
        }

        $details->put('extra_info', new Details($this->getExtraInfo(null, [])));

        return $details;
    }

    /**
     * Get all information of cart group as a collection.
     *
     * @param  null|string  $groupName  The group part from cart name
     * @param  bool  $withCartsHaveNoItems  Include carts have no items in the result
     * @param  bool  $withItems  Include details of added items in the result
     * @param  bool  $withActions  Include details of applied actions in the result
     * @param  bool  $withTaxes  Include details of applied taxes in the result
     *
     * @throws InvalidAssociatedException
     * @throws InvalidModelException|InvalidCartNameException
     */
    public function getGroupDetails(?string $groupName = null, bool $withCartsHaveNoItems = false, bool $withItems = true, bool $withActions = true, bool $withTaxes = true): Details
    {
        $groupName = $groupName ?: $this->getGroupName();

        return $this->groupAnalysic($groupName, $withCartsHaveNoItems, $withItems, $withActions, $withTaxes);
    }

    /**
     * Standardize the cart name.
     *
     * @param  null|string  $name  The cart name before standardized
     *
     * @throws InvalidCartNameException
     */
    protected function standardizeCartName(?string $name = null): string
    {
        $name = $name ?: $this->defaultCartName;
        $name = trim($name, '.');

        if (in_array('extra_info', explode('.', $name))) {
            throw new InvalidCartNameException("The keyword 'extra_info' is not allowed to name the cart or group.");
        }

        return $name;
    }

    /**
     * Initialize attributes for current cart instance.
     *
     * @return bool return false if attributes already exist without initialization
     */
    protected function initSessions(): bool
    {
        if (! session()->has($this->getSessionPath())) {
            $appConfig = config('cart');
            $noneCommercialCarts = array_values((array) Arr::get($appConfig, 'none_commercial_carts', []));
            $useForCommercial = ! in_array($this->getName(), $noneCommercialCarts);
            $useBuiltinTax = (bool) Arr::get($appConfig, 'use_builtin_tax', false);

            $this->setConfig('use_for_commercial', $useForCommercial);
            $this->setConfig('use_builtin_tax', $useBuiltinTax);
            $this->setConfig('default_tax_rate', floatval(Arr::get($appConfig, 'default_tax_rate', 0)));
            $this->setConfig('default_action_rules', (array) Arr::get($appConfig, 'default_action_rules', []));
            $this->setConfig('action_groups_order', array_values((array) Arr::get($appConfig, 'action_groups_order', [])));

            session()->put($this->getSessionPath('type'), 'cart');
            session()->put($this->getSessionPath('name'), $this->getName());
            session()->put($this->getSessionPath('extra_info'), []);
            session()->put($this->getSessionPath('items'), new ItemsContainer);

            if ($useForCommercial) {
                session()->put($this->getSessionPath('applied_actions'), new ActionsContainer);

                if ($useBuiltinTax) {
                    session()->put($this->getSessionPath('applied_taxes'), new TaxesContainer);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Set config for this cart.
     *
     * @return $this
     */
    protected function setConfig(string $name, mixed $value = null): static
    {
        if ($name) {
            session()->put($this->getSessionPath('config.'.$name), $value);
        }

        return $this;
    }

    /**
     * Get the session path from the path to the cart.
     *
     * @return string $sessionKey The sub session key from session of this cart
     */
    protected function getSessionPath(mixed $sessionKey = null): string
    {
        if (is_null($sessionKey)) {
            return $this->cartName;
        }

        return $this->cartName.'.'.$sessionKey;
    }

    /**
     * Get the item container.
     */
    protected function getItemsContainer(): ItemsContainer
    {
        return session($this->getSessionPath('items'), new ItemsContainer);
    }

    /**
     * Get the tax container.
     */
    protected function getTaxesContainer(): TaxesContainer
    {
        return session($this->getSessionPath('applied_taxes'), new TaxesContainer);
    }

    /**
     * Get the action container.
     */
    protected function getActionsContainer(): ActionsContainer
    {
        return session($this->getSessionPath('applied_actions'), new ActionsContainer);
    }

    /**
     * Indicates whether this instance can apply cart.
     */
    protected function canApplyAction(): bool
    {
        if ($this->isCommercialCart()) {
            return true;
        }

        return false;
    }

    /**
     * Analyze data from the session group.
     *
     * @param  string  $groupName  The group part from cart name
     * @param  bool  $withCartsHaveNoItems  Include carts have no items in the result
     * @param  bool  $withItems  Include details of added items in the result
     * @param  bool  $withActions  Include details of applied actions in the result
     * @param  bool  $withTaxes  Include details of applied taxes in the result
     *
     * @throws InvalidAssociatedException
     * @throws InvalidModelException|InvalidCartNameException
     */
    protected function groupAnalysic(string $groupName, bool $withCartsHaveNoItems, bool $withItems, bool $withActions, bool $withTaxes, array $moneyAmounts = []): ?Details
    {
        $info = session($this->rootSessionName.'.'.$groupName, []);

        // If this is a group
        if (Arr::get($info, 'type') !== 'cart') {
            $extraInfo = Arr::get($info, 'extra_info', []);
            $info = Arr::except($info, ['extra_info']);
            $details = new Details;
            $subsections = new Details;

            $details->put('type', 'group');
            $details->put('name', $groupName);

            foreach ($info as $key => $value) {
                // Get details of subsections
                $subInfo = $this->groupAnalysic($groupName.'.'.$key, $withCartsHaveNoItems, $withItems, $withActions, $withTaxes, $moneyAmounts);

                if ($subInfo instanceof Details) {
                    if ($subInfo->has(['subtotal', 'tax_amount'])) {
                        $moneyAmounts['subtotal'] = Arr::get($moneyAmounts, 'subtotal', 0) + $subInfo->get('subtotal', 0);
                        $moneyAmounts['tax_amount'] = Arr::get($moneyAmounts, 'tax_amount', 0) + $subInfo->get('tax_amount', 0);
                    }

                    if ($subInfo->has(['total'])) {
                        $moneyAmounts['total'] = Arr::get($moneyAmounts, 'total', 0) + $subInfo->get('total', 0);
                    }

                    $subsections->put($key, $subInfo);
                }
            }

            $details->put('items_count', $subsections->sum('items_count'));
            $details->put('quantities_sum', $subsections->sum(function ($section) {
                return $section->get('quantities_sum', $section->get('items_count'));
            }));

            if (! empty($moneyAmounts)) {
                foreach ($moneyAmounts as $key => $value) {
                    $details->put($key, $value);
                }
            }

            $details->put('subsections', $subsections);
            $details->put('extra_info', $extraInfo);

            // Return group details
            return $details;
        }

        // If this is a cart
        $cart = $this->newInstance($groupName);

        if (! $withCartsHaveNoItems && $cart->hasNoItems()) {
            return null;
        }

        return $cart->getDetails($withItems, $withActions, $withTaxes);
    }
}
