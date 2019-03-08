<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MZI;

use \Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler;
use \Closure;

class ParseMFTF
{
    /**
     * @var ParseMFTF
     */
    private static $instance;

    /**
     * ParseMFTF constructor
     */
    private function __construct()
    {
        // private constructor
    }

    /**
     * Static singleton getInstance
     *
     * @return ParseMFTF
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new ParseMFTF();
        }
        return self::$instance;
    }

    /**
     * Collecting Mftf tests metadata
     * @return array
     */
    public function getTestObjects()
    {
        $annotations = [];
        $testObjects = TestObjectHandler::getInstance()->getAllObjects();
        foreach ($testObjects as $key => $test) {
            $propGetter = Closure::bind(function($prop){return $this->$prop;}, $test, $test );
            $annotations[$key] = $propGetter('annotations');
        }
        $missingMetadata = [];
        foreach ($annotations as $key => $annotation) {
            if (!isset($annotation['title'])
                || !isset($annotation['stories'])
                || !isset($annotation['description'])
                || !isset($annotation['severity'])
            ) {
                // Missing metadata are reported by Mftf.
                $missingMetadata[$key] = $annotation;
            }
            if (isset($annotation['title'])) {
                $annotations[$key]['title'][0] = trim(
                    substr($annotation['title'][0], strpos($annotation['title'][0], ":") + 1)
                );
            }
        }
        print("\nFinished collecting mftf tests metadata\n");
        print ("Total mftf tests: " . count($annotations) . "\n\n");
        ZephyrIntegrationManager::$totalMftf = count($annotations);
        return $annotations;
    }
}
