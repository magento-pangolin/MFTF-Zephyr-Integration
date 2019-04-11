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
     * Note for test update
     */
    const NOTE_FOR_UPDATE = "\nUpdated by mftf zephyr integration from test: ";

    /**
     * Label for no match issue
     */
    const NO_MATCH_LABEL = 'mzi_no_match';

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
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
                    "\nException Occurs In JIRA update(). " . $e->getMessage()
                );
                $success = false;
                for ($i = 0; $i < ZephyrIntegrationManager::$retryCount; $i++) {
                    print("\nRetry # $i...\n");
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info("\nRetry # $i...\n");
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
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
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
        if (isset($update['skip'])) {
            if ($update['status'] != "Automated") {
                $transitionExecutor->statusTransitionToAutomated($update['key'], $update['status']);
            }
            $transitionExecutor->oneStepStatusTransition($update['key'], "Skipped");
            $this->skipTestLinkIssue($update);
        }
        // Transition issue status to "Automated"
        if (isset($update['unskip'])) {
            $transitionExecutor->oneStepStatusTransition($update['key'], "Automated");
        }
    }

    /**
     * Label unmatched zephyr issue by key
     *
     * @param array $zephyrTest
     * @param string $key
     *
     * @return void
     * @throws \Exception
     */
    public function labelIssueREST(array $zephyrTest, $key)
    {
        $issueField = new IssueField(null, null, __DIR__  . '/../../../');

        foreach ($zephyrTest['labels'] as $label) {
            $issueField->addLabel($label);
        }
        if (in_array(self::NO_MATCH_LABEL . ZephyrIntegrationManager::$timestamp, $zephyrTest['labels']) === false) {
            $issueField->addLabel(self::NO_MATCH_LABEL . ZephyrIntegrationManager::$timestamp);
        }

        $logMessage = "\nAdding Unmatched Label for Test: $key\n";
        $logMessage .= "Summary: " . $zephyrTest['summary'] . "\n";
        $logMessage .= "Stories: ";
        if (isset($zephyrTest[JiraInfo::JIRA_FIELD_STORIES])) {
            $logMessage .= $zephyrTest[JiraInfo::JIRA_FIELD_STORIES];
        }
        $logMessage .= "\n";

        $logMessage .= "Release Line: ";
        if (isset($zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE])) {
            $logMessage .= $zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'];
        }
        $logMessage .= "\n";

        $logMessage .= "Status: " . $zephyrTest['status']['name']. "\n";
        $logMessage .= $this->getLinkedIssues($zephyrTest);

        $logMessage .= "Labels: ";
        if (isset($zephyrTest['labels'])) {
            $logMessage .= implode(',', $zephyrTest['labels']);
        }
        $logMessage .= "\n";

        $time_start = microtime(true);
        if (!ZephyrIntegrationManager::$dryRun) {
            try {
                $issueService = new IssueService(null, null, __DIR__  . '/../../../');
                $response = $issueService->update($key, $issueField);
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                $logMessage .= "Completed In $time Seconds\n";
            } catch (JiraException $e) {
                print("\nException Occurs In JIRA update(). " . $e->getMessage());
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
                    "\nException Occurs In JIRA update(). " . $e->getMessage()
                );
                $success = false;
                for ($i = 0; $i < ZephyrIntegrationManager::$retryCount; $i++) {
                    print("\nRetry # $i...\n");
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info("\nRetry # $i...\n");
                    try {
                        $issueService = new IssueService(null, null, __DIR__  . '/../../../');
                        $issueService->update($key, $issueField);
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
                    LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
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
            $issueField->setDescription(
                $update['description']
                . self::NOTE_FOR_UPDATE
                . $update['mftf_test_name']
                . "\n"
            );
            $this->updatedFields .=
                "description = "
                . $update['description']
                . self::NOTE_FOR_UPDATE
                . $update['mftf_test_name']
                . "\n";
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
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
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

    /**
     * Return information for all linked issues for a test
     *
     * @param array $test
     *
     * @return string
     * @throws \Exception
     */
    private function getLinkedIssues(array $test)
    {
        $linkInfo = "Issues linked:\n";
        if (!isset($test[JiraInfo::JIRA_FIELD_ISSUE_LINKS])) {
            return $linkInfo;
        }
        for ($i = 0; $i < count($test[JiraInfo::JIRA_FIELD_ISSUE_LINKS]); $i++) {
            $link = $test[JiraInfo::JIRA_FIELD_ISSUE_LINKS][$i];
            $linkInfo .= "#" . strval($i+1) . ":\n";

            if (isset($link[JiraInfo::JIRA_FIELD_INWARD_ISSUE])) {
                $linkInfo .= "Inward Issue = " . $link[JiraInfo::JIRA_FIELD_INWARD_ISSUE]['key'] . " ";
                $linkInfo .= "Issue Type = " . $link['type'][JiraInfo::JIRA_FIELD_INWARD] . "\n";
            }

            if (isset($link[JiraInfo::JIRA_FIELD_OUTWARD_ISSUE])) {
                $linkInfo .= "Outward Issue = " . $link[JiraInfo::JIRA_FIELD_OUTWARD_ISSUE]['key'] . " ";
                $linkInfo .= "Issue Type = " . $link['type'][JiraInfo::JIRA_FIELD_OUTWARD] . "\n";
            }
        }
        return $linkInfo;
    }
}
