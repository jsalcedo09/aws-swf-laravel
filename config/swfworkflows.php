<?php

/******
 *'activities'=>[
 *  [
 *       'defaultTaskHeartbeatTimeout' => '<string>',
 *       'defaultTaskList' => [
 *           'name' => '<string>', // REQUIRED
 *       ],
 *       'defaultTaskPriority' => '<string>',
 *       'defaultTaskScheduleToCloseTimeout' => '<string>',
 *       'defaultTaskScheduleToStartTimeout' => '<string>',
 *       'defaultTaskStartToCloseTimeout' => '<string>',
 *       'description' => '<string>',
 *       'domain' => '<string>', // REQUIRED
 *       'name' => '<string>', // REQUIRED
 *       'version' => '<string>', // REQUIRED
 *  ]
 *
 *'workflows' => [
 *   [
 *       'defaultChildPolicy' => 'TERMINATE|REQUEST_CANCEL|ABANDON',
 *       'defaultExecutionStartToCloseTimeout' => '<string>',
 *       'defaultLambdaRole' => '<string>',
 *       'defaultTaskList' => [
 *           'name' => '<string>', // REQUIRED
 *       ],
 *       'defaultTaskPriority' => '<string>',
 *       'defaultTaskStartToCloseTimeout' => '<string>',
 *       'description' => '<string>',
 *       'domain' => '<string>', // REQUIRED
 *       'name' => '<string>', // REQUIRED
 *       'version' => '<string>', // REQUIRED
 *   ],
 *
 ******/


return [
    'workflowExecutionRetentionPeriodInDays'=>"90",
    'activities'=>[

    ],
    'workflows' => [

    ],
];