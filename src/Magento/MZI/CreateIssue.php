<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use Magento\MZI\Util\LoggingUtil;
use Magento\MZI\GetZephyr;

class CreateIssue
{
    /**
     * Test containing all information for issue to be created from MFTF annotations
     *
     * @var array
     */
    public $test;

    /**
     * createIssue constructor.
     * @param $id
     */
    function __construct($id)
    {
        $this->test = $this->defaultMissingFields($id);
    }

    /**
     * For any missing required fields on a test to be created,
     * sets default fields
     *
     * @param $test
     * @return array
     */
    static function defaultMissingFields($test)
    {
        if (!(isset($test['stories']))) {
            $test['stories'][0] = '';
        }
        if (!(isset($test['severity']))) {
            $test['severity'][0] = '4-Minor';
        }
        if (!(isset($test['title']))) {
            $test['title'][0] = 'NO TITLE';
        }
        if (!(isset($test['description']))) {
            $test['description'][0] = 'NO DESCRIPTION';
        }
        return $test;
    }

    /**
     * Creates an issue in Zephyr from test data
     * Will transition new issue to Automated status
     * If test is skipped, will call skip transition and issuelink functions
     *
     * @param string $test
     * @param string $releaseLine
     * @param bool $isDryRun
     *
     * @return String
     * @throws \Exception
     */
    static function createIssueREST($test, $releaseLine, $isDryRun = true)
    {
        $test = self::defaultMissingFields($test);
        $issueField = new IssueField();
        /** Created fields:
         *
         * - Required fields:
         * project, issueType, summary, components, severity, test type, release line
         *
         * - Additional fields:
         * stories
         * status
         * label
         * description + test name
        */

        $issueField->setProjectKey('MC');
        $issueField->setSummary($test['title'][0]);
        //$issueField->setAssigneeName(getenv('JIRA_USER'));
        $issueField->setIssueType('Test');
        $issueField->setDescription($test['description'][0]);
        $issueField->addComponents(self::getZephyrComponentName($test['features'][0]));
        if (!emtpy($test['stories'][0])) {
            $issueField->addCustomField('customfield_14364', $test['stories'][0]);
        }
        //$issueField->addCustomField('customfield_14362', ['value' => 'Catalog']);// TODO what's this field
        $issueField->addCustomField('customfield_12720', ['value' => $test['severity'][0]]);
        $issueField->addCustomField('customfield_13324', ['value' => 'MFTF Test']); // Test Type
        $issueField->addCustomField('customfield_14121', ['value' => $releaseLine]); // Release Line
        $issueField->addLabel('mzi_created');

        $key = '';
        $logMessage = $issueField->summary . " " . $issueField->description;
        if (!$isDryRun) {
            $issueService = new IssueService(null, null, realpath('../../../').'/');
            $time_start = microtime(true);
            $ret = $issueService->create($issueField);
            $key = $ret->key;
            $time_end = microtime(true);
            $time = $time_end - $time_start;
            $logMessage = "\nCREATED NEW TEST: " . $key . ": " . $logMessage . " Took time:  " . $time . "\n";
            print_r($logMessage);
        } else {
            $logMessage = "Dry Run... CREATED NEW TEST: " . $logMessage . "\n";
            $key = 'MC-000'; // Dummy MC key
        }
        LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info($logMessage);

        // transition this newly created issue from "Open" to "AUTOMATED" or "Skipped"
        TransitionIssue::statusTransitionToAutomated($key, 'Open', $isDryRun);
        if (isset($test['skip'])) {
            $test += ['key' => $key];
            TransitionIssue::oneStepStatusTransition($key, 'Skipped', $isDryRun);
            UpdateIssue::skipTestLinkIssue($test, $isDryRun);
        }
        return $key;
    }

    /**
     * @param string $feature
     * @return string
     * @throws \Exception
     */
    private static function getZephyrComponentName($feature)
    {
        $zephyr = new GetZephyr();
        $components = $zephyr->getComponentsForProject();
        foreach ($components as $component) {
            if (stripos($component, 'Module/ ' . $feature) !== false) {
                return $component;
            }
        }
        foreach ($components as $component) {
            if (stripos($feature, substr($component, 8)) !== false) {
                return $component;
            }
        }
        return 'Module/ Backend';
    }
}
