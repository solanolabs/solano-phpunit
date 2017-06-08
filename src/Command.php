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
        if (getenv('TDDIUM') && !empty($config->outputFile)) {
            $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($config->outputFile);

            // test files
            foreach (array_keys($config->testFiles) as $file) {
                $shortFilename = SolanoLabs_PHPUnit_Util::shortenFilename($file);
                if (isset($jsonData['byfile'][$shortFilename])) {
                    if (count($jsonData['byfile'][$shortFilename])) {
                        // Output for this test file has already been written
                        unset($config->testFiles[$file]);
                        echo("NOTICE: File already run: $shortFilename\n");
                    }
                } else {
                    // There is no listing for this test file, so create one
                    $jsonData['byfile'][$shortFilename] = array();
                }
            }

            // Excluded files
            foreach (array_keys($config->excludeFiles) as $file) {
                $file = SolanoLabs_PHPUnit_Util::shortenFilename($file);
                if (isset($jsonData['byfile'][$file]) && count($jsonData['byfile'][$file])) {
                    // Output for this excluded file has already been written
                    unset($config->excludeFiles[$file]);
                } else {
                    // There is no listing for this excluded file, so create one
                    $jsonData['byfile'][$file] = SolanoLabs_PHPUnit_JsonReporter::generateExcludeFileNotice($file);
                }
            }

            // Cli specified files that are not in test or excluded files
            foreach (array_keys($config->cliTestFiles) as $file) {
                $shortFilename = SolanoLabs_PHPUnit_Util::shortenFilename($file);
                if (!array_key_exists($file, $config->testFiles) && !array_key_exists($file, $config->excludeFiles)) {
                    if (empty($jsonData['byfile'][$shortFilename])) {
                        $jsonData['byfile'][$shortFilename] = array(array(
                        'id' => $shortFilename,
                            'address' => $shortFilename,
                            'status' => 'skip',
                            'stderr' => 'Skipped Test File: ' . $shortFilename . "\nPHPUnit did not record running CLI specified test file\nCheck --[exclude-]group or --testsuite flags, php version, etc.",
                            'stdout' => '',
                            'time' => 0,
                            'traceback' => array()));
                    }
                }
            }

            SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($config->outputFile, $jsonData);
        }

        if (count($config->testFiles)) {
            // Set env var for determining state in case of fatal PHP error
            $filesList = array_keys($config->testFiles);
            putenv('SOLANO_PHPUNIT_STATE=init;' . SolanoLabs_PHPUnit_Util::shortenFilename($filesList[0], $config->workingDir));
        } else {
            // If this is a replacement/continuation of a failed process, exit with its code
            if ($setExitCode = getenv('SOLANO_PHPUNIT_EXIT_CODE')) {
                if ($exit) {
                    exit($setExitCode);
                } else {
                    return false;
                }
            } elseif ($exit) {
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
	        foreach ($config->testFiles as $file => $attributes) {
                $shortFilename = substr($file, strlen($config->workingDir) + 1);
                echo("\nRunning tests in: $shortFilename\n");
                $splitConfig = $config;
                $splitConfig->testFiles = array($file => $attributes);
                $splitExitCode = self::runTests($splitConfig);
                if ($splitExitCode != 0) {
                    $exitCode = $splitExitCode;
                }
            }
            foreach (array_keys($config->excludeFiles) as $excludeFile) {
                $shortFilename = substr($excludeFile, strlen($config->workingDir) + 1);
                $skipMessages[] = "File in XML <exclude/>: $shortFilename";
            }
        } else {
            foreach (array_keys($config->excludeFiles) as $excludeFile) {
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
        if ($config->configDebug) {
          $tempFileXML = $tempFile . ".xml";
          # PHP versions prior to 5.5 can only use variables as parameters for empty()
          # http://php.net/manual/en/function.empty.php#refsect1-function.empty-parameters
          if ($test_exec_id = getenv('TDDIUM_TEST_EXEC_ID') && !empty($test_exec_id)) {
            $tempFileXML = dirname($tempFile) . DIRECTORY_SEPARATOR . 'phpunit-' . trim($test_exex_id) . '.xml';
          }
          rename($tempFile, $tempFileXML);
          $tempFile = $tempFileXML;
        }
        file_put_contents($tempFile, $xml);

        // Run PHPUnit
        $config->args[0] = 'vendor/phpunit/phpunit/phpunit'; // Just a placeholder
        $config->args[] = "--configuration";
        $config->args[] = $tempFile;
        $phpUnit = new PHPUnit_TextUI_Command();
        $exitCode = $phpUnit->run($config->args, false);

        // Add skip notices if group/testsuite filters are in use
        if (getenv('TDDIUM') && !empty($config->outputFile)) {
            $jsonData = SolanoLabs_PHPUnit_JsonReporter::readOutputFile($config->outputFile);
            $allTestFiles = array_merge($config->testFiles, $config->cliTestFiles);
            foreach(array_keys($allTestFiles) as $testFile) {
                $shortFilename = substr($testFile, 1 + strlen(getcwd()));
                if (empty($jsonData['byfile'][$shortFilename])) {
                    // All tests in file were skipped
                    $jsonData['byfile'][$shortFilename] = array(array(
                        'id' => $shortFilename,
                        'address' => $shortFilename,
                        'status' => 'skip',
                        'stderr' => 'Skipped Test File: ' . $shortFilename . "\nPHPUnit did not record running\nCheck --[exclude-]group or --testsuite flags, php version, etc.",
                        'stdout' => '',
                        'time' => 0,
                        'traceback' => array()));
                }
            }

            SolanoLabs_PHPUnit_JsonReporter::writeJsonToFile($config->outputFile, $jsonData);
        }

        // Save generated configuration XML file?
        if ($config->configDebug) {
            echo("# XML phpunit configuration file: " . $tempFile . "\n");
        } else {
            unlink($tempFile);
        }

        return $exitCode;
    }

    /**
     * Usage/help
     */
    private static function usage() 
    {
        echo("Usage: " . basename($_SERVER['argv'][0]) . " [options]\n");
        echo("Options:\n");
        echo("   --configuration <file>          Specify a non-default phpunit.xml file\n");
        echo("   --temp-dir <dir>                Specify a directory for temporary files\n");
        echo("   --files <file_list>             Comma separated list of files. If not specified, all tests defined in configuration file will be run.\n");
        echo("   --tddium-output-file <file>     Can also be set with \$TDDIUM_OUTPUT_FILE environment variable\n");
        echo("   --ignore-exclude                Ignore <exclude/> child nodes of <testsuite/>.\n");
        echo("   --split                         Run tests one test file per process.\n");
        echo("   --config-debug                  XML configuration passed to phpunit will not be deleted.\n");
        echo("   --[rev-]alpha                   Run test files in alphabetical (or reverse) order.\n");
        echo("   --priority-file                 Set priority of tests from separate file.\n");
        echo("                                   See https://github.com/solano/solano-phpunit/tree/master/tests/_files/phpunit_priority_separate_file.txt\n");
        echo("   --rerun-fatal-max-count <count> Rerun test files that triggered fatal PHP errors up to <count> times.\n");
        echo("                                   By default a fatal PHP error will cause a test file to be marked an error.\n");
        echo("   -h|--help                       Prints this usage information.\n");
        echo(" * Any other supplied options will be passed on to phpunit\n");
    }
}
