<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 * Catch fatal errors that crash phpunit (requires PHP 5.2+)
 */

function solanoPHPUnitShutdown()
{
    $error = error_get_last();
    if ($error['type'] === E_ERROR && ($outputFile = getenv('SOLANO_PHPUNIT_OUTPUT_FILE')) && ($state = getenv('SOLANO_PHPUNIT_STATE'))) {

        // Extract/prepare information
        list($state, $lastFile) = explode(';', $state);
        $lastFile = SolanoLabs_PHPUnit_Util::shortenFilename($lastFile);
        if ($maxCount = getenv('SOLANO_PHPUNIT_MAX_TRIES')) {
            $maxCount = intval($maxCount);
        } else {
            $maxCount = 1;
        }
        $stripPath = getenv('SOLANO_WORKING_DIRECTORY');
        foreach ($error as $key => $value) {
            if (0 === strpos($error[$key], $stripPath)) {
                $error[$key] = substr($error[$key], strlen($stripPath) + 1);
            }
        }
        $backtrace = $error['file'] . ' (line ' . $error['line'] . "):\n" . $error['message'];
        $messageArray = array(
            'id' => $lastFile,
            'address' => $lastFile,
            'status' => 'error',
            'stderr' => 'PHP FATAL ERROR: ' . $lastFile . "\n" . $backtrace,
            'stdout' => '',
            'time' => 0,
            'traceback' => array());
        $rerun = false;

        // Load current build status data
        $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($outputFile);

        if ($state == 'testing') {
            // The fatal error occurred after a test file has been loaded
            if (empty($jsonData['fatal_errors']['tests'][$lastFile])) {
                // The current test has not failed
                if (!isset($jsonData['fatal_errors']) || !is_array($jsonData['fatal_errors'])) {
                    $jsonData['fatal_errors'] = array('tests' => array($lastFile => array($messageArray)));
                } elseif (!isset($jsonData['fatal_errors']['tests']) || !is_array($jsonData['fatal_errors']['tests'])) {
                    $jsonData['fatal_errors']['tests'] = array($lastfile => $messageArray);
                }
                $failCount = 1;
            } else {
                $failCount = count($jsonData['fatal_errors']['tests'][$lastFile]) + 1;
                array_push($jsonData['fatal_errors']['tests'][$lastFile], $messageArray);
            }
            if ($failCount >= $maxCount) {
                // Mark specific test as failed
                if (isset($jsonData['byfile'][$lastFile]) && count($jsonData['byfile'][$lastFile])) {
                    $jsonData['byfile'][$lastFile][] = $messageArray;
                } else {
                    $jsonData['byfile'][$lastFile] = array($messageArray);
                }
            }
            // Attempt to restart process to run remaining tests
            $rerun = true;
        } else {
            // The fatal error occurred outside of testing
            if (empty($jsonData['fatal_errors'][$state])) {
                if (isset($jsonData['fatal_errors']) || !is_array($jsonData['fatal_errors'])) {
                    $jsonData['fatal_errors'] = array($state => array($messageArray));
                }
                $failCount = 1;
            } else {
                $failCount = count($jsonData['fatal_errors'][$state]) + 1;
                array_push($jsonData['fatal_errors'][$state], $messageArray);
            }
            // Attempt to restart process depending on fail count
            if ($failCount < $maxCount) {
                $rerun = true;
            }
        }

        // Write results to file
        SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($outputFile, $jsonData);

        // Should/can the process be restarted?
        if ($rerun && function_exists('pcntl_exec')) {
            // Store a non-zero exit code so the replacement process doesn't "pass"
            putenv('SOLANO_PHPUNIT_EXIT_CODE=6');
            $args = $_SERVER['argv'];
            $cmd = array_shift($args);
            pcntl_exec($cmd, $args);
        } else {
            // Won't/can't restart the process, so populate JSON with errors (so they don't get marked as skipped)
            foreach($jsonData['byfile'] as $file => $data) {
                if (!is_array($data) || !count($data)) {
                    $jsonData['byfile'][$file] = array(array(
                        'id' => $file,
                        'address' => $file,
                        'status' => 'error',
                        'stderr' => $file . " was not run due to:\nPHP FATAL ERROR: " . $lastFile . "\n" . $backtrace,
                        'stdout' => '',
                        'time' => 0,
                        'traceback' => array()));
                }
            }
            SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($outputFile, $jsonData);
            exit(6);
        }
    }
}

register_shutdown_function('solanoPHPUnitShutdown');
