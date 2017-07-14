<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

use SebastianBergmann\Environment\Console;

/**
 * PHPUnit Printer for Solano-PHPUnit
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */

class SolanoLabs_PHPUnit_Printer extends MapResultPrinter
{
    /**
     * @var array
     */
    private static $ansiCodes = array(
      'bold'       => 1,
      'fg-black'   => 30,
      'fg-red'     => 31,
      'fg-green'   => 32,
      'fg-yellow'  => 33,
      'fg-cyan'    => 36,
      'fg-white'   => 37,
      'bg-red'     => 41,
      'bg-green'   => 42,
      'bg-yellow'  => 43
    );

    public function __construct($out = null, $verbose = false, $colors = self::COLOR_DEFAULT, $debug = false, $numberOfColumns = 80)
    {
        parent::__construct($out, $verbose, $colors, $debug, $numberOfColumns);
    }

    /**
     * @var string
     */
    private $lastTestName = '';

    /**
     * An error occurred.
     *
     * @param MapTest   $test
     * @param Exception $e
     * @param float     $time
     */
    public function addError(MapTest $test, Exception $e, $time)
    {
        $this->writeProgressWithColor('fg-red, bold', 'ERROR');
        $this->lastTestFailed = true;
        if (getenv('TDDIUM')) {
            print "\n" . $e->__toString();
        }
    }

    /**
     * A failure occurred.
     *
     * @param MapTest                 $test
     * @param MapAssertionFailedError $e
     * @param float                   $time
     */
    public function addFailure(MapTest $test, MapAssertionFailedError $e, $time)
    {
        $this->writeProgressWithColor('bg-red, fg-white', 'FAIL');
        $this->lastTestFailed = true;
        if (getenv('TDDIUM')) {
            print "\n" . $e->__toString();
        }
    }

    /**
     * A warning occurred.
     *
     * @param MapTest    $test
     * @param MapWarning $e
     * @param float      $time
     */
    public function addWarning(MapTest $test, MapWarning $e, $time)
    {
        $this->writeProgressWithColor('fg-red, bold', 'WARNING');
        $this->lastTestFailed = true;
        if (getenv('TDDIUM')) {
            print "\n" . $e->__toString();
        }
    }

    /**
     * Incomplete test.
     *
     * @param MapTest   $test
     * @param Exception $e
     * @param float     $time
     */
    public function addIncompleteTest(MapTest $test, Exception $e, $time)
    {
        $this->writeProgressWithColor('fg-yellow, bold', 'INCOMPLETE');
        $this->lastTestFailed = true;
        if (getenv('TDDIUM')) {
            print "\n" . $e->__toString();
        }
    }

    /**
     * Risky test.
     *
     * @param MapTest   $test
     * @param Exception $e
     * @param float     $time
     */
    public function addRiskyTest(MapTest $test, Exception $e, $time)
    {
        $this->writeProgressWithColor('fg-yellow, bold', 'RISKY');
        $this->lastTestFailed = true;
        if (getenv('TDDIUM')) {
            print "\n" . $e->__toString();
        }
    }

    /**
     * Skipped test.
     *
     * @param MapTest   $test
     * @param Exception $e
     * @param float     $time
     */
    public function addSkippedTest(MapTest $test, Exception $e, $time)
    {
        // PHPUnit will skip a test without "starting" or "ending" it if a dependency isn't being met.
        if ($test->getName() != $this->lastTestName) {
            $this->writeNewLine();
            $this->writeProgressWithColor('fg-cyan, bold', 'SKIPPING: ' . MapTestUtil::describe($test));
            $this->writeNewLine();
            $this->writeProgress($e->getMessage());
            $this->writeNewLine();
        } else {
            $this->writeProgressWithColor('fg-cyan, bold', 'SKIPPED');
            $this->lastTestFailed = true;
        }
    }

    /**
     * A test started.
     *
     * @param MapTest $test
     */
    public function startTest(MapTest $test)
    {
        $this->write(
            sprintf(
                "\nStarting test '%s'.\n",
                MapTestUtil::describe($test)
            )
        );
        $this->lastTestName = $test->getName();
    }

    /**
     * A test ended.
     *
     * @param MapTest $test
     * @param float   $time
     */
    public function endTest(MapTest $test, $time)
    {
        if (!$this->lastTestFailed) {
            $this->writeProgressWithColor('fg-green, bold', 'PASS');
        }

        if ($test instanceof MapTestCase) {
            $this->numAssertions += $test->getNumAssertions();
        } elseif ($test instanceof MapPhptTestCase) {
            $this->numAssertions++;
        }

        $this->lastTestFailed = false;
        $this->lastTestName = '';

        if ($test instanceof MapTestCase) {
            if (!$test->hasExpectationOnOutput()) {
                if ($output = $test->getActualOutput()) {
                    $this->writeNewLine();
                    $this->write($output);
                }
            }
        }
        $this->writeNewLine();
    }

    /**
     * Formats a buffer with a specified ANSI color sequence if colors are
     * enabled.
     *
     * @param  string $color
     * @param  string $buffer
     * @return string
     */
    protected function formatWithColor($color, $buffer)
    {
        if (!$this->colors) {
            return $buffer;
        }

        $codes = array_map('trim', explode(',', $color));
        $lines = explode("\n", $buffer);
        $padding = max(array_map('strlen', $lines));

        $styles = array();
        foreach ($codes as $code) {
            $styles[] = self::$ansiCodes[$code];
        }
        $style = sprintf("\x1b[%sm", implode(';', $styles));

        $styledLines = array();
        foreach ($lines as $line) {
            $styledLines[] = $style . str_pad($line, $padding) . "\x1b[0m";
        }

        return implode("\n", $styledLines);
    }

    /**
     * @param string $buffer
     */
    public function write($buffer)
    {
        if ($this->out) {
            fwrite($this->out, $buffer);

            if ($this->autoFlush) {
                $this->incrementalFlush();
            }
        } else {
            if (PHP_SAPI != 'cli') {
                $buffer = htmlspecialchars($buffer);
            }

            print $buffer;

            if ($this->autoFlush) {
                $this->incrementalFlush();
            }
        }
        if (getenv('TDDIUM')) {
            global $tddium_output_buffer;
            $tddium_output_buffer .= $buffer;
        }
    }
}
