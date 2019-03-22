<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use Magento\MZI\LoggingUtil;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use JiraRestApi\Issue\Transition;
use JiraRestApi\IssueLink\IssueLink;
use JiraRestApi\IssueLink\IssueLinkService;

/**
 * Class CreateManager, handles all the CREATE requests for new Zephyr tests.
 */
class CreateManager
{
    /**
     * @var CreateManager
     */
    private static $instance;

    /**
     * CreateManager constructor
     */
    private function __construct()
    {
        // private constructor
    }

    /**
     * Static singleton getInstance
     *
     * @return CreateManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new CreateManager();
        }
        return self::$instance;
    }

    /**
     * Manages passing data to Create operation and skipping test if necessary
     *
     * @param mixed $toBeCreatedTests
     *
     * @return void
     * @throws \Exception
     */
    public function performCreateOperations($toBeCreatedTests)
    {
        if (!is_array($toBeCreatedTests)) {
            print("\n\nTotal Zephyr Tests Created: 0\n\n");
            return;
        }
        $count = 0;
        $total = count($toBeCreatedTests);
        print("\n\nTotal Zephyr Tests To Be Created: $total\n\n");
        foreach ($toBeCreatedTests as $testName => $test) {
            $createIssue = new CreateIssue($test);
            $response = $createIssue->createIssueREST($testName, $test);
            $createdIssueByName[] = $response;
            $count += 1;
            print("\nZephyr Tests Created: $count" . "/" . "$total\n\n");
        }
        ZephyrIntegrationManager::$totalCreated = $count;
        print("\n\nTotal Zephyr Tests Created: $count\n\n");
    }
}
