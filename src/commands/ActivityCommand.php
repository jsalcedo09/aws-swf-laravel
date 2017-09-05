<?php

namespace Jsalcedo09\SwfWorkflows\Commands;

use Illuminate\Console\Command;
use AWS;
use Jsalcedo09\SwfWorkflows\Events\DeciderEvent;
use Jsalcedo09\SwfWorkflows\Tasks\ActivityTask;

class ActivityCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swfworkflows:activity
                            {domain : Starts all the workflows of a given domain}
                            {taskList : The Decision taskList to poll to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a workflow activity';
    protected $swfclient;
    protected $config;
    protected static $currentActivities = [];
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
        $this->swfclient = AWS::createClient('Swf');
        $this->config = config("swfworkflows");
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $domainArg = $this->argument('domain');
        foreach ($this->config["workflows"] as $workflow) {
            if ($domainArg === $workflow['domain']) {
                $stack[] = $this->runWorkflowActivity($domainArg);
            }
        }

        if (empty($stack)) {
            $this->warn("No workflows activity running");
        }
    }

    private function runWorkflowActivity($domain) {

        $this->info("Starting activity worker in domain '" . $domain . "'");
        do {
            $task = $this->pollForActivityTask($domain);
            $this->processTask($domain, $task);
        } while (1);
    }

    private function pollForActivityTask($domain) {
        $options = [
            'domain' => $domain,
            'taskList' => [
                "name" => $this->argument('taskList')
            ]
        ];

        return $this->swfclient->pollForActivityTask($options);
    }

    private function processTask($domain, $task) {
        if (isset($task['taskToken']) && $task['taskToken'] !== '') {
            $activityTask = new ActivityTask($domain, $task);
            $this->info("Got new activity task - " . $activityTask->getEventName());
            event($activityTask->getEventName(), $activityTask);
        } else {
            $this->info("No task in the last 60 second... waiting");
        }
    }
    
    public static function addActivity($activity) {
        array_push(ActivityCommand::$currentActivities, $activity);
    }
    
    public static function removeActivity($activity) {
        unset(ActivityCommand::$currentActivities[$activity]);
    }

}
