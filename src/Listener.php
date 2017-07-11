<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Util\Filter;
use PHPUnit\Util\Printer;

if (getenv('TDDIUM')):

/**
 * PHPUnit listener for Solano-PHPUnit
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_Listener extends Printer implements TestListener
{
    /**
     * @var    string
     */
    private $outputFile = 'tddium_output.json';

    /**
     * @var    array
     */
    private $excludeFiles = array();

    /**
     * @var    string
     */
    private $currentTestSuiteName = '';

    /**
     * @var    array
     */
    private $currentTestcase;

    /**
     * @var    array
     */
    private $files = array();

    /**
     * @var    string
     */
    private $stripPath = '';

    /**
     * Constructor.
     *
     * @param string   $outputFile
     */
    public function __construct($outputFile = '', $excludeFiles = '')
    {
        $this->outputFile = $outputFile;
        if ($excludeFiles) {
            $this->excludeFiles = explode(',', $excludeFiles);
        }
        $this->stripPath = getenv('TDDIUM_REPO_ROOT') ? getenv('TDDIUM_REPO_ROOT') : getcwd();
    }

    /**
     * A test started.
     *
     * @param Test $test
     */
    public function startTest(Test $test)
    {
        if (getenv('TDDIUM')) {
            global $tddium_output_buffer;
            $tddium_output_buffer = "";
        }
        if (!$test instanceof Warning) {
            $testcase = array('id' => '', 'address' => '', 'status' => '', 'stderr' => '', 'stdout' => '', 'file' => '');
            if ($test instanceof TestCase) {
                $class = new ReflectionClass($test);
                $className = $class->getName();
                $testName = $test->getName();
                $testcase['id'] = $className . '::' . $testName;
                $testcase['file'] = $class->getFileName();
                // Set an environment variable to the filename in case of fatal error
                putenv("SOLANO_LAST_FILE_STARTED=" . $testcase['file']);
                if ($class->hasMethod($testName)) {
                    $testcase['address'] = $className . '::' . $testName;
                } else {
                    // This is a paramaterized test...get the address from the testsuite
                    $testcase['address'] = $this->currentTestSuiteName;
                }
                $this->currentTestcase = $testcase;
            }
       
        }
    }

    /**
     * A test ended.
     *
     * @param Test  $test
     * @param float $time
     */
    public function endTest(Test $test, $time)
    {
        $testcase = $this->currentTestcase;
        if (!$testcase['status']) {
            $testcase['status'] = 'pass';
            $testcase['time'] = $time;
        }
        if (method_exists($test, 'hasOutput') && $test->hasOutput()) {
            $testcase['stdout'] = $test->getActualOutput();
        }
        if (getenv('TDDIUM')) {
            global $tddium_output_buffer;
            $testcase['stdout'] .= $tddium_output_buffer;
        }
        $this->addTestCase($testcase);
        $this->currentTestcase = array();
    }

    /**
     * An error occurred.
     *
     * @param Test      $test
     * @param Exception $e
     * @param float     $time
     */
    public function addError(Test $test, Exception $e, $time)
    {   
        $this->addNonPassTest('error', $test, $e, $time);
    }

    /**
     * A failure occurred.
     *
     * @param Test                 $test
     * @param AssertionFailedError $e
     * @param float                $time
     */
    public function addFailure(Test $test, AssertionFailedError $e, $time)
    {
        $this->addNonPassTest('fail', $test, $e, $time);
    }

    /**
     * A warning occurred.
     *
     * @param Test    $test
     * @param Warning $e
     * @param float   $time
     */
    public function addWarning(Test $test, Warning $e, $time)
    {
        $this->addNonPassTest('error', $test, $e, $time);
    }

    /**
     * Incomplete test.
     *
     * @param Test      $test
     * @param Exception $e
     * @param float     $time
     */
    public function addIncompleteTest(Test $test, Exception $e, $time)
    {
        $this->addNonPassTest('skip', $test, $e, $time, 'Incomplete Test: ');
    }

    /**
     * Risky test.
     *
     * @param Test      $test
     * @param Exception $e
     * @param float     $time
     */
    public function addRiskyTest(Test $test, Exception $e, $time)
    {
        $this->addNonPassTest('error', $test, $e, $time, 'Risky Test: ');
    }

    /**
     * Skipped test.
     *
     * @param Test      $test
     * @param Exception $e
     * @param float     $time
     */
    public function addSkippedTest(Test $test, Exception $e, $time)
    {
        if (count($this->currentTestcase)) {
            $this->addNonPassTest('skip', $test, $e, $time, 'Skipped Test: ');
        } else {
            // PHPUnit skips tests with unsatisfied @depends without "starting" or "ending" them.
            $this->startTest($test);
            $this->addNonPassTest('skip', $test, $e, 0, 'Skipped Test: ');
            $this->endTest($test, 0);
        }
    }

    /**
     * Add a non-passing test to the output
     *
     * @param string    $status
     * @param Test      $test
     * @param Exception $e
     * @param string    $stderrPrefix
     */
    private function addNonPassTest($status, Test $test, Exception $e, $time, $stderrPrefix = '')
    {
        $this->currentTestcase['status'] = $status;
        $this->currentTestcase['time'] = $time;
        if (method_exists($test, 'hasOutput') && $test->hasOutput()) {
            $this->currentTestcase['stdout'] = $test->getActualOutput();
        }
        $this->currentTestcase['stderr'] = $stderrPrefix . $e->getMessage();
        $traceback = Filter::getFilteredStacktrace($e, false);
        // Strip path from traceback?
        for($i = 0; $i < count($traceback); $i++) {
            if (0 === strpos($traceback[$i]['file'], $this->stripPath)) {
                $traceback[$i]['file'] = substr($traceback[$i]['file'], strlen($this->stripPath) + 1);
            }
        }
        $this->currentTestcase['traceback'] = $traceback;
    }

    /**
     * A testsuite started.
     *
     * @param TestSuite $suite
     */
    public function startTestSuite(TestSuite $suite)
    {
        $this->currentTestSuiteName = $suite->getName();
    }

    /**
     * A testsuite ended.
     *
     * @param TestSuite $suite
     */
    public function endTestSuite(TestSuite $suite)
    {
        $this->currentTestSuiteName = '';
        $this->currentTestSuiteAddress = '';
    }

    /**
     * Add testcase to the output
     *
     * @param array $testcase
     */
    private function addTestCase($testcase) 
    {
        $file = $testcase['file'];
        if (0 === strpos($file, $this->stripPath)) {
            $file = substr($file, strlen($this->stripPath) + 1);
        }
        unset($testcase['file']);
        if (!isset($this->files[$file])) {
            $this->files[$file] = array();
        }
        $this->files[$file][] = $testcase;
        // Flush test to report
        SolanoLabs_PHPUnit_JsonReporter::writeTestcaseToFile($this->outputFile, $file, $testcase);

    }

}

endif;
