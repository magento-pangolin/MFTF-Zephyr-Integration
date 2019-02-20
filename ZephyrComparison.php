<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;
include_once ('Util/LoggingUtil.php');

class ZephyrComparison {

    /**
     * 2d array of MFTF tests from ParseMFTF class
     * @var array
     */
    public $mftfTests;

    /**
     * array of tests returned from Zephyr
     * @var array 
     */
    public $zephyrTests;
    
    /**
     * Array of tests to be created
     * @var array
     */
    public $createArray;
    
    /**
     * array of MFTF test which need to be created in Zephyr
     * @var array
     */
    public $createArrayByName;
    
    /**
     * array of discrepencies found between MFTF and associated Zephyr test
     * @var array
     */
    public $mismatches;
    
    /**
     * array of tests which hae MFTF <skip> annotation set
     * @var array
     */
    public $skippedTests;

    /**
     * Concatenated string of Story and Title in Zephyr for comparison
     * @var array
     */
    public $zephyrStoryTitle;
    
    public $updateByName;
    
	public $updateById;

    /**
     * Constructor for ZephyrComparison
     * 
     * @param $mftfTests
     * @param $zephyrTests
     */
	public function __construct($mftfTests, $zephyrTests) {
	    $this->mftfTests = $mftfTests;
	    $this->zephyrTests = $zephyrTests;
	    $this->checkForSkippedTests();
        foreach ($this->zephyrTests as $key => $zephyrTest) {
            if (isset($zephyrTest['customfield_14364'])) {
                $this->zephyrStoryTitle[$key] = $zephyrTest['customfield_14364'] . $zephyrTest['summary'];
            }
            else {
                $this->zephyrStoryTitle[$key] = 'NO STORY ' . $zephyrTest['summary'];
            }
        }
    }

    /**
     * Checks for TestCaseID as MFTF annotation.
     * Sends for ID comnpare if exists, otherwise compares by Story.Title Value.
     */
    public function matchOnIdOrName() {
	    foreach ($this->mftfTests as $mftfTest) {
	        if (isset($mftfTest['testCaseId'])) {
	            $this->idCompare($mftfTest);
            }
            else {
	            $this->storyTitleCompare($mftfTest);
            }
        }
    }

    /**
     * Checks that given MFTF TestCaseId annotation value corresponds to key in zephyrTests array
     * Throws error if testCaseId not found in Zephyr data
     *
     * @param $mftfTest
     * @throws \Exception
     */
    public function idCompare($mftfTest)
    {
        $mftfTestCaseId = $mftfTest['testCaseId'][0];
        if (!(array_key_exists($mftfTestCaseId, $this->zephyrTests))) { // IF we can't find the MFTF testCaseID in the zephyr Tests array
            //$this->createArrayById[] = $mftfTest;
            //Array of MFTF tests which have a TestCaseId annotation but the value does not match anything in Zephyr
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn($mftfTestCaseId .
                ' exists as TestCaseId annotation but can not be found in Zephyr. 
                No Integration functions will be run');
        }
        elseif (array_key_exists($mftfTestCaseId, $this->zephyrTests)) { // If we find the MFTF TCID in Zephyr, send the MFTF test and matching zephyr test to check for updates
            $this->testDataComparison($mftfTest, $this->zephyrTests[$mftfTestCaseId], $mftfTestCaseId);
            $this->updateById[] = $mftfTest; // MFTF has TCID and found a match
        }
    }

    /**
     * Compares by Story.Title concatenated string - based on enforced uniqueness in MFTF
     *
     * @param $mftfTest
     * @throws \Exception
     */
    public function storyTitleCompare($mftfTest)
    {
        if ((isset($mftfTest['stories'])) && (isset($mftfTest['title']))) { // Check if MFTF has title and story set
            $mftfStoryTitle = $mftfTest['stories'][0] . $mftfTest['title'][0];
            $storyTitleMatch = array_search($mftfStoryTitle, $this->zephyrStoryTitle);
            if (!($storyTitleMatch === false)) {
                $this->updateByName[] = $mftfTest; // MFTF has Story Title and found a match
                $this->testDataComparison($mftfTest, $this->zephyrTests[$storyTitleMatch], $storyTitleMatch);
            }
            else {
                // TODO - DO NOT create anything that doesn't have story set (cf_14364)
                if (isset($mftfTest['severity'])) {
                    $mftfTest['severity'][0] = $this->transformSeverity($mftfTest['severity'][0]);
                }
                $this->createArrayByName[] = $mftfTest; // MFTF has Story Title but has not found a match
            }
        }
        else {
            $mftfLoggingDescriptor = self::mftfLoggingDescriptor($mftfTest);
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn('MFTF TEST MISSING STORY OR TITLE ANNOTATIONS: ' . $mftfLoggingDescriptor);
        }

    }

    /**
     * Checks if tests are skipped, adds them to array for logging/processing later
     */
    public function checkForSkippedTests() {
	    foreach ($this->mftfTests as $mftfTest) {
	        if (isset($mftfTest['skip'])) {
	            $this->skippedTests[] = $mftfTest;
            }
        }
    }

    /**
     * For each potential field supported by Update class,
     * Will set the $mismatches array by $key,
     * if the MFTF value differs from the Zephyr value (excluding where MFTF value not set)
     *
     * @param $mftfTest
     * @param $zephyrTest
     * @param $key
     */
	function testDataComparison($mftfTest, $zephyrTest, $key) {

        if (isset($mftfTest['description']) && isset($zephyrTest['description'])) {
            if (!($mftfTest['description'][0] == $zephyrTest['description'])) {
                $this->mismatches[$key]['description'] = $zephyrTest['description'];
            }
        }
        elseif (isset($mftfTest['description'])) {
            $this->mismatches[$key]['description'] = $zephyrTest['description'];
        }

       if (isset($mftfTest['title']) && isset($zephyrTest['summary'])) {
           if (!($mftfTest['title'][0] == $zephyrTest['summary'])) {
               $this->mismatches[$key]['summary'] = $mftfTest['title'][0];
           }
       }
       elseif (isset($mftfTest['title'])){
           $this->mismatches[$key]['summary'] = $mftfTest['title'][0];
       }

        if (isset($mftfTest['severity'][0])) {
            $mftfSeverity = $this->transformSeverity($mftfTest['severity'][0]);
            if (isset($zephyrTest['customfield_12720'])){
                if (!($mftfSeverity == $zephyrTest['customfield_12720']['value'])) {
                    $this->mismatches[$key]['severity'] = $mftfSeverity;
                }
            }
            else {
                $this->mismatches[$key]['severity'] = $mftfSeverity;
            }
        }

        if (isset($mftfTest['stories']) && isset($zephyrTest['customfield_14364'])) {
            if (!($mftfTest['stories'][0] == $zephyrTest['customfield_14364'])) {
                $this->mismatches[$key]['stories'] = $mftfTest['stories'][0];
            }
        }
        elseif (isset($mftfTest['stories'])) {
            $this->mismatches[$key]['stories'] = $mftfTest['stories'][0];
        }

        if ((isset($mftfTest['skip'])) && (!($zephyrTest['status']['name'] == "Skipped"))) {
            $this->mismatches[$key]['skip'] = $mftfTest['skip'][0];
        }
        if (!(isset($mftfTest['skip'])) && ($zephyrTest['status']['name'] == "Skipped")) {
            $this->mismatches[$key]['unskip'] = TRUE;
        }

        if (isset($this->mismatches[$key])) {
            $this->mismatches[$key]['status'] = $zephyrTest['status']['name'];
        }
    }

    /**
     * Mapping of MFTF/Allure severity values to Jira/Zephyr values
     *
     * @param $mftfSeverity
     * @return string
     */
    public function transformSeverity($mftfSeverity) {
        switch ($mftfSeverity) {
            case "BLOCKER" :
                $mftfSeverity = '0-Blocker';
                break;
            case "CRITICAL" :
                $mftfSeverity = '1-Critical';
                break;
            case "NORMAL" :
                $mftfSeverity = '2-Major';
                break;
            case "MINOR" :
                $mftfSeverity = '3-Average';
                break;
            case "TRIVIAL" :
                $mftfSeverity = '4-Minor';
                break;
        }
        return $mftfSeverity;
    }

    /**
     * Helper function to build a useful identifier for logging
     *
     * @param $mftfTest
     * @return string
     */
    public static function mftfLoggingDescriptor($mftfTest) {
        if (isset($mftfTest['testCaseId'])) {
            $mftfLoggingDescriptor = $mftfTest['testCaseId'][0];
        }
        elseif (isset($mftfTest['stories']) && isset($mftfTest['title'])) {
            $mftfLoggingDescriptor = $mftfTest['stories'][0] . $mftfTest['title'][0];
        }
        elseif (isset($mftfTest['title'])) {
            $mftfLoggingDescriptor = $mftfTest['title'][0];
        }
        else {
            $mftfLoggingDescriptor = 'NO STORY OR TITLE SET ON TEST';
        }
        return $mftfLoggingDescriptor;
    }

    /**
     * setter for $zephyrTests
     * @param $zephyrTests
     */
	function setZephyrTests($zephyrTests){
	    $this->zephyrTests = $zephyrTests;
    }

    /**
     * setter for $mftfTests
     * @param $mftfTests
     */
    function setMFTFTests($mftfTests){
	    $this->mftfTests = $mftfTests;
    }

    /**
     * getter for createArrayById
     * @return array
     */
	function getCreateArrayById(){
		return $this->createArrayById;
	}

    /**
     * getter for createArrayByName
     * @return array
     */
	function getCreateArrayByName()
    {
        return $this->createArrayByName;
    }

    /**
     * getter for mismatches
     * @return array
     */
    function getUpdateArray(){
        return $this->mismatches;
    }

    /**
     * getter for skippedTests
     * @return array
     */
    function getSkippedTests() {
	    return $this->skippedTests;
    }
}