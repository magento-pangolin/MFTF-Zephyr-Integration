<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;

//require_once ('../../autoload.php');
use Magento\JZI\GetZephyr;
use Magento\JZI\ParseMFTF;
include_once ('GetZephyr.php');
include_once ('ParseMFTF.php');
include_once ('ZephyrComparison.php');
include_once ('CreateManager.php');
include_once ('UpdateManager.php');

ini_set('memory_limit', '512M');

class ZephyrIntegrationManager {
	/**
	*Purpose of Manager
	* 1. call GetZephyrTest and store resulting json
	* 2. get MFTF test data from parsers
	* 3. call ZephyrComparison to build list of Create and Update
	* 4. call Creates
	* 5. call Updates
	* 6. Log returns and created IDs
	* 7. Log errors (TODO: manage retries)
	*/

	private $project = 'MC'; //same as JQL search
    private $jql = '';  // Allow invocation to directly pass jql to get Zephyr issues for match
    //TODO: How will this work with Zephyr subset and full tests? How will we prevent CREATE against filter excluded but existing tests

	public function synchronizeZephyr($project) {
		$getZephyr = new GetZephyr();
		$zephyrTests= $getZephyr->getIssuesByProject($project); // This now returns annotation array
		//$zephyrTests = $getZephyr->getAllZephyrTests($zephyrTestList); // This is no longer needed
		// TODO: Will getZephyr manage the querying, looping, and parsing return to create array of Ids or objects?
        $parseMFTF = new ParseMFTF();
        $mftfTests = $parseMFTF->getTestObjects();

		$zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
//		$toCreate = $zephyrComparison->getCreateArrayById();
//		$toUpdate = $zephyrComparison->getUpdateArray();
        $createVerify = $zephyrComparison->matchOnIdOrName();
        $createById =$zephyrComparison->getCreateArrayById();
        $createByName = $zephyrComparison->getCreateArrayByName();
        $skippedTests = $zephyrComparison->checkForSkippedTests();//simpleCompare CreateArrayById

        $createManager = CreateManager::getInstance()->performCreateOperations($createByName, $createById, $skippedTests);
        $createResponse = $createManager->getResponses();
	}

	public function setProject($project) {
	    $this->project = $project;
    }

    public function runMftfZephyrIntegration($project) {

	    $project = (isset($argv[1])) ? $argv[1] : "None";
        $getZephyr = new GetZephyr();
        $zephyrTests = $getZephyr->getBothProjects();

        $parseMFTF = new ParseMFTF();
        $mftfTests = $parseMFTF->getTestObjects();

        $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
        $zephyrComparison->matchOnIdOrName();
        $createById = $zephyrComparison->getCreateArrayById();
        $createByName = $zephyrComparison->getCreateArrayByName();
        $skippedTests = $zephyrComparison->checkForSkippedTests();
        $mismatches = $zephyrComparison->getUpdateArray();

        CreateManager::getInstance()->performDryRunCreateOperations($createByName, $createById, $skippedTests);
        UpdateManager::getInstance()->performDryRunUpdateOperations($mismatches);
    }


public function realScopedToJZIDemo() {
    $time_start = microtime(true);
    $getZephyr = new GetZephyr();
//$zephyrTests = $getZephyr->getBothProjects();
//$jql = "project = MC  and reporter = terskine and issuetype = Test";
    $jql = "project = MC AND issueType = Test AND status in (Automated, Skipped)";
    $time_start = microtime(true);
    $zephyrTests = $getZephyr->jqlPagination($jql);
    $time_end = microtime(true);
    $time = $time_end - $time_start;
    print_r("\nGetting MC from Zephyr took  : " . $time . "\n");
//$zephyrTests = $getZephyr->getBothProjects();
    $parseMFTF = new ParseMFTF();
    $mftfTestsAll = $parseMFTF->getTestObjects();

    foreach ($mftfTestsAll as $mftfTest) {
        if (isset($mftfTest['title'])) {
            if ($mftfTest['title'][0] == "005 - JZI CREATE NEW TEST") {
                $mftfTests[] = $mftfTest;
            }
        }
    }
    for ($i = 1; $i <= 100; $i++) {
        $mftfTestOneHundred[$i] = $mftfTests[0];
        $mftfTestOneHundred[$i]['title'][0] = $mftfTests[0]['title'][0] . $i;
    }
//$zephyrComparison = new ZephyrComparison($mftfTestsAll, $zephyrTests);
    $zephyrComparison = new ZephyrComparison($mftfTestOneHundred, $zephyrTests);
    $zephyrComparison->matchOnIdOrName();
    $createById = $zephyrComparison->getCreateArrayById();
    $createByName = $zephyrComparison->getCreateArrayByName();
    $skippedTests = $zephyrComparison->checkForSkippedTests();
    $mismatches = $zephyrComparison->getUpdateArray();
//print_r($zephyrTests['MC-4231']);
    if (isset($createByName)) {
        CreateManager::getInstance()->performCreateOperations($createByName);
    }
    if (isset($mismatches)) {
        UpdateManager::getInstance()->performUpdateOperations($mismatches);
    }
    $time_end = microtime(true);
    $time = $time_end - $time_start;
    print_r("\nTOTAL RUN TIME  : " . $time . "\n");
    return "Finished";
}

public function dryRunREST()
{
    $getZephyr = new GetZephyr();
    $zephyrTests = $getZephyr->getBothProjects();
    $parseMFTF = new ParseMFTF();
    $mftfTestsAll = $parseMFTF->getTestObjects();
    $zephyrComparison = new ZephyrComparison($mftfTestsAll, $zephyrTests);
    $zephyrComparison->matchOnIdOrName();
    $createByName = $zephyrComparison->getCreateArrayByName();
    $mismatches = $zephyrComparison->getUpdateArray();
    if (isset($createByName)) {
        CreateManager::getInstance()->performDryRunCreateOperations($createByName);
    }
    if (isset($mismatches)) {
        UpdateManager::getInstance()->performDryRunUpdateOperations($mismatches);
    }
    return "Finished";
}

    public function m2Migration()
    {
        $getZephyr = new GetZephyr();
        $m2jql = "project = MAGETWO and issuetype = test and 'Automation Status' in (Automated, Skipped) and status != Closed";
        $m2ZephyrTests = $getZephyr->jqlPagination($m2jql);
        // No need to parse MFTF or Compare.

        $m2ZephyrTests = array_slice($m2ZephyrTests, 0, 5);// Only need to format all M2 tests and create
        CreateManager::getInstance()->performM2Migration($m2ZephyrTests);
        return "Finished";
    }

    public function realRunmc297() {
        $getZephyr = new GetZephyr();
        $time_start = microtime(true);
        //$zephyrTests = $getZephyr->getBothProjects();
        $m2jql = "project = MAGETWO and issuetype = test and 'Automation Status' in (Automated, Skipped) and status != Closed";
        $singleJql = "issuekey = MC-297";
        $zephyrTests = $getZephyr->jqlPagination($singleJql);
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        print_r("\nGet all zephyr tests took : " . $time . "\n");
        $parseMFTF = new ParseMFTF();
        $mftfTestsAll = $parseMFTF->getTestObjects();
        $zephyrComparison = new ZephyrComparison($mftfTestsAll, $zephyrTests);
        $zephyrComparison->matchOnIdOrName();
        //$createByName = $zephyrComparison->getCreateArrayByName();
        $mismatches = $zephyrComparison->getUpdateArray();
        foreach ($mismatches as $key => $mismatch) {
            if ($key == "MC-297") {
                    $mc297[$key] = $mismatch;
                }
            }
        if (isset($createByName)) {
            CreateManager::getInstance()->performCreateOperations($createByName);
        }
        if (isset($mc297)) {
            UpdateManager::getInstance()->performUpdateOperations($mc297);
        }
        return "Finished";
    }

}

//$finish = ZephyrIntegrationManager::m2Migration();
//$finish =ZephyrIntegrationManager::realRunmc297();
//$finish = ZephyrIntegrationManager::realScopedToJZIDemo();
//print_r($finish);
ZephyrIntegrationManager::runMftfZephyrIntegration($project);