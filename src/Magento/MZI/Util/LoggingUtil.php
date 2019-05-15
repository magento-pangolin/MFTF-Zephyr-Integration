<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\MZI\Util;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Magento\MZI\UpdateIssue;
use Magento\MZI\CreateIssue;
use Magento\MZI\UpdateManager;
use Magento\MZI\CreateManager;

class LoggingUtil
{
    // mftf logs
    const LOG_TYPE_MFTF = "mftf";
    const LOG_TYPE_CREATED = "create"; // mftf_name
    const LOG_TYPE_UPDATED = "update"; // mftf_name, zephyr_key
    const LOG_TYPE_SAME = "same"; // mftf_name, zephyr_key
    const LOG_TYPE_UNPROCESSED = "notitle"; // mftf_name
    const LOG_TYPE_MATCHED = "unimatch"; // mftf_name, zephyr_key
    const LOG_TYPE_ONE_M_TO_MANY_Z = "one_m_many_z"; // mftf_name, zephyr_key, zephyr_key...
    // zephyr logs
    const LOG_TYPE_ONE_Z_TO_MANY_M = "one_z_many_m"; // zephyr_key, mftf_name, mftf_name...
    const LOG_TYPE_UNMATCHED = "unmatch"; // zephyr_key

    /**
     * Private Map of Logger instances, indexed by Class Name.
     *
     * @var array
     */
    private $loggers = [];

    /**
     * Singleton LoggingUtil Instance
     *
     * @var LoggingUtil
     */
    private static $instance;

    /**
     * Singleton accessor for LoggingUtil instance
     *
     * @return LoggingUtil
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new LoggingUtil();
        }

        return self::$instance;
    }

    /**
     * Constructor for LoggingUtil
     */
    private function __construct()
    {
        // private constructor
    }

    /**
     * Creates a new logger instances based on class name if it does not exist. If logger instance already exists, the
     * existing instance is simply returned.
     *
     * @param string $clazz
     * @return array
     * @throws \Exception
     */
    public function getLogger($clazz)
    {
        if ($clazz == null) {
            throw new \Exception("You must pass a class to receive a logger");
        }

        if (!array_key_exists($clazz, $this->loggers)) {
            $logger = new Logger($clazz);
            $logger->pushHandler(new StreamHandler($this->getLoggingPath($clazz)));
            $this->loggers[$clazz] = $logger;
        }
        return $this->loggers[$clazz];
    }

    /**
     * Function which returns a static path to the the log file.
     *
     * @return string
     */
    private function getLoggingPath($clazz)
    {
        if (($clazz == UpdateIssue::class) || ($clazz == UpdateManager::class)) {
            return "log/updateIssue.log";
        } elseif (($clazz == CreateIssue::class) || ($clazz == CreateManager::class)) {
            return "log/createIssue.log";
        } elseif ($clazz == self::LOG_TYPE_MATCHED) {
            return  "log/matched.log";
        } elseif ($clazz == self::LOG_TYPE_UNMATCHED) {
            return  "log/unmatched.log";
        } elseif ($clazz == self::LOG_TYPE_CREATED) {
            return  "log/created.log";
        } elseif ($clazz == self::LOG_TYPE_UPDATED) {
            return  "log/updated.log";
        } elseif ($clazz == self::LOG_TYPE_UNPROCESSED) {
            return  "log/unprocessed.log";
        } elseif ($clazz == self::LOG_TYPE_MFTF) {
            return  "log/mftf.log";
        } elseif ($clazz == self::LOG_TYPE_SAME) {
            return  "log/same.log";
        } elseif ($clazz == self::LOG_TYPE_ONE_M_TO_MANY_Z) {
            return  "log/m_to_z_duplicates.log";
        } elseif ($clazz == self::LOG_TYPE_ONE_Z_TO_MANY_M) {
            return  "log/z_to_m_duplicates.log";
        } else {
            return "log/mzi.log";
        }
    }
}
