<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

/**
 * Utilities for Solano-PHPUnit
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_Util
{
    /**
     * Inspired by http://stackoverflow.com/questions/4049856/replace-phps-realpath#answer-4050444
     *
     * @param  string       $path         The original path, can be relative etc.
     * @param  string       $workingDir   The directory to base the path on, getcwd() is default.
     * @return string                     The resolved path, it might not exist.
     */
    public static function truepath($path, $workingDir = '')
    {
        if (!$workingDir) { $workingDir = getcwd(); }

        if (!self::isRootRelativePath($path)) {
            $path = $workingDir . DIRECTORY_SEPARATOR . $path;
        }

        // Resolve path parts
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $pieces = array();
        foreach ($parts as $part) {
            if ('.' == $part) { continue; }
            if ('..' == $part) { array_pop($pieces); continue; }
            $pieces[] = $part;
        }

        $path = implode(DIRECTORY_SEPARATOR, $pieces);
        // Need to add initial / back if on *nix
        if (DIRECTORY_SEPARATOR == '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Determines if the supplied path is root relative
     *
     * @param  string       $path         
     * @return boolean
     */
    public static function isRootRelativePath($path)
    {
        // *nix
        if ($path[0] === '/') {
            return true;
        }

        // Matches the following on Windows:
        //  - \\NetworkComputer\Path
        //  - \\.\D:
        //  - \\.\c:
        //  - C:\Windows
        //  - C:\windows
        //  - C:/windows
        //  - c:/windows
        if (defined('PHP_WINDOWS_VERSION_BUILD') &&
            ($path[0] === '\\' ||
            (strlen($path) >= 3 && preg_match('#^[A-Z]\:[/\\\]#i', substr($path, 0, 3))))) {
            return true;
        }

        // Stream
        if (strpos($path, '://') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Converts path to absolute path, checking the include_path ini settings if directed
     *
     * @param  string           $path
     * @param  boolean          $useIncludePath
     * @return string
     */
    public static function toAbsolutePath($path, $useIncludePath = false)
    {
        if (self::isRootRelativePath($path)) {
            return $path;
        }

        // file/folder
        $derivedPath = realpath(getcwd() . DIRECTORY_SEPARATOR . $path);
        if (file_exists($derivedPath)) {
            return $derivedPath;
        }

        // Include path
        if ($useIncludePath) {
            $derivedPath = stream_resolve_include_path($path);
            if ($derivedPath) { $path = $derivedPath; }
        }

        return $path;
    }

    /**
     * Create output for files that were excluded in the XML config
     *
     * @param array           $excludeFiles
     * @param string          $stripPath
     */
    public static function generateExcludeFileNotices($excludeFiles, $stripPath)
    {
        $skipFiles = array();
        foreach($excludeFiles as $file) {
            $shortFilename = $file;
            if (0 === strpos($file, $stripPath)) {
                $shortFilename = substr($file, strlen($stripPath) + 1);
            }
            // Can we inspect the file?
            try {
                $fileMethods = array();
                $declaredClasses = get_declared_classes();
                PHPUnit_Util_Fileloader::checkAndLoad($file);
                $newClasses = array_diff(get_declared_classes(), $declaredClasses);
                foreach($newClasses as $className) {
                    $class = new ReflectionClass($className);
                    if ($class->implementsInterface('PHPUnit_Framework_Test')) {
                        $methods = $class->getMethods();
                        foreach ($methods as $method) {
                            if (0 === strpos($method->name, 'test')) {
                                $fileMethod = array('id' => $className . '::' . $method->name,
                                    'address' => $className . '::' . $method->name,
                                    'status' => 'skip',
                                    'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Excluded by <exclude/> in configuration',
                                    'stdout' => '',
                                    'time' => 0,
                                    'traceback' => array());
                                $fileMethods[] = $fileMethod;
                            }
                        }
                    }
                }
                if (count($fileMethods)) {
                    $skipFiles[$shortFilename] = $fileMethods;
                } else {
                    $skipFiles[$shortFilename] = array(array('id' => $shortFilename,
                        'address' => $shortFilename,
                        'status' => 'skip',
                        'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Excluded by <exclude/> and no test methods found',
                        'stdout' => '',
                        'time' => 0,
                        'traceback' => array()));
                }
            } catch (Exception $e) {
                $skipFiles[$shortFilename] = array(array('id' => $shortFilename,
                    'address' => $shortFilename,
                    'status' => 'skip',
                    'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Excluded by <exclude/> in configuration and could not inspect file:' . "\n" . $e->getMessage(),
                    'stdout' => '',
                    'time' => 0,
                    'traceback' => array()));
            }

        }
        return $skipFiles;
    }

    /**
     * Write output file.
     * 
     * @param string          $outputFile
     * @param string          $stripPath
     * @param array           $files
     * @param array           $excludeFiles
     */
    public static function writeOutputFile($outputFile, $stripPath, $files = array(), $excludeFiles = array())
    {
        $files = self::convertOutputToUTF8($files);
        if (file_exists($outputFile)) {
            // If the output file exists, add to it
            $json = json_decode(file_get_contents($outputFile), true);
            if (is_null($json) || !isset($json['byfile']) || !is_array($json['byfile'])) {
                // JSON could not be read
                echo("### ERROR: JSON data could not be read from " . $outputFile . "\n");
                $jsonData = array('byfile' => $files);
            } else {
                $jsonData = array('byfile' => array_merge($json['byfile'], $files));
            }
        } else {
            // Output file doesn't exist, create fresh.
            $jsonData = array('byfile' => $files);
        }

        if (count($excludeFiles)) {
            $jsonData = array('byfile' => array_merge($jsonData['byfile'], self::generateExcludeFileNotices($excludeFiles, $stripPath)));
        }

        $file = fopen($outputFile, 'w');
        if (!defined('JSON_PRETTY_PRINT')) { define('JSON_PRETTY_PRINT', 128); } // JSON_PRETTY_PRINT available since PHP 5.4.0
        fwrite($file, json_encode($jsonData, JSON_PRETTY_PRINT));
        fclose($file);

        // Debugging
        if (getenv('TDDIUM')) {
            shell_exec("cp $outputFile " . '$HOME/results/$TDDIUM_SESSION_ID/session/');
            shell_exec("ls -la $outputFile > " . '$HOME/results/$TDDIUM_SESSION_ID/session/ls-la_outputFile-$TDDIUM_TEST_EXEC_ID.txt');
        }
    }

    /**
     * Convert to utf8
     *
     * @param array                 $array
     */
    public static function convertOutputToUTF8($array)
    {
        array_walk_recursive($array, function (&$input) {
        if (is_string($input)) {
                $input = PHPUnit_Util_String::convertToUtf8($input);
            }
        });
        unset($input);
        return $array;
    }
}
