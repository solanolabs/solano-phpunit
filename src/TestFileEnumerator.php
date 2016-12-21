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

        // If a priority file is present apply the priorities therein
        if (count($config->testPriorities)) {
            foreach($config->testPriorities as $file => $priority) {
                if (isset($config->testFiles[$file])) {
                    $config->testFiles[$file]['priority'] = $priority;
                }
            }
        }

        // Sort test files (supplied priority takes precedene over --[rev-]alpha flags)
        if ($config->alphaOrder == 1) {
            ksort($config->testFiles);
            ksort($config->excludeFiles);
        } elseif ($config->alphaOrder == -1) {
            krsort($config->testFiles);
            krsort($config->excludeFiles);
        }
        self::sortTestFilesByPriority($config->testFiles);
        self::sortTestFilesByPriority($config->excludeFiles);
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
     * Find the files in a directory and apply XML attributes to each
     *
     * @param string             $path
     * @param string             $attributes
     */
    private function getDirectoryFiles($path, $attributes)
    {
        $files = array();
        $suffix = 'Test.php'; // Default PHPUnit test file suffix
        if (!empty($attributes['suffix'])) { $suffix = $attributes['suffix']; }
        $foundFiles = $this->getDirectoryFilesRecursive($path, $suffix);
        unset($attributes['suffix']);
        foreach ($foundFiles as $file) {
            $file = SolanoLabs_PHPUnit_Util::truepath($file);
            $files[$file] = $attributes;
        }
        return $files;
    }

    /**
     * Recursively find all files in a directory matching a suffix
     *
     * @param string             $path
     * @param string             $suffix
     */
    private function getDirectoryFilesRecursive($path, $suffix)
    {
        $files = glob($path . DIRECTORY_SEPARATOR . "*" . $suffix);
        foreach (glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $this->getDirectoryFilesRecursive($path . DIRECTORY_SEPARATOR . basename($dir), $suffix));
        }
        return $files;
    }

    /**
     * Sort test files by priority
     * XML 'priority' attribute or priority defined in '--priority-file' file take precedence
     */
    public static function sortTestFilesByPriority($testFiles)
    {
        uasort($testFiles, array(__CLASS__, 'compareTestPriorty'));
        return $testFiles;
    }

    /**
     * Compare priority of test file array items
     */
    private static function compareTestPriorty($a, $b)
    {
        if (empty($a['priority']) || !is_numeric($a['priority'])) { $a['priority'] = 0; }
        if (empty($b['priority']) || !is_numeric($b['priority'])) { $b['priority'] = 0; }
        if ($a['priority'] == $b['priority']) { return 0; }
        return ($a['priority'] < $b['priority']) ? -1 : 1;
    }

}
