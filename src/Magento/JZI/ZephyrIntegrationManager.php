<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;

require_once (__DIR__ . '/../../../../magento/vendor/autoload.php');

ini_set('memory_limit', '512M');

class ZephyrIntegrationManager
{
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

    public function synchronizeZephyr($project)
    {
        $getZephyr = new GetZephyr();
        $zephyrTests = $getZephyr->getIssuesByProject($project); // This now returns annotation array
        //$zephyrTests = $getZephyr->getAllZephyrTests($zephyrTestList); // This is no longer needed
        // TODO: Will getZephyr manage the querying, looping, and parsing return to create array of Ids or objects?
        $parseMFTF = new ParseMFTF();
        $mftfTests = $parseMFTF->getTestObjects();

        $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
//		$toCreate = $zephyrComparison->getCreateArrayById();
//		$toUpdate = $zephyrComparison->getUpdateArray();
        $createVerify = $zephyrComparison->matchOnIdOrName();
        $createById = $zephyrComparison->getCreateArrayById();
        $createByName = $zephyrComparison->getCreateArrayByName();
        $skippedTests = $zephyrComparison->checkForSkippedTests();//simpleCompare CreateArrayById

        $createManager = CreateManager::getInstance()->performCreateOperations($createByName, $createById, $skippedTests);
        $createResponse = $createManager->getResponses();
    }

    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @param string $project
     */
    public static function runMftfZephyrIntegration($project = null)
    {
        $project = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : $project;
        $getZephyr = new GetZephyr();
        if (is_null($project)) {
            $zephyrTests = $getZephyr->getBothProjects();
        } else {
            $zephyrTests = $getZephyr->getIssuesByProject($project);
        }
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

ZephyrIntegrationManager::runMftfZephyrIntegration();