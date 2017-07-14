<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

/**
 * Ensure solano-phpunit is compatible with PHPUnit (which implemented namespaced classes at version 6)
 * https://github.com/sebastianbergmann/phpunit/wiki/Release-Announcement-for-PHPUnit-6.0.0#backwards-compatibility-issues
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_Version_Prepare
{
    /**
     * @var    string
     */
    private static $phpunit_version;

    /**
     * Determines if the supplied path is root relative
     *
     * @param  string       $path         
     * @return boolean
     */
    public static function prepare()
    {
        self::getPHPUnitVersion();
        if (version_compare(self::$phpunit_version, '6.0', '>=')) {
            self::writePHPUnitClassMapFiles('6+');
        } else {
            self::writePHPUnitClassMapFiles('5-');
        }
        // If the current working directory is solano-phpunit's root directory,
        // presume solano-phpunit is self testing and update test files accordingly
        if (dirname(dirname(__FILE__)) == getcwd()) {
            self::writeSolanoPHPUnitTestFiles();
        }
    }

    /**
     * Determine PHPUnit version
     */
    private static function getPHPUnitVersion()
    {
        if (!empty(self::$phpunit_version)) { return self::$phpunit_version; }
        if (class_exists('\\PHPUnit\\Runner\\Version')) {
            self::$phpunit_version = \PHPUnit\Runner\Version::id();
        } elseif (class_exists('\\PHPUnit_Runner_Version')) {
            self::$phpunit_version = \PHPUnit_Runner_Version::id();
        }
        return self::$phpunit_version;
    }

    /**
     * Map class names for different versions of PHPUnit that solano-phpunit uses
     */
    private static function getPHPUnitClassMaps()
    {
        return array(
            'AssertionFailedError' => array('5-' => '\\PHPUnit_Framework_AssertionFailedError', '6+' => '\\PHPUnit\\Framework\\AssertionFailedError'),
            'Command' => array('5-' => '\\PHPUnit_TextUI_Command', '6+' => '\\PHPUnit\\TextUI\\Command'),
            'Filter' => array('5-' => '\\PHPUnit_Util_Filter', '6+' => '\\PHPUnit\\Util\\Filter'),
            'Listener' => array('5-' => '\\PHPUnit_Util_Printer implements \\PHPUnit_Framework_TestListener', '6+' => '\\PHPUnit\\Util\\Printer implements \\PHPUnit\\Framework\\TestListener'),
            'PhptTestCase' => array('5-' => '\\PHPUnit_Extensions_PhptTestCase', '6+' => '\\PHPUnit\\Runner\\PhptTestCase'),
            'Printer' => array('5-' => '\\PHPUnit_Util_Printer', '6+' => '\\PHPUnit\\Util\\Printer'),
            'ResultPrinter' => array('5-' => '\\PHPUnit_TextUI_ResultPrinter', '6+' => '\\PHPUnit\\TextUI\\ResultPrinter'),
            'Test' => array('5-' => '\\PHPUnit_Framework_Test', '6+' => '\\PHPUnit\\Framework\\Test'),
            'TestCase' => array('5-' => '\\PHPUnit_Framework_TestCase', '6+' => '\\PHPUnit\\Framework\\TestCase'),
            'TestListener' => array('5-' => '\\PHPUnit_Framework_TestListener', '6+' => '\\PHPUnit\\Framework\\TestListener'),
            'TestSuite' => array('5-' => '\\PHPUnit_Framework_Suite', '6+' => '\\PHPUnit\\Framework\\Suite'),
            'TestUtil' => array('5-' => '\\PHPUnit_Util_Test', '6+' => '\\PHPUnit\\Util\\Test'),
            'Warning' => array('5-' => '\\PHPUnit_Framework_Warning', '6+' => '\\PHPUnit\\Framework\\Error\\Warning')
        );
    }

    /**
     * Dynamically write class mapping files
     * Replace lines starting with 'class ' with appropraite class definition
     */
    private static function writePHPUnitClassMapFiles($version_id)
    {
        $dir = dirname(__FILE__);
        foreach (self::getPHPUnitClassMaps() as $key => $version_mappings) {
            $map_file = $dir . DIRECTORY_SEPARATOR . 'Map' . $key . '.php';
            $lines = file($map_file);
            $new_lines = array();
            foreach ($lines as $line) {
                if (0 === strpos($line, 'class ')) {
                    $new_lines[] = 'class Map' . $key . ' extends ' . $version_mappings[$version_id] . ' {}' ;
                } else {
                    $new_lines[] = $line;
                }
            }
            file_put_contents($map_file, $new_lines);
        }
    }

    /**
    * Write test files to use the correct path
    * e.g. change MapTestCase to \PHPUnit_Framework_TestCase or \PHPUnit\Framework\TestCase
    */
    private static function writeSolanoPHPUnitTestFiles()
    {
        $facade = new File_Iterator_Facade;
        $files = $facade->getFilesAsArray(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'tests', 'Test.php');
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (false !== strpos($contents, 'MapTestCase')) {
                if (version_compare(self::$phpunit_version, '6.0', '>=')) {
                    $contents = str_replace('MapTestCase', '\\PHPUnit\\Framework\\TestCase', $contents);
                } else {
                    $contents = str_replace('MapTestCase', '\\PHPUnit_Framework_TestCase', $contents);
                }
                file_put_contents($file, $contents);
            }
        }
    }
}
