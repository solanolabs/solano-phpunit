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

        if (!SolanoLabs_PHPUnit_Util::isRootRelativePath($path)) {
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
        if (SolanoLabs_PHPUnit_Util::isRootRelativePath($path)) {
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
     * Convert to utf8
     *
     * @param array                 $array
     */
    public static function convertOutputToUTF8($data)
    {
        if (is_array($data)) {
            array_walk_recursive($data, function (&$input) {
                if (is_string($input)) {
                    $input = PHPUnit_Util_String::convertToUtf8($input);
                }
            });
            unset($input);
        } else {
            $data = PHPUnit_Util_String::convertToUtf8($data);
        }
        return $data;
    }

    /**
     * Return shorten path name
     */
    public static function shortenFilename($file, $stripPath = '')
    {
        if (!$stripPath) {
            if (getenv('SOLANO_WORKING_DIRECTORY')) {
                $stripPath = getenv('SOLANO_WORKING_DIRECTORY');
            } else {
                $stripPath = getcwd();
            }
        }
        if (0 === strpos($file, $stripPath)) {
            $file = substr($file, strlen($stripPath) + 1);
        }
        return $file;
    }

    // PHPUnit_Util_String removed in later versions of PHPUnit :(

    /**
     * Convert string to UTF-8
     *
     * @param string                $string
     *
     * @return string
     */
    public static function convertToUtf8($string)
    {
        if (!SolanoLabs_PHPUnit_Util::isUtf8($string)) {
            if (function_exists('mb_convert_encoding')) {
                $string = mb_convert_encoding($string, 'UTF-8');
            } else {
                $string = utf8_encode($string);
            }
        }
        return $string;
    }

    /**
     * Checks a string for UTF-8 encoding.
     *
     * @param string $string
     *
     * @return bool
     */
    private static function isUtf8($string)
    {
        $length = strlen($string);
        for ($i = 0; $i < $length; $i++) {
            if (ord($string[$i]) < 0x80) {
                $n = 0;
            } elseif ((ord($string[$i]) & 0xE0) == 0xC0) {
                $n = 1;
            } elseif ((ord($string[$i]) & 0xF0) == 0xE0) {
                $n = 2;
            } elseif ((ord($string[$i]) & 0xF0) == 0xF0) {
                $n = 3;
            } else {
                return false;
            }
            for ($j = 0; $j < $n; $j++) {
                if ((++$i == $length) || ((ord($string[$i]) & 0xC0) != 0x80)) {
                    return false;
                }
            }
        }
        return true;
    }
}
