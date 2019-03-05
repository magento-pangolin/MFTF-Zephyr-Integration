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
     * CreateManager instance.
     *
     * @var CreateManager
     */
    public static $createManager;

    /**
     * Get CreateManager instance.
     *
     * @return CreateManager
     */
    public static function getInstance()
    {
        if (!self::$createManager) {
            self::$createManager = new CreateManager();
        }

        return self::$createManager;
    }

    /**
     * Manages passing data to Create operation and skipping test if necessary
     *
     * @param array $toBeCreatedTests
     * @param string $releaseLine
     * @param bool $isDryRun
     *
     * @return void
     * @throws \Exception
     */
    public function performCreateOperations(array $toBeCreatedTests, $releaseLine, $isDryRun = true)
    {
        foreach ($toBeCreatedTests as $test) {
            $mftfLoggingDescriptor = ZephyrComparison::mftfLoggingDescriptor($test);
            $createIssue = new CreateIssue($test);
            $response = $createIssue::createIssueREST($test, $releaseLine, $isDryRun);
            $createdIssueByName[] = $response;
        }
    }
}
