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
            self::aliasClasses();
            self::RewriteClasses();
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
     * Alias PHPUnit classes
     * Inspired by From https://github.com/symfony/symfony/issues/21534#issuecomment-278278352
     */
    private static function aliasClasses()
    {
        $phpunit_classes_used_by_solano_phpunit = array(
            '\\PHPUnit_Framework_TestListener',
            '\\PHPUnit_TextUI_Command',
            '\\PHPUnit_Framework_Test',
            '\\PHPUnit_Framework_AssertionFailedError',
            '\\PHPUnit_Util_Printer',
            '\\PHPUnit_Framework_Warning',
            '\\PHPUnit_Framework_TestSuite',
            '\\PHPUnit_TextUI_ResultPrinter',
            '\\PHPUnit_Framework_TestCase',
            '\\PHPUnit_Framework_Error_Warning',
            '\\PHPUnit_Util_Test',
            '\\PHPUnit_Runner_Version'
        );

        foreach ($phpunit_classes_used_by_solano_phpunit as $old_class) { 
            $new_class = str_replace('_', '\\', $old_class);
            if (!class_exists($old_class, true) && class_exists($new_class, true)) {
                class_alias($new_class, $old_class, true);
            } elseif (!class_exists($new_class, true) && class_exists($old_class, true)) {
                class_alias($old_class, $new_class, true);
            }
        }
    }

    /**
     * Aliasing classes is not enough for inheritance, so re-write some classes if needed
     */
    private static function RewriteClasses()
    {
        $rewrite_files = array(
            dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Listener.php' => array(
                'PHPUnit_Util_Printer' => '\\PHPUnit\\Util\\Printer',
                'PHPUnit_Framework_TestListener' => '\\PHPUnit\\Framework\\TestListener',
                'PHPUnit_Framework_Test' => '\\PHPUnit\\Framework\\Test',
                'PHPUnit_Framework_AssertionFailedError' => '\\PHPUnit\\Framework\\AssertionFailedError',
                'PHPUnit_Util_Filter' => '\\PHPUnit\\Util\\Filter',
                'PHPUnit_Framework_TestSuite' => '\\PHPUnit\\Framework\\TestSuite',
                'PHPUnit_Framework_Warning' => '\\PHPUnit\\Framework\\Warning'
            ),
            dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Printer.php' => array(
                'PHPUnit_TextUI_ResultPrinter' => '\\PHPUnit\\TextUI\\ResultPrinter',
                'PHPUnit_Framework_Test' => '\\PHPUnit\\Framework\\Test',
                'PHPUnit_Framework_AssertionFailedError' => '\\PHPUnit\\Framework\\AssertionFailedError',
                'PHPUnit_Framework_Warning' => '\\PHPUnit\\Framework\\Warning'
            )
        );
        foreach($rewrite_files as $rewrite_file => $rewrite_strings) {
            $content = file_get_contents($rewrite_file);
            $content_pre_rewrite = $content;
            foreach($rewrite_strings as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }
            self::MaybeRewriteFile($rewrite_file, $content_pre_rewrite, $content);
        }
    }

    /**
     * Write changes to file?
     *
     * @param  string       $file
     * @param  string       $original_content
     * @param  string       $new_content         
     * @return boolean
     */
    private static function MaybeRewriteFile($file, $original_content, $new_content)
    {
        if ($original_content == $new_content) { return; }
        $path_parts = pathinfo($file);
        // Save original as backup?
        $file_backup = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . '-backup.' . $path_parts['extension']; 
        if (!file_exists($file_backup)) {
            file_put_contents($file_backup, $original_content);
        }
        // Save modified version for potential reference later?
        $file_version_specific = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . '-' . self::$phpunit_version . '.' . $path_parts['extension']; 
        if (!file_exists($file_version_specific)) {
            file_put_contents($file_version_specific, $new_content);
        }
        // Save new content in original file location
        file_put_contents($file, $new_content);
    }

}