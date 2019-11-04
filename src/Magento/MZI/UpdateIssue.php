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
use JiraRestApi\IssueLink\IssueLink;
use JiraRestApi\IssueLink\IssueLinkService;
use Magento\MZI\Util\JiraInfo;
use Magento\MZI\Util\LoggingUtil;

/**
 * Class UpdateIssue, builds and sends an update REST call
 * @package Magento\MZI
 */
class UpdateIssue
{
    /**
     * Label for updated issue
     */
    const UPDATE_LABEL = 'mzi_updated';

    /**
     * Updated fields as a concatenated string
     *
     * @var string
     */
    private $updatedFields = '';

    /**
     * Builds a REST update from array
     * Associates against key
     *
     * @param array $update
     * @param string $key
     * @return void
     * @throws \Exception
     */
    public function updateIssueREST(array $update, $key)
    {
        /** Updated fields:
         *
         * summary, description, stories, severity, status, label, test type
         *
         * - add mzi_updated label if it does not exist previously
         * - description + test name
         * - print in log if mftf has testCaseID and $testCaseID != $key
         */

        $update += ['key' => $key];
        $issueField = $this->buildUpdateIssueField($update);
        $issueField->setProjectKey(ZephyrIntegrationManager::$project);
        $issueField->setIssueType("Test");
        $logMessage = "\nUpdating Test " . $update['key'] . " With Data: \n" . $this->updatedFields;
        $time_start = microtime(true);
        if (!ZephyrIntegrationManager::$dryRun) {
            try {
                $issueService = new IssueService(null, null, __DIR__  . '/../../../');
                $response = $issueService->update($update['key'], $issueField);
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                $logMessage .= "Completed In $time Seconds\n";
            } catch (JiraException $e) {
                print("\nException Occurs In JIRA update(). " . $e->getMessage());
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->warn(
                    "\nException Occurs In JIRA update(). " . $e->getMessage()
                );
                $success = false;
                for ($i = 0; $i < ZephyrIntegrationManager::$retryCount; $i++) {
                    print("\nRetry # $i...\n");
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->warn("\nRetry # $i...\n");
                    try {
                        $issueService = new IssueService(null, null, __DIR__  . '/../../../');
                        $issueService->update($update['key'], $issueField);
                        $time_end = microtime(true);
                        $time = $time_end - $time_start;
                        $logMessage .= "Completed In $time Seconds\n";
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
                        . " After "
                        . ZephyrIntegrationManager::$retryCount
                        . " Tries, Still Getting JIRA Exception: "
                        . $e->getMessage()
                    );
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->warn(
                        "While Processing "
                        . $logMessage
                        . " After "
                        . ZephyrIntegrationManager::$retryCount
                        . " Tries, Still Getting JIRA Exception: "
                        . $e->getMessage()
                    );
                    print("\nExiting With Code 1\n");
                    exit(1);
                }
            }
        } else {
            $logMessage = "Dry Run... $logMessage" . "Completed!\n\n";
        }
        print($logMessage);
        LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info($logMessage);

        // Transition issue status to "Skipped"
        $transitionExecutor = new TransitionIssue(null, null, __DIR__  . '/../../../');
        if (isset($update['skip']) && $update['status'] != "Skipped") {
            $transitionExecutor->statusTransitionToAutomated($update['key'], $update['status']);
            $transitionExecutor->oneStepStatusTransition($update['key'], "Skipped");
            $this->skipTestLinkIssue($update);
        }
        // Transition issue status to "Automated"
        if (isset($update['automate']) && $update['status'] != "Automated") {
            $transitionExecutor->statusTransitionToAutomated($update['key'], $update['status']);
        }
    }

    /**
     * Sets the issueField values if they exist in $update
     * Value exists in $update only if it differs from existing zephyr data and requires update
     *
     * @param array $update
     * @return IssueField
     */
    private function buildUpdateIssueField(array $update)
    {
        $this->updatedFields = '';
        $issueField = new IssueField(null, null, __DIR__  . '/../../../');
        if (isset($update['title'])) {
            $issueField->setSummary($update['title']);
            $this->updatedFields .= "title = " . $update['title'] . "\n";
        }

        if (isset($update['description'])) {
            $description = $update['description']
                . "\n\n"
                . ZephyrComparison::SYNC_END_DELIMITER
                . "\n\n"
                . $update['sticky_description']
                . "\n";
            $issueField->setDescription($description);
            $this->updatedFields .= "description = " . $description;
        }

        if (isset($update['stories']) && !empty($update['stories'])) {
            $issueField->addCustomField(JiraInfo::JIRA_FIELD_STORIES, $update['stories']);
            $this->updatedFields .= "stories = " . $update['stories'] . "\n";
        }

        if (isset($update['severity'])) {
            $issueField->addCustomField(JiraInfo::JIRA_FIELD_SEVERITY, ['value' => $update['severity']]);
            $this->updatedFields .= "severity = " . $update['severity'] . "\n";
        }

        if (isset($update['test_type'])) {
            $issueField->addCustomField(JiraInfo::JIRA_FIELD_TEST_TYPE, ['value' => $update['test_type']]);
            $this->updatedFields .= "test type = " . $update['test_type'] . "\n";
        }

        /* Not update release line ever
        if (isset($update['release_line'])) {
            $issueField->addCustomField(JiraInfo::JIRA_FIELD_RELEASE_LINE, ['value' => $update['release_line']]);
            $this->updatedFields .= "release line = " . $update['release_line'] . "\n";
        }
        */

        foreach ($update['labels'] as $label) {
            $issueField->addLabel($label);
        }
        if (in_array(self::UPDATE_LABEL . ZephyrIntegrationManager::$timestamp, $update['labels']) === false) {
            $issueField->addLabel(self::UPDATE_LABEL . ZephyrIntegrationManager::$timestamp);
        }
        return $issueField;
    }

    /**
     * Sets an issueLink to the blocking issue for SKIPPED test
     *
     * @param array $update
     * @throws \Exception
     */
    public function skipTestLinkIssue(array $update)
    {
        foreach($update['skip'] as $skippedKey) {
            $logMessage = "\nLinking Issue " . $skippedKey . " To Skipped Test " . $update['key'];
            $time_start = microtime(true);
            try {
                $il = new IssueLink(null, null, __DIR__  . '/../../../');

                $il->setInwardIssue($skippedKey)
                    ->setOutwardIssue($update['key'])
                    ->setLinkTypeName('Blocks' );
                //->setComment('Blocking issue for Skipped test');

                if (!ZephyrIntegrationManager::$dryRun) {
                    $ils = new IssueLinkService(null, null, __DIR__  . '/../../../');
                    $ret = $ils->addIssueLink($il);
                    $time_end = microtime(true);
                    $time = $time_end - $time_start;
                    $logMessage .=  ". Completed In $time Seconds\n";
                } else {
                    $logMessage .= "Completed!\n\n";
                }
                print($logMessage);
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info($logMessage);

            } catch (JiraException $e) {
                print(
                    "\nException Occurs In JIRA addIssueLink() On InwardIssue "
                    . $skippedKey
                    . " OutwardIssue "
                    . $update['key']
                    . "\n"
                    . $e->getMessage()
                );
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->warn(
                    "\nException Occurs In JIRA addIssueLink() On InwardIssue "
                    . $skippedKey
                    . " OutwardIssue "
                    . $update['key']
                    . "\n"
                    . $e->getMessage()
                );
                $success = false;
                for ($i = 0; $i < ZephyrIntegrationManager::$retryCount; $i++) {
                    print("\nRetry # $i...\n");
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info("\nRetry # $i...\n");
                    try {
                        $ils = new IssueLinkService(null, null, __DIR__  . '/../../../');
                        $ret = $ils->addIssueLink($il);
                        $time_end = microtime(true);
                        $time = $time_end - $time_start;
                        $logMessage .=  "\nCompleted In $time Seconds\n";
                        print($logMessage);
                        LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info($logMessage);
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
                        . " After "
                        . ZephyrIntegrationManager::$retryCount
                        . " Tries, Still Getting JIRA Exception: "
                        . $e->getMessage()
                    );
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
                        "While Processing "
                        . $logMessage
                        . " After "
                        . ZephyrIntegrationManager::$retryCount
                        . " Tries, Still Getting JIRA Exception: "
                        . $e->getMessage()
                    );
                    // Will not exit for linking errors
                    print("\nReport error and continue...\n");
                    //print("\nExiting With Code 1\n");
                    //exit(1);
                }
            }
        }
    }
}
