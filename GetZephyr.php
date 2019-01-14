<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

//require 'vendor/autoload.php';
namespace Magento\JZI;

require(__DIR__ . "/../../../vendor/autoload.php");

use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;

/**
 * Class GetZephyr
 * @package Magento\JZI
 */
class GetZephyr {

    /**
     * Recursive fucntion which takes object and returns array of values
     *
     * @param $object
     * @param int $assoc
     * @param string $empty
     * @return array
     */
    public function object_to_array_recursive ( $object, $assoc=1, $empty='' )
    {
        $out_arr = array();
        $assoc = (!empty($assoc)) ? TRUE : FALSE;

        if (!empty($object)) {

            $arrObj = is_object($object) ? get_object_vars($object) : $object;

            $i=0;
            foreach ($arrObj as $key => $val) {
                $akey = ($assoc !== FALSE) ? $key : $i;
                if (is_array($val) || is_object($val)) {
                    $out_arr[$key] = (empty($val)) ? $empty : $this->object_to_array_recursive($val);
                }
                else {
                    $out_arr[$key] = (empty($val)) ? $empty : (string)$val;
                }
                $i++;
            }

        }

        return $out_arr;
    }

    function getZephyrById($zephyrID){
        $issue = [];
		try {
		    $issueService = new IssueService();
			
		    $queryParam = [
		        'fields' => [  // default: '*all'
		            'summary',
		            'comment',
		        ],
		        'expand' => [
		            'renderedFields',
		            'names',
		            'schema',
		            'transitions',
		            'operations',
		            'editmeta',
		            'changelog',
		        ]
		    ];
		            
		    $issue = $issueService->get($zephyrID, $queryParam);
			
		    //var_dump($issue->fields);

		} catch (JiraException $e) {
			print("Error Occurred! " . $e->getMessage());
		}
        return json_decode($issue);
	}

	public function getIssuesByProject($project) {
        //TODO: Send JQL query and parse results
        //$jql = 'project = '. $project. ' and issueType = Test and status Automated';
        //$jql = 'project = MC AND issueType = Test AND status = Automated AND reporter = treece';
        //$jql = 'project = MC AND issueType = Test AND status = Automated';
        $jql = 'project = MAGETWO and issueType = Test';
        $jql = "project = MAGETWO and issuetype = test and 'Automation Status' in (Automated, Skipped)";
        print_r($jql . "\n");
        $zephyrIDs =[];

        try {
            $query = new IssueService();
            $startAt = 0;
            $maxResults = 5;
            $ret1 = $query->search($jql, $startAt, $maxResults);
            $data = $this->object_to_array_recursive($ret1);
            $startAt = 10;
            $ret2 = $query->search($jql, $startAt, $maxResults);
            $data2 = $this->object_to_array_recursive($ret2);
            $retAll = array_merge($data1 , $data2);
            //$data = json_decode(json_encode($ret), false); //= json_decode(json_encode($response->response->docs), true);
            //$data = json_decode(json_encode($ret), false); //= json_decode(json_encode($response->response->docs), true);
            //var_dump(get_object_vars($ret));
            $data = $this->object_to_array_recursive($retAll, FALSE);
            //print_r($data["issues"][0]);
            foreach ($data['issues'] as $k) {
                $zephyrIDs[$k['key']] = $k['fields']; // creates array of [1001 : MC-01, 1002 : MC-02]
            }
            if (isset($zephyrIDs)) {
                print_r("Zephyr Tests returned: " . count($zephyrIDs) . "\n");
                return $zephyrIDs;
            }
        } catch (JiraException $e) {
            //$this->assertTrue(false, 'testSearch Failed : '.$e->getMessage());
        }
        return $zephyrIDs;
    }

    function getAllZephyrTests($ids) {
        $zephyrTests =[];
        foreach ($ids as $id) {
            //$zephyrTests[] = '$id' => $this->getZephyrById($id); // ?
            //$zephyrTests[$id] = $this->getZephyrById($id);
            $zephyrTests[$id] = $this->getZephyrById($id);  // Returns array of [MC-01 : [arrayOfFields]
        }
        return $zephyrTests;

    }

    function prototypeGetIssuesByProject() {
        return null;

    }

    function prototypeGetAllZephyrTests() {
            $zephyrTests = ["features" => "prototypeFeature", "stories" => "prototypeStory", "title" => "prototypeTitle", "description" => "prototypeDescription", "testCaseId" => "TOM-123"];
            return $zephyrTests;
    }

    /**
     * Gets jql results via pagination
     * Current Jira instance returns max of 2000 issues
     * Function gets page of results, then gets next page and appends data
     * Current page size $maxresult = 100 to prevent timeouts on queries
     *
     * @param $jql
     * @return array
     */
    public function jqlPagination($jql) {
        try {
            $issueService = new IssueService();

            //$jql = "project = MAGETWO and issuetype = test and 'Automation Status' in (Automated, Skipped)";

            $pagination = -1;

            $startAt = 0;	//the index of the first issue to return (0-based)
            $maxResult = 100;	// the maximum number of issues to return (defaults to 50).
            $totalCount = -1;	// the number of issues to return

            // first fetch
            $totalRet = $issueService->search($jql, $startAt, $maxResult);
            $totalCount = $totalRet->total;
            //$totalCount = 300;
            $totalData = $this->object_to_array_recursive($totalRet, FALSE);
            foreach ($totalData['issues'] as $k) {
                $zephyrIDs[$k['key']] = $k['fields']; // creates array of [1001 : MC-01, 1002 : MC-02]
            }

            // do something with fetched data
            foreach ($totalRet->issues as $issue) {
                print (sprintf("%s %s \n", $issue->key, $issue->fields->summary));
            }

            // fetch remained data
            //$page = $totalCount / $maxResult;

            for ($startAt = $maxResult; $startAt < $totalCount; $startAt+=$maxResult) {
                $ret = $issueService->search($jql, $startAt, $maxResult);
                $data = $this->object_to_array_recursive($ret, FALSE);
                foreach ($data['issues'] as $k) {
                    $zephyrIDs[$k['key']] = $k['fields']; // creates array of [1001 : MC-01, 1002 : MC-02]
                }
                //$totalZephyrIDs = $totalZephyrIDs += $zephyrIDs;
                print ("\nPaging $startAt\n");
                print ("-------------------\n");
                foreach ($ret->issues as $issue) {
                    print (sprintf("%s %s \n", $issue->key, $issue->fields->summary));
                }
            }
            Print('Finished collecting Zephyr Tests');
        } catch (JiraException $e) {
            //$this->assertTrue(false, 'testSearch Failed : '.$e->getMessage());
        }
        return $zephyrIDs;
    }

    /**
     * Gets tests from two jql queries
     * Gets tests from both MC and MAGETWO projects
     *
     * @return array
     */
    public function getBothProjects() {
        $jqlMAGETWO = "project = MAGETWO and issuetype = test and 'Automation Status' in (Automated, Skipped)";
        $jqlMC = "project = MC AND issueType = Test AND status in (Automated, Skipped)";
        $zephyrIdsMAGETWO = $this->jqlPagination($jqlMAGETWO);
        $zephyrIdsMC = $this->jqlPagination($jqlMC);
        $zephyrIds = array_merge($zephyrIdsMAGETWO, $zephyrIdsMC);
        return $zephyrIds;
    }
}
//$getZephyr = new GetZephyr();
//$zephyrIds = $getZephyr->getIssuesByProject('tom');
//$zephyrIds = array_slice($zephyrIds,0,5);
//print_r($zephyrIds);