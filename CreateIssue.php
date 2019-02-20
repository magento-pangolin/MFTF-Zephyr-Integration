<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;

//require 'vendor/autoload.php';

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;

include_once ('TransitionIssue.php');

class createIssue
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
            $test['stories'][0] = 'NO STORY';
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

    function templatecreateIssuesREST($test)
    {
        //need to make multiple payloads for mass create issue
        //need each test as separate array of tuples
        try {
            $issueField = new IssueField();

            $issueField->setProjectKey($test['Project']) //Project not currently recorded in MFTF data
            ->setSummary($test['title'][0])// Use Title from MFTF array
            ->setAssigneeName($test['AssigneeName'])
            ->setIssueType('Test')// OK
            ->setDescription($test['description'][0])// OK
            // ->addVersion($test['version']) // version?
            // ->addComponents(['', '']) // MFTF does not record Components --
            // set issue security if you need.
            //->setSecurityId(10001 /* security scheme id */)
            //->setDueDate('')
            // 'customfield_14362', implode("', '", $test['group'])) // have to implode any customfield that will use multiple values (strings)
            // Add custom Field mappings
            ->addCustomField('customfield_14364', $test['stories'][0])
                ->addCustomField('customfield_14362', implode("', '", $test['group'][0]))// have to implode any customfield that will use multiple values (strings)
                ->addCustomField('customfield_12720', ['value' => $test['severity'][0]])
            ;

            $issueService = new IssueService();

            $ret = $issueService->create($issueField);
            //$ret = $issueService->createMultiple([$issueFieldOne, $issueFieldTwo]);

            //If success, Returns a link to the created issue.
            return $ret;
        } catch (JiraException $e) {
            print("Error Occured! " . $e->getMessage());
        }

    }

    /**
     * Creates an issue in Zephyr from test data
     * Will transition new issue to Automated status
     * If test is skipped, will call skip transition and issuelink functions
     *
     * @param $test
     * @return String
     * @throws \Exception
     */
    static function createIssuesREST($test)
    {
        $test = self::defaultMissingFields($test);
        $issueField = new IssueField();

        $issueField->setProjectKey('MC')
        ->setSummary($test['title'][0])
        ->setAssigneeName('terskine') //TODO: change to auth user
        ->setIssueType('Test')// OK
        ->setDescription($test['description'][0])// OK
        //->addVersion('2.3.0') // version?
        ->addComponents('Module/ Catalog')// MFTF does not record Components --
        ->addCustomField('customfield_14364', $test['stories'][0])
            ->addCustomField('customfield_14362', ['value' => 'Catalog'])// TODO: Read from directory structure
            ->addCustomField('customfield_12720', ['value' => $test['severity'][0]])
            ->addCustomField('customfield_13324', ['value' => 'MFTF Test']);
        //$issueField->fixVersions = [['id'=>'18972']]; // TODO versioning
        $issueField->fixVersions = [['name' => '2.3.0']];

        $issueService = new IssueService();
        $time_start = microtime(true);
        $ret = $issueService->create($issueField);
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        print_r("\n Creating a test took : " . $time . "\n");
        LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info
        ("CREATING REAL REST : " . $issueField->summary . " " . $issueField->description);
        //LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info("CREATED ISSUE: " . $ret->key);

        // transition this newly created issue to AUTOMATED. Newly created status = "Open"
        $status = "Open";
        TransitionIssue::statusTransitionToAutomated($ret->key, $status);
        if (isset($test['skip'])) {
            $test += ['key' => $ret->key];
            updateIssue::skipTestStatusTransition($test);
            updateIssue::skipTestLinkIssue($test);
        }
        //LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info("NEW ISSUE SET TO AUTOMATED: " . $ret-key);
        return $ret->key;
    }

    /**
     * Sets fields and creates new MC zephyr test from MAGETWO zephyr test
     * @param $zephyrTest
     * @return String
     */
    public function createM2Migration($zephyrTest) {
        $test = [];
        if (isset($zephyrTest['description'])) {
            $test['description'][0] = $zephyrTest['description'];
        }
        else {
            $test['description'][0] = "NO DESCRIPTION";
        }

        if (isset($zephyrTest['summary'])) {
            $test['title'][0] = $zephyrTest['summary'];
        }
        else {
            $test['title'][0] = "NO TITLE";
        }

        if (isset($zephyrTest['customfield_12720'])) {
            $test['severity'][0] = $zephyrTest['customfield_12720']['value'];
        }
        else {
            $test['severity'] = '4-Minor';
        }

        $test = self::defaultMissingFields($test);
        $issueField = new IssueField();

        $issueField->setProjectKey('MC')//TODO: No project in MFTF array
        ->setSummary($test['title'][0])// Use Title from MFTF array
        ->setAssigneeName('terskine')// TODO: set to QA_API
        ->setIssueType('Test')// OK
        ->setDescription($test['description'][0])// OK
        //->addVersion('2.3.0') // TOOD versioning
        ->addComponents('Module/ Catalog')// MFTF does not record Components -- TODO: is component used for reporting?
        ->addCustomField('customfield_14364', $test['stories'][0])
            ->addCustomField('customfield_14362', ['value' => 'Catalog'])// have to implode any customfield that will use multiple values (strings)
            ->addCustomField('customfield_12720', ['value' => $test['severity'][0]])
            ->addCustomField('customfield_13324', ['value' => 'MFTF Test']);
        //$issueField->fixVersions = [['id'=>'18972']];
        $issueField->fixVersions = [['name' => '2.3.0']];

        $issueService = new IssueService();
        $ret = $issueService->create($issueField);
        //LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info("CREATED ISSUE: " . $ret->key);

        // transition this newly created issue to AUTOMATED. Newly created status = "Open"
        $status = "Open";
        TransitionIssue::statusTransitionToAutomated($ret->key, $status);
        //LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info("NEW ISSUE SET TO AUTOMATED: " . $ret-key);
        return $ret->key;
    }

    // TODO : REMOVE

    /**
     * This builds the issueFields for a create call for testing/verification
     * Does not send any REST request
     *
     * @param $test
     * @throws \Exception
     */
    static function createDryRunIssuesREST($test)
    {
        $test = self::defaultMissingFields($test);

        $issueField = new IssueField();

        $issueField->setProjectKey('MC')//TODO: No project in MFTF array
        ->setSummary($test['title'][0])// Use Title from MFTF array
        ->setAssigneeName('terskine')// TODO: choose assignee
        ->setIssueType('Test')// OK
        ->setDescription($test['description'][0])// OK
        //->addVersion('2.3.0') // version?
        ->addComponents('Module/ Catalog')// MFTF does not record Components -- TODO: is component used for reporting?
        ->addCustomField('customfield_14364', $test['stories'][0])
            ->addCustomField('customfield_14362', ['value' => 'Catalog'])// have to implode any customfield that will use multiple values (strings)
            // TODO: group value doesnt match to anything in MC. Will have to ignore and find default value from single select dropdown (like 'severity' field below)
            ->addCustomField('customfield_12720', ['value' => $test['severity'][0]])// TODO: for any customfields taking LIST, need to ['value' => 'foo'] and [ ['value => 'foo'], ['value' => 'bar'] ] or multiple list selections
            ->addCustomField('customfield_13324', ['value' => 'MFTF Test']);
        //$issueField->fixVersions = [['id'=>'18972']];
        $issueField->fixVersions = [['name' => '2.3.0']];

        $issueService = new IssueService();
        //$ret = $issueService->create($issueField);
        LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info
        ("DRY RUN : " . $issueField->summary . " " . $issueField->description);
        //LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info("CREATED ISSUE: " . $ret->key);

        // transition this newly created issue to AUTOMATED. Newly created status = "Open"
        $status = "Open";
        //TransitionIssue::statusTransitionToAutomated($ret->key, $status);
        //LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info("NEW ISSUE SET TO AUTOMATED: " . $ret-key);
//        $test += ['key' => $ret->key];
        if (isset($test['skip'])) {
            //updateIssue::skipTestStatusTransition($update);
            //updateIssue::skipTestLinkIssue($update);
            LoggingUtil::getInstance()->getLogger(CreateIssue::class)->info("NEW TEST SET SKIPPED");
            //return $ret->key;
        }
    }
}
