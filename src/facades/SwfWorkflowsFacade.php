<?php 

namespace Jsalcedo09\SwfWorkflows\Facades;

use Illuminate\Support\Facades\Facade;

class SwfWorkflowsFacade extends Facade {
    /**
     * Get the binding in the IoC container
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'swfworkflows'; // the IoC binding.
    }
}