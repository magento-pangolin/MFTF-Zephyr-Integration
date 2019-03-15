<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use JiraRestApi\Issue\IssueService;
use JiraRestApi\Project\ProjectService;
use JiraRestApi\JiraException;
use Magento\MZI\Util\LoggingUtil;

/**
 * Class GetZephyr
 * @package Magento\MZI
 */
class GetZephyr
{
    /**
     * Project Components
     *
     * @var array
     */
    private static $projectComponents = [];

    /**
     * @var GetZephyr
     */
    private static $instance;

    /**
     * GetZephyr constructor
     */
    private function __construct()
    {
        // private constructor
    }

    /**
     * Static singleton getInstance
     *
     * @return GetZephyr
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new GetZephyr();
        }
        return self::$instance;
    }

    /**
     * Gets tests from project key
     * @param string $projectKey
     *
     * @return array
     * @throws \Exception
     */
    public function getTestsByProject($projectKey = 'MC')
    {
        $jql = "project = $projectKey AND issueType = Test AND status in (Automated, Skipped) ORDER BY key ASC";
        return $this->jqlPagination($jql);
    }

    /**
     * Get component names for a given project key
     *
     * @param string $projectKey
     * @return array
     * @throws \Exception
     */
    public function getComponentsForProject($projectKey = 'MC')
    {
        if (isset(self::$projectComponents[$projectKey])) {
            return self::$projectComponents[$projectKey];
        }
        self::$projectComponents[$projectKey] = [];
        $projectService = new ProjectService(null, null, __DIR__  . '/../../../');
        $project = $projectService->get($projectKey);
        foreach ($project->components as $component) {
            if (strpos($component->name, 'Module/') === false) {
                continue;
            }
            if (empty(self::$projectComponents[$projectKey])
                || array_search($component->name, self::$projectComponents[$projectKey]) === false) {
                self::$projectComponents[$projectKey][] = $component->name;
            }
        }
        return self::$projectComponents[$projectKey];
    }

    /**
     * Gets jql results via pagination
     * Current Jira instance returns max of 2000 issues
     * Function gets page of results, then gets next page and appends data
     * Current page size $maxresult = 100 to prevent timeouts on queries
     *
     * @param $jql
     * @return array
     * @throws \Exception
     */
    private function jqlPagination($jql)
    {
        try {
            print ("\nFetching zephyr tests by jql...\n");

            $zephyrIDs = [];
            $issueService = new IssueService(null, null, __DIR__  . '/../../../');
            $startAt = 0;	//the index of the first issue to return (0-based)
            $maxResult = 100;	// the maximum number of issues to return (defaults to 50).

            // first fetch
            $totalRet = $issueService->search($jql, $startAt, $maxResult);
            $totalCount = $totalRet->total; // the total number of issues to return
            ZephyrIntegrationManager::$totalZephyr = $totalCount;
            print ("Total zephyr tests: $totalCount\n");

            print ("\nPaging starts at $startAt\n");
            print ("-------------------------------\n");
            $totalData = $this->object_to_array_recursive($totalRet);
            foreach ($totalData['issues'] as $k) {
                $zephyrIDs[$k['key']] = $k['fields']; // creates array of [1001 : MC-01, 1002 : MC-02]
                print (sprintf("%s %s \n", $k['key'], $k['fields']['summary']));
            }

            // fetch remaining data
            for ($startAt = $maxResult; $startAt < $totalCount; $startAt+=$maxResult) {
                print ("\nPaging starts at $startAt\n");
                print ("-------------------------------\n");
                $ret = $issueService->search($jql, $startAt, $maxResult);
                $data = $this->object_to_array_recursive($ret);
                foreach ($data['issues'] as $k) {
                    $zephyrIDs[$k['key']] = $k['fields']; // creates array of [1001 : MC-01, 1002 : MC-02]
                    print (sprintf("%s %s \n", $k['key'], $k['fields']['summary']));
                }
            }
            print("\nFinished collecting Zephyr Tests\n\n");
        } catch (JiraException $e) {
            print("JIRA Exception: " . $e->getMessage());
            LoggingUtil::getInstance()->getLogger(UpdateIssue::class)->info(
                "JIRA Exception: " . $e->getMessage()
            );
            print("\nException occurs in JIRA search(), exiting with code 1\n");
            exit(1);
        }
        return $zephyrIDs;
    }

    /**
     * Recursive function which takes object and returns array of values
     *
     * @param mixed $object
     * @return mixed
     */
    private function object_to_array_recursive($object)
    {
        $out_arr = is_object($object) ? get_object_vars($object) : $object;
        foreach ($out_arr as $key => $val) {
            if (is_array($val) || is_object($val)) {
                $out_arr[$key] = $this->object_to_array_recursive($val);
            } else {
                $out_arr[$key] = $val;
            }
        }
        return $out_arr;
    }
}
