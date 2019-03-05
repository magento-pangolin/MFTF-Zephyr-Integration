<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use JiraRestApi\Issue\Transition;
use Magento\MZI\Util\LoggingUtil;

/**
 * Class transitionIssue, handles issue transitions in Zephyr
 * @package Magento\MZI
 */
class TransitionIssue
{
    /**
     * Transitions an MC project issue to AUTOMATED status
     *
     * @param string $key
     * @param string $status
     * @param bool $isDryRun
     * @return void
     * @throws \Exception
     */
    public static function statusTransitionToAutomated($key, $status, $isDryRun = true)
    {
//        $update = ['key' => 'MC-4232', 'status'=>'Review Passed'];
//        $issueKey = $update['key'];
//        $startingStatus = $update['status'];
        $issueKey = $key;
        $startingStatus = $status;

        $projectMcTransitionStates = ["Open", "In Progress", "Ready for Review", "In Review", "Review Passed", "Automated"]; //List of all transitions from Open to Automated. Skip transition handled separately.
        $currentStatusOffset = array_search($startingStatus, $projectMcTransitionStates);
        if ($startingStatus == "Skipped") {
            unset($projectMcTransitionStates[6]);
        }
        $requiredTransitions = array_slice($projectMcTransitionStates, $currentStatusOffset+1);

        foreach ($requiredTransitions as $status) {
            try {
                $logMessage = $issueKey . " set to status " . $status;
                $transition = new Transition();
                if ($status == "Automated") {
                    $transition->fields = ['resolution' => ['name' => 'Done'], 'customfield_13783' => ['value' =>'Unknown']];
                }
                $transition->setTransitionName($status);
                $transition->setCommentBody("MFTF INTEGRATION - Setting " . $status . " status.");

                $transitionIssueService = new IssueService(null, null, realpath('../../../').'/');

                if (!$isDryRun) {
                    $time_start = microtime(true);
                    $ret = $transitionIssueService->transition($issueKey, $transition);
                    if ($transitionIssueService->http_response == 204) {
                        print_r($logMessage);
                        LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info('SUCCESS! ' . $logMessage);
                    }
                } else {
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info('Dry Run... SUCCESS! ' . $logMessage);
                }
            } catch (JiraException $e) {
                print_r("While processing " . $logMessage . "JIRA Exception: " . $e->getMessage());
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info("While processing " . $logMessage . "JIRA Exception: " . $e->getMessage());
            }
        }
        if (!$isDryRun) {
            $time_end = microtime(true);
            $time = $time_end - $time_start;
            print_r("\nTransition to Automated took : " . $time . "\n");
            LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info("\nTransition to Automated took: $time\n");
        }
    }

    /**
     * One step transit an issue to next status
     *
     * @param string $key
     * @param string $status
     * @param bool $isDryRun
     * @return void
     * @throws \Exception
     */
    public static function oneStepStatusTransition($key, $status, $isDryRun = true)
    {
        try {
            $transition = new Transition();
            $transition->setTransitionName($status);
            $transition->setCommentBody("MFTF INTEGRATION - Setting $status status.");

            $logMessage = $key . " set to status " . $status;
            if (!$isDryRun) {
                $transitionIssueService = new IssueService(null, null, realpath('../../../').'/');

                $transitionIssueService->transition($key, $transition);
                if ($transitionIssueService->http_response == 204) {
                    print_r("\n" . "SUCCESS! $logMessage");
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info('SUCCESS! ' . $logMessage);
                }
            } else {
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info('Dry Run... SUCCESS! ' . $logMessage);
            }
        } catch (JiraException $e) {
            print_r("Error Occurred! " . $e->getMessage());
            LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->error('Error Occurred!  ' . $e->getMessage());
        }
    }
}
