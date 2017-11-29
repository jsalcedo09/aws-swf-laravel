# Laravel-Amazon-SWF
A Laravel 5 package to integrate with Amazon Simplified Workflows Service

## Installation
1. Add respository in composer.json
```sh
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/jsalcedo09/aws-swf-laravel"
    }
]
```

2. Add as dependency in require block
```sh
"require": {
    "jsalcedo09/aws-swf-laravel": "dev-master"
}
```

## Commands

- Copy configuration files
```sh
php artisan vendor:publish
```

Copy configuration files activityworkflow.php & swfworkflows.php in config folder

- Run Decider task
```sh
php artisan swfworkflows:decider <domain> <tasklist>
```

- Run Activity task
```sh
php artisan swfworkflows:activity <domain> <tasklist>
```

- Register workflows and activities to SWF
```sh
php artisan swfworkflows:register all|domains|workflows|activities
```

## Workflow config structure
workflows can be configured in swfworkflows.php

```sh
return [
    "workflowExecutionRetentionPeriodInDays" => "90",
    'activities'=>[[
            "domain" => "TestDomain",
            "name" => "Activity1",
            "version" => "1",
            'defaultTaskScheduleToCloseTimeout' => '31536000',
            'defaultTaskScheduleToStartTimeout' => '31536000',
            'defaultTaskStartToCloseTimeout' => '31536000',
            'defaultTaskHeartbeatTimeout' => '1500',
            "defaultTaskList"=>[
                "name"=>"default"
            ],
            "domain" => "TestDomain",
            "name" => "Activity2",
            "version" => "1",
            'defaultTaskScheduleToCloseTimeout' => '31536000',
            'defaultTaskScheduleToStartTimeout' => '31536000',
            'defaultTaskStartToCloseTimeout' => '31536000',
            'defaultTaskHeartbeatTimeout' => '1500',
            "defaultTaskList"=>[
                "name"=>"default"
            ]
        ]],
    'workflows' => [[
            "domain" => "TestDomain",
            "name" => "TestWorkflowWithDecider",
            "version" => "1",
            "defaultExecutionStartToCloseTimeout" => "31536000",
            "defaultTaskStartToCloseTimout" => "31536000",
            "defaultChildPolicy" => "TERMINATE",
            "defaultTaskList"=>[
                    "name"=>"default"
                ]
        ]],
];
```

## Activityflow config structure

activity flow can be configured in activityworkflow.php

- Basic flow scenario with 'start' and 'finish' flags
```sh
return [
    'workflows' => [
        'TestWorkflowWithDecider' => [
            'start'=>'Activity1',
            'Activity1'=>'Activity2',
            'Activity2'=>'finish'
        ]
    ]
];
```

- Pause workflow scenario with 'wait' flag
```sh
return [
    'workflows' => [
        'TestWorkflowWithDecider' => [
            'start'=>'Activity1',
            'Activity1'=>'wait',
            'Activity1-wait'=>'Activity2',
            'Activity2'=>'finish'
        ]
    ]
];
```

- Child workflow scenario
```sh
return [
    'workflows' => [
        'TestWorkflowWithDecider' => [
            'start'=>'Activity1',
            'Activity1'=>'startChildWorkflow',
            'ChildWait'=>['ChildWait','finish'=>'Activity2'],
            'Activity2'=>'finish'
        ],
        'ChildTestWorkflowWithDecider' => [
            'start'=>'Activity1',
            'Activity1'=>'Activity2',
            'Activity2'=>'finish'
        ]
    ],
    'childWorkflows' => [
        'GenerateBannerWorkflowTest'
    ]
];
```

**Note : To be identify the child workflow name it's necessary to input it in ['childWorkFlowData']['workflowType']

## Decider event structure

- <domain>.<workflow_name>.decider.<event_type>

'event_type' can be any swf events (WorkflowExecutionStarted, DecisionTaskScheduled etc.)
for example TestDomain.TestWorkflowWithDecider.decider.WorkflowExecutionStarted

## Activity event structure
- <domain>.decider.<workflow_name><activity_name>

for example TestDomain.activity.TestWorkflowWithDeciderActivity1

**Note : It's developer responsibility to build listener to listen for those events. This library provider some useful methods to build the flow.**
