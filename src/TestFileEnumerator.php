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
     * @param DOMDocument         $test
     * @param string              $workingDir
     * @param boolean             $ignoreExclude
     */
    public static function EnumerateTestFiles($domDoc, $workingDir, $ignoreExclude = false)
    {
        $enumerator = new static;
        $enumerator->setWorkingDir($workingDir);
        $enumerator->domDoc = $domDoc;
        $enumerator->xpath = new DOMXPath($enumerator->domDoc);
        $enumerator->ignoreExclude = $ignoreExclude;

        $testSuiteNodes = $enumerator->xpath->query('//testsuites/testsuite');

        if($testSuiteNodes->length == 0) {
            $testSuiteNodes = $enumerator->xpath->query('testsuite');
        }

        if($testSuiteNodes->length == 0) {
            return $enumerator;
        }

        foreach ($testSuiteNodes as $testSuiteNode) {
            $enumerator->extractTestFiles($testSuiteNode);
        }
        $enumerator->testFiles = array_unique($enumerator->testFiles);
        sort($enumerator->testFiles);
        $enumerator->excludeFiles = array_unique($enumerator->excludeFiles);
        sort($enumerator->excludeFiles);

        return $enumerator;
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
                    $suffix = 'Test.php';
                    if($node->hasAttribute('suffix')) {
                        $suffix = $node->getAttribute('suffix');
                    }
                    $files = array_merge($files, $this->getDirectoryFiles(SolanoLabs_PHPUnit_Util::truepath($node->nodeValue, $this->workingDir), $suffix));
                    break;
                case 'file':
                    $file = SolanoLabs_PHPUnit_Util::truepath($node->nodeValue, $this->workingDir);
                    if (is_file($file)) {
                        $files[] = $file;
                    } else {
                        echo("[WARNING] File does not exist: $file\n");
                    }
                    break;
                case 'exclude':
                    $excludePaths[] = SolanoLabs_PHPUnit_Util::truepath($node->nodeValue, $this->workingDir);
                    break;
            }
        }
        if (!count($files)) { return; }

        // Should some files be excluded?
        if (!$this->ignoreExclude && count($excludePaths)) {
            for ($i = count($files) - 1; $i >= 0; $i--) {
                foreach ($excludePaths as $excludePath) {
                    if (is_dir($excludePath)) {
                        if (0 === strpos($files[$i], $excludePath . DIRECTORY_SEPARATOR)) {
                            $this->excludeFiles[] = $files[$i];
                            unset($files[$i]);
                            break;
                        }
                    } elseif ($excludePath == $files[$i]) {
                        $this->excludeFiles[] = $files[$i];
                        unset($files[$i]);
                        break;
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
     * @param string             $suffix
     */
    private function getDirectoryFiles($path, $suffix)
    {
        $files = glob($path . DIRECTORY_SEPARATOR . "*" . $suffix);
        foreach (glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
             $files = array_merge($files, $this->getDirectoryFiles($path . DIRECTORY_SEPARATOR . basename($dir), $suffix));
        }
        return array_map(array('SolanoLabs_PHPUnit_Util', 'truepath'), $files);
    }
}
