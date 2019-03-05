<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

class UpdateManager
{
    public static $updateManager;

    public static function getInstance()
    {
        if (!self::$updateManager) {
            self::$updateManager = new UpdateManager();
        }

        return self::$updateManager;
    }

    /**
     * @param array $toBeUpdatedTests
     * @param string $releaseLine
     * @param bool $isDryRun
     * @throws \Exception
     */
    public function performUpdateOperations(array $toBeUpdatedTests, $releaseLine, $isDryRun = true)
    {
        foreach ($toBeUpdatedTests as $key => $update) {
            $updateIssue = new UpdateIssue();
            $updateIssue::updateIssueREST($update, $key, $releaseLine, $isDryRun);
        }
    }
}
