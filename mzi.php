<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 *  Usage: php mzi [options]=[values]
 *
 *  Options:
 *  --dryRun           Run local test or run on Jira database
 *  --project          Jira project key
 *  --releaseLine      Magento release line that mftf tests run on
 *  --pbReleaseLine    Magento Page Builder release line that mftf tests run on
 */
try {
    require_once (__DIR__ . '/../../autoload.php');
} catch (\Exception $e) {
    echo 'Autoload error: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString();
    exit(1);
}
try {
    Magento\MZI\ZephyrIntegrationManager::getInstance()->runMftfZephyrIntegration();
} catch (\Exception $e) {
    while ($e) {
        echo $e->getMessage();
        echo $e->getTraceAsString();
        echo "\n\n";
        $e = $e->getPrevious();
    }
    exit(1);
}
