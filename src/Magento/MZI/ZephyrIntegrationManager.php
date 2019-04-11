<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

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
     * Valid release lines
     *
     * @var array
     */
    public static $validReleaseLines = [
        '2.0.x',
        '2.1.x',
        '2.2.x',
        '2.3.x',
        '2.4.x',
    ];

    /**
     * Valid page builder release lines
     *
     * @var array
     */
    public static $validPbReleaseLines = [
        'PB1.0.x',
        'PB2.0.x',
    ];

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
     * Valid project keys
     *
     * @var array
     */
    public static $validProjectKeys = [
        'MC',
        'MCTEST',
        'MAGETWO',
    ];

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
     * Total unmatched zephyr tests
     *
     * @var integer
     */
    public static $totalUnmatched = 0;

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
            print("\nInvalid command option: \"--pbReleaseLine\"\n");
            $this->printUsage();
            exit(1);
        }

        $zephyrTests = GetZephyr::getInstance()->getTestsByProject(self::$project);
        $mftfTests = ParseMFTF::getInstance()->getTestObjects();

        $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
        $zephyrComparison->matchOnIdOrName();
        $toBeCreatedTests = $zephyrComparison->getCreateArrayByName();
        $toBeUpdatedTests = $zephyrComparison->getUpdateArray();
        $unmatchedTests = $zephyrComparison->getUnmatchedZephyrTests();

        CreateManager::getInstance()->performCreateOperations($toBeCreatedTests);
        UpdateManager::getInstance()->performUpdateOperations($toBeUpdatedTests);
        UpdateManager::getInstance()->performCleanupOperations($unmatchedTests);

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
        if (in_array($outStr, self::$validReleaseLines)) {
            self::$releaseLine = $outStr;
            return true;
        } else {
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
        if (in_array($outStr, self::$validPbReleaseLines)) {
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
        if (in_array($key, self::$validProjectKeys)) {
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
        print("Total Zephyr Tests Matched:                 " . self::$totalMatched . "\n");
        print("Total Zephyr Tests Unmatched:               " . self::$totalUnmatched . "\n");
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
