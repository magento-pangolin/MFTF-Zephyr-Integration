<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\JZI;

include_once ('UpdateIssue.php');
include_once ('Util/LoggingUtil.php');

class UpdateManager
{
    public static $updateManager;

    public static function getInstance() {
        if (!self::$updateManager) {
            self::$updateManager = new UpdateManager();
        }

        return self::$updateManager;
    }

    public function performUpdateOperations($updates) {
        foreach ($updates as $key => $update) {
            $updateIssue = new UpdateIssue($update);
            $response = $updateIssue::updateIssueREST($update, $key);
            //$updateIssue[] = $response;
            //LoggingUtil::getInstance()->getLogger(UpdateManager::class)->info('TEST sent to UPDATE: ' . $key);
        }
    }

    public function updateSkipped($id) {
        $updateIssue = new UpdateIssue($id);
        $response = $updateIssue::updateSkippedTest($id);
        return $response;

    }

    //TODO : REMOVE
    public function performDryRunUpdateOperations($updates) {
        foreach ($updates as $key => $update) {
                $updateIssue = new UpdateIssue($update);
                $response = $updateIssue::updateDryRunIssuesREST($update, $key);
                //$updateIssue[] = $response;
                //LoggingUtil::getInstance()->getLogger(UpdateManager::class)->info('TEST sent to UPDATE: ' . $key);
        }
    }

    //TODO : REMOVE
    public function updateDryRunSkipped($id, $key) {
        //$updateIssue = new UpdateIssue($id);
        LoggingUtil::getInstance()->getLogger(UpdateManager::class)->info('SKIPPED TEST sent to UPDATE: ' . $key);
        //$response = $createIssue::createDryRunSkippedTest($id);
        //return $response;
    }

}