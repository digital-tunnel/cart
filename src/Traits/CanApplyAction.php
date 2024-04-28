<?php

namespace DigitalTunnel\Cart\Traits;

use DigitalTunnel\Cart\Action;

/**
 * The CanApplyAction traits.
 */
trait CanApplyAction
{
    /**
     * Add an action into the actions' container.
     *
     * @param  array  $attributes  The action attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function applyAction(array $attributes = [], bool $withEvent = true): ?Action
    {
        if (! $this->canApplyAction()) {
            return null;
        }

        return $this->getActionsContainer()->addAction($attributes, $withEvent);
    }

    /**
     * Update an action in the actions' container.
     *
     * @param  string  $actionHash  The unique identifier of the action
     * @param  array  $attributes  The new attributes
     * @param  bool  $withEvent  Enable firing the event
     */
    public function updateAction(string $actionHash, array $attributes = [], bool $withEvent = true): ?Action
    {
        return $this->getActionsContainer()->updateAction($actionHash, $attributes, $withEvent);
    }

    /**
     * Determines if the action exists in the action container by given action hash.
     *
     * @param  string  $actionHash  The unique identifier of the action
     */
    public function hasAction(string $actionHash): bool
    {
        return $this->getActionsContainer()->has($actionHash);
    }

    /**
     * Get an action in the actions' container.
     *
     * @param  string  $actionHash  The unique identifier of the action
     */
    public function getAction(string $actionHash): ?Action
    {
        return $this->getActionsContainer()->getAction($actionHash);
    }

    /**
     * Get all actions in the actions container that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function getActions(mixed $filter = null, bool $complyAll = true): array
    {
        return $this->getActionsContainer()->getActions($filter, $complyAll);
    }

    /**
     * Remove an action from the actions' container.
     *
     * @param  string  $actionHash  The unique identifier of the action
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     */
    public function removeAction(string $actionHash, bool $withEvent = true): static
    {
        $this->getActionsContainer()->removeAction($actionHash, $withEvent);

        return $this;
    }

    /**
     * Remove all actions from the actions container.
     *
     * @param  bool  $withEvent  Enable firing the event
     * @return $this
     */
    public function clearActions(bool $withEvent = true): static
    {
        $this->getActionsContainer()->clearActions($withEvent);

        return $this;
    }

    /**
     * Count all actions in the actions container that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function countActions(mixed $filter = null, bool $complyAll = true): int
    {
        return $this->getActionsContainer()->countActions($filter, $complyAll);
    }

    /**
     * Calculate the sum of action amounts in the action container that match the given filter.
     *
     * @param  mixed  $filter  Search filter
     * @param  bool  $complyAll  indicates that the results returned must satisfy
     *                           all the conditions of the filter at the same time
     *                           or that only parts of the filter
     */
    public function sumActionsAmount(mixed $filter = null, bool $complyAll = true): float
    {
        return $this->getActionsContainer()->sumAmount($filter, $complyAll);
    }
}
