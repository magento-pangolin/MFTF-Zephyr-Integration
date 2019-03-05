<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use Magento\MZI\Util\LoggingUtil;

class ZephyrComparison
{
    /**
     * 2d array of MFTF tests from ParseMFTF class
     * @var array
     */
    private $mftfTests;

    /**
     * array of tests returned from Zephyr
     * @var array 
     */
    private $zephyrTests;
    
    /**
     * array of MFTF test which need to be created in Zephyr
     * @var array
     */
    private $createArrayByName;

    /**
     * array of discrepencies found between MFTF and associated Zephyr test
     * @var array
     */
    private $mismatches;
    
    /**
     * array of tests which hae MFTF <skip> annotation set
     * @var array
     */
    private $skippedTests;

    /**
     * Concatenated string of Story and Title in Zephyr for comparison
     * @var array
     */
    private $zephyrStoryTitle;

    /**
     * array of MFTF test which need to be updated in Zephyr
     * @var array
     */
    private $updateByName;

    /**
     * array of MFTF test which need to be updated in Zephyr
     * @var array
     */
    private $updateById;

    /**
     * Constructor for ZephyrComparison
     * 
     * @param array $mftfTests
     * @param array $zephyrTests
     * @return void
     */
	public function __construct(array $mftfTests, array $zephyrTests)
    {
	    $this->mftfTests = $mftfTests;
	    $this->zephyrTests = $zephyrTests;
        foreach ($this->zephyrTests as $key => $zephyrTest) {
            if (isset($zephyrTest['customfield_14364'])) {
                $this->zephyrStoryTitle[$key] = $zephyrTest['customfield_14364'] . $zephyrTest['summary'];
            }
            else {
                $this->zephyrStoryTitle[$key] = $zephyrTest['summary'];
            }
        }
    }

    /**
     * Checks for TestCaseID as MFTF annotation.
     * Sends for ID comparision if exists, otherwise compares by Story.Title Value.
     * @return void
     * @throws \Exception
     */
    public function matchOnIdOrName()
    {
	    foreach ($this->mftfTests as $mftfTest) {
	        if (isset($mftfTest['testCaseId'])) {
	            if (array_key_exists($mftfTest['testCaseId'][0], $this->zephyrTests)) {
                    $this->idCompare($mftfTest);
                } else {
                    LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                        'TestCaseId '
                        . $mftfTest['testCaseId'][0]
                        . ' exists in MFTF but can not be found in Zephyr MC project. '
                        . 'Integration will try to match by story & title combination.'
                    );
                    $this->storyTitleCompare($mftfTest);
                }
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
     * @param array $mftfTest
     * @return void
     * @throws \Exception
     */
    public function idCompare(array $mftfTest)
    {
        $mftfTestCaseId = $mftfTest['testCaseId'][0];
        // MFTF testCaseID found a match in Zephyr, send test to comparison processing and add it to update array
        $this->testDataComparison($mftfTest, $this->zephyrTests[$mftfTestCaseId], $mftfTestCaseId);
        $this->updateById[] = $mftfTest;
    }

    /**
     * Compares by Story.Title concatenated string - based on enforced uniqueness in MFTF
     *
     * @param array $mftfTest
     * @return void
     * @throws \Exception
     */
    public function storyTitleCompare(array $mftfTest)
    {
        if (isset($mftfTest['title'])) {
            // Set 'stories' to empty string when MFTF test does not set 'stories'
            if (!isset($mftfTest['stories'])) {
                $mftfTest['stories']  = [];
                $mftfTest['stories'][] = '';
            }
            $mftfStoryTitle = $mftfTest['stories'][0] . $mftfTest['title'][0];
            $storyTitleMatch = array_search($mftfStoryTitle, $this->zephyrStoryTitle);
            if ($storyTitleMatch !== false) {
                // MFTF StoryTitle found a match in Zephyr, send test to comparison processing and add it to update array
                $this->testDataComparison($mftfTest, $this->zephyrTests[$storyTitleMatch], $storyTitleMatch);
                $this->updateByName[] = $mftfTest; // TODO update which key by name ???
            }
            else {
                // MFTF StoryTitle match is not found, add test to create array
                if (isset($mftfTest['severity'])) {
                    $mftfTest['severity'][0] = $this->transformSeverity($mftfTest['severity'][0]);
                }
                $this->createArrayByName[] = $mftfTest;
            }
        }
        else {
            $mftfLoggingDescriptor = self::mftfLoggingDescriptor($mftfTest);
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                'MFTF TEST MISSING TITLE ANNOTATION: ' . $mftfLoggingDescriptor . ' No integration will be performed.'
            );
        }
    }

    /**
     * For each potential field supported by Update class,
     * Will set the $mismatches array by $key,
     * if the MFTF value differs from the Zephyr value (excluding where MFTF value not set)
     *
     * @param array $mftfTest
     * @param array $zephyrTest
     * @param string $key
     * @return void
     */
	public function testDataComparison(array $mftfTest, array $zephyrTest, $key)
    {
        if (isset($mftfTest['description']) && isset($zephyrTest['description'])) {
            if ($mftfTest['description'][0] != $zephyrTest['description']) {
                $this->mismatches[$key]['description'] = $mftfTest['description'];
                print(
                    "Description comparison failed: key = $key\nmftf="
                    . $mftfTest['description'][0]
                    . "\nzephyr="
                    . $zephyrTest['description']
                    ."\n"
                );
            }
        } elseif (isset($mftfTest['description'])) {
            $this->mismatches[$key]['description'] = $mftfTest['description'];
            print(
                "Description comparison failed: key = $key\nmftf="
                . $mftfTest['description'][0]
                . "\nzephyr=\n"
            );
        }

        if (isset($mftfTest['title']) && isset($zephyrTest['summary'])) {
            if ($mftfTest['title'][0] != $zephyrTest['summary']) {
               $this->mismatches[$key]['summary'] = $mftfTest['title'][0];
                print(
                    "Title comparison failed: key = $key\nmftf="
                    . $mftfTest['title'][0]
                    . "\nzephyr="
                    . $zephyrTest['summary']
                    ."\n"
                );
           }
        } elseif (isset($mftfTest['title'])){
            $this->mismatches[$key]['summary'] = $mftfTest['title'][0];
            print(
                "Title comparison failed: key = $key\nmftf="
                . $mftfTest['title'][0]
                . "\nzephyr=\n"
            );
        }

        if (isset($mftfTest['severity'][0])) {
            $mftfSeverity = $this->transformSeverity($mftfTest['severity'][0]);
            if (isset($zephyrTest['customfield_12720'])) {
                if ($mftfSeverity != $zephyrTest['customfield_12720']['value']) { //TODO when update, need to consider this as well
                    $this->mismatches[$key]['severity'] = $mftfSeverity;
                    print(
                        "Severity comparison failed: key = $key\nmftf="
                        . $mftfSeverity
                        . "\nzephyr="
                        . $zephyrTest['customfield_12720']['value']
                        ."\n"
                    );
                }
            } else {
                $this->mismatches[$key]['severity'] = $mftfSeverity;
                print(
                    "Severity comparison failed: key = $key\nmftf="
                    . $mftfSeverity
                    . "\nzephyr=\n"
                );
            }
        }

        if (isset($mftfTest['stories']) && isset($zephyrTest['customfield_14364'])) {
            if ($mftfTest['stories'][0] != $zephyrTest['customfield_14364']) {
                $this->mismatches[$key]['stories'] = $mftfTest['stories'][0];
                print(
                    "Stories comparison failed: key = $key\nmftf="
                    . $mftfTest['stories'][0]
                    . "\nzephyr="
                    . $zephyrTest['customfield_14364']
                    ."\n"
                );
            }
        } elseif (isset($mftfTest['stories'])) {
            $this->mismatches[$key]['stories'] = $mftfTest['stories'][0];
            print(
                "Stories comparison failed: key = $key\nmftf="
                . $mftfTest['stories'][0]
                . "\nzephyr=\n"
            );
        }

        if ((isset($mftfTest['skip'])) && ($zephyrTest['status']['name'] != "Skipped")) {
            $this->mismatches[$key]['skip'] = $mftfTest['skip'][0];
            print("Automation Status comparison failed: key = $key\nmftf is \"Skipped\" zephyr is NOT \"Skipped\"\n");
        }

        if (!(isset($mftfTest['skip'])) && ($zephyrTest['status']['name'] == "Skipped")) {
            $this->mismatches[$key]['unskip'] = TRUE;
            print("Automation Status comparison failed: key = $key\nmftf is NOT \"Skipped\" zephyr is \"Skipped\"\n");
        }

        if (isset($this->mismatches[$key])) {
            //$this->mismatches[$key]['mftf_test_name'] = $zephyrTest['status']['name']; //TODO set mftf test name
            $this->mismatches[$key]['status'] = $zephyrTest['status']['name']; // Save current Zephyr status
            print("key = $key comparison failed:\n Current Zephyr status is:" . $zephyrTest['status']['name'] . "\n");
            //$this->mismatches[$key]['labels'] = $zephyrTest['labels']['name']; // TODO set labels
        }
    }

    /**
     * Mapping of MFTF/Allure severity values to Jira/Zephyr values
     *
     * @param string $mftfSeverity
     * @return string
     */
    public function transformSeverity($mftfSeverity)
    {
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
     * @param array $mftfTest
     * @return string
     */
    public static function mftfLoggingDescriptor(array $mftfTest)
    {
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
     * getter for createArrayByName
     * @return array
     */
	public function getCreateArrayByName()
    {
        return $this->createArrayByName;
    }

    /**
     * getter for mismatches
     * @return array
     */
    public function getUpdateArray()
    {
        return $this->mismatches;
    }
}
