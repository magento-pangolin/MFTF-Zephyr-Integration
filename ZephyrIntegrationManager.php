<?php

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

    /**
     * Can hardcode project or use setter
     * This is used as part of a JQL query to filter tests to project scope
     *
     * @var string
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

//		$created = new CreateIssue($toCreate);
//		$updated = new UpdateIssue($toUpdate);
//		$createErrors = $created->getErrors();
//		$updateErrors = $updated->getErrors();
		// Write $created, $updated, $createErrors, and $updateErrors to file (or var_dump for now)
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

}
$getZephyr = new GetZephyr();
//$zephyrTests = $getZephyr->getBothProjects();
//$jql = "project = MC  and reporter = terskine and issuetype = Test";
$jql = "project = MC AND issueType = Test AND status in (Automated, Skipped)";
$zephyrTests = $getZephyr->jqlPagination($jql);
//$zephyrTests = $getZephyr->getBothProjects();
$parseMFTF = new ParseMFTF();
$mftfTestsAll = $parseMFTF->getTestObjects();
//foreach ($mftfTestsAll as $mftfTest) {
//    if (isset($mftfTest['stories'])) {
//        if ($mftfTest['stories'][0] == "JZI DEMO STORY") {
//            $mftfTests[] = $mftfTest;
//        }
//    }
//}
$zephyrComparison = new ZephyrComparison($mftfTestsAll, $zephyrTests);
$zephyrComparison->matchOnIdOrName();
$createById = $zephyrComparison->getCreateArrayById();
$createByName = $zephyrComparison->getCreateArrayByName();
$skippedTests = $zephyrComparison->checkForSkippedTests();
$mismatches = $zephyrComparison->getUpdateArray();
//print_r($zephyrTests['MC-4231']);
if (isset($createByName)) {
    CreateManager::getInstance()->performDryRunCreateOperations($createByName);
}
//if (isset($mismatches)) {
//    UpdateManager::getInstance()->performDryRunUpdateOperations($mismatches);
//}




print_r("Finished");