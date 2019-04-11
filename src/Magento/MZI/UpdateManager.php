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
     * Manages passing data to Update operation and skipping test if necessary
     *
     * @param mixed $toBeUpdatedTests
     *
     * @return void
     * @throws \Exception
     */
    public function performUpdateOperations($toBeUpdatedTests)
    {
        if (!is_array($toBeUpdatedTests)) {
            print("\n\nTotal Zephyr Tests Updated: 0\n\n");
            return;
        }
        $count = 0;
        $total = count($toBeUpdatedTests);
        print("\n\nTotal Zephyr Tests To Be Updated: $total\n\n");
        foreach ($toBeUpdatedTests as $key => $update) {
            $updateIssue = new UpdateIssue();
            $updateIssue->updateIssueREST($update, $key);
            $count += 1;
            print("\nZephyr Tests Updated: $count" . "/" . "$total\n\n");
        }
        ZephyrIntegrationManager::$totalUpdated = $count;
        print("\n\nTotal Zephyr Tests Updated: $count\n\n");
    }

    /**
     * Manages labeling unmatched Zephyr tests
     *
     * @param array $unmatchedTests
     *
     * @return void
     * @throws \Exception
     */
    public function performCleanupOperations($unmatchedTests)
    {
        $count = 0;
        $total = count($unmatchedTests);
        print("\n\nTotal Unmatched Zephyr Tests To Be Labeled: $total\n\n");
        foreach ($unmatchedTests as $key => $test) {
            $updateIssue = new UpdateIssue();
            $updateIssue->labelIssueREST($test, $key);
            $count += 1;
            print("\nUnmatched Zephyr Tests Labeled: $count" . "/" . "$total\n\n");
        }
        print("\n\nTotal Unmatched Zephyr Tests Labeled: $count\n\n");
    }
}
