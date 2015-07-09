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
    if ($error['type'] === E_ERROR && $outputFile = getenv('SOLANO_PHPUNIT_OUTPUT_FILE')) {
        $lastFile = SolanoLabs_PHPUnit_Util::shortenFilename(getenv('SOLANO_LAST_FILE_STARTED'));
        $stripPath = getcwd();
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
        // Write the error to the JSON file
        $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($outputFile);

        if (isset($jsonData['byfile'][$lastFile]) && count($jsonData['byfile'][$lastFile])) {
            $jsonData['byfile'][$lastFile][] = $messageArray;
        } else {
            $jsonData['byfile'][$lastFile] = array($messageArray);
        }
        SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($outputFile, $jsonData);

        // Can the process be restarted?
        if (function_exists('pcntl_exec')) {
            // Store a non-zero exit code so the replacement process doesn't "pass"
            putenv('SOLANO_PHPUNIT_EXIT_CODE=6');
            $args = $_SERVER['argv'];
            $cmd = array_shift($args);
            pcntl_exec($cmd, $args);
        } else {
            // Can't restart the process, so populate JSON with errors (so they don't get marked as skipped)
            $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($outputFile);
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
        }
    }
}

register_shutdown_function('solanoPHPUnitShutdown');
