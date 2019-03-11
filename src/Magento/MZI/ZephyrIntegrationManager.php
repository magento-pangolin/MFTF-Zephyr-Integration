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
     * Page builder relase line
     *
     * @var string
     */
    public static $pbReleaseLine;

    /**
     * Total mftf tests
     *
     * @var string
     */
    public static $totalMftf = 0;

    /**
     * Total zephyr tests
     *
     * @var string
     */
    public static $totalZephyr = 0;

    /**
     * Total created zephyr tests
     *
     * @var string
     */
    public static $totalCreated = 0;

    /**
     * Total updated zephyr tests
     *
     * @var string
     */
    public static $totalUpdated = 0;

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
     * @param bool $isDryRun
     * @param string $project
     * @param string $releaseLine
     * @param string $pbReleaseLine
     * @return void
     * @throws \Exception
     */
    public function runMftfZephyrIntegration(
        $isDryRun = true,
        $project = '',
        $releaseLine = '',
        $pbReleaseLine = ''
    ) {
        // Set timestamp
        self::$timestamp = date("_m-d-Y_H-i-s");

        $this->parseOptions();

        $isDryRun = !is_null(self::$cmdOptions['dryRun']) ? self::$cmdOptions['dryRun'] : $isDryRun;
        $project = !is_null(self::$cmdOptions['project']) ? self::$cmdOptions['project'] : $project;
        $releaseLine = !is_null(self::$cmdOptions['releaseLine']) ? self::$cmdOptions['releaseLine'] : $releaseLine;
        $pbReleaseLine = !is_null(self::$cmdOptions['pbReleaseLine']) ? self::$cmdOptions['pbReleaseLine'] : $pbReleaseLine;
        if (!$this->validateReleaseLine($releaseLine) || !$this->validatePbReleaseLine($pbReleaseLine)) {
            $this->printUsage();
            exit(1);
        }

        $zephyrTests = GetZephyr::getInstance()->getTestsByProject($project);
        $mftfTests = ParseMFTF::getInstance()->getTestObjects();

        $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
        $zephyrComparison->matchOnIdOrName();
        $toBeCreatedTests = $zephyrComparison->getCreateArrayByName();
        $toBeUpdatedTests = $zephyrComparison->getUpdateArray();

        CreateManager::getInstance()->performCreateOperations($toBeCreatedTests, $isDryRun);
        UpdateManager::getInstance()->performUpdateOperations($toBeUpdatedTests, $isDryRun);

        $this->printStats();
    }

    private function parseOptions()
    {
        if (!isset($_SERVER['argv']) || !is_array($_SERVER['argv'])) {
            $this->printUsage();
            exit(1);
        }
        foreach ($_SERVER['argv'] as $arg) {
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
     * Print statistics
     */
    private function printStats()
    {
        print("\n\n=========================================================\n");
        print("Total Zephyr Tests Found by JQL:                  " . self::$totalZephyr . "\n");
        print("Total MFTF Tests Run:                             " . self::$totalMftf . "\n");
        print("Total New Zephyr Tests Created by Integration:    " . self::$totalCreated . "\n");
        print("Total Zephyr Tests Updated by Integration:        " . self::$totalUpdated . "\n");
        print("=========================================================\n\n");
    }

    /**
     * Print usage
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
}
