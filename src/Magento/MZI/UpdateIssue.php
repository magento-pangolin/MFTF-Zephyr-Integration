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
     * @param bool $isDryRun
     * @return void
     * @throws \Exception
     */
    public function updateIssueREST(array $update, $key, $isDryRun = true)
    {
        /** Updated fields:
         *
         * summary, description, stories, severity, status, label, release line, ? test type
         *
         * - add mzi_updated label if it does not exist previously
         * - description + test name
         * - print in log if mftf has testCaseID and $testCaseID != $key
         */

        $update += ['key' => $key];
        $issueField = $this->buildUpdateIssueField($update);
        $issueField->setProjectKey("MC");
        $issueField->setIssueType("Test");
        $logMessage = $update['key'] . " with data: \n" . $this->updatedFields;
        if (!$isDryRun) {
            try {
                $issueService = new IssueService();
                $time_start = microtime(true);
                $response = $issueService->update($update['key'], $issueField); // return true on success
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                $logMessage = "UPDATED TEST: " . $logMessage . " Took time:  " . $time . "\n";
            } catch (JiraException $e) {
                print("JIRA Exception: " . $e->getMessage());
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
                    "JIRA Exception: " . $e->getMessage()
                );
            }
        } else {
            $logMessage = "Dry Run... UPDATED TEST: " . $logMessage . "\n";
        }
        print($logMessage);
        LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info($logMessage);

        // Transition issue status to "Skipped"
        $transitionExecutor = new TransitionIssue();
        if (isset($update['skip'])) {
            if ($update['status'] != "Automated") {
                $transitionExecutor->statusTransitionToAutomated($update['key'], $update['status'], $isDryRun);
            }
            $transitionExecutor->oneStepStatusTransition($update['key'], "Skipped", $isDryRun);
            $this->skipTestLinkIssue($update, $isDryRun);
        }
        // Transition issue status to "Automated"
        if (isset($update['unskip'])) {
            $transitionExecutor->oneStepStatusTransition($update['key'], "Automated", $isDryRun);
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
        $issueField = new IssueField();
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
            $issueField->addCustomField('customfield_14364', $update['stories']);
            $this->updatedFields .= "stories = " . $update['stories'] . "\n";
        }

        if (isset($update['severity'])) {
            $issueField->addCustomField('customfield_12720', ['value' => $update['severity']]);
            $this->updatedFields .= "severity = " . $update['severity'] . "\n";
        }

        if (isset($update['release_line'])) {
            $issueField->addCustomField('customfield_14121', ['value' => $update['release_line']]);
            $this->updatedFields .= "release line = " . $update['release_line'] . "\n";
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
     * @param bool $isDryRun
     * @throws \Exception
     */
    public function skipTestLinkIssue(array $update, $isDryRun = true)
    {
        try {
            $il = new IssueLink();

            $il->setInwardIssue($update['skip'][0])
                ->setOutwardIssue($update['key'])
                ->setLinkTypeName('Blocks' )
                ->setComment('Blocking issue for Skipped test');

            $logMessage = "\nLinked Issue: " . $update['skip'][0] . " to SKIPPED Test: " . $update['key'];
            if (!$isDryRun) {
                $ils = new IssueLinkService();

                $time_start = microtime(true);
                $ret = $ils->addIssueLink($il);
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                $logMessage .=  " Took time: $time\n";
            } else {
                $logMessage .= "\n";
            }
            print($logMessage);
            LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info($logMessage);

        } catch (JiraException $e) {
            print("JIRA Exception: " . $e->getMessage());
            LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
                "JIRA Exception: " . $e->getMessage()
            );
        }
    }
}
