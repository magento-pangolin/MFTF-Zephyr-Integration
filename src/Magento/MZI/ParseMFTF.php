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
     * Collecting Mftf tests metadata
     * @return array
     */
    public function getTestObjects()
    {
        $annotations = [];
        $testObjects = TestObjectHandler::getInstance()->getAllObjects();
        foreach ($testObjects as $key => $test) {
            $propGetter = Closure::bind(function($prop){return $this->$prop;}, $test, $test );
            $annotations[] = $propGetter('annotations');
        }
        $missingMetadata = [];
        foreach ($annotations as $annotation) {
            if (!isset($annotation['title'])
                || !isset($annotation['stories'])
                || !isset($annotation['description'])
                || !isset($annotation['severity'])
            ) {
                // Missing metadata are reported by Mftf.
                $missingMetadata[] = $annotation;
            }
            if (isset($annotation['title'])) {
                $annotation['title'][0] = trim(
                    substr($annotation['title'][0], strpos($annotation['title'][0], ":") + 1)
                );
            }
        }
        print("\nFinished collecting Mftf tests metadata\n\n");
        return $annotations;
    }
}
