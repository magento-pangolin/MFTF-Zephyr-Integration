<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use Magento\MZI\Util\JiraInfo;

/**
 *Purpose of Manager
 * 1. call GetZephyrTest and store resulting json
 * 2. get MFTF test data from parsers
 * 3. call ZephyrComparison to build list of Create and Update
 * 4. call Creates
 * 5. call Updates
 * 6. Log returns and created IDs
 * 7. Log errors
 */
class ZephyrIntegrationManager
{
    /**
     * Release line
     *
     * @var string
     */
    public static $releaseLine;

    /**
     * Page builder release line
     *
     * @var string
     */
    public static $pbReleaseLine;

    /**
     * Project key
     *
     * @var string
     */
    public static $project;

    /**
     * Run mode: dryRun or production
     *
     * @var bool
     */
    public static $dryRun = true;

    /**
     * Total mftf tests
     *
     * @var integer
     */
    public static $totalMftf = 0;

    /**
     * Total zephyr tests
     *
     * @var integer
     */
    public static $totalZephyr = 0;

    /**
     * Total created zephyr tests
     *
     * @var integer
     */
    public static $totalCreated = 0;

    /**
     * Total updated zephyr tests
     *
     * @var integer
     */
    public static $totalUpdated = 0;

    /**
     * Total matched zephyr tests
     *
     * @var integer
     */
    public static $totalMatched = 0;

    /**
     * Total same mftf and zephyr / no change
     *
     * @var integer
     */
    public static $totalSame = 0;

    /**
     * Total unmatched zephyr tests
     *
     * @var integer
     */
    public static $totalUnmatched = 0;

    /**
     * Total unmatched PWA zephyr tests
     *
     * @var integer
     */
    public static $totalUnmatchedPwa = 0;

    /**
     * Total unmatched Page Builder zephyr tests
     *
     * @var integer
     */
    public static $totalUnmatchedPageBuilder = 0;

    /**
     * Total unmatched skipped due to mtf zephyr tests
     *
     * @var integer
     */
    public static $totalUnmatchedSkippedMtf = 0;

    /**
     * Total unmatched skipped tests
     *
     * @var integer
     */
    public static $totalUnmatchedSkipped = 0;

    /**
     * Total unmatched other tests
     *
     * @var integer
     */
    public static $totalUnmatchedOther = 0;

    /**
     * Total mftf tests that matches more than one zephyr tests
     *
     * @var integer
     */
    public static $totalMtoZDuplicate = 0;

    /**
     * Total zephyr tests that matches more than one mftf tests
     *
     * @var integer
     */
    public static $totalZtoMDuplicate = 0;

    /**
     * Total mftf tests that are not processed due to missing title
     *
     * @var integer
     */
    public static $totalUnprocessed = 0;

    /**
     * Retry count (default to 5)
     *
     * @var integer
     */
    public static $retryCount = 5;

    /**
     * Timestamp for labeling
     *
     * @var string
     */
    public static $timestamp;

    /**
     * Commandline options
     *
     * @var array
     */
    public static $cmdOptions = [
        'dryRun' => null,
        'releaseLine' => null,
        'pbReleaseLine' => null,
        'project' => null,
    ];

    /**
     * @var ZephyrIntegrationManager
     */
    private static $instance;

    /**
     * ZephyrIntegrationManager constructor
     */
    private function __construct()
    {
        // private constructor
    }

    /**
     * Static singleton getInstance
     *
     * @return ZephyrIntegrationManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new ZephyrIntegrationManager();
        }
        return self::$instance;
    }

    /**
     * @param bool $dryRun
     * @param string $project
     * @param string $releaseLine
     * @param string $pbReleaseLine
     * @return void
     * @throws \Exception
     */
    public function runMftfZephyrIntegration(
        $dryRun = true,
        $project = '',
        $releaseLine = '',
        $pbReleaseLine = ''
    ) {
        // Set retry count
        $this->setRetryCount();

        // Set timestamp
        self::$timestamp = date("_m-d-Y_H-i-s");

        $this->parseOptions();

        self::$dryRun = !is_null(self::$cmdOptions['dryRun']) ? self::$cmdOptions['dryRun'] : $dryRun;
        $project = !is_null(self::$cmdOptions['project']) ? self::$cmdOptions['project'] : $project;
        $releaseLine = !is_null(self::$cmdOptions['releaseLine']) ? self::$cmdOptions['releaseLine'] : $releaseLine;
        $pbReleaseLine = !is_null(self::$cmdOptions['pbReleaseLine']) ? self::$cmdOptions['pbReleaseLine'] : $pbReleaseLine;

        if (!$this->validateProject($project)) {
            print("\nInvalid command option: \"--project\"\n");
            $this->printUsage();
            exit(1);
        }

        if (!$this->validateReleaseLine($releaseLine)) {
            print("\nInvalid command option: \"--releaseLine\"\n");
            $this->printUsage();
            exit(1);
        }

        if (!$this->validatePbReleaseLine($pbReleaseLine)) {
            print("\nSkipping Page Builder Synchronization\n");
        }

        $mftfTests = ParseMFTF::getInstance()->getTestObjects();

        $zephyrTests = GetZephyr::getInstance()->getTestsByProject(
            self::$project,
            self::$releaseLine,
            self::$pbReleaseLine
        );

        $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
        $zephyrComparison->matchOnIdOrName();
        $toBeCreatedTests = $zephyrComparison->getCreateArray();
        $toBeUpdatedTests = $zephyrComparison->getUpdateArray();

        CreateManager::getInstance()->performCreateOperations($toBeCreatedTests);
        UpdateManager::getInstance()->performUpdateOperations($toBeUpdatedTests);

        $zephyrComparison->printJqlForUnmatchedZephyrTests();
        $this->printStats();
        exit(0); // Done
    }

    /**
     * Parse command line options
     * @return void
     */
    private function parseOptions()
    {
        $args = [];
        if (isset($_SERVER['argv'])) {
            $args = $_SERVER['argv'];
        } elseif (isset($argv)) {
            $args = $argv;
        } else {
            print("\nCommand line argv is not set\n");
            $this->printUsage();
            exit(1);
        }

        foreach ($args as $arg) {
            $parts = explode('=', $arg);
            if (count($parts) == 2) {
                $key = ltrim(trim($parts[0]," \t\n\r\0\x0B\'\""), "\-\--");
                $val = trim($parts[1]," \t\n\r\0\x0B\'\"");
                if (array_key_exists($key, self::$cmdOptions)) {
                    self::$cmdOptions[$key] = $val;
                }
            }
        }
    }

    /**
     * Validate release line
     *
     * @param string $inStr
     * @return bool
     */
    private function validateReleaseLine($inStr)
    {
        $outStr = substr($inStr, 0, 2);
        $remainStr = substr($inStr, 2);
        $outStr .= strtolower($remainStr);
        if (in_array($outStr, JiraInfo::$validReleaseLines)) {
            self::$releaseLine = $outStr;
            return true;
        } else {
            self::$releaseLine = null;
            return false;
        }
    }

    /**
     * Validate page builder release line
     *
     * @param string $inStr
     * @return bool
     */
    private function validatePbReleaseLine($inStr)
    {
        $outStr = substr($inStr, 0, 2);
        $remainStr = substr($inStr, 2);
        $outStr .= strtolower($remainStr);
        if (in_array($outStr, JiraInfo::$validPbReleaseLines)) {
            self::$pbReleaseLine = $outStr;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Validate project
     *
     * @param string $key
     * @return bool
     */
    private function validateProject($key)
    {
        if (in_array($key, JiraInfo::$validProjectKeys)) {
            self::$project = $key;
            return true;
        }
        return false;
    }

    /**
     * Print statistics
     * @return void
     */
    private function printStats()
    {
        print("\n\n===================================================\n");
        print("Total Zephyr Tests Retrieved from JIRA:     " . self::$totalZephyr . "\n");
        print("Total MFTF Tests Read from Code:            " . self::$totalMftf . "\n");
        print("---------------------------------------------------\n");
        print("Total Zephyr Tests Created:                 " . self::$totalCreated . "\n");
        print("Total Zephyr Tests Updated:                 " . self::$totalUpdated . "\n");
        print("Total Zephyr Tests Same As MFTF:            " . self::$totalSame . "\n");
        print("Total Zephyr Tests Unmatched:               " . self::$totalUnmatched . "\n");
        print("Total 1 Zephyr <-> 1 MFTF Matched:          " . self::$totalMatched . "\n");
        print("Total 1 Zephyr  -> x MFTF Matched:          " . self::$totalZtoMDuplicate . "\n");
        print("Total 1 MFTF    -> x Zephyr Matched:        " . self::$totalMtoZDuplicate . "\n");
        print("Total MFTF Tests Unprocessed/No Title:      " . self::$totalUnprocessed . "\n");
        print("- - - - - - - - - - - - - - - - - - - - - - - - - -\n");
        print("- Total Unmatched Page Builder:             " . self::$totalUnmatchedPageBuilder . "\n");
        print("- Total Unmatched PWA:                      " . self::$totalUnmatchedPwa . "\n");
        print("- Total Unmatched Skip (MTF-TO-MFTF):       " . self::$totalUnmatchedSkippedMtf . "\n");
        print("- Total Unmatched Skip:                     " . self::$totalUnmatchedSkipped . "\n");
        print("- Total Unmatched Other:                    " . self::$totalUnmatchedOther . "\n");
        print("===================================================\n\n");
    }

    /**
     * Print usage
     * @return void
     */
    private function printUsage()
    {
        print("\nUsage: ZephyrIntegrationManager [options]=[values]\n\n");
        print("Options:\n");
        print("--dryRun           Run local test or run on Jira database\n");
        print("--project          Jira project key\n");
        print("--releaseLine      Magento release line that mftf tests run on\n");
        print("--pbReleaseLine    Magento Page Builder release line that mftf tests run on\n\n");
    }

    /**
     * Set retry count
     * @return void
     */
    private function setRetryCount()
    {
        $retryCnt = getenv('RETRY_COUNT');
        if ($retryCnt !== false && !is_null($retryCnt)) {
            self::$retryCount = $retryCnt;
        }
    }
}
