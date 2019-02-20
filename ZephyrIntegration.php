<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;


//require(__DIR__ . "/../../../vendor/autoload.php");
//use Magento\JZI\GetZephyr;
include_once ('GetZephyr.php');
include_once ('ParseMFTF.php');
include_once ('ZephyrComparison.php');
include_once ('CreateManager.php');
include_once ('UpdateManager.php');
//include_once ('CreateIssue.php');

ini_set('memory_limit', '512M');

function debugOutputArrays()
{
    $getZephyr = new \Magento\JZI\GetZephyr();
    //$zephyrTests = $getZephyr->getIssuesByProject('MC');
    $zephyrTests = array_slice($zephyrTests, 0, 5);
    //print_r($zephyrTests);
    print("----------------------------\n");
    print("----------------------------\n");
    print("----------------------------\n");
    print("----------------------------\n");


    $parseMFTF = new ParseMFTF();
    $mftfTests = $parseMFTF->getTestObjects();
    $mftfTests = array_slice($mftfTests, 1, 3);
    //print_r($mftfTests);

    $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
    $createVerify = $zephyrComparison->matchOnIdOrName();
    $createById = $zephyrComparison->getCreateArrayById();
    $createByName = $zephyrComparison->getCreateArrayByName();
    $skippedTests = $zephyrComparison->getSkippedTests();
    print_r($createById);
    print("***************\n****************\n*******************\n");
    print_r($createByName);
    print("***************\n****************\n*******************\n");
    print_r($skippedTests);

//    $createManager = CreateManager::getInstance();
//    $createResponse = $createManager->performCreateOperations($createByName, $createById, $skippedTests);
//
//
//    $createManager = new CreateManager::getInstance($createByName, $createById, $skippedTests)->preformCreateOperations;
//    $updateHandler = new UpdateHandler::getInstance($updates, $skippedTests)->performUpdateOperations;
    }

function dryRunOutputs()
{
    $getZephyr = new GetZephyr();
    //$zephyrTests = $getZephyr->getIssuesByProject('MC');
    //$zephyrTests = $getZephyr->jqlPagination();
    $zephyrTests = $getZephyr->getBothProjects();
    //$zephyrTests = array_slice($zephyrTests, 0, 5);

    $parseMFTF = new ParseMFTF();
    $mftfTests = $parseMFTF->getTestObjects();
    //$mftfTests = array_slice($mftfTests, 1, 3);

    $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
    $zephyrComparison->matchOnIdOrName();
    $createById = $zephyrComparison->getCreateArrayById();
    $createByName = $zephyrComparison->getCreateArrayByName();
    $skippedTests = $zephyrComparison->checkForSkippedTests();
    $mismatches = $zephyrComparison->getUpdateArray();

    CreateManager::getInstance()->performDryRunCreateOperations($createByName, $createById, $skippedTests);
    UpdateManager::getInstance()->performDryRunUpdateOperations($mismatches);
}

$parseMFTF = new ParseMFTF();
$mftfTests = $parseMFTF->getTestObjects();
$mftfTest = $mftfTests[71];

$createIssue = new CreateIssue($mftfTest);
$mftfTest = $createIssue->defaultMissingFields($mftfTest);
$mftfTest['severity'][0] = ZephyrComparison::transformSeverity($mftfTest['severity'][0]);
//$createResponse = $createIssue->createDryRunIssuesREST($mftfTest);
$createResponse = 'MC-4237';
print_r($createResponse);
print_r("\n");

$mftfUpdate = ['severity'=>'0-Blocker', 'key'=>$createResponse, 'skip'=>'MC-4236'];
$updateResponse = UpdateIssue::updateDryRunIssuesREST($mftfUpdate);
print_r($updateResponse);

//$dryRunCreate = CreateManager::getInstance()->performDryRunCreateOperations($createByName, $createById, $skippedTests);
//print_r($dryRunCreate);
//testing composer update

