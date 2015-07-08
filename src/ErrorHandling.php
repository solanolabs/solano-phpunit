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
        $lastFile = getenv('SOLANO_LAST_FILE_STARTED');
        $stripPath = getcwd();
        foreach ($error as $key => $value) {
            if (0 === strpos($error[$key], $stripPath)) {
                $error[$key] = substr($error[$key], strlen($stripPath) + 1);
            }
        }

        $messageArray = array('id' => $lastFile,
                              'address' => $lastFile,
                              'status' => 'error',
                              'stderr' => 'PHP FATAL ERROR: ' . $lastFile . "\n" . $error['file'] . ' (line ' . $error['line'] . "):\n" . $error['message'],
                              'stdout' => '',
                              'time' => 0,
                              'traceback' => array());
        // Load outputFile
        $jsonData = SolanoLabs_PHPUnit_Util::readOutputFile($outputFile);

        if (isset($jsonData['byfile'][$lastFile]) && count($jsonData['byfile'][$lastFile])) {
            $jsonData['byfile'][$lastFile][] = $messageArray;
        } else {
            $jsonData['byfile'][$lastFile] = array($messageArray);
        }
        SolanoLabs_PHPUnit_Util::writeJsonToFile($outputFile, $jsonData);

        // Restart solano-phpunit
        $args = $_SERVER['argv'];
        $cmd = array_shift($args);
        pcntl_exec($cmd, $args);
    }
}

register_shutdown_function('solanoPHPUnitShutdown');
