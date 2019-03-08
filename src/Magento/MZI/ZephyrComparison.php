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
     *
     * @var array
     */
    private $mftfTests;

    /**
     * array of tests returned from Zephyr
     *
     * @var array 
     */
    private $zephyrTests;
    
    /**
     * array of MFTF test which need to be created in Zephyr
     *
     * @var array
     */
    private $createArrayByName;

    /**
     * array of discrepencies found between MFTF and associated Zephyr test
     *
     * @var array
     */
    private $mismatches;
    
    /**
     * array of tests which hae MFTF <skip> annotation set
     *
     * @var array
     */
    private $skippedTests;

    /**
     * Concatenated string of Story and Title in Zephyr for comparison
     *
     * @var array
     */
    private $zephyrStoryTitle;

    /**
     * array of MFTF test which need to be updated in Zephyr
     *
     * @var array
     */
    private $updateByName;

    /**
     * array of MFTF test which need to be updated in Zephyr
     *
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
     * Checks for TestCaseID as MFTF annotation.
     * Sends for ID comparision if exists, otherwise compares by Story.Title Value.
     * @return void
     * @throws \Exception
     */
    public function matchOnIdOrName()
    {
	    foreach ($this->mftfTests as $mftfTestName => $mftfTest) {
	        // Set release line for the mftf test
            $mftfTest['releaseLine'][] = $this->getReleaseLine($mftfTest);
	        if (isset($mftfTest['testCaseId'])) {
	            if (array_key_exists($mftfTest['testCaseId'][0], $this->zephyrTests)) {
                    $this->idCompare($mftfTestName, $mftfTest);
                } else {
                    LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                        'TestCaseId '
                        . $mftfTest['testCaseId'][0]
                        . ' exists in MFTF but can not be found in Zephyr MC project. '
                        . 'Integration will try to match by story & title combination.'
                    );
                    $this->storyTitleCompare($mftfTestName, $mftfTest);
                }
            }
            else {
	            $this->storyTitleCompare($mftfTestName, $mftfTest);
            }
        }
    }

    /**
     * Checks that given MFTF TestCaseId annotation value corresponds to key in zephyrTests array
     * Throws error if testCaseId not found in Zephyr data
     *
     * @param string $mftfTestName
     * @param array $mftfTest
     * @return void
     * @throws \Exception
     */
    private function idCompare($mftfTestName, array $mftfTest)
    {
        $mftfTestCaseId = $mftfTest['testCaseId'][0];
        // MFTF testCaseID found a match in Zephyr, send test to comparison processing and add it to update array
        $this->testDataComparison($mftfTestName, $mftfTest, $this->zephyrTests[$mftfTestCaseId], $mftfTestCaseId);
        $this->updateById[] = $mftfTest;
    }

    /**
     * Compares by Story.Title concatenated string - based on enforced uniqueness in MFTF
     *
     * @param string $mftfTestName
     * @param array $mftfTest
     * @return void
     * @throws \Exception
     */
    private function storyTitleCompare($mftfTestName, array $mftfTest)
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
                $this->testDataComparison(
                    $mftfTestName,
                    $mftfTest,
                    $this->zephyrTests[$storyTitleMatch],
                    $storyTitleMatch
                );
                $this->updateByName[] = $mftfTest; // TODO update which key by name ???
            }
            else {
                // MFTF StoryTitle match is not found, add test to create array
                if (isset($mftfTest['severity'])) {
                    $mftfTest['severity'][0] = $this->transformSeverity($mftfTest['severity'][0]);
                }
                $this->createArrayByName[$mftfTestName] = $mftfTest;
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
     * @param string $mftfTestName
     * @param array $mftfTest
     * @param array $zephyrTest
     * @param string $key
     * @return void
     */
	private function testDataComparison($mftfTestName, array $mftfTest, array $zephyrTest, $key)
    {
        $logMessage = '';
        if (isset($mftfTest['title']) && isset($zephyrTest['summary'])) {
            if ($mftfTest['title'][0] != trim($zephyrTest['summary'])) {
                $this->mismatches[$key]['title'] = $mftfTest['title'][0];
                $logMessage .=
                    "Title comparison failed:\nmftf="
                    . $mftfTest['title'][0]
                    . "\nzephyr="
                    . $zephyrTest['summary']
                    ."\n";
            }
        } elseif (isset($mftfTest['title'])){
            $this->mismatches[$key]['title'] = $mftfTest['title'][0];
            $logMessage .=
                "Title comparison failed:\nmftf="
                . $mftfTest['title'][0]
                . "\nzephyr=\n";
        }

        if (isset($zephyrTest['description'])) {
            $parts = explode(CreateIssue::NOTE_FOR_CREATE, $zephyrTest['description']);
            $zephyrTest['description'] = isset($parts[0]) ? $parts[0] : $zephyrTest['description'];
            $parts = explode(UpdateIssue::NOTE_FOR_UPDATE, $zephyrTest['description']);
            $zephyrTest['description'] = isset($parts[0]) ? $parts[0] : $zephyrTest['description'];
        }
        if (isset($mftfTest['description']) && isset($zephyrTest['description'])) {
            if ($mftfTest['description'][0] != trim($zephyrTest['description'])) {
                $this->mismatches[$key]['description'] = $mftfTest['description'][0];
                $logMessage .=
                    "Description comparison failed:\nmftf="
                    . $mftfTest['description'][0]
                    . "\nzephyr="
                    . $zephyrTest['description']
                    ."\n";
            }
        } elseif (isset($mftfTest['description'])) {
            $this->mismatches[$key]['description'] = $mftfTest['description'][0];
            $logMessage .=
                "Description comparison failed:\nmftf="
                . $mftfTest['description'][0]
                . "\nzephyr=\n";
        }

        if (isset($mftfTest['stories']) && isset($zephyrTest['customfield_14364'])) {
            if ($mftfTest['stories'][0] != trim($zephyrTest['customfield_14364'])) {
                $this->mismatches[$key]['stories'] = $mftfTest['stories'][0];
                $logMessage .=
                    "Stories comparison failed:\nmftf="
                    . $mftfTest['stories'][0]
                    . "\nzephyr="
                    . $zephyrTest['customfield_14364']
                    ."\n";
            }
        } elseif (isset($mftfTest['stories'])) {
            $this->mismatches[$key]['stories'] = $mftfTest['stories'][0];
            $logMessage .=
                "Stories comparison failed:\nmftf="
                . $mftfTest['stories'][0]
                . "\nzephyr=\n";
        }

        if (isset($mftfTest['severity'][0])) {
            $mftfSeverity = $this->transformSeverity($mftfTest['severity'][0]);
            if (isset($zephyrTest['customfield_12720'])) {
                if ($mftfSeverity != trim($zephyrTest['customfield_12720']['value'])) {
                    $this->mismatches[$key]['severity'] = $mftfSeverity;
                    $logMessage .=
                        "Severity comparison failed:\nmftf="
                        . $mftfSeverity
                        . "\nzephyr="
                        . $zephyrTest['customfield_12720']['value']
                        ."\n";
                }
            } else {
                $this->mismatches[$key]['severity'] = $mftfSeverity;
                $logMessage .=
                    "Severity comparison failed:\nmftf="
                    . $mftfSeverity
                    . "\nzephyr=\n";
            }
        }

        if ((isset($mftfTest['skip'])) && trim(($zephyrTest['status']['name']) != "Skipped")) {
            $this->mismatches[$key]['skip'] = $mftfTest['skip'][0];
            $logMessage .= "Automation Status comparison failed:\nmftf is \"Skipped\" zephyr is NOT \"Skipped\"\n";
        }

        if (!(isset($mftfTest['skip'])) && (trim($zephyrTest['status']['name']) == "Skipped")) {
            $this->mismatches[$key]['unskip'] = TRUE;
            $logMessage .= "Automation Status comparison failed:\nmftf is NOT \"Skipped\" zephyr is \"Skipped\"\n";
        }

        if (isset($mftfTest['testCaseId']) && $mftfTest['testCaseId'][0] != $key) {
            $logMessage .= "mftf testCaseId " . $mftfTest['testCaseId'][0] . " is linked to zephyr issue " . $key."\n";
            $logMessage .= "Please update mftf testCaseId in code in release line " . $mftfTest['releaseLine'][0]." \n";
        }
        // customfield_14362 Group
        // customfield_14121 Release Line
        // customfield_14621 Skipped Reason
        // customfield_13324 Test Type
        // customfield_14364 Stories
        // customfield_12720 Severity
        if (!isset($zephyrTest['customfield_14121'])
            || $zephyrTest['customfield_14121']['value'] != $mftfTest['releaseLine'][0]) {
            $this->mismatches[$key]['release_line'] = $mftfTest['releaseLine'][0];
            $logMessage .= "Release line comparison failed:\nmftf is run from "
                . $mftfTest['releaseLine'][0]
                . " zephyr is "
                . $zephyrTest['customfield_14121']['value']
                . "\n";
        }

        if (isset($this->mismatches[$key])) {
            // Save description as we will always update it
            if (!isset($this->mismatches[$key]['description'] )) {
                $this->mismatches[$key]['description'] = $mftfTest['description'][0];
            }
            $this->mismatches[$key]['mftf_test_name'] = $mftfTestName; // Save mftf test name
            $this->mismatches[$key]['status'] = $zephyrTest['status']['name']; // Save current Zephyr status
            $this->mismatches[$key]['labels'] = $zephyrTest['labels']; // Save current zephyr labels
            $logMessage = "\n$key Comparision Failed:\n" . $logMessage;
            $logMessage .= "Current Zephyr status is: " . $zephyrTest['status']['name'] . "\n";
            print($logMessage);
        }
    }

    /**
     * Mapping of MFTF/Allure severity values to Jira/Zephyr values
     *
     * @param string $mftfSeverity
     * @return string
     */
    private function transformSeverity($mftfSeverity)
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
     * getter for createArrayByName
     *
     * @return array
     */
	public function getCreateArrayByName()
    {
        return $this->createArrayByName;
    }

    /**
     * getter for mismatches
     *
     * @return array
     */
    public function getUpdateArray()
    {
        return $this->mismatches;
    }

    /**
     * Determine if the given mftf test is a page builder test
     *
     * @param array $mftfTest
     * @return bool
     */
    private function isPageBuilderTest($mftfTest)
    {
        if (isset($mftfTest['features'])) {
            $feature = strtolower($mftfTest['features'][0]);
            if (strpos('pagebuilder', $feature) !== false) {
                return true;
            } else {
                return false;
            }
        }
        if (isset($mftfTest['group'])) {
            foreach ($mftfTest['group'] as $group) {
                if (strpos('pagebuilder', strtolower($group)) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Return release line for a mftf test
     *
     * @param array $mftfTest
     * @return string
     */
    private function getReleaseLine($mftfTest)
    {
        if ($this->isPageBuilderTest($mftfTest)) {
            return ZephyrIntegrationManager::$pbReleaseLine;
        } else {
            return ZephyrIntegrationManager::$releaseLine;
        }
    }
}
