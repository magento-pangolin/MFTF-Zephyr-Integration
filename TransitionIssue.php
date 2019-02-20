<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;

require_once ('../../autoload.php');

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use JiraRestApi\Issue\Transition;
//include_once ('UpdateIssue.php');
//include_once ('../../lesstif/php-jira-rest-client/src/Issue/Transition.php');
//include_once ('../../lesstif/php-jira-rest-client/src/Issue/IssueService.php');

/**
 * Class transitionIssue, handles issue transitions in Zephyr
 * @package Magento\JZI
 */
class transitionIssue
{

    /**
     * Transitions an MC project issue to AUTOMATED status
     *
     * @param string $key
     * @param string $status
     * @return
     * @throws JiraException $e
     */
    public static function statusTransitionToAutomated($key, $status) {
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
        $requiredTransitons = array_slice($projectMcTransitionStates, $currentStatusOffset+1);

        $time_start = microtime(true);
        foreach ($requiredTransitons as $status) {
            try {
                $transition = new Transition();
                if ($status == "Automated") {
                    //UpdateIssue::updateRequireTransitionFields($update);
                    // 'customfield_13783' => ['value' =>'None'],
                    $transition->fields = ['resolution' => ['name' => 'Done'], 'customfield_13783' => ['value' =>'Unknown']];
                }
                $transition->setTransitionName($status);
                $transition->setCommentBody("MFTF INTEGRATION - Setting " . $status . " status.");

                $skipTransitionIssueService = new IssueService();

                $skipTransitionIssueService->transition($issueKey, $transition);
//                if ($skipTransitionIssueService->http_response == 204) {
//                print_r("\n" . "SUCCESS! " . $issueKey . " set to status "  . $status);
//            }
            } catch (JiraException $e) {
                //$this->assertTrue(FALSE, "add Comment Failed : " . $e->getMessage());
            }
        }
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        print_r("\nTransition to Automated took : " . $time . "\n");

    }


    public static function unskip($key) {
        try {
            $transition = new Transition();
            $transition->setTransitionName('Automated');
            $transition->setCommentBody('MFTF INTEGRATION - UNSKIPPING.');
            $skipTransitionIssueService = new IssueService();
            $skipTransitionIssueService->transition($key, $transition);
            //if ($skipTransitionIssueService->http_response == 204) {
//                print_r("\n" . "SUCCESS! " . $issueKey . " set to status SKIPPED");
//            }
        } catch (JiraException $e) {
            //$this->assertTrue(FALSE, "add Comment Failed : " . $e->getMessage());
        }
    }
}

