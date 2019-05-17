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
     * Unmatched categories
     */
    const UNMATCHED_CATEGORY_PAGE_BUILDER = 'pagebuilder';
    const UNMATCHED_CATEGORY_PWA = 'pwa';
    const UNMATCHED_CATEGORY_SKIPPED_MTF_TO_MFTF = 'skip_mtf_to_mftf';
    const UNMATCHED_CATEGORY_SKIPPED = 'skip';
    const UNMATCHED_CATEGORY_OTHER = 'other';

    /**
     * Chars to be trimmed
     */
    const TRIMMED_CHARS = " \t\n\r\0\x0B.;";

    /**
     * Delimiters to be used in Zephyr Description
     */
    const SYNC_END_DELIMITER = "*=== MFTF ZEPHYR SYNC END ===*";
    const SYNC_TEST_NAME_DELIMITER = "*Mftf Test: ";

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
     * array of MFTF test which need to be created in zephyr
     *
     * @var array
     */
    private $creates;

    /**
     * array of MFTF test which need to be updated in zephyr
     *
     * @var array
     */
    private $mismatches;

    /**
     * array of zephyr tests that uniquely match by mftf tests (including mismatches)
     *
     * @var array
     */
    private $matches;

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
     * array of relevant zephyr tests unmatched by any mftf tests
     *
     * @var array
     */
    private $unmatches;

    /**
     * array of mftf test names that don't have title and cannot be processed
     *
     * @var array
     */
    private $unProcessed;

    /**
     * mftf to zephyr map
     *
     * @var array
     */
    private $mftfToZephyr;

    /**
     * zephyr to mftf map
     *
     * @var array
     */
    private $zephyrToMftf;

    /**
     * Constructor for ZephyrComparison
     *
     * @param array $mftfTests
     * @param array $zephyrTests
     * @return void
     */
    public function __construct(array $mftfTests, array $zephyrTests)
    {
        $this->creates = [];
        $this->mismatches = [];
        $this->matches = [];
        $this->unmatches = [];
        $this->unProcessed = [];
        $this->zephyrToMftf = [];
        $this->mftfToZephyr = [];
        $this->mftfTests = $mftfTests;
        $this->zephyrTests = $zephyrTests;
        if (empty($zephyrTests)) {
            $this->zephyrStoryTitle = [];
            $this->zephyrTitle = [];
        }
        foreach ($this->zephyrTests as $key => $zephyrTest) {
            $title = trim($zephyrTest['summary'], self::TRIMMED_CHARS);
            if (isset($zephyrTest[JiraInfo::JIRA_FIELD_STORIES])) {
                $this->zephyrStoryTitle[$key] = trim($zephyrTest[JiraInfo::JIRA_FIELD_STORIES], self::TRIMMED_CHARS)
                    . $title;
            } else {
                $this->zephyrStoryTitle[$key] = $title;
            }
            $this->zephyrTitle[$key] = $title;
        }
    }

    /**
     * getter for creates array
     *
     * @return array
     */
    public function getCreateArray()
    {
        return $this->creates;
    }

    /**
     * getter for mismatches array
     *
     * @return array
     */
    public function getUpdateArray()
    {
        return $this->mismatches;
    }

    /**
     * getter for unmatches array
     *
     * @return array
     */
    public function getUnmatchedZephyrTests()
    {
        return $this->unmatches;
    }

    /**
     * Checks for TestCaseID as MFTF annotation.
     * Sends for ID comparision if exists, otherwise compares by Story.Title Value.
     * @return void
     * @throws \Exception
     */
    public function matchOnIdOrName()
    {
        if (empty($this->mftfTests) || empty($this->zephyrTests)) {
            print("\nNo MFTF or Zephyr test found. Exiting With Code 1\n");
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                "\nNo MFTF or Zephyr test found. Exiting With Code 1\n"
            );
            exit(1);
        }

        // Id match takes precedence, so do it first
        foreach ($this->mftfTests as $mftfTestName => $mftfTest) {
            // Set Release Line for the mftf test
            $mftfTest['releaseLine'][] = $this->getReleaseLine($mftfTest);
            if (isset($mftfTest['testCaseId']) && array_key_exists($mftfTest['testCaseId'][0], $this->zephyrTests)) {
                $this->setMatches($mftfTestName, $mftfTest['testCaseId'][0]);
                if ($this->uniqueMatch($mftfTestName, $mftfTest['testCaseId'][0])) {
                    $this->idCompare($mftfTestName, $mftfTest);
                }
            }
        }

        // Title/Story match next
        foreach ($this->mftfTests as $mftfTestName => $mftfTest) {
            $mftfTest['releaseLine'][] = $this->getReleaseLine($mftfTest);
            if (!isset($mftfTest['testCaseId']) || !array_key_exists($mftfTest['testCaseId'][0], $this->zephyrTests)) {
                $this->storyTitleCompare($mftfTestName, $mftfTest);
            }
        }
        $this->setComparisonArrays();
        $this->printComparisonArraysToLogs();
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
     * Print comparison arrays to logs
     *
     * @return void
     * @throws \Exception
     */
    private function printComparisonArraysToLogs()
    {
        foreach (array_keys($this->creates) as $key) {
            LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_CREATED)->info($key);
        }

        foreach ($this->mismatches as $key => $test) {
            LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_UPDATED)->info(
                $this->mismatches[$key]['mftf_test_name'] . ", " . $key);
        }

        foreach ($this->matches as $key => $value) {
            LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_MATCHED)->info($key . ", " . $value);
        }

        foreach ($this->mftfToZephyr as $name => $zKeys) {
            if (is_array($zKeys) && count($zKeys) > 1) {
                LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_ONE_M_TO_MANY_Z)->info(
                    $name . " -> " . implode(",", $zKeys)
                );
            }
        }

        foreach ($this->zephyrToMftf as $key => $mNames) {
            if (is_array($mNames) && count($mNames) > 1) {
                LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_ONE_Z_TO_MANY_M)->info(
                    $key . " -> " . implode(",", $mNames)
                );
            }
        }

        foreach ($this->unProcessed as $name) {
            LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_UNPROCESSED)->info($name);
        }

        foreach ($this->matches as $key => $value) {
            if (!array_key_exists($this->matches[$key], $this->mismatches)) {
                LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_SAME)->info(
                    $key . ", " . $value
                );
            }
        }

        LoggingUtil::getInstance()->getLogger(LoggingUtil::LOG_TYPE_UNMATCHED)->info(
            implode(",", array_keys($this->unmatches))
        );
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
        print("\nId Matched: $mftfTestName <-> " . $mftfTest['testCaseId'][0] . "   Comparing Data...\n");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
            "\nId Matched: $mftfTestName <-> " . $mftfTest['testCaseId'][0] . "   Comparing Data...\n"
        );
        $mftfTestCaseId = $mftfTest['testCaseId'][0];
        // MFTF testCaseID found a match in Zephyr, send test to comparison processing
        $this->testDataComparison(
            $mftfTestName,
            $mftfTest,
            $this->zephyrTests[$mftfTestCaseId],
            $mftfTestCaseId,
            true
        );
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
        if (!isset($mftfTest['title']) || empty(trim($mftfTest['title'][0], self::TRIMMED_CHARS))) {
            print(
                "\nMFTF TEST MISSING TITLE ANNOTATION: "
                . $mftfTestName
                . " No integration will be performed.\n"
            );
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->warn(
                "\nMFTF TEST MISSING TITLE ANNOTATION: " . $mftfTestName . " No integration will be performed.\n"
            );
            $this->unProcessed[] = $mftfTestName;
            return;
        }

        $title = trim($mftfTest['title'][0], self::TRIMMED_CHARS);
        $toCompares = [];
        if (isset($mftfTest['stories'])) {
            $mftfStoryTitle = trim($mftfTest['stories'][0], self::TRIMMED_CHARS) . $title;
            $toCompares[$mftfStoryTitle] = $this->zephyrStoryTitle;
        } else {
            // Set 'Stories' to empty string when MFTF test does not set 'Stories'
            $mftfTest['stories'] = [];
            $mftfTest['stories'][] = '';
        }
        $toCompares[$title] = $this->zephyrTitle;

        foreach ($toCompares as $key => $toCompare) {
            $subKeyMatched = $this->arraySearchWithDuplicates($key, $toCompare);
            $matches1 = [];
            foreach ($subKeyMatched as $subKey) {
                // Compare Test Type and Release Line
                // Use more test types to allow more matches and will eliminate when duplicates are found
                if ((!isset($this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_TEST_TYPE])
                        || ($this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_TEST_TYPE]['value'] != JiraInfo::JIRA_TEST_TYPE_MTF
                            && $this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_TEST_TYPE]['value'] != JiraInfo::JIRA_TEST_TYPE_INTEGRATION
                            && $this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_TEST_TYPE]['value'] != JiraInfo::JIRA_TEST_TYPE_API))
                    /* This has to be commented because of MQE-1385
                    && (!isset($this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_RELEASE_LINE])
                        || $this->zephyrTests[$subKey][JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] == $mftfTest['releaseLine'][0])*/) {
                    $matches1[] = $subKey;
                }
            }
            if (count($matches1) > 1) {
                $matches2 = [];
                foreach ($matches1 as $item) {
                    if ($this->zephyrTests[$item][JiraInfo::JIRA_FIELD_TEST_TYPE]['value'] == JiraInfo::JIRA_TEST_TYPE_MFTF) {
                        $matches2[] = $item;
                    }
                }
                if (count($matches2) == 1) {
                    $this->setMatches($mftfTestName, $matches2[0]);
                    if ($this->uniqueMatch($mftfTestName, $matches2[0])) {
                        print("\nStoryTitle Matched: $mftfTestName <-> " . $matches2[0] . "   Comparing Data...\n");
                        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                            "\nStoryTitle Matched: $mftfTestName <-> " . $matches2[0] . "   Comparing Data...\n"
                        );
                        $this->testDataComparison(
                            $mftfTestName,
                            $mftfTest,
                            $this->zephyrTests[$matches2[0]],
                            $matches2[0]
                        );
                    }
                } else {
                    foreach ($matches2 as $item2) {
                        $this->setMatches($mftfTestName, $item2);
                    }
                }
                return;
            } elseif (count($matches1) == 1) {
                $this->setMatches($mftfTestName, $matches1[0]);
                if ($this->uniqueMatch($mftfTestName, $matches1[0])) {
                    print("\nStoryTitle Matched: $mftfTestName <-> " . $matches1[0] . "   Comparing Data...\n");
                    LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
                        "\nStoryTitle Matched: $mftfTestName <-> " . $matches1[0] . "   Comparing Data...\n"
                    );
                    $this->testDataComparison(
                        $mftfTestName,
                        $mftfTest,
                        $this->zephyrTests[$matches1[0]],
                        $matches1[0]
                    );
                }
                return;
            }
        }

        // Add test to create array
        if (isset($mftfTest['severity'])) {
            $mftfTest['severity'][0] = $this->transformSeverity($mftfTest['severity'][0]);
        }
        $this->creates[$mftfTestName] = $mftfTest;
        print("\nNo Match: $mftfTestName To Be Created In Zephyr\n");
        LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info(
            "\nNo Match: $mftfTestName To Be Created In Zephyr\n"
        );
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
     * @param bool $force
     * @return void
     */
    private function testDataComparison($mftfTestName, array $mftfTest, array $zephyrTest, $key, $force = false)
    {
        $logMessage = '';
        if (isset($mftfTest['title']) && isset($zephyrTest['summary'])) {
            $mftf = trim($mftfTest['title'][0], self::TRIMMED_CHARS);
            $zephyr = trim($zephyrTest['summary'], self::TRIMMED_CHARS);
            if (strcasecmp($mftf, $zephyr) != 0) {
                $this->mismatches[$key]['title'] = $mftf;
                $logMessage .= "Title comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
            }
        } elseif (isset($mftfTest['title']) && !empty(trim($mftfTest['title'][0], self::TRIMMED_CHARS))) {
            $mftf = trim($mftfTest['title'][0], self::TRIMMED_CHARS);
            $this->mismatches[$key]['title'] = $mftf;
            $logMessage .= "Title comparison failed:\nmftf=" . $mftf . "\nzephyr=\n";
        }

        $description = '';
        $stickyDescription = '';
        if (isset($zephyrTest['description'])) {
            $parts1 = explode(self::SYNC_END_DELIMITER, $zephyrTest['description'], 2);
            if (count($parts1) == 2) {
                $stickyDescription = trim($parts1[1]);
                $parts2 = explode(self::SYNC_TEST_NAME_DELIMITER, $parts1[0], 2);
                $description = trim($parts2[0]);
            } else {
                $description = trim($zephyrTest['description']);
                $stickyDescription = trim($zephyrTest['description']);
            }
        }
        if (isset($mftfTest['description']) && !empty($description)) {
            $mftf = trim($mftfTest['description'][0]);
            $zephyr = $description;
            if (strcasecmp($mftf, $zephyr) != 0) {
                $this->mismatches[$key]['description'] = $mftf;
                $this->mismatches[$key]['sticky_description'] = $stickyDescription;
                $logMessage .= "Description comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
            }
        } elseif (isset($mftfTest['description']) && !empty(trim($mftfTest['description'][0]))) {
            $mftf = trim($mftfTest['description'][0]);
            $this->mismatches[$key]['description'] = $mftf;
            $this->mismatches[$key]['sticky_description'] = '';
            $logMessage .= "Description comparison failed:\nmftf=" . $mftf . "\nzephyr=\n";
        }

        if (isset($mftfTest['stories']) && isset($zephyrTest[JiraInfo::JIRA_FIELD_STORIES])) {
            $mftf = trim($mftfTest['stories'][0], self::TRIMMED_CHARS);
            $zephyr = trim($zephyrTest[JiraInfo::JIRA_FIELD_STORIES], self::TRIMMED_CHARS);
            if (strcasecmp($mftf, $zephyr) != 0) {
                $this->mismatches[$key]['stories'] = $mftf;
                $logMessage .= "Stories comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
            }
        } elseif (isset($mftfTest['stories']) && !empty(trim($mftfTest['stories'][0], self::TRIMMED_CHARS))) {
            $mftf = trim($mftfTest['stories'][0], self::TRIMMED_CHARS);
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

        if (!(isset($mftfTest['skip'])) && (trim($zephyrTest['status']['name']) != "Automated")) {
            $this->mismatches[$key]['automate'] = true;
            $logMessage .= "Automation Status comparison failed:\nmftf is \"Automated\" zephyr is "
                . trim($zephyrTest['status']['name'])
                . "\n";
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

        /* Not update release line ever
        if ($force && (!isset($zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE])
                || $zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] != $mftfTest['releaseLine'][0])) {
            $mftf = $mftfTest['releaseLine'][0];
            $zephyr = $zephyrTest[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'];
            $this->mismatches[$key]['release_line'] = $mftf;
            $logMessage .= "Release Line comparison failed:\nmftf=" . $mftf . "\nzephyr=" . $zephyr . "\n";
        }
        */

        if (isset($this->mismatches[$key])) {
            // Save description as we will always update it
            if (!isset($this->mismatches[$key]['description'])) {
                $this->mismatches[$key]['description'] = $description;
                $this->mismatches[$key]['sticky_description'] = $stickyDescription;
            }
            $this->mismatches[$key]['mftf_test_name'] = $mftfTestName; // Save mftf test name
            $this->mismatches[$key]['status'] = $zephyrTest['status']['name']; // Save current Zephyr status
            $this->mismatches[$key]['labels'] = $zephyrTest['labels']; // Save current zephyr labels
            print($logMessage);
            LoggingUtil::getInstance()->getLogger(ZephyrComparison::class)->info($logMessage);
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
            if (array_key_exists($key, $this->zephyrToMftf)) {
                if (count($this->zephyrToMftf[$key]) == 1 && $this->uniqueMatch($this->zephyrToMftf[$key][0], $key)) {
                    $this->matches[$this->zephyrToMftf[$key][0]] = $key;
                }
            } elseif ($test[JiraInfo::JIRA_FIELD_TEST_TYPE]['value'] == JiraInfo::JIRA_TEST_TYPE_MFTF
                && isset($test[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'])
                && ($test[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] == ZephyrIntegrationManager::$releaseLine
                    || $test[JiraInfo::JIRA_FIELD_RELEASE_LINE]['value'] == ZephyrIntegrationManager::$pbReleaseLine)
                && ($test['status']['name'] == 'Automated' || $test['status']['name'] == 'Skipped')

            ) {
                $this->unmatches[$key] = $test;
            }
        }
        foreach ($this->mftfTests as $name => $test) {
            if (array_key_exists($name, $this->mftfToZephyr)) {
                if (count($this->mftfToZephyr[$name]) == 1
                    && $this->uniqueMatch($name, $this->mftfToZephyr[$name][0])
                    && !isset($this->matches[$name])) {
                    $this->matches[$name] = $this->mftfToZephyr[$name][0];
                }
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
        ZephyrIntegrationManager::$totalMatched = count($this->matches);
        ZephyrIntegrationManager::$totalUnprocessed = count($this->unProcessed);
        ZephyrIntegrationManager::$totalUnmatched = count($this->unmatches);
        ZephyrIntegrationManager::$totalUnmatchedSkippedMtf = 0;
        ZephyrIntegrationManager::$totalUnmatchedSkipped = 0;
        ZephyrIntegrationManager::$totalUnmatchedPageBuilder = 0;
        ZephyrIntegrationManager::$totalUnmatchedPwa = 0;
        ZephyrIntegrationManager::$totalUnmatchedOther = 0;
        ZephyrIntegrationManager::$totalMtoZDuplicate = 0;
        ZephyrIntegrationManager::$totalZtoMDuplicate = 0;
        ZephyrIntegrationManager::$totalSame = 0;

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

        foreach ($this->mftfToZephyr as $name => $zKeys) {
            if (count($zKeys) > 1) {
                ZephyrIntegrationManager::$totalMtoZDuplicate += 1;
            }
        }

        foreach ($this->zephyrToMftf as $key => $mNames) {
            if (count($mNames) > 1) {
                ZephyrIntegrationManager::$totalZtoMDuplicate += 1;
            }
        }

        foreach ($this->matches as $key => $value) {
            if (!array_key_exists($this->matches[$key], $this->mismatches)) {
                ZephyrIntegrationManager::$totalSame += 1;
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
        // This is not entirely reliable until MQE-1385 is fixed, and it will indirectly affect release line value
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
        $count = array_count_values(array_values($haystack));
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

    /**
     * @param string $mftf
     * @param string $zephyr
     * @return void
     */
    private function setMatches($mftf, $zephyr)
    {
        $this->mftfToZephyr[$mftf][] = $zephyr;
        $this->zephyrToMftf[$zephyr][] = $mftf;
    }

    /**
     * @param string $mftf
     * @param string $zephyr
     * @return bool
     */
    private function uniqueMatch($mftf, $zephyr)
    {
        if (isset($this->mftfToZephyr[$mftf])
            && count($this->mftfToZephyr[$mftf]) == 1
            && $this->mftfToZephyr[$mftf][0] == $zephyr
            && isset($this->zephyrToMftf[$zephyr])
            && count($this->zephyrToMftf[$zephyr]) == 1
            && $this->zephyrToMftf[$zephyr][0] == $mftf) {
            return true;
        } else {
            return false;
        }
    }
}
