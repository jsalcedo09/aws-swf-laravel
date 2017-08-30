<?php
namespace Jsalcedo09\SwfWorkflows\Commands;

use Aws\Exception\AwsException;
use Illuminate\Console\Command;
use AWS;

class RegisterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swfworkflows:register
                            {type=all : Register on AWS domains, workflows, activities or all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registers Domains, Workflows and Activities in AWS';

    protected $defaultDomainDescription = "Domain automatically generated with aws-swf-laravel package";

    protected $swfclient;

    protected $config;

    protected $pageSize = 15;

    protected $options = ["all", "domains", "workflows", "activities"];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->swfclient = AWS::createClient('Swf');
        //TODO: Validate configuration
        $this->config = config("swfworkflows");
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $registerType = $this->argument('type');
        //Validate the type
        if(!in_array($registerType, $this->options)){
            $this->error("Invalid type '".$registerType."' available options are ".implode(", ",$this->options));
            return;
        }
        if(in_array($registerType,["all", "domains"]))
            $this->registerDomains();
        if(in_array($registerType,["all", "workflows"]))
            $this->registerWorkflows();
        if(in_array($registerType,["all", "activities"]))
            $this->registerActivities();
    }

    private function registerActivities(){
        try {
            $configuredDomains = $this->getDomainsFromConfig();
            foreach($configuredDomains as $domain){
                $this->info('Finding current activities in domain '.$domain);
                $awsActivities = array_map(function($value) {
                    if(key_exists("activityType", $value) && key_exists("name", $value["activityType"]))
                        return $value["activityType"]["name"];
                }, $this->getSWFActivities($domain));
                foreach ($this->config['activities'] as $activity){
                    if($activity['domain'] === $domain) {
                        if (in_array($activity['name'], $awsActivities)) {
                            $this->info("Activity '" . $activity['name'] . "' already registered in domain '" . $domain . "'... skipping registration");
                        } else {
                            $this->info("Registering activity '" . $activity['name'] . "' in domain '" . $domain . "'...");
                            $this->swfclient->registerActivityType($activity);
                            $this->info('Activity '.$activity['name'].' successfully registered');
                        }
                    }
                }
            }
        }catch(AwsException $exeption){
            $this->error("There was connection error with aws, have you setup the config/aws.php? ");
            $this->error($exeption->getMessage());
        }catch(\Exception $exeption){
            $this->error($exeption->getMessage());
        }
    }

    private function registerWorkflows(){
        try {
            $configuredDomains = $this->getDomainsFromConfig();
            foreach($configuredDomains as $domain){
                $this->info('Finding current workflows in domain '.$domain);
                $awsWorflows = array_map(function($value) {
                    if(key_exists("workflowType", $value) && key_exists("name", $value["workflowType"]))
                        return $value["workflowType"]["name"];
                }, $this->getSWFWorkflows($domain));
                foreach ($this->config['workflows'] as $workflow){
                    if($workflow['domain'] === $domain) {
                        if (in_array($workflow['name'], $awsWorflows)) {
                            $this->info("Workflow '" . $workflow['name'] . "' already registered in domain '" . $domain . "'... skipping registration");
                        } else {
                            $this->info("Registering workflow '" . $workflow['name'] . "' in domain '" . $domain . "'...");
                            $this->swfclient->registerWorkflowType($workflow);
                            $this->info('Workflow '.$workflow['name'].' successfully registered');
                        }
                    }
                }
            }
        }catch(AwsException $exeption){
            $this->error("There was connection error with aws, have you setup the config/aws.php? ");
            $this->error($exeption->getMessage());
        }catch(\Exception $exeption){
            $this->error($exeption->getMessage());
        }
    }

    private function registerDomains(){
        try {
            $this->info('Finding current domains list in ' . $this->swfclient->getRegion() . ' region');
            $awsDomains = array_map(function($value){
                if(key_exists("name",$value))
                    return $value['name'];
            },$this->getSWFDomains());
            $configuredDomains = $this->getDomainsFromConfig();
            foreach($configuredDomains as $domain){
                if(in_array($domain, $awsDomains)){
                    $this->info('Domain '.$domain.' already registered in Amazon SWF... skipping registration');
                }else{
                    $this->info('Registering domain '.$domain.' in SWF...');
                    $this->swfclient->registerDomain([
                        'description' => $this->defaultDomainDescription,
                        'name' => $domain, // REQUIRED
                        'workflowExecutionRetentionPeriodInDays' => $this->config['workflowExecutionRetentionPeriodInDays'], // REQUIRED
                    ]);
                    $this->info('Domain '.$domain.' domain successfully registered');
                }
            }
        }catch(AwsException $exeption){
            $this->error("There was connection error with aws, have you setup the config/aws.php? ");
            $this->error($exeption->getMessage());
        }catch(\Exception $exeption){
            $this->error($exeption->getMessage());
        }
    }

    private function getDomainsFromConfig(){
        $domains = [];
        foreach ($this->config['activities'] as $activity){
            if(!key_exists("domain", $activity)){
                throw new \Exception("All the activities should have a domain configured");
            }else if(!in_array($activity["domain"], $domains)){
                array_push($domains, $activity["domain"]);
            }
        }
        foreach ($this->config['workflows'] as $workflow){
            if(!key_exists("domain",$workflow)){
                throw new \Exception("All the workflows should have a domain configured");
            }else if(!in_array($workflow["domain"], $domains)){
                array_push($domains, $workflow["domain"]);
            }
        }
        return $domains;
    }

    private function getSWFActivities($domain){
        $activities = [];
        $token = "";
        do{
            $page = $this->getActivityPage($domain, $token);
            $token = $page['nextPageToken'];
            $activities = array_merge($page["typeInfos"], $activities);
        }while(!empty($token));

        return $activities;
    }

    private function getActivityPage($domain, $token){
        $options = [
            'domain' => $domain,
            'maximumPageSize' => $this->pageSize,
            'registrationStatus' => "REGISTERED"
        ];
        if($token !== ""){
            $options['nextPageToken'] = $token;
        }

        return $this->swfclient->listActivityTypes($options);
    }

    private function getSWFWorkflows($domain){
        $workflows = [];
        $token = "";
        do{
            $page = $this->getWorkflowPage($domain, $token);
            $token = $page['nextPageToken'];
            $workflows = array_merge($page["typeInfos"], $workflows);
        }while(!empty($token));

        return $workflows;
    }

    private function getWorkflowPage($domain, $token){
        $options = [
            'domain' => $domain,
            'maximumPageSize' => $this->pageSize,
            'registrationStatus' => "REGISTERED"
        ];
        if($token !== ""){
            $options['nextPageToken'] = $token;
        }

        return $this->swfclient->listWorkflowTypes($options);
    }

    private function getSWFDomains(){
        $domains = [];
        $token = "";
        do{
            $page = $this->getDomainPage($token);
            $token = $page['nextPageToken'];
            $domains = array_merge($page["domainInfos"], $domains);
        }while(!empty($token));

        return $domains;

    }

    private function getDomainPage($token){
        $options = [
            'maximumPageSize' => $this->pageSize,
            'registrationStatus' => "REGISTERED"
        ];
        if($token !== ""){
            $options['nextPageToken'] = $token;
        }

        return $this->swfclient->listDomains($options);
    }

}