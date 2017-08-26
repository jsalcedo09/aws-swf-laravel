<?php
namespace Jsalcedo09\SwfWorkflows\Commands;

use Illuminate\Console\Command;
use AWS;

class DeciderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swfworkflows:decider';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a workflow decider';


    protected $swfclient;
    protected $config;

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
        print_r($this->config);
    }
}