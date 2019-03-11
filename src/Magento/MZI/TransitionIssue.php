<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use JiraRestApi\Issue\IssueService;
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
    public function statusTransitionToAutomated($key, $status, $isDryRun = true)
    {
        $issueKey = $key;
        $startingStatus = $status;

        //List of all transitions from Open to Automated. Skip transition handled separately
        $projectMcTransitionStates = [
            "Open", "In Progress", "Ready for Review", "In Review", "Review Passed", "Automated"
        ];
        $currentStatusOffset = array_search($startingStatus, $projectMcTransitionStates);
        $requiredTransitions = array_slice($projectMcTransitionStates, $currentStatusOffset+1);

        foreach ($requiredTransitions as $status) {
            try {
                $logMessage = $issueKey . " set to status " . $status;
                $transition = new Transition();
                if ($status == "Automated") {
                    $transition->fields = [
                        'resolution' => ['name' => 'Done'],
                        'customfield_13783' => ['value' =>'Unknown']
                    ];
                }
                $transition->setTransitionName($status);
                //$transition->setCommentBody("MFTF INTEGRATION - Setting " . $status . " status.");

                $transitionIssueService = new IssueService();

                if (!$isDryRun) {
                    $time_start = microtime(true);
                    $response = $transitionIssueService->transition($issueKey, $transition);
                    if ($response === true) {
                        print($logMessage);
                        LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                            'SUCCESS! ' . $logMessage
                        );
                    }
                } else {
                    print('Dry Run... SUCCESS! ' . $logMessage);
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                        'Dry Run... SUCCESS! ' . $logMessage
                    );
                }
            } catch (JiraException $e) {
                print("While processing " . $logMessage . "JIRA Exception: " . $e->getMessage());
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                    "While processing " . $logMessage . "JIRA Exception: " . $e->getMessage()
                );
            }
        }
        if (!$isDryRun) {
            $time_end = microtime(true);
            $time = $time_end - $time_start;
            print("\nTransition to Automated took : " . $time . "\n");
            LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                "\nTransition to Automated took: $time\n"
            );
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
    public function oneStepStatusTransition($key, $status, $isDryRun = true)
    {
        try {
            $transition = new Transition();
            $transition->setTransitionName($status);
            //$transition->setCommentBody("MFTF INTEGRATION - Setting $status status.");

            $logMessage = $key . " set to status " . $status;
            if (!$isDryRun) {
                $transitionIssueService = new IssueService();

                $response = $transitionIssueService->transition($key, $transition);
                if ($response === true) {
                    print("\n" . "SUCCESS! $logMessage");
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                        'SUCCESS! ' . $logMessage
                    );
                }
            } else {
                print('Dry Run... SUCCESS! ' . $logMessage);
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                    'Dry Run... SUCCESS! ' . $logMessage
                );
            }
        } catch (JiraException $e) {
            print("Error Occurred! " . $e->getMessage());
            LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->error(
                'Error Occurred!  ' . $e->getMessage()
            );
        }
    }
}
