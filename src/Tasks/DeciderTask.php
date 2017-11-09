<?php

/**
 * Created by PhpStorm.
 * User: jorgesalcedo
 * Date: 8/30/17
 * Time: 6:28 PM
 */

namespace Jsalcedo09\SwfWorkflows\Tasks;

use Illuminate\Queue\SerializesModels;
use Event;
use AWS;

class DeciderTask {

    use SerializesModels;

    protected $task;
    protected $domain;
    protected $terminate = false;
    protected $terminateReason;

    const SWF_EVENTS = ['WorkflowExecutionStarted', 'WorkflowExecutionCompleted', 'CompleteWorkflowExecutionFailed', 'WorkflowExecutionFailed', 'DecisionTaskScheduled', 'DecisionTaskStarted', 'DecisionTaskCompleted', 'ActivityTaskScheduled', 'ActivityTaskStarted', 'ActivityTaskCompleted', 'ActivityTaskFailed', 'WorkflowExecutionSignaled', 'SignalExternalWorkflowExecutionInitiated', 'ExternalWorkflowExecutionSignaled', 'ScheduleActivityTaskFailed'];

    public $wokflowStat = [];

    public function __construct($domain, $task) {
        //TODO: Validate task
        $this->task = $task;
        $this->domain = $domain;
        $this->swfclient = AWS::createClient('Swf');
    }

    /**
     * Event name builder
     * @return type
     */
    public function getEventName($eventType) {
        return implode(".", [
            $this->domain,
            $this->getWorkflowType()["name"],
            "decider",
            $eventType
        ]);
    }

    public function getCurrentEvent() {
        //Is 0 because events are reversed
        return $this->task["events"][0];
    }

    public function getEventType() {
        return $this->getCurrentEvent()["eventType"];
    }

    public function getWorkflowType() {
        return $this->task["workflowType"];
    }

    public function getTask() {
        return $this->task;
    }

    public function processTask() {
        $previoudTaskEventId = $this->task["previousStartedEventId"];
        $startedEventId = $this->task["startedEventId"];
        $eventIdArr = [];
        for ($start = $previoudTaskEventId; $start < $startedEventId; $start++) {
            array_push($eventIdArr, $start);
        }
        // Get all non-processed new events
        $freshEvents = $this->filterNewEvents($this->task["events"], $eventIdArr);
        // Fire all new events
        foreach ($freshEvents as $e) {
            $eventParams = [
                'task' => $this,
                'currentTaskAttrib' => $e[lcfirst($e['eventType'] . 'EventAttributes')],
                'eventId' => $e['eventId'],
                'workflowData' => $this->task['workflowExecution']
            ];
            Event::fire($this->getEventName($e['eventType']), $eventParams);
        }
    }

    /**
     * Schedule activity task
     * @param type $taskData
     */
    public function ScheduleActivityTask($taskData) {
        try {
            $this->swfclient->respondDecisionTaskCompleted(
                    ['decisions' => [
                            [
                                "scheduleActivityTaskDecisionAttributes" => [
                                    'activityId' => $taskData['id'], // REQUIRED
                                    'activityType' => [ // REQUIRED
                                        'name' => $taskData['name'], // REQUIRED
                                        'version' => $taskData['version'], // REQUIRED
                                    ],
                                    'control' => isset($taskData['control']) ? $taskData['control'] : '',
                                    'input' => $taskData['input'],
                                    'taskList' => [
                                        'name' => 'default', // REQUIRED
                                    ]
                                ],
                                "decisionType" => 'ScheduleActivityTask',
                            ]
                        ],
                        "taskToken" => $this->getTask()['taskToken']
            ]);
        } catch (\Exception $e) {
            echo 'Error while scheduling activity task - '.$e->getMessage();
        }
    }

    public function SignalExternalWorkflowExecution($taskData) {
        try {
            $this->swfclient->respondDecisionTaskCompleted(
                    ['decisions' => [
                            [
                                "signalExternalWorkflowExecutionDecisionAttributes" => [
                                    'control' => isset($taskData['control']) ? $taskData['control'] : '',
                                    'input' => $taskData['input'],
                                    'signalName' => 'Test',
                                    'workflowId' => $this->task['workflowExecution']['workflowId']
                                ],
                                "decisionType" => 'SignalExternalWorkflowExecution',
                            ]
                        ],
                        "taskToken" => $this->getTask()['taskToken']
            ]);
        } catch (\Exception $e) {
            echo 'Error while signal workflow - '.$e->getMessage();
        }
    }

    /**
     * Terminate workflow with reason
     * @param type $taskData
     */
    public function terminateWorkFlow($taskData) {
        try {
            $this->swfclient->terminateWorkflowExecution([
                'childPolicy' => 'TERMINATE',
                'domain' => $this->domain, // REQUIRED
                'reason' => isset($taskData['reason']) ? $taskData['reason'] : 'Successfully finished',
                'runId' => $this->task['workflowExecution']['runId'],
                'workflowId' => $this->task['workflowExecution']['workflowId'], // REQUIRED
            ]);
        } catch (\Exception $e) {
            echo 'Error while terminating workflow - '.$e->getMessage();
        }
    }

    /**
     * Find next task details
     * @return array
     */
    public function getNextTaskDetails() {
        $nextTaskData = [];
        $workflowName = $this->task['workflowType']['name'];
        $workflows = \Config::get('activityworkflow.workflows');
        if (!$workflows[$workflowName]) {
            return $nextTaskData;
        }

        // Check if it's external execution
        $externalExecution = $this->isExternalExcecution();
        if ($externalExecution) {
            $previousTask = $this->findPreviousTaskHistory();
            $nextTaskData['name'] = $workflows[$workflowName][$previousTask['name'] . '-wait'];
            $nextTaskData['input'] = $this->getExternalExecutionInput();
            return $nextTaskData;
        }

        // Check if previous task failed
        $previousTaskStatus = $this->previousTaskFailed();
        if ($previousTaskStatus['status']) {
            $nextTaskData['name'] = 'finish';
            $nextTaskData['reason'] = $previousTaskStatus['reason'];
            return $nextTaskData;
        }
        $previousTaskAttributes = $this->findPreviousTask();
        if (isset($previousTaskAttributes['complete']) && $previousTaskAttributes['complete']) {
            $taskName = $this->findNextTaskFromCondition($workflows[$workflowName][$previousTaskAttributes['name']],$previousTaskAttributes);
            $nextTaskData['name'] = $taskName;
            $nextTaskData['input'] = $previousTaskAttributes['result'];
        }
        if ($previousTaskAttributes['name'] == 'start') {
            $taskName = $this->findNextTaskFromCondition($workflows[$workflowName][$previousTaskAttributes['name']],$previousTaskAttributes);
            $nextTaskData['name'] = $taskName;
            $nextTaskData['input'] = $previousTaskAttributes['result'];
        }
        return $nextTaskData;
    }

    /**
     * Filter out new events only
     * @param type $allEvents
     * @param type $eventIdArr
     * @return type
     */
    protected function filterNewEvents($allEvents, $eventIdArr) {
        return array_intersect_key($allEvents, array_flip($eventIdArr));
    }

    /**
     * Check previous task
     * @return type
     */
    public function findPreviousTask() {
        $eventData = ['name' => 'start'];
        foreach (array_reverse($this->task['events']) as $event) {
            // If schdeule activity task failed
            if ($event['eventType'] == 'ScheduleActivityTaskFailed') {
                $eventData['name'] = 'finish';
                $eventData['reason'] = $event['scheduleActivityTaskFailedEventAttributes']['cause'];
                return $eventData;
            }
            
            if ($event['eventType'] == 'ActivityTaskCompleted') {
                $eventData['result'] = $event['activityTaskCompletedEventAttributes']['result'];
                $eventData['complete'] = true;
            }

            if ($event['eventType'] == 'ActivityTaskScheduled') {
                $eventData['name'] = $event['activityTaskScheduledEventAttributes']['activityType']['name'];
                $eventData['version'] = $event['activityTaskScheduledEventAttributes']['activityType']['version'];
                return $eventData;
            }
            
            if ($event['eventType'] == 'WorkflowExecutionStarted') {
                $eventData['result'] = isset($event['workflowExecutionStartedEventAttributes']['input']) ? $event['workflowExecutionStartedEventAttributes']['input'] : '';
                $eventData['name'] = 'start';
                return $eventData;
            }
        }
        return $eventData;
    }

    /**
     * Check previous task from history
     * @return type
     */
    protected function findPreviousTaskHistory() {
        $taskData = [];
        $eventData = ['name' => 'start'];
        $last = false;
        do {
            $last = true;
            $options = [
                'domain' => $this->domain, // REQUIRED
                'execution' => [ // REQUIRED
                    'runId' => $this->task['workflowExecution']['runId'],
                    'workflowId' => $this->task['workflowExecution']['workflowId'], // REQUIRED
                ],
                'maximumPageSize' => 1,
                'reverseOrder' => true,
            ];
            if (!empty($pageToken))
                $options["nextPageToken"] = $pageToken;

            $result = $this->swfclient->getWorkflowExecutionHistory($options);
            if (isset($result['nextPageToken']) && $result['nextPageToken'] !== '') {
                $pageToken = $result["nextPageToken"];
                $last = false;
            }
            if (isset($result['events']) && $result['events'] !== '') {
                $taskData = $this->findTask($result['events'], $taskData);
                if (isset($taskData['name']) && isset($taskData['version'])) {
                    $last = true;
                }
            }
        } while (!$last);
        if (isset($taskData['name']) && $taskData['name'] !== '') {
            return $taskData;
        }
        return $eventData;
    }

    /**
     * Check previous task is successfully finished or not
     * @return type
     */
    protected function previousTaskFailed() {
        foreach (array_reverse($this->task['events']) as $event) {
            if (in_array($event['eventType'], ['ActivityTaskFailed'])) {
                return ['status' => true,
                    'reason' => $event[lcfirst($event['eventType']) . 'EventAttributes']['reason']
                ];
            }
            
            if (in_array($event['eventType'], ['ActivityTaskTimedOut'])) {
                return ['status' => true,
                    'reason' => 'Activity task timeout'
                ];
            }

            if (in_array($event['eventType'], ['ScheduleActivityTaskFailed'])) {
                return ['status' => true,
                    'reason' => $event[lcfirst($event['eventType']) . 'EventAttributes']['cause']
                ];
            }
        }
        return ['status' => false, 'reason' => ''];
    }

    protected function isExternalExcecution() {
        foreach (array_reverse($this->task['events']) as $event) {
            
            if (in_array($event['eventType'], ['ExternalWorkflowExecutionSignaled'])) {
                return true;
            }
        }
        return false;
    }

    protected function getExternalExecutionInput() {
        foreach (array_reverse($this->task['events']) as $event) {
            if (in_array($event['eventType'], ['SignalExternalWorkflowExecutionInitiated'])) {
                return $event['signalExternalWorkflowExecutionInitiatedEventAttributes']['input'];
            }
        }
        return '';
    }

    protected function findTask($events, $eventData) {
        foreach ($events as $event) {
            if ($event['eventType'] == 'ActivityTaskCompleted') {
                $eventData['result'] = $event['activityTaskCompletedEventAttributes']['result'];
                $eventData['complete'] = true;
            }

            if ($event['eventType'] == 'ActivityTaskScheduled') {
                $eventData['name'] = $event['activityTaskScheduledEventAttributes']['activityType']['name'];
                $eventData['version'] = $event['activityTaskScheduledEventAttributes']['activityType']['version'];
                return $eventData;
            }
        }
    }
    
    protected function findNextTaskFromCondition($data, $previousTaskAttributes) {
        if(is_array($data)) {
            if(!isset($previousTaskAttributes['result'])) {
                $taskName = $data[0];
            }
            $nextTaskData = $data;
            // check for each input
            array_shift($nextTaskData);
            foreach ($nextTaskData as $input=>$nextTask) {
                if(isset($previousTaskAttributes['result']) && $previousTaskAttributes['result'] !== '' && property_exists(json_decode($previousTaskAttributes['result']), $input)) {
                    $taskName = $nextTask;
                    break;
                }
            }
            if(!isset($taskName)) {
                $taskName = $data[0];
            }
            return $taskName;
        } else {
            return $data;
        }
    }

    public function setTermination($reason = '') {
        $this->terminate = true;
        $this->terminateReason = $reason;
    }
    
    public function getTerminationDetails() {
        return ['status'=>$this->terminate, 'reason'=>$this->terminateReason];
    }

}
