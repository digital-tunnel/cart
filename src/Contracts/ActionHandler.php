<?php

namespace DigitalTunnel\Cart\Contracts;

use DigitalTunnel\Cart\Action;

interface ActionHandler
{
    /**
     * Control action rules.
     *
     * @param  Action  $action  The action
     */
    public function actionHandler(Action $action): array;
}
