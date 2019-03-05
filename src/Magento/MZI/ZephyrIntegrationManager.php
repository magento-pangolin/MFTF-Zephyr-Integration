<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

require_once (__DIR__ . '/../../../../magento/vendor/autoload.php');

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
    public static $validReleaseLines = [
        'None',
        '2.4.x',
        '2.3.x',
        '2.0.x',
        '2.1.x',
        '2.2.x',
        'PB2.0.x',
        'PB2.1.x',
    ];

    /**
     * @param string $project
     * @param string $releaseLine
     * @return void
     * @throws \Exception
     */
    public static function runMftfZephyrIntegration($project = 'MC', $releaseLine = '2.3.x')
    {
        $project = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : $project;
        $releaseLine = self::normalizedReleaseLine(
            isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : $releaseLine
        );
        $getZephyr = new GetZephyr();
        $zephyrTests = $getZephyr->getTestsByProject($project);
        $parseMFTF = new ParseMFTF();
        $mftfTests = $parseMFTF->getTestObjects();

        $zephyrComparison = new ZephyrComparison($mftfTests, $zephyrTests);
        $zephyrComparison->matchOnIdOrName();
        $toBeCreatedTests = $zephyrComparison->getCreateArrayByName();
        $toBeUpdatedTests = $zephyrComparison->getUpdateArray();

        CreateManager::getInstance()->performCreateOperations($toBeCreatedTests, $releaseLine, true);
        UpdateManager::getInstance()->performUpdateOperations($toBeUpdatedTests, $releaseLine, true);
    }

    /**
     * @param string $inStr
     * @return string
     */
    public static function normalizedReleaseLine($inStr)
    {
        $outStr = substr($inStr, 0, 2);
        $remainStr = substr($inStr, 2);
        $outStr .= strtolower($remainStr);
        if (in_array($outStr, self::$validReleaseLines) === true) {
            return $outStr;
        } else {
            return self::$validReleaseLines[0];
        }
    }
}

ZephyrIntegrationManager::runMftfZephyrIntegration();
