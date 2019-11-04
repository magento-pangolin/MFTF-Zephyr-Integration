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
     * Symbolic keywords of CE
     *
     * @var array
     */
    private static $keywordsCe = [
        'adminnotification',
        'backend',
        'bundle',
        'cache',
        'msrp',
        'newsletter',
        'quote',
        'reports',
        'store',
        'swagger',
        'swatches',
        'tax',
        'user',
    ];

    /**
     * Symbolic keywords of EE
     *
     * @var array
     */
    private static $keywordsEe = [
        'admingws',
        'banner',
        'rma',
        'reward',
        'giftcard',
        'scalable',
    ];


    /**
     * Symbolic keywords of B2B
     *
     * @var array
     */
    private static $keywordsB2b = [
        'b2b',
        'company',
        'negotiablequote',
        'requisitionlist',
        'sharedcatalog',
        'quickorder',
    ];

    /**
     * Symbolic keywords of Page Builder
     *
     * @var array
     */
    private static $keywordsPb = [
        'pagebuilder',
    ];

    /**
     * Symbolic keywords of Page Builder EE
     *
     * @var array
     */
    private static $keywordsPbEe = [
        'bannerpagebuilder',
        'catalogstagingpagebuilder',
        'pagebuildercommerce',
        'stagingpagebuilder',
        'pagebuilder-ee',
        'pagebuilder-staging',
    ];

    /**
     * @var array
     */
    private static $flags = [
        'ce' => 0,
        'ee' => 0,
        'b2b' => 0,
        'pb' => 0,
        'pbee' => 0,
    ];

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
        if (null === ZephyrIntegrationManager::$pbReleaseLine) {
            self::$flags['pb'] = 1;
            self::$flags['pbee'] = 1;
        }

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

            $annotations[$key]['description'][0] = $this->formatDescriptionAnnotation($annotation['description'][0]);

            if (isset($annotation['title'])) {
                $annotations[$key]['title'][0] = trim(
                    substr($annotation['title'][0], strpos($annotation['title'][0], ":") + 1)
                );
            }

            if (isset($annotation['features'])) {
                $this->updateFlags($annotation['features']);
            }
        }

        if (!$this->validateAllFlags()) {
            $logMessage = implode(',', $this->getInvalidFlags());
            print("\nMissing mftf $logMessage tests. Please fix test setup and try again!\n");
            exit(1);
        }
        print("\nFinished collecting mftf tests metadata\n");
        print ("Total mftf tests: " . count($annotations) . "\n\n");
        ZephyrIntegrationManager::$totalMftf = count($annotations);
        return $annotations;
    }

    /**
     * Update flags
     *
     * @param array $features
     * @return void
     */
    private function updateFlags(array $features)
    {
        if ($this->validateAllFlags()) {
            return;
        }

        foreach ($features as $feature) {
            $found = false;
            if (self::$flags['ce'] == 0) {
                foreach (self::$keywordsCe as $keyword) {
                    if (strpos(strtolower($feature), $keyword) !== false) {
                        self::$flags['ce'] = 1;
                        $found = true;
                        break;
                    }
                }
            }

            if (self::$flags['ee'] == 0 && !$found) {
                foreach (self::$keywordsEe as $keyword) {
                    if (strpos(strtolower($feature), $keyword) !== false) {
                        self::$flags['ee'] = 1;
                        $found = true;
                        break;
                    }
                }
            }

            if (self::$flags['b2b'] == 0 && !$found) {
                foreach (self::$keywordsB2b as $keyword) {
                    if (strpos(strtolower($feature), $keyword) !== false) {
                        self::$flags['b2b'] = 1;
                        $found = true;
                        break;
                    }
                }
            }

            // Check page builder ee first
            if (self::$flags['pbee'] == 0 && !$found) {
                foreach (self::$keywordsPbEe as $keyword) {
                    if (strpos(strtolower($feature), $keyword) !== false) {
                        self::$flags['pbee'] = 1;
                        $found = true;
                        break;
                    }
                }
            }

            if (self::$flags['pb'] == 0 && !$found) {
                foreach (self::$keywordsPb as $keyword) {
                    if (strpos(strtolower($feature), $keyword) !== false) {
                        self::$flags['pb'] = 1;
                        $found = true;
                        break;
                    }
                }
            }

            if ($found) {
                break;
            }
        }
    }

    /**
     * Validate if flags are all set
     *
     * @return bool
     */
    private function validateAllFlags()
    {
       foreach (self::$flags as $flag) {
           if ($flag == 0) {
               return false;
           }
       }
       return true;
    }


    /**
     * Return key of the flags which is not set
     *
     * @return array
     */
    private function getInvalidFlags()
    {
        $out = [];
        foreach (self::$flags as $key => $flag) {
            if ($flag == 0) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * Format description annotation
     *
     * @param string $description
     * @return string
     */
    private function formatDescriptionAnnotation($description)
    {
        $outDescription = trim($description);
        $outDescription = str_replace('<br><br><b><font size=+0.9>', "\n\n*", $outDescription);
        $outDescription = str_replace('</font></b><br><br>', "*\n", $outDescription);
        $outDescription = trim(str_replace('<br>', '', $outDescription));
        return $outDescription;
    }
}
