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
     * Updated fields as a concatenated string
     *
     * @var string
     */
    private static $updatedFields = '';

    /**
     * Builds a REST update from array
     * Associates against key
     *
     * @param array $update
     * @param string $key
     * @param string $releaseLine
     * @param bool $isDryRun
     * @return void
     * @throws \Exception
     */
    static function updateIssueREST(array $update, $key, $releaseLine, $isDryRun = true)
    {
        /** Updated fields:
         *
         * summary, description, stories, severity, status, label, ? test type, ? release line // TODO
         *
         * - add mzi_updated label if it does not exist previously
         * - description + test name
         * - print in log if mftf has testCaseID and $testCaseID != $key
         */

        $update += ['key' => $key];
        $issueField = self::buildUpdateIssueField($update);
        //$issueField->setProjectKey("MC");
        //$issueField->setIssueType("Test");
        $logMessage = $update['key'] . " with data: \n" . self::$updatedFields;
        if (!$isDryRun) {
            try {
                $issueService = new IssueService(null, null, realpath('../../../').'/');
                $time_start = microtime(true);
                $issueService->update($update['key'], $issueField);
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                $logMessage = "UPDATED TEST: " . $logMessage . " Took time:  " . $time . "\n";
            } catch (JiraException $e) {
                print_r("JIRA Exception: " . $e->getMessage());
                LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info("JIRA Exception: " . $e->getMessage());
            }
        } else {
            $logMessage = "Dry Run... UPDATED TEST: " . $logMessage . "\n";
        }
        print_r($logMessage);
        LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info($logMessage);

        // Transition issue status to "Skipped"
        if (isset($update['skip'])) {
            if (!($update['status'] == "Automated")) {
                TransitionIssue::statusTransitionToAutomated($update['key'], $update['status']['name'], $isDryRun);
            }
            TransitionIssue::oneStepStatusTransition($update['key'], "Skipped", $isDryRun);
            self::skipTestLinkIssue($update, $isDryRun);
        }
        // Transition issue status to "Automated"
        if (isset($update['unskip'])) {
            if (!($update['status'] == "Automated")) {
                TransitionIssue::statusTransitionToAutomated($update['key'], $update['status']['name'], $isDryRun);
            }
        }
    }

    /**
     * Sets the issueField values if they exist in $update
     * Value exists in $update only if it differs from existing zephyr data and requires update
     *
     * @param array $update
     * @return IssueField
     */
    public static function buildUpdateIssueField(array $update)
    {
        self::$updatedFields = '';
        $issueField = new IssueField();
        if (isset($update['title'])) {
            $issueField->setSummary($update['title']);
            self::$updatedFields .= "title = " . $update['title'] . "\n";
        }
        if (isset($update['description'])) {
            $issueField->setDescription($update['description']);
            self::$updatedFields .= "description = " . $update['description'] . "\n";
        }
        if (isset($update['stories']) && !empty($update['stories'])) {
            $issueField->addCustomField('customfield_14364', $update['stories']);
            self::$updatedFields .= "stories = " . $update['stories'] . "\n";
        }
        if (isset($update['severity'])) {
            $issueField->addCustomField('customfield_12720', ['value' => $update['severity']]);
            self::$updatedFields .= "severity = " . $update['severity'] . "\n";
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
    public static function skipTestLinkIssue(array $update, $isDryRun = true)
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
            print_r($logMessage);
            LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info($logMessage);

        } catch (JiraException $e) {
            print_r("JIRA Exception: " . $e->getMessage());
            LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info("JIRA Exception: " . $e->getMessage());
        }
    }
}
