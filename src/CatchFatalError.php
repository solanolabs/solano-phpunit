<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 * Catch fatal errors that crash phpunit (requires PHP 5.2+)
 */


function skippedFileDueToFatalError($errorFileName, $errorLineNumber, $errorMessage, $testFile)
{
    return array(array(
            'id' => $testFile,
            'address' => $testFile,
            'status' => 'error',
            'stderr' => 'Skipped due to PHP fatal error in ' . $errorFileName . ":" . $errorLineNumber . "\n" . $errorMessage,
            'stdout' => '',
            'time' => 0,
            'traceback' => array()));
}

function solanoPHPUnitShutdown() {
    $error = error_get_last();
    if ($error['type'] === E_ERROR && $outputFile = getenv('TDDIUM_OUTPUT_FILE_PROCESS')) {
        $file = getenv('TDDIUM_LAST_TEST_FILE_STARTED');
        $stripPath = getcwd();
        $shortFilename = $file;
        if (0 === strpos($file, $stripPath)) {
            $shortFilename = substr($file, strlen($stripPath) + 1);
        }
        $lastFileError = array(array(
            'id' => $shortFilename,
            'address' => $shortFilename,
            'status' => 'error',
            'stderr' => 'PHP FATAL ERROR: ' . $shortFilename . ":" . $error['line'] . "\n" . $error['message'],
            'stdout' => '',
            'time' => 0,
            'traceback' => array()));
        // Load outputFile
        $jsonData = SolanoLabs_PHPUnit_Util::readOutputFile($outputFile);
        foreach ($jsonData['byfile'] as $testFile => $data) {
            if (is_array($data) && !count($data)) {
                if ($testFile == $shortFilename) {
                    $jsonData['byfile'][$testFile] = $lastFileError;
                } else {
                    $jsonData['byfile'][$testFile] = skippedFileDueToFatalError($shortFilename, $error['line'], $error['message'], $testFile);
                }
            }
        }
        SolanoLabs_PHPUnit_Util::writeJsonToFile($outputFile, $jsonData);
    }
}

register_shutdown_function('solanoPHPUnitShutdown');
