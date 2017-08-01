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
 * Once PHPUnit versions prior to 6 are EOL, this will no longer be necessary.
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
     * @var    array
     */
    private static $class_translations;
    /**
     * @var    string
     */
    private static $version_file = ".phpunit_version";
   
    /**
     * Entrypoint
     *
     */
    public static function prepare()
    {
        self::getPHPUnitVersion();
        self::checkVersionCompatibility();
    }

    /**
     * Determine PHPUnit version
     */
    private static function getPHPUnitVersion()
    {
        if (self::$phpunit_version !== null) { return self::$phpunit_version; }
        if (class_exists('\\PHPUnit\\Runner\\Version')) {
            self::$phpunit_version = \PHPUnit\Runner\Version::id();
        } elseif (class_exists('\\PHPUnit_Runner_Version')) {
            self::$phpunit_version = \PHPUnit_Runner_Version::id();
        } else {
            fwrite(STDERR, "Could not determine PHPUnit version. Terminating.");
            die(1);
        }
    }

    /**
     * Check if the PHPUnit version is not recorded or the recorded value doesn't match the current value
     *
     * @return boolean
     */
    private static function checkVersionCompatibility()
    {
        $version_file_path = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . self::$version_file;
        $from = '';
        if (file_exists($version_file_path)) {
            $from = file_get_contents($version_file_path);
        }
        if (false !== strpos(file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Command.php'), 'new Replace_Command')) {
            $from = ''; // Despite the version stored in a file, a class name hasn't been altered appropriately
        }
        if ($from != self::$phpunit_version) {
            self::loadClassTranslations($from, self::$phpunit_version);
            self::UpdateSrcFiles();
            self::UpdateTestFiles();
            fwrite(STDERR, "Recording PHPUnit version " . self::$phpunit_version . " to file:\n" . $version_file_path . "\n");
            file_put_contents($version_file_path, self::$phpunit_version);
        }
    }

    /**
     * Determine how classes should be referenced
     *
     * @param string   $version_from
     * @param string   $version_to
     */
    private static function loadClassTranslations($version_from, $version_to)
    {
        $class_mappings = array (
            'AssertionFailedError' => array('5-' => '\\PHPUnit_Framework_AssertionFailedError', '6+' => '\\PHPUnit\\Framework\\AssertionFailedError'),
            'Command' => array('5-' => '\\PHPUnit_TextUI_Command', '6+' => '\\PHPUnit\\TextUI\\Command'),
            'ErrorWarning' => array('5-' => '\\PHPUnit_Framework_Error_Warning', '6+' => '\\PHPUnit\\Framework\\Error\\Warning'),
            'Filter' => array('5-' => '\\PHPUnit_Util_Filter', '6+' => '\\PHPUnit\\Util\\Filter'),
            'PhptTestCase' => array('5-' => '\\PHPUnit_Extensions_PhptTestCase', '6+' => '\\PHPUnit\\Runner\\PhptTestCase'),
            'Printer' => array('5-' => '\\PHPUnit_Util_Printer', '6+' => '\\PHPUnit\\Util\\Printer'),
            'ResultPrinter' => array('5-' => '\\PHPUnit_TextUI_ResultPrinter', '6+' => '\\PHPUnit\\TextUI\\ResultPrinter'),
            'TestCase' => array('5-' => '\\PHPUnit_Framework_TestCase', '6+' => '\\PHPUnit\\Framework\\TestCase'),
            'TestListener' => array('5-' => '\\PHPUnit_Framework_TestListener', '6+' => '\\PHPUnit\\Framework\\TestListener'),
            'TestSuite' => array('5-' => '\\PHPUnit_Framework_TestSuite', '6+' => '\\PHPUnit\\Framework\\TestSuite'),
            'TestUtil' => array('5-' => '\\PHPUnit_Util_Test', '6+' => '\\PHPUnit\\Util\\Test'),
            'Test' => array('5-' => '\\PHPUnit_Framework_Test', '6+' => '\\PHPUnit\\Framework\\Test'),
            'Warning' => array('5-' => '\\PHPUnit_Framework_Warning', '6+' => '\\PHPUnit\\Framework\\Warning')
        );
        self::$class_translations = array();
        foreach($class_mappings as $key => $class_map) {
            if ($version_from == '') {
                $class_from = 'Replace_' . $key;
            } elseif (version_compare($version_from, '6.0', '>=')) {
                $class_from = $class_map['6+'];
            } else {
                $class_from = $class_map['5-'];
            }
            if (version_compare($version_to, '6.0', '>=')) {
                $class_to= $class_map['6+'];
            } else {
                $class_to = $class_map['5-'];
            }
            if ($class_from != $class_to) {
                self::$class_translations[] = array('from' => $class_from, 'to' => $class_to);
            }
        }
    }
    
    /**
     * Update appropriate files in 'src/' directory
     */
    private static function UpdateSrcFiles()
    {
        $facade = new File_Iterator_Facade;
        $files = $facade->getFilesAsArray(dirname(__FILE__), '.php');
        foreach ($files as $file) {
            // Do not rewrite this file!
            if ($file == __FILE__) { continue; }
            $contents_original = file_get_contents($file);
            $contents_modified = $contents_original;
            foreach (self::$class_translations as $class_translation) {
                $contents_modified = str_replace($class_translation['from'], $class_translation['to'], $contents_modified);
            }
            if ($contents_original != $contents_modified) {
                fwrite(STDERR, "Updating " . $file . "\n");
                file_put_contents($file, $contents_modified);
            }
        }
    }

    /**
     * Update appropriate files in 'tests/' directory
     */
    private static function UpdateTestFiles()
    {
        $facade = new File_Iterator_Facade;
        $files = $facade->getFilesAsArray(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'tests', 'Test.php');
        foreach ($files as $file) {
            // Do not rewrite this file!
            if ($file == __FILE__) { continue; }
            $contents_original = file_get_contents($file);
            $contents_modified = $contents_original;
            foreach (self::$class_translations as $class_translation) {
                $contents_modified = str_replace($class_translation['from'], $class_translation['to'], $contents_modified);
            }
            if ($contents_original != $contents_modified) {
                fwrite(STDERR, "Updating " . $file . "\n");
                file_put_contents($file, $contents_modified);
            }
        }
    }

}
