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

        // Determine all files that should run
        if ($config->minXmlFile) {
            if (!count($config->testFiles)) {
                $config->parseErrors[] = "### Error: No test files specified and no configuration file found.";
            }
        } else {
            // Load file lists from xml file
            SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        }

        // Pre-populate json report when appropriate
        $already_run_files = array();
        if (getenv('TDDIUM') && !empty($config->outputFile)) {
            $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($config->outputFile);

            // test files
            for ($i = count($config->testFiles) - 1; $i >= 0; $i--) {
                $file = SolanoLabs_PHPUnit_Util::shortenFilename($config->testFiles[$i]);//, $config->workingDir);
                if (isset($jsonData['byfile'][$file])) {
                    if (count($jsonData['byfile'][$file])) {
                        // Output for this test file has already been written
                        unset($config->testFiles[$i]);
                        $already_run_files[] = $file;
                    }
                } else {
                    // There is no listing for this test file, so create one
                    $jsonData['byfile'][$file] = array();
                }
            }

            // Excluded files
            for ($i = count($config->excludeFiles) - 1; $i >= 0; $i--) {
                $shortFilename = SolanoLabs_PHPUnit_Util::shortenFilename($config->excludeFiles[$i]);//, $config->workingDir);
                if (isset($jsonData['byfile'][$shortFilename]) && count($jsonData['byfile'][$shortFilename])) {
                    // Output for this excluded file has already been written
                    unset($config->excludeFiles[$i]);
                } else {
                    // There is no listing for this excluded file, so create one
                    $jsonData['byfile'][$shortFilename] = SolanoLabs_PHPUnit_JsonReporter::generateExcludeFileNotice($shortFilename);
                }
            }

            SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($config->outputFile, $jsonData);
        }

        if (count($config->testFiles)) {
            //  note first test file in case it causes a fatal PHP error
            $filesList = array_keys($config->testFiles);
            putenv("SOLANO_LAST_FILE_STARTED=" . SolanoLabs_PHPUnit_Util::shortenFilename($config->testFiles[$filesList[0]], $config->workingDir));
        } else {
            if ($exit) {
                echo("No test files found or all test files have already been reported.\n");
                self::usage();
                exit(0);
            } else {
                return true;
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

        // If this is a replacement/continuation of a failed process, exit with its code
        if ($setExitCode = getenv('SOLANO_PHPUNIT_EXIT_CODE')) {
            if ($exit) {
                exit($setExitCode);
            } else {
                return false;
            }
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

        // Add skip notices if group excludes are in place
        if (getenv('TDDIUM') && !empty($config->outputFile)) {
            $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($config->outputFile);
            foreach($config->testFiles as $testFile) {
                $shortFilename = substr($testFile, 1 + strlen(getcwd()));
                if (empty($jsonData['byfile'][$shortFilename])) {
                    // All tests in file were skipped
                    $jsonData['byfile'][$shortFilename] = array(array(
                        'id' => $shortFilename,
                        'address' => $shortFilename,
                        'status' => 'skip',
                        'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Due to --group, --exclude-group, or --testsuite flags',
                        'stdout' => '',
                        'time' => 0,
                        'traceback' => array()));
                }
            }

            SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($config->outputFile, $jsonData);
        }


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
