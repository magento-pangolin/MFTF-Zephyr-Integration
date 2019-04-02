<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use Magento\MZI\Util\JiraInfo;
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
	    if (empty($zephyrTests)) {
            $this->zephyrStoryTitle = [];
        }
        foreach ($this->zephyrTests as $key => $zephyrTest) {
            if (isset($zephyrTest[JiraInfo::JIRA_FIELD_STORIES])) {
                $this->zephyrStoryTitle[$key] = $zephyrTest[JiraInfo::JIRA_FIELD_STORIES] . $zephyrTest['summary'];
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
        if (empty($this->mftfTests)) {
            print("\nNo MFTF test found. Exiting With Code 1\n");
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                "\nNo MFTF test found. Exiting With Code 1\n"
            );
            exit(1);
        }
	    foreach ($this->mftfTests as $mftfTestName => $mftfTest) {
	        // Set release line for the mftf test
            $mftfTest['releaseLine'][] = $this->getReleaseLine($mftfTest);
	        if (isset($mftfTest['testCaseId'])) {
	            if (!empty($this->zephyrTests) && array_key_exists($mftfTest['testCaseId'][0], $this->zephyrTests)) {
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
            $matched = false;
            if (!empty($this->zephyrStoryTitle)) {
                $storyTitleMatch = array_search($mftfStoryTitle, $this->zephyrStoryTitle);
                if ($storyTitleMatch !== false) {
                    $matched = true;
                    // MFTF StoryTitle found a match in Zephyr, send test to comparison processing and add it to update array
                    $this->testDataComparison(
                        $mftfTestName,
                        $mftfTest,
                        $this->zephyrTests[$storyTitleMatch],
                        $storyTitleMatch
                    );
                    $this->updateByName[] = $mftfTest;
                }
            }

            if (!$matched) {
                // MFTF StoryTitle match is not found, add test to create array
                if (isset($mftfTest['severity'])) {
                    $mftfTest['severity'][0] = $this->transformSeverity($mftfTest['severity'][0]);
                }
                $this->createArrayByName[$mftfTestName] = $mftfTest;
            }
        }
        else {
            $mftfLoggingDescriptor = self::mftfLoggingDescriptor($mftfTest);
            print(
                "\nMFTF TEST MISSING TITLE ANNOTATION: "
                . $mftfLoggingDescriptor
                . " No integration will be performed.\n"
            );
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                "\nMFTF TEST MISSING TITLE ANNOTATION: " . $mftfLoggingDescriptor . " No integration will be performed.\n"
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
            $mftf = trim($mftfTest['title'][0]);
            $zephyr = trim($zephyrTest['summary']);
            if (strcasecmp($mftf, $zephyr) != 0) {
                $this->mismatches[$key]['title'] = $mftf;
                $logMessage .= "Title comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr ."\n";
            }
        } elseif (isset($mftfTest['title']) && !empty(trim($mftfTest['title'][0]))){
            $mftf = trim($mftfTest['title'][0]);
            $this->mismatches[$key]['title'] = $mftf;
            $logMessage .= "Title comparison failed:\nmftf=" . $mftf . "\nzephyr=\n";
        }

        if (isset($zephyrTest['description'])) {
            $parts = explode(CreateIssue::NOTE_FOR_CREATE, $zephyrTest['description']);
            $zephyrTest['description'] = isset($parts[0]) ? $parts[0] : $zephyrTest['description'];
            $parts = explode(UpdateIssue::NOTE_FOR_UPDATE, $zephyrTest['description']);
            $zephyrTest['description'] = isset($parts[0]) ? $parts[0] : $zephyrTest['description'];
        }
        if (isset($mftfTest['description']) && isset($zephyrTest['description'])) {
            $mftf = trim($mftfTest['description'][0]);
            $zephyr = trim($zephyrTest['description']);
            if (strcasecmp($mftf, $zephyr) != 0) {
                $this->mismatches[$key]['description'] = $mftf;
                $logMessage .= "Description comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr ."\n";
            }
        } elseif (isset($mftfTest['description']) && !empty(trim($mftfTest['description'][0]))) {
            $mftf = trim($mftfTest['description'][0]);
            $this->mismatches[$key]['description'] = $mftf;
            $logMessage .= "Description comparison failed:\nmftf=" . $mftf . "\nzephyr=\n";
        }

        if (isset($mftfTest['stories']) && isset($zephyrTest[JiraInfo::JIRA_FIELD_STORIES])) {
            $mftf = trim($mftfTest['stories'][0]);
            $zephyr = trim($zephyrTest[JiraInfo::JIRA_FIELD_STORIES]);
            if (strcasecmp($mftf, $zephyr) != 0) {
                $this->mismatches[$key]['stories'] = $mftf;
                $logMessage .= "Stories comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr ."\n";
            }
        } elseif (isset($mftfTest['stories']) && !empty(trim($mftfTest['stories'][0]))) {
            $mftf = trim($mftfTest['stories'][0]);
            $this->mismatches[$key]['stories'] = $mftf;
            $logMessage .= "Stories comparison failed:\nmftf=" . $mftf . "\nzephyr=\n";
        }

        if (isset($mftfTest['severity'][0])) {
            $mftf = $this->transformSeverity($mftfTest['severity'][0]);
            if (isset($zephyrTest[JiraInfo::JIRA_FIELD_SEVERITY])) {
                $zephyr = trim($zephyrTest[JiraInfo::JIRA_FIELD_SEVERITY]['value']);
                if (strcasecmp($mftf, $zephyr) != 0) {
                    $this->mismatches[$key]['severity'] = $mftf;
                    $logMessage .= "Severity comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr ."\n";
                }
            } else {
                $this->mismatches[$key]['severity'] = $mftf;
                $logMessage .= "Severity comparison failed:\nmftf=" . $mftf . "\nzephyr=\n";
            }
        }

        if ((isset($mftfTest['skip'])) && trim(($zephyrTest['status']['name']) != "Skipped")) {
            $this->mismatches[$key]['skip'] = $mftfTest['skip'];
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

        if (!isset($zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE])
            || $zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] != $mftfTest['releaseLine'][0]) {
            $this->mismatches[$key]['release_line'] = $mftfTest['releaseLine'][0];
            $logMessage .= "Release line comparison failed:\nmftf is run from "
                . $mftfTest['releaseLine'][0]
                . " zephyr is "
                . $zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value']
                . "\n";
        }

        if (isset($this->mismatches[$key])) {
            // Save description as we will always update it
            if (!isset($this->mismatches[$key]['description'] )) {
                $this->mismatches[$key]['description'] = trim($mftfTest['description'][0]);
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
