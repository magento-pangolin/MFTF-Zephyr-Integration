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
        }
        elseif (($clazz == CreateIssue::class) || ($clazz == CreateManager::class)) {
            return "log/createIssue.log";
        } else {
            return "log/mzi.log";
        }
    }
}
