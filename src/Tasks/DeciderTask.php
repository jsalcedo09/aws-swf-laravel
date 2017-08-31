<?php
/**
 * Created by PhpStorm.
 * User: jorgesalcedo
 * Date: 8/30/17
 * Time: 6:28 PM
 */

namespace Jsalcedo09\SwfWorkflows\Tasks;


use Illuminate\Queue\SerializesModels;

class DeciderTask
{
    use SerializesModels;
    protected $task;

    public function __construct($task)
    {
        $this->task = $task;
    }

    public function getEventName(){
        return "workflow.max.event";
    }

}