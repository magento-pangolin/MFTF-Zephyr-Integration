<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use JiraRestApi\Issue\Transition;
use JiraRestApi\IssueLink\IssueLink;
use JiraRestApi\IssueLink\IssueLinkService;
use Magento\JZI\Util\LoggingUtil;

/**
 * Class UpdateIssue, builds and sends an update REST call
 * @package Magento\JZI
 */
class UpdateIssue {

    /**
     * Array of data to populate update
     * @var array
     */
    public $Update;

    /**
     * UpdateIssue constructor.
     * @param $Update
     */
    function __construct($Update){
        $this->toUpdate = $Update;
    }

    function templateREST(){
        try{
        $issueField = new IssueField(true);

        $issueField->setAssigneeName("admin")
            ->setPriorityName("Blocker")
            ->setIssueType("Task")
            ->addLabel("test-label-first")
            ->addLabel("test-label-second")
            ->addVersion("1.0.1")
            ->addVersion("1.0.2")
            ->setDescription("This is a shorthand for a set operation on the summary field")
        ;

        // optionally set some query params
        $editParams = [
            'notifyUsers' => false,
        ];

        $issueService = new IssueService(null, null, realpath('../../../').'/');

        // You can set the $paramArray param to disable notifications in example
        $ret = $issueService->update($issueKey, $issueField, $editParams);

        var_dump($ret);
    } catch (JiraException $e) {
        $this->assertTrue(FALSE, "update Failed : " . $e->getMessage());
        }
    }

    /**
     * Builds a REST update from array
     * Associates against key
     *
     * @param array $update
     * @param string $key
     * @return object
     * @throws \Exception
     */
    static function updateIssueREST($update, $key)
    {
        $update += ['key' => $key];
        $issueField = self::buildUpdateIssueField($update);
        if (isset($update['skip'])) {
            if (!($update['status'] == "Automated")) {
                TransitionIssue::statusTransitionToAutomated($update['key'], $update['status']['name']);
            }
            self::skipTestStatusTransition($update);
            self::skipTestLinkIssue($update);
        }
        if ($update['unskip']) {
            TransitionIssue:unskip($update['key']);
        }
        $issueField->setIssueType("Test");
        $issueField->setProjectKey("MC");
        $updateLogString = '';
        foreach($update as $key=>$item) {
            $updateLogString .= $key.':'.$item. ", \n";
        }
        rtrim($updateLogString, ',');
        LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info('TEST sent to UPDATE : ' . $update['key'] . " : " . $updateLogString . "\n");
        $issueService = new IssueService(null, null, realpath('../../../').'/');

        // You can set the $paramArray param to disable notifications in example
        $time_start = microtime(true);
        $ret = $issueService->update($update['key'], $issueField);
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        print_r("\nUpdate took : " . $time . "\n");
        return $ret;
    }

    /**
     * Sets the issueField values if they exist in $update
     * Value exists in $update only if it differs from existing zephyr data and requires update
     *
     * @param array $update
     * @return IssueField
     */
    public static function buildUpdateIssueField($update) {
        $issueField = new IssueField();
        if (isset($update['title'])) {
            $issueField->setSummary($update['title']);
        }
        if (isset($update['description'])) {
            $issueField->setDescription($update['description']);
        }
        if (isset($update['stories'])) {
            $issueField->addCustomField('customfield_14364', $update['stories']);
        }
        if (isset($update['severity'])) {
            $issueField->addCustomField('customfield_12720', ['value' => $update['severity']]);
            //$issueField->addCustomField('customfield_12720', ['value' => $test['severity'][0]])
        }
        return $issueField;
    }

    /**
     * Function that sets additional fields required for transition to AUTOMATED
     *
     * @param array $update
     * @return array
     */
    static function updateRequireTransitionFields($update) {
        $issueField = new IssueField();
        $issueField->setIssueType("Test");
        $issueField->setProjectKey("MC");
        $issueField->environment = "None";
        $issueField->resolution = "Done";
        $issueService = new IssueService(null, null, realpath('../../../').'/');

        // You can set the $paramArray param to disable notifications in example
        $ret = $issueService->update($update['key'], $issueField);
        print_r('Updating reqd fields');
        return $ret;
    }

    /**
     * Transitions an issue to SKIPPED status
     *
     * @param array $update
     */
    public static function skipTestStatusTransition($update) {
        $issueKey = $update['key'];
        try {
            $transition = new Transition();
            $transition->setTransitionName('Skipped');
            $transition->setCommentBody('MFTF INTEGRATION - Setting SKIPPED status.');

            $skipTransitionIssueService = new IssueService(null, null, realpath('../../../').'/');

            $skipTransitionIssueService->transition($issueKey, $transition);
            //if ($skipTransitionIssueService->http_response == 204) {
//                print_r("\n" . "SUCCESS! " . $issueKey . " set to status SKIPPED");
//            }
        } catch (JiraException $e) {
            //$this->assertTrue(FALSE, "add Comment Failed : " . $e->getMessage());
        }
    }

    /**
     * Sets an issueLink to the blocking issue for SKIPPED test
     *
     * @param array $update
     */
    public function skipTestLinkIssue($update) {
        try {
            $il = new IssueLink();

            $il->setInwardIssue($update['skip'][0])
                ->setOutwardIssue($update['key'])
                ->setLinkTypeName('Blocks' )
                ->setComment('Blocking issue for Skipped test');

            $ils = new IssueLinkService();

            $time_start = microtime(true);
            $ret = $ils->addIssueLink($il);
            $time_end = microtime(true);
            $time = $time_end - $time_start;
            print_r("\nSkip Test Issue Link took  : " . $time . "\n");

        } catch (JiraException $e) {
            print("Error Occured! " . $e->getMessage());
        }
    }

    static function updateDryRunIssuesREST($update, $key)
    {
        $update += ['key' => $key];
        $issueField = self::buildUpdateIssueField($update);
        if (isset($update['skip'])) {
            self::dryRunSkipTestStatusTransition($update);
            self::dryRunSkipTestLinkIssue($update);
        }
        $issueField->setIssueType("Test");
        $issueField->setProjectKey("MC");
        $updateLogString = '';
        foreach($update as $key=>$item) {
            $updateLogString .= $key.':'.$item. ", \n";
        }
        rtrim($updateLogString, ',');
        LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info('TEST sent to UPDATE : ' . $update['key'] . " : " . $updateLogString . "\n");
        //LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info("UPDATE :" . $update['key']
        //. "\n ");
    }

    public static function dryRunSkipTestStatusTransition($update) {
        $issueKey = $update['key'];
        try {
            $transition = new Transition();
            $transition->setTransitionName('Skipped');
            $transition->setCommentBody('MFTF INTEGRATION - Setting SKIPPED status.');

            $skipTransitionIssueService = new IssueService(null, null, realpath('../../../').'/');

            //$skipTransitionIssueService->transition($issueKey, $transition);
            //if ($skipTransitionIssueService->http_response == 204) {
//                print_r("\n" . "SUCCESS! " . $issueKey . " set to status SKIPPED");
//            }
        } catch (JiraException $e) {
            //$this->assertTrue(FALSE, "add Comment Failed : " . $e->getMessage());
        }
    }

    public static function dryRunSkipTestLinkIssue($update) {
        try {
            $il = new IssueLink();

            $il->setInwardIssue($update['skip'])
                ->setOutwardIssue($update['key'])
                ->setLinkTypeName('Blocks' )
                ->setComment('Blocking issue for Skipped test');

            $ils = new IssueLinkService(null, null, realpath('../../../').'/');

            //$ret = $ils->addIssueLink($il);

        } catch (JiraException $e) {
            print("Error Occured! " . $e->getMessage());
        }
    }
}