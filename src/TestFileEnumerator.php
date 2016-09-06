<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

/**
 * PHPUnit XML parser for Solano-PHPUnit
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_TestFileEnumerator
{
    /**
     * @var array
     */
    public $testFiles = array();

    /**
     * @var array
     */
    public $excludeFiles = array();
    
    /**
     * @var string
     */
    private $workingDir = '';

    /**
     * @var boolean
     */
    private $ignoreExclude = false;
    
    /**
     * @var DOMXPath
     */
    private $xpath;
    
    /**
     * @var DOMDocument
     */
    private $domDoc;

    /**
     * Find all tests specified in a PHPUnit XML configuration file.
     *
     * @param SolanoLabs_PHPUnit_Configuration         $config
     */
    public static function EnumerateTestFiles(&$config)//$domDoc, $workingDir, $ignoreExclude = false)
    {
        $enumerator = new static;
        $enumerator->setWorkingDir($config->workingDir);
        $enumerator->domDoc = $config->domDoc;
        $enumerator->xpath = new DOMXPath($enumerator->domDoc);
        $enumerator->ignoreExclude = $config->ignoreExclude;

        $testSuiteNodes = $enumerator->xpath->query('//testsuites/testsuite');

        if($testSuiteNodes->length == 0) {
            $testSuiteNodes = $enumerator->xpath->query('testsuite');
        }

        if($testSuiteNodes->length == 0) {
            return $enumerator;
        }

        foreach ($testSuiteNodes as $testSuiteNode) {
            // If a --testsuite was specified, only use that one
            if ($config->testsuiteFilter && $testSuiteNode->getAttribute('name') != $config->testsuiteFilter) {
                continue;
            }
            $enumerator->extractTestFiles($testSuiteNode);
        }

        // If tests were supplied by the command line, use only those...else include all tests.
        if (count($config->cliTestFiles)) {
            $config->excludeFiles = array_intersect_key($config->cliTestFiles, $enumerator->excludeFiles);
            $config->testFiles = array_intersect_key($config->cliTestFiles, $enumerator->testFiles);
        } else {
            $config->testFiles = $enumerator->testFiles;
            $config->excludeFiles = $enumerator->excludeFiles;
        }
        ksort($config->testFiles);
        ksort($config->excludeFiles);
    }

    /**
     * Set the working directory.
     *
     * @param string              $workingDir
     */
    private function setWorkingDir($workingDir)
    {
        $this->workingDir = $workingDir;
    }

    /**
     * Get the relevant attributes from testsuite child nodes
     * PHPUnit 5 introduced additional attributes besides 'suffix'
     *
     * @param DomNode             $node
     */
    private function extractNodeAttributes($node)
    {
        $attributes = array();
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attributeNode) {
                $attributes[$attributeNode->nodeName] = $attributeNode->nodeValue;
            }
            /*foreach(array('suffix', 'phpVersion', 'phpVersionOperator') as $attribute) {
                if($node->hasAttribute($attribute)) {
                    $attributes[$attribute] = $node->getAttribute($attribute);
                }
            }*/
        }
        return $attributes;
    }

    /**
     * Get the files in a specific <testsuite/>.
     *
     * @param DomNode             $testSuiteNode
     */
    private function extractTestFiles($testSuiteNode)
    {
        $files = array();
        $excludePaths = array();
        if (!$testSuiteNode->hasChildNodes()) { return; }
        foreach($testSuiteNode->childNodes as $node) {
            switch ($node->nodeName) {
                case 'directory':
                    $attributes = $this->extractNodeAttributes($node);
                    $files = array_merge($files, $this->getDirectoryFiles(SolanoLabs_PHPUnit_Util::truepath($node->nodeValue, $this->workingDir), $attributes));
                    break;
                case 'file':
                    $attributes = $this->extractNodeAttributes($node);
                    $file = SolanoLabs_PHPUnit_Util::truepath($node->nodeValue, $this->workingDir);
                    if (is_file($file)) {
                        $files[$file] = $attributes;
                    } else {
                        echo("[WARNING] File does not exist: $file\n");
                    }
                    break;
                case 'exclude':
                    $attributes = $this->extractNodeAttributes($node);
                    if ($node->hasChildNodes()) {
                        foreach($node->childNodes as $excludeNode) {
                            if ($excludeNode->nodeValue) {
                                $excludePaths[SolanoLabs_PHPUnit_Util::truepath($excludeNode->nodeValue, $this->workingDir)] = $attributes;
                            }
                        }
                    } elseif ($node->nodeValue) {
                        $excludePaths[SolanoLabs_PHPUnit_Util::truepath($node->nodeValue, $this->workingDir)] = $attributes;
                    }
                    break;
            }
        }
        
        if (!count($files)) { return; }

        // Should some files be excluded?
        if (!$this->ignoreExclude && count($excludePaths)) {
            foreach ($files as $file => $attributes) {
                foreach (array_keys($excludePaths) as $excludePath) {
                    if (is_dir($excludePath)) {
                        if (0 === strpos($file, $excludePath . DIRECTORY_SEPARATOR)) {
                            $this->excludeFiles[$file] = $attributes;
                            unset($files[$file]);
                            break;
                        }
                    } elseif ($excludePath == $file) {
                        $this->excludeFiles[$file] = $attributes;
                        unset($files[$file]);
                        break;
                    } elseif (false !== strpos($excludePath, '*')) {
                        // check wildcard match
                        if (fnmatch($excludePath, $file)) {
                            $this->excludeFiles[$file] = $attributes;
                            unset($files[$file]);
                            break;
                        }
                    }
                }
            }
        }

        $this->testFiles = array_merge($this->testFiles, $files);
    }

    /**
     * Get the files in a specific <testsuite/>.
     *
     * @param string             $path
     * @param string             $attributes
     */
    private function getDirectoryFiles($path, $attributes)
    {
        $files = array();
        $suffix = 'Test.php'; // Default PHPUnit test file suffix
        if (!empty($attributes['suffix'])) { $suffix = $attributes['suffix']; }
        $foundFiles = $this->getDirectoryFilesRaw($path, $suffix);
        unset($attributes['suffix']);
        foreach ($foundFiles as $file) {
            $shortFilepath = SolanoLabs_PHPUnit_Util::truepath($file);
            $files[$shortFilepath] = $attributes;
        }
        return $files;
    }

    /**
     * Get all files matching a suffix
     *
     * @param string             $path
     * @param string             $suffix
     */
    private function getDirectoryFilesRaw($path, $suffix)
    {
        $files = glob($path . DIRECTORY_SEPARATOR . "*" . $suffix);
        foreach (glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $this->getDirectoryFilesRaw($path . DIRECTORY_SEPARATOR . basename($dir), $suffix));
        }
        return $files;
    }


}
