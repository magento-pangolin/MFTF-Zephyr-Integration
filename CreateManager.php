<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;

use Magento\JZI\LoggingUtil;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use JiraRestApi\Issue\Transition;
use JiraRestApi\IssueLink\IssueLink;
use JiraRestApi\IssueLink\IssueLinkService;
include_once ('CreateIssue.php');
include_once ('ZephyrComparison.php');
include_once ('UpdateIssue.php');
include_once ('Util/LoggingUtil.php');

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
    public static function getInstance() {
        if (!self::$createManager) {
            self::$createManager = new CreateManager();
        }

        return self::$createManager;


    }

    /**
     * Manages passing data to Create operation and skipping test if necessary
     *
     * @param $createByName
     *
     * @return array
     */
    public function performCreateOperations($createByName) {
        //loop - test for if skipped. if skipped, pass to skipCreate, if not, vanillaCreate
        foreach ($createByName as $id) {
            // if (array_key_exists($id, $skipped)) {
            $createIssue = new CreateIssue($id);
            $response = $createIssue::createIssuesREST($id);
            $createdIssueByName[] = $response;
            $mftfLoggingDescriptor = ZephyrComparison::mftfLoggingDescriptor($id);
            //LoggingUtil::getInstance()->getLogger(CreateManager::class)->info('NEW TEST sent to CREATE: ' . $mftfLoggingDescriptor);

            if (isset($id['skip'])) {
                $id += ['key' => $response];
                UpdateIssue::skipTestStatusTransition($id);
                UpdateIssue::skipTestLinkIssue($id);
            }
        }
        return $createdIssueByName;
    }

    /**
     * create a Skipped test
     *
     * @param $id
     * @return mixed
     */
    public function createSkipped($id) {
        $createIssue = new CreateIssue($id);
        $response = $createIssue::createSkippedTest($id); //TODO: create createSkippedTest method in CreateIssue class
        return $response;
    }

    /**
     * Takes MAGETWO zephyr tests and sends all to Create function
     *
     * @param $m2ZephyrTests
     * @return array
     * @throws \Exception
     */
    public function performM2Migration($m2ZephyrTests) {
        foreach ($m2ZephyrTests as $m2Key => $id) {
            // if (array_key_exists($id, $skipped)) {
            $createIssue = new CreateIssue($id);
            $response = $createIssue::createM2Migration($id);
            $createdIssueByName[] = $response;
            CreateManager::m2MigrationSetIssueLink($response, $m2Key);
            $mftfLoggingDescriptor = ZephyrComparison::mftfLoggingDescriptor($id);
            LoggingUtil::getInstance()->getLogger(CreateManager::class)->info('M2 Migrated: ' . $response ." : " . $m2Key);


            if (isset($id['skip'])) {
                $id += ['key' => $response];
                UpdateIssue::skipTestStatusTransition($id);
                UpdateIssue::skipTestLinkIssue($id);
            }
        }
        return $createdIssueByName;
    }

    /**
     * For M2Migration, this create a link between newly create MC test and the MAGETWO test it was created from
     *
     * @param $mcKey
     * @param $m2Key
     */
    public function m2MigrationSetIssueLink($mcKey, $m2Key) {
        try {
            $il = new IssueLink();

            $il->setInwardIssue($m2Key)
                ->setOutwardIssue($mcKey)
                ->setLinkTypeName('Relates' )
                ->setComment('API Integration - Moving MAGETWO issue to MC');

            $ils = new IssueLinkService();

            $ret = $ils->addIssueLink($il);

        } catch (JiraException $e) {
            print("Error Occured! " . $e->getMessage());
        }
    }

    //TODO : REMOVE

    /**
     * DRY RUN of creation
     *
     * @param $createByName
     * @return array
     */
    public function performDryRunCreateOperations($createByName) {
        //loop - test for if skipped. if skipped, pass to skipCreate, if not, vanillaCreate
        foreach ($createByName as $id) {
            // if (array_key_exists($id, $skipped)) {
            $createIssue = new CreateIssue($id);
            $response = $createIssue::createDryRunIssuesREST($id);
            $createdIssueByName[] = $response;
            $mftfLoggingDescriptor = ZephyrComparison::mftfLoggingDescriptor($id);
            //LoggingUtil::getInstance()->getLogger(CreateManager::class)->info('NEW TEST sent to CREATE: ' . $mftfLoggingDescriptor);

            if (isset($id['skip'])) {
                $id += ['key' => $response];
                //UpdateIssue::skipTestLinkIssue($id);
            }
        }
        return $createdIssueByName;
    }

    //TODO : REMOVE
    public function createDryRunSkipped($id) {
        $createIssue = new CreateIssue($id);
        LoggingUtil::getInstance()->getLogger(CreateManager::class)->info('SKIPPED TEST sent to CREATE: ' . $id['title'][0]);
        //$response = $createIssue::createDryRunSkippedTest($id); //TODO: create createSkippedTest method in CreateIssue class
        //return $response;
        }


}