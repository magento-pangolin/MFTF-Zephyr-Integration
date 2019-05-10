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
     * Zephyr test process status key
     */
    const MZI_STATUS_KEY = "mzi_status";
    /**
     * Zephyr test process status values
     */
    const MZI_STATUS_VALUE_NO_MATCH = "NOMATCH";
    const MZI_STATUS_VALUE_MATCHED = "MATCHED";
    const MZI_STATUS_VALUE_UPDATED = "UPDATED";
    /**
     * Unmatched categories
     */
    const UNMATCHED_CATEGORY_PAGE_BUILDER = 'pagebuilder';
    const UNMATCHED_CATEGORY_PWA = 'pwa';
    const UNMATCHED_CATEGORY_SKIPPED_MTF_TO_MFTF = 'skip_mtf_to_mftf';
    const UNMATCHED_CATEGORY_SKIPPED = 'skip';
    const UNMATCHED_CATEGORY_OTHER = 'other';

    /**
     * array of MFTF tests from ParseMFTF class
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
     * Concatenated string of Story and Title in Zephyr for comparison
     *
     * @var array
     */
    private $zephyrStoryTitle;

    /**
     * Zephyr Title array for comparison
     *
     * @var array
     */
    private $zephyrTitle;

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
     * array of zephyr tests that match entirely by mftf tests (excluding mismatches)
     *
     * @var array
     */
    private $matches;

    /**
     * array of discrepancies found between mftf and associated zephyr test
     *
     * @var array
     */
    private $mismatches;

    /**
     * array of zephyr tests unmatched by any mftf tests
     *
     * @var array
     */
    private $unmatches;

    /**
     * Constructor for ZephyrComparison
     *
     * @param array $mftfTests
     * @param array $zephyrTests
     * @return void
     */
    public function __construct(array $mftfTests, array $zephyrTests)
    {
        $this->matches = [];
        $this->mismatches = [];
        $this->unmatches = [];
        $this->mftfTests = $mftfTests;
        $this->zephyrTests = $zephyrTests;
        if (empty($zephyrTests)) {
            $this->zephyrStoryTitle = [];
            $this->zephyrTitle = [];
        }
        foreach ($this->zephyrTests as $key => $zephyrTest) {
            $this->zephyrTests[$key][self::MZI_STATUS_KEY] = self::MZI_STATUS_VALUE_NO_MATCH;
            $title = trim($zephyrTest['summary']);
            if (isset($zephyrTest[JiraInfo::JIRA_FIELD_STORIES])) {
                $this->zephyrStoryTitle[$key] = trim($zephyrTest[JiraInfo::JIRA_FIELD_STORIES]) . $title;
            } else {
                $this->zephyrStoryTitle[$key] = $title;
            }
            $this->zephyrTitle[$key] = $title;
        }
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
     * getter for unmatches
     *
     * @return array
     */
    public function getUnmatchedZephyrTests()
    {
        return $this->unmatches;
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
        } elseif (isset($mftfTest['stories']) && isset($mftfTest['title'])) {
            $mftfLoggingDescriptor = $mftfTest['stories'][0] . $mftfTest['title'][0];
        } elseif (isset($mftfTest['title'])) {
            $mftfLoggingDescriptor = $mftfTest['title'][0];
        } else {
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
            // Set Release Line for the mftf test
            $mftfTest['releaseLine'][] = $this->getReleaseLine($mftfTest);
            if (isset($mftfTest['testCaseId'])) {
                if (!empty($this->zephyrTests) && array_key_exists($mftfTest['testCaseId'][0], $this->zephyrTests)) {
                    $this->zephyrTests[$mftfTest['testCaseId'][0]][self::MZI_STATUS_KEY] = self::MZI_STATUS_VALUE_MATCHED;
                    $this->idCompare($mftfTestName, $mftfTest);
                } else {
                    LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                        'TestCaseId '
                        . $mftfTest['testCaseId'][0]
                        . ' exists in MFTF but can not be found in Zephyr MC project.'
                    );
                    $this->storyTitleCompare($mftfTestName, $mftfTest);
                }
            } else {
                $this->storyTitleCompare($mftfTestName, $mftfTest);
            }
        }
        $this->setComparisonArrays();
        $this->postComparisonData();
    }

    /**
     * Print JQL for unmatched zephyr tests
     *
     * @return void
     * @throws \Exception
     */
    public function printJqlForUnmatchedZephyrTests()
    {
        $keys = [];
        foreach ($this->unmatches as $key => $test) {
            if ($this->isPageBuilderZephyrTest($test)) {
                $keys[self::UNMATCHED_CATEGORY_PAGE_BUILDER][] = $key;
            } elseif ((isset($test[JiraInfo::JIRA_FIELD_GROUP]) && $test[JiraInfo::JIRA_FIELD_GROUP]['value'] == 'PWA')
                || in_array(JiraInfo::JIRA_LABEL_PWA, $test['labels'])) {
                $keys[self::UNMATCHED_CATEGORY_PWA][] = $key;
            } elseif ($test['status']['name'] == 'Skipped'
                && in_array(JiraInfo::JIRA_LABEL_MTF_TO_MFTF, $test['labels'])) {
                $keys[self::UNMATCHED_CATEGORY_SKIPPED_MTF_TO_MFTF][] = $key;
            } elseif ($test['status']['name'] == 'Skipped') {
                $keys[self::UNMATCHED_CATEGORY_SKIPPED][] = $key;
            } else {
                $keys[self::UNMATCHED_CATEGORY_OTHER][] = $key;
            }
        }
        $b = 'key in (';
        $e = ')';

        print("\nJQLs for Unmatched Zephyr Tests:");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
            "\nJQLs for Unmatched Zephyr Tests:"
        );

        print("\n\nPage Builder:\n");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info("\n\nPage Builder:\n");
        if (isset($keys[self::UNMATCHED_CATEGORY_PAGE_BUILDER])) {
            print($b . implode(',', $keys[self::UNMATCHED_CATEGORY_PAGE_BUILDER]) . $e);
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                $b . implode(',', $keys[self::UNMATCHED_CATEGORY_PAGE_BUILDER]) . $e
            );
        }

        print("\n\nPWA:\n");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info("\n\nPWA:\n");
        if (isset($keys[self::UNMATCHED_CATEGORY_PWA])) {
            print($b . implode(',', $keys[self::UNMATCHED_CATEGORY_PWA]) . $e);
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                $b . implode(',', $keys[self::UNMATCHED_CATEGORY_PWA]) . $e
            );
        }

        print("\n\nMTF Inherited Skipped:\n");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info("\n\nSkipped (MTF-To-MFTF):\n");
        if (isset($keys[self::UNMATCHED_CATEGORY_SKIPPED_MTF_TO_MFTF])) {
            print(
                $b
                . implode(',', $keys[self::UNMATCHED_CATEGORY_SKIPPED_MTF_TO_MFTF])
                . $e
            );
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                $b
                . implode(',', $keys[self::UNMATCHED_CATEGORY_SKIPPED_MTF_TO_MFTF])
                . $e
            );
        }

        print("\n\nOther Skipped:\n");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info("\n\nOther Skipped:\n");
        if (isset($keys[self::UNMATCHED_CATEGORY_SKIPPED])) {
            print($b . implode(',', $keys[self::UNMATCHED_CATEGORY_SKIPPED]) . $e);
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                $b . implode(',', $keys[self::UNMATCHED_CATEGORY_SKIPPED]) . $e
            );
        }

        print("\n\nOther:\n");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info("\n\nOther:\n");
        if (isset($keys[self::UNMATCHED_CATEGORY_OTHER])) {
            print($b . implode(',', $keys[self::UNMATCHED_CATEGORY_OTHER]) . $e . "\n");
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                $b . implode(',', $keys[self::UNMATCHED_CATEGORY_OTHER]) . $e . "\n"
            );
        }
    }

    /**
     * Return information for all linked issues for a test
     *
     * @param array $test
     *
     * @return string
     * @throws \Exception
     */
    public function getLinkedIssues(array $test)
    {
        $linkInfo = "Issues linked:\n";
        if (!isset($test[JiraInfo::JIRA_FIELD_ISSUE_LINKS])) {
            return $linkInfo;
        }
        for ($i = 0; $i < count($test[JiraInfo::JIRA_FIELD_ISSUE_LINKS]); $i++) {
            $link = $test[JiraInfo::JIRA_FIELD_ISSUE_LINKS][$i];
            $linkInfo .= "#" . strval($i + 1) . ":\n";

            if (isset($link[JiraInfo::JIRA_FIELD_INWARD_ISSUE])) {
                $linkInfo .= "Inward Issue = " . $link[JiraInfo::JIRA_FIELD_INWARD_ISSUE]['key'] . " ";
                $linkInfo .= "Issue Type = " . $link['type'][JiraInfo::JIRA_FIELD_INWARD] . "\n";
            }

            if (isset($link[JiraInfo::JIRA_FIELD_OUTWARD_ISSUE])) {
                $linkInfo .= "Outward Issue = " . $link[JiraInfo::JIRA_FIELD_OUTWARD_ISSUE]['key'] . " ";
                $linkInfo .= "Issue Type = " . $link['type'][JiraInfo::JIRA_FIELD_OUTWARD] . "\n";
            }
        }
        return $linkInfo;
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
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
            "TestCaseId " . $mftfTest['testCaseId'][0] . " matched. Comparing data...\n"
        );
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
        if (!isset($mftfTest['title']) || empty(trim($mftfTest['title'][0]))) {
            $mftfLoggingDescriptor = self::mftfLoggingDescriptor($mftfTest);
            print(
                "\nMFTF TEST MISSING TITLE ANNOTATION: "
                . $mftfLoggingDescriptor
                . " No integration will be performed.\n"
            );
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                "\nMFTF TEST MISSING TITLE ANNOTATION: " . $mftfLoggingDescriptor . " No integration will be performed.\n"
            );
            return;
        }

        $toCompares = [];
        if (isset($mftfTest['stories'])) {
            $mftfStoryTitle = trim($mftfTest['stories'][0]) . trim($mftfTest['title'][0]);
            if (!empty($mftfStoryTitle)) {
                $toCompares = [$mftfStoryTitle => $this->zephyrStoryTitle];
            }
        } else {
            // Set 'Stories' to empty string when MFTF test does not set 'Stories'
            $mftfTest['stories'] = [];
            $mftfTest['stories'][] = '';
        }
        if (!empty(trim($mftfTest['title'][0]))) {
            $toCompares = [trim($mftfTest['title'][0]) => $this->zephyrTitle];
        }

        foreach ($toCompares as $key => $toCompare) {
            $subKeyMatched = $this->arraySearchWithDuplicates($key, $toCompare);
            foreach ($subKeyMatched as $subKey) {
                // Keep track of the match
                $this->zephyrTests[$subKey][self::MZI_STATUS_KEY] = self::MZI_STATUS_VALUE_MATCHED;
                LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                    "Found A Match for \"$key\", Comparing Release Line...\n"
                );
                // Compare Release Line
                if (!isset($this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_RELEASE_LINE])
                    || $this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] == $mftfTest['releaseLine'][0]) {
                    // Release line matched or Zephyr test does not have Release Line set, add test to update array
                    $this->testDataComparison(
                        $mftfTestName,
                        $mftfTest,
                        $this->zephyrTests[$subKey],
                        $subKey
                    );
                    $this->updateByName[] = $mftfTest;
                    return;
                }
            }
        }

        // Add test to create array
        if (isset($mftfTest['severity'])) {
            $mftfTest['severity'][0] = $this->transformSeverity($mftfTest['severity'][0]);
        }
        $this->createArrayByName[$mftfTestName] = $mftfTest;
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
                $logMessage .= "Title comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
            }
        } elseif (isset($mftfTest['title']) && !empty(trim($mftfTest['title'][0]))) {
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
                $logMessage .= "Description comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
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
                $logMessage .= "Stories comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
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
                    $logMessage .= "Severity comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
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
            $logMessage .= "mftf testCaseId " . $mftfTest['testCaseId'][0] . " is linked to zephyr issue " . $key . "\n";
            $logMessage .= "Please update mftf testCaseId in code in release line " . $mftfTest['releaseLine'][0] . " \n";
        }

        if ($zephyrTest[JiraInfo::JIRA_FIELD_TEST_TYPE]['value'] != JiraInfo::JIRA_TEST_TYPE_MFTF) {
            $mftf = JiraInfo::JIRA_TEST_TYPE_MFTF;
            $zephyr = $zephyrTest[JiraInfo::JIRA_FIELD_TEST_TYPE]['value'];
            $this->mismatches[$key]['test_type'] = $mftf;
            $logMessage .= "Test Type comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
        }

        if (isset($this->mismatches[$key])) {
            $this->zephyrTests[$key][self::MZI_STATUS_KEY] = self::MZI_STATUS_VALUE_UPDATED;
            // Save description as we will always update it
            if (!isset($this->mismatches[$key]['description'])) {
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
     * Set comparison results in matches & unmathes array
     *
     * @return void
     */
    private function setComparisonArrays()
    {
        $this->matches = [];
        $this->unmatches = [];
        foreach ($this->zephyrTests as $key => $test) {
            if ($test[self::MZI_STATUS_KEY] == self::MZI_STATUS_VALUE_NO_MATCH
                && $test[JiraInfo::JIRA_FIELD_TEST_TYPE]['value'] == JiraInfo::JIRA_TEST_TYPE_MFTF
                && ($test[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] == ZephyrIntegrationManager::$releaseLine
                    || $test[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] == ZephyrIntegrationManager::$pbReleaseLine)
            ) {
                $this->unmatches[$key] = $test;
            } elseif ($test[self::MZI_STATUS_KEY] == self::MZI_STATUS_VALUE_MATCHED) {
                $this->matches[$key] = $test;
            }
        }
    }

    /**
     * Post mftf and zephyr comparison statistic data
     *
     * @return void
     */
    private function postComparisonData()
    {
        ZephyrIntegrationManager::$totalMatched = count($this->matches) + count($this->mismatches);
        ZephyrIntegrationManager::$totalUnmatched = count($this->unmatches);
        ZephyrIntegrationManager::$totalUnmatchedSkippedMtf = 0;
        ZephyrIntegrationManager::$totalUnmatchedSkipped = 0;
        ZephyrIntegrationManager::$totalUnmatchedPageBuilder = 0;
        ZephyrIntegrationManager::$totalUnmatchedPwa = 0;
        ZephyrIntegrationManager::$totalUnmatchedOther = 0;

        foreach ($this->unmatches as $key => $test) {
            if ($this->isPageBuilderZephyrTest($test)) {
                ZephyrIntegrationManager::$totalUnmatchedPageBuilder += 1;
            } elseif ((isset($test[JiraInfo::JIRA_FIELD_GROUP]) && $test[JiraInfo::JIRA_FIELD_GROUP]['value'] == 'PWA')
                || in_array(JiraInfo::JIRA_LABEL_PWA, $test['labels'])) {
                ZephyrIntegrationManager::$totalUnmatchedPwa += 1;
            } elseif ($test['status']['name'] == 'Skipped'
                && in_array(JiraInfo::JIRA_LABEL_MTF_TO_MFTF, $test['labels'])) {
                ZephyrIntegrationManager::$totalUnmatchedSkippedMtf += 1;
            } elseif ($test['status']['name'] == 'Skipped') {
                ZephyrIntegrationManager::$totalUnmatchedSkipped += 1;
            } else {
                ZephyrIntegrationManager::$totalUnmatchedOther += 1;
            }
        }
    }

    /**
     * Determine if the given zephyr test is a page builder test
     *
     * @param array $zephyrTest
     * @return bool
     */
    private function isPageBuilderZephyrTest(array $zephyrTest)
    {
        foreach ($zephyrTest['components'] as $component) {
            if ($component['name'] == 'Module/ PageBuilder') {
                return true;
            }
        }
        if ($zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] == ZephyrIntegrationManager::$pbReleaseLine) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the given mftf test is a page builder test
     *
     * @param array $mftfTest
     * @return bool
     */
    private function isPageBuilderMftfTest($mftfTest)
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
        if ($this->isPageBuilderMftfTest($mftfTest)) {
            return ZephyrIntegrationManager::$pbReleaseLine;
        } else {
            return ZephyrIntegrationManager::$releaseLine;
        }
    }

    /**
     * Searches the array for a given value and returns all possible keys
     *
     * @param string $needle
     * @param array $haystack
     *
     * @return array
     */
    private function arraySearchWithDuplicates($needle, array $haystack)
    {
        $outArray = [];
        $count = array_count_values($haystack);
        if (isset($count[$needle])) {
            for ($i = 0; $i < $count[$needle]; $i++) {
                $key = array_search($needle, $haystack);
                if ($key !== false) {
                    $outArray[] = $key;
                    $haystack[$key] = '**USED**' . $needle . '**USED**';
                }
            }
        }
        return $outArray;
    }
}
