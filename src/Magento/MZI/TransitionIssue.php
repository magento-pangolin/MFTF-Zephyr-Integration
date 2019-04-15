<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;
use JiraRestApi\Issue\Transition;
use Magento\MZI\Util\JiraInfo;
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
     * @return void
     * @throws \Exception
     */
    public function statusTransitionToAutomated($key, $status)
    {
        $issueKey = $key;
        $startingStatus = $status;

        $currentStatusOffset = array_search($startingStatus, JiraInfo::$transitionStates);
        $requiredTransitions = array_slice(JiraInfo::$transitionStates, $currentStatusOffset+1);

        foreach ($requiredTransitions as $status) {
            $logMessage = "\nSetting " . $issueKey . " To " . $status . "\n";
            $time_start = microtime(true);
            try {
                $transition = new Transition(null, null, __DIR__  . '/../../../');
                if ($status == "Automated") {
                    $transition->fields = [
                        'resolution' => ['name' => 'Done'],
                    ];
                }
                $transition->setTransitionName($status);

                $transitionIssueService = new IssueService(null, null, __DIR__  . '/../../../');

                if (!ZephyrIntegrationManager::$dryRun) {
                    $response = $transitionIssueService->transition($issueKey, $transition);
                    $time_end = microtime(true);
                    $time = $time_end - $time_start;
                    print($logMessage . "Completed In $time Seconds\n");
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                        $logMessage . "Completed In $time Seconds\n"
                    );
                } else {
                    print("Dry Run... " . $logMessage . "Completed!\n");
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                        "Dry Run... " . $logMessage . "Completed!\n"
                    );
                }
            } catch (JiraException $e) {
                print("\nException Occurs In JIRA transition(). " . $e->getMessage());
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->warn(
                    "\nException Occurs In JIRA transition(). " . $e->getMessage()
                );
                $success = false;
                for ($i = 0; $i < ZephyrIntegrationManager::$retryCount; $i++) {
                    print("\nRetry # $i...\n");
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info("\nRetry # $i...\n");
                    try {
                        $transition = new Transition(null, null, __DIR__  . '/../../../');
                        $transition->setTransitionName($status);
                        $transitionIssueService = new IssueService(null, null, __DIR__  . '/../../../');
                        $response = $transitionIssueService->transition($issueKey, $transition);
                        $time_end = microtime(true);
                        $time = $time_end - $time_start;
                        print($logMessage . "Completed In $time Seconds\n");
                        LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                            $logMessage . "Completed In $time Seconds\n"
                        );
                        $success = true;
                        break;
                    } catch (JiraException $e2) {
                        $e = $e2;
                    }
                }
                if (!$success) {
                    print(
                        "While Processing "
                        . $logMessage
                        . "After "
                        . ZephyrIntegrationManager::$retryCount
                        . " Tries, Still Getting JIRA Exception: "
                        . $e->getMessage()
                    );
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->warn(
                        "While Processing "
                        . $logMessage
                        . "After "
                        . ZephyrIntegrationManager::$retryCount
                        . " Tries, Still Getting JIRA Exception: "
                        . $e->getMessage()
                    );
                    print("\nExiting With Code 1\n");
                    exit(1);
                }
            }
        }
    }

    /**
     * One step transit an issue to next status
     *
     * @param string $key
     * @param string $status
     * @return void
     * @throws \Exception
     */
    public function oneStepStatusTransition($key, $status)
    {
        $logMessage = "\nSetting $key To $status\n";
        $time_start = microtime(true);
        try {
            $transition = new Transition(null, null, __DIR__  . '/../../../');
            $transition->setTransitionName($status);
            if ($status == "Automated") {
                $transition->fields = [
                    'resolution' => ['name' => 'Done'],
                ];
            }

            if (!ZephyrIntegrationManager::$dryRun) {
                $transitionIssueService = new IssueService(null, null, __DIR__  . '/../../../');
                $response = $transitionIssueService->transition($key, $transition);
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                print($logMessage . "Completed In $time Seconds\n");
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                    $logMessage . "Completed In $time Seconds\n"
                );
            } else {
                print("Dry Run... " . $logMessage . "Completed!\n");
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                    "Dry Run... " . $logMessage . "Completed!\n"
                );
            }
        } catch (JiraException $e) {
            print("\nException Occurs In JIRA transition(). " . $e->getMessage());
            LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->warn(
                "\nException Occurs In JIRA transition(). " . $e->getMessage()
            );
            $success = false;
            for ($i = 0; $i < ZephyrIntegrationManager::$retryCount; $i++) {
                print("\nRetry # $i...\n");
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info("\nRetry # $i...\n");
                try {
                    $transition = new Transition(null, null, __DIR__  . '/../../../');
                    $transition->setTransitionName($status);
                    if ($status == "Automated") {
                        $transition->fields = [
                            'resolution' => ['name' => 'Done'],
                        ];
                    }
                    $transitionIssueService = new IssueService(null, null, __DIR__  . '/../../../');
                    $response = $transitionIssueService->transition($key, $transition);
                    $time_end = microtime(true);
                    $time = $time_end - $time_start;
                    print($logMessage . "Completed In $time Seconds\n");
                    LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->info(
                        $logMessage . "Completed In $time Seconds\n"
                    );
                    $success = true;
                    break;
                } catch (JiraException $e2) {
                    $e = $e2;
                }
            }
            if (!$success) {
                print(
                    "While Processing "
                    . $logMessage
                    . "After "
                    . ZephyrIntegrationManager::$retryCount
                    . " Tries, Still Getting JIRA Exception: "
                    . $e->getMessage()
                );
                LoggingUtil::getInstance()->getLogger(TransitionIssue::class)->warn(
                    "While Processing "
                    . $logMessage
                    . "After "
                    . ZephyrIntegrationManager::$retryCount
                    . " Tries, Still Getting JIRA Exception: "
                    . $e->getMessage()
                );
                print("\nExiting With Code 1\n");
                exit(1);
            }
        }
    }
}
