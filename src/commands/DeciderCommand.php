<?php
namespace Jsalcedo09\SwfWorkflows\Commands;

use Illuminate\Console\Command;
use AWS;
use Jsalcedo09\SwfWorkflows\Events\DeciderEvent;
use Jsalcedo09\SwfWorkflows\Tasks\DeciderTask;

class DeciderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swfworkflows:decider
                            {domain : Starts all the workflows of a given domain}
                            {taskList : The Decision taskList to poll to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a workflow decider';


    protected $swfclient;

    protected $config;

    protected $maximumPageSize = 100;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->swfclient = AWS::createClient('Swf');
        $this->config = config("swfworkflows");
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domainArg = $this->argument('domain');
        foreach($this->config["workflows"] as $workflow){
            if($domainArg === $workflow['domain']){
                $stack[] =  $this->runWorkflowDecider($domainArg);
            }
        }

        if(empty($stack)){
            $this->warn("No workflows decider running");
        }
    }

    private function runWorkflowDecider($domain){

        $this->info("Starting decider in domain '".$domain."'");
        do {
            $task = $this->pollForDecisionTasks($domain);
            $this->processTask($task);
        } while (1);
    }

    private function pollForDecisionTasks($domain){
        $pageToken = "";
        $task = [];
        do{
            $result = $this->pollForDecisionTasksPage($domain, $pageToken);
            foreach ($result as $key => $value){
                switch ($key){
                    case "nextPageToken":
                        $pageToken = $result["nextPageToken"];
                        break;
                    case "events":
                        $task["events"] = array_merge($task["events"], $value);
                        break;
                    default:
                        $task[$key] = $value;
                }
            }
        }while($pageToken !== "");
        return $task;

    }


    private function pollForDecisionTasksPage($domain, $pageToken){
        $options = [
            'domain' => $domain,
            'maximumPageSize' => $this->maximumPageSize,
            'taskList'=>[
                "name" => $this->argument('taskList')
            ]
        ];

        if(!empty($pageToken))
            $options["nextPageToken"] = $pageToken;

        return $this->swfclient->pollForDecisionTask($options);

    }

    private function processTask($task){
        $this->info("Got new decision task");
        $deciderTask = new DeciderTask($task);
        event($deciderTask->getEventName(), $deciderTask);
    }
}