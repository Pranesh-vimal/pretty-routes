<?php

namespace Pranesh\PrettyRoutes;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Pranesh\PrettyRoutes\Skeleton\SkeletonClass
 */
class PrettyRoutesFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'pretty-routes';
    }
}
