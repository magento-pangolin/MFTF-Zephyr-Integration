<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

class UpdateManager
{
    /**
     * @var UpdateManager
     */
    private static $instance;

    /**
     * UpdateManager constructor
     */
    private function __construct()
    {
        // private constructor
    }

    /**
     * Static singleton getInstance
     *
     * @return UpdateManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new UpdateManager();
        }
        return self::$instance;
    }

    /**
     * @param array $toBeUpdatedTests
     * @param bool $isDryRun
     * @throws \Exception
     */
    public function performUpdateOperations(array $toBeUpdatedTests, $isDryRun = true)
    {
        foreach ($toBeUpdatedTests as $key => $update) {
            $updateIssue = new UpdateIssue();
            $updateIssue->updateIssueREST($update, $key, $isDryRun);
        }
        ZephyrIntegrationManager::$totalUpdated = count($toBeUpdatedTests);
        print("\n\nTotal updated zephyr tests: " . count($toBeUpdatedTests) . "\n\n");
    }
}
