<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

/**
 * Command Line Interface (CLI) for Solano-PHPUnit
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_JsonReporter
{
    /**
     * Read output file.
     *
     * @param string          $outputFile
     */
    public static function readOutputFile($outputFile)
    {
        if (!file_exists($outputFile)) {
            return array('byfile' => array());
        } else {
            $jsonData = json_decode(file_get_contents($outputFile), true);
            if (is_null($jsonData) || !isset($jsonData['byfile']) || !is_array($jsonData['byfile'])) {
                echo("### ERROR: JSON data could not be read from " . $outputFile . "\n");
                $jsonData = array('byfile' => array());
                SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($jsonData);
            }
            return $jsonData;
        }
    }

    /** 
     * Write json to file
     *
     * @param string            $outputFile
     * @param array             $jsonData
     */
    public static function writeJsonToFile($outputFile, $jsonData)
    {
        $file = fopen($outputFile, 'w');
        if (!defined('JSON_PRETTY_PRINT')) { define('JSON_PRETTY_PRINT', 128); } // JSON_PRETTY_PRINT available since PHP 5.4.0
        fwrite($file, str_replace('\/', '/', json_encode($jsonData, JSON_PRETTY_PRINT))); // unescape the json_encode slashes
        fclose($file);
    }

    /** 
     * Add individual testfile result to output file
     *
     * @param string            $outputFile
     * @param string            $file
     * @param array             $testcase
     */
    public static function writeTestcaseToFile($outputFile, $file, $testcase)
    {
        $file = SolanoLabs_PHPUnit_Util::convertOutputToUTF8($file);
        $testcase = SolanoLabs_PHPUnit_Util::convertOutputToUTF8($testcase);
        $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($outputFile);
        if (empty($jsonData['byfile'][$file])) {
            $jsonData['byfile'][$file] = array($testcase);
        } else {
            $jsonData['byfile'][$file][] = $testcase;
        }

        SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($outputFile, $jsonData);
    }

    /**
     * Create exluded file notice
     *
     * @param string          $shortFilename
     */
    public static function generateExcludeFileNotice($shortFilename)
    {
        return array(array('id' => $shortFilename,
                           'address' => $shortFilename,
                           'status' => 'skip',
                           'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Excluded by <exclude/> in configuration',
                           'stdout' => '',
                           'time' => 0,
                           'traceback' => array()));
    }
}