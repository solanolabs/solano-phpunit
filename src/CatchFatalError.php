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
    if ($error['type'] === E_ERROR && $outputFile = getenv('TDDIUM_OUTPUT_FILE_PROCESS')) {
        $error['lastFile'] = getenv('TDDIUM_LAST_TEST_FILE_STARTED');
        $stripPath = getcwd();
        foreach ($error as $key => $value) {
            if (0 === strpos($error[$key], $stripPath)) {
                $error[$key] = substr($error[$key], strlen($stripPath) + 1);
            }
        }
	$error['messageLine'] = $error['file'] . ' (line ' . $error['line'] . "):\n" . $error['message'];

        // Load outputFile
        $jsonData = SolanoLabs_PHPUnit_Util::readOutputFile($outputFile);
        foreach ($jsonData['byfile'] as $testFile => $data) {
            if (is_array($data) && !count($data)) {
                if ($testFile == $error['lastFile']) {
                    $jsonData['byfile'][$testFile] = array(array(
                        'id' => $error['lastFile'],
                        'address' => $error['lastFile'],
                        'status' => 'error',
                        'stderr' => 'PHP FATAL ERROR: ' . $error['lastFile'] . "\n" . $error['messageLine'],
                        'stdout' => '',
                        'time' => 0,
                        'traceback' => array()));
                } else {
                    $jsonData['byfile'][$testFile] = array(array(
                        'id' => $testFile,
                        'address' => $testFile,
                        'status' => 'error',
                        'stderr' => "Skipped due to PHP fatal error in:\n" . $error['messageLine'],
                        'stdout' => '',
                        'time' => 0,
                        'traceback' => array()));
                }
            }
        }
        SolanoLabs_PHPUnit_Util::writeJsonToFile($outputFile, $jsonData);
    }
}

register_shutdown_function('solanoPHPUnitShutdown');
