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
class SolanoLabs_PHPUnit_Command
{
    /**
     * Starts Solano-PHPUnit-Wrapper
     *
     * @param  boolean              $exit
     * @return boolean              (true = no errors, false = errors)
     */
    public static function main($exit = true) 
    {
        return self::run($_SERVER['argv'], $exit);
    }

    /**
     * Runs Solano-PHPUnit-Wrapper
     *
     * @param  array                $args
     * @param  boolean              $exit
     * @return boolean              (true = no errors, false = errors)
     */
    public static function run(array $args, $exit = true)
    {
        // If -h|--help are in the args, print usage.
        if (in_array('--help', $args) || in_array('-h', $args)) {
            self::usage();
            if ($exit) {
                exit(0);
            } else {
                return true;
            }
        }

        // Load configuration and ensure no errors
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        if (isset($config->parseErrors) && count($config->parseErrors)) {
            if ($exit) {
                echo(implode("\n", $config->parseErrors) . "\n");
                self::usage();
                exit(1);
            } else {
                return false;
            }
        }

        // Ensure there are files to test
        if (!count($config->testFiles)) {
            if ($exit) {
                echo("No test files found.\n");
                self::usage();
                exit(2);
            } else {
                return false;
            }
        }

        $skipMessages = array();

        // Should all of the tests be run at once or split?
        if ($config->splitTests) {
            $exitCode = 0;
	        foreach ($config->testFiles as $testFile) {
                $shortFilename = substr($testFile, strlen($config->workingDir) + 1);
                echo("\nRunning tests in: $shortFilename\n");
                $splitConfig = $config;
                $splitConfig->testFiles = array($testFile);
                $splitExitCode = self::runTests($splitConfig);
                if ($splitExitCode != 0) {
                    $exitCode = $splitExitCode;
                }
            }
            foreach ($config->excludeFiles as $excludeFile) {
                $shortFilename = substr($excludeFile, strlen($config->workingDir) + 1);
                $skipMessages[] = "File in XML <exclude/>: $shortFilename";
            }
        } else {
            foreach ($config->excludeFiles as $excludeFile) {
                $shortFilename = substr($excludeFile, strlen($config->workingDir) + 1);
                $skipMessages[] = "File in XML <exclude/>: $shortFilename";
            }
            $exitCode = self::runTests($config);
        }

        if (count($skipMessages)) {
            echo("\n### Skipped Files ###\n" . 
                 implode("\n", $skipMessages) . 
                 "\n#####################\n");
        }

        if ($exit) {
            exit($exitCode);
        } elseif ($exitCode) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Run tests
     */
    private static function runTests($config)
    {
        // Write temporary XML file
        $xml = SolanoLabs_PHPUnit_XmlGenerator::GenerateXml($config);
        $tempFile = tempnam($config->tempDir, 'SLPHPU');
        file_put_contents($tempFile, $xml);

        // Run PHPUnit
        $config->args[0] = 'vendor/phpunit/phpunit/phpunit'; // Just a placeholder
        $config->args[] = "--configuration";
        $config->args[] = $tempFile;
        $phpUnit = new PHPUnit_TextUI_Command();
        $exitCode = $phpUnit->run($config->args, false);

        // Delete temporary XML file
        unlink($tempFile);

        return $exitCode;
    }

    /**
     * Usage/help
     */
    private static function usage() 
    {
        echo("Usage: " . basename($_SERVER['argv'][0]) . " [options]\n");
        echo("Options:\n");
        echo("   --configuration <file>       Specify a non-default phpunit.xml file\n");
        echo("   --temp-dir <dir>             Specify a directory for temporary files\n");
        echo("   --files <file_list>          Comma separated list of files. If not specified, all tests defined in configuration file will be run.\n");
        echo("   --tddium-output-file <file>  Can also be set with \$TDDIUM_OUTPUT_FILE environment variable\n");
        echo("   --ignore-exclude             Ignore <exclude/> child nodes of <testsuite/>.\n");
        echo("   --split                      Run tests one test file per process.\n");
        echo("   -h|--help                    Prints this usage information.\n");
        echo(" * Any other supplied options will be passed on to phpunit\n");
    }
}
