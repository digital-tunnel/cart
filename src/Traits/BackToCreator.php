<?php

namespace DigitalTunnel\Cart\Traits;

use DigitalTunnel\Cart\Cart;
use DigitalTunnel\Cart\Exceptions\UnknownCreatorException;
use Illuminate\Support\Arr;

/**
 * This trait provides the ability to find about who created myself
 * To do that, perform the following two things:
 *     - Use this trait.
 *     - Call the storeCreator() method in the constructor of the
 *       class.
 */
trait BackToCreator
{
    /**
     * Stores the creator instance.
     */
    protected ?object $creator;

    /**
     * Get the creator of this instance.
     *
     *
     * @throws UnknownCreatorException
     */
    public function getCreator(): object
    {
        if (! $this->hasKnownCreator()) {
            throw new UnknownCreatorException('The interacting instance does not belong to any cart tree.');
        }

        return $this->creator;
    }

    /**
     * Determines weather this instance has stored the creator.
     */
    public function hasKnownCreator(): bool
    {
        return ! is_null($this->creator);
    }

    /**
     * Stores the creator into instance.
     *
     * @param  int  $stepsBackward  The steps backward from the method containing
     *                              this method to the constructor or the cloner.
     *                              It is 0 if this method is in the constructor
     *                              or the cloner.
     * @param  callable|null  $laterJob  the action will be taken later if this instance
     *                                   stored the creator
     * @return $this
     */
    protected function storeCreator(int $stepsBackward = 0, ?callable $laterJob = null): static
    {
        $stepsBackward = max(0, $stepsBackward);
        $caller = getCaller(__CLASS__.'::'.__FUNCTION__, 1 + $stepsBackward);
        $callerClass = Arr::get($caller, 'class');
        $callerObject = Arr::get($caller, 'object');
        $acceptedCreators = is_array($this->acceptedCreators) ? $this->acceptedCreators : [];

        if (in_array($callerClass, $acceptedCreators) && is_object($callerObject)) {
            $this->creator = ($callerClass == Cart::class) ? clone $callerObject : $callerObject;

            if ($laterJob instanceof \Closure) {
                call_user_func_array($laterJob, [$this->creator, $caller]);
            }
        }

        return $this;
    }
}
