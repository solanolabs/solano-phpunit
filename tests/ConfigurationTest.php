<?php
use PHPUnit\Framework\TestCase;

class Solano_PHPUnit_Wrapper_Configuration_Test extends TestCase
{
    public function testCliBootstrap()
    {
        $args = array('', '--bootstrap', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'bootstrap.php');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals($config->bootstrap, dirname(__FILE__) . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'bootstrap.php');
    }

    public function testXmlBootstrap()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'bootstrap_and_include_path.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals($config->bootstrap, dirname(__FILE__) . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'bootstrap.php');
    }

    public function testCliIncludePath()
    {
        $origIncludePath = ini_get('include_path');
        $args = array('', '--include-path', dirname(__FILE__));
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $includePaths = explode(PATH_SEPARATOR, ini_get('include_path'));
        $this->assertTrue(in_array(dirname(__FILE__), $includePaths));
        ini_set('include_path', $origIncludePath);
    }

    public function testXmlIncludePath()
    {
        $origIncludePath = ini_get('include_path');
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'bootstrap_and_include_path.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $includePaths = explode(PATH_SEPARATOR, ini_get('include_path'));
        $this->assertTrue(in_array(dirname(__FILE__), $includePaths));
        ini_set('include_path', $origIncludePath);
    }

    public function testInvalidXml()
    {
        try {
            $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'invalid.xml');
            $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        }
        catch (Exception $e) {
            // PHPUnit versions 6+ use namespaced class names: https://github.com/sebastianbergmann/phpunit/wiki/Release-Announcement-for-PHPUnit-6.0.0#backwards-compatibility-issues
            $class = get_class($e);
            if ($class == 'PHPUnit_Framework_Error_Warning') {
                $class = 'PHPUnit\Framework\Error\Warning';
            }
            $this->assertEquals($class, 'PHPUnit\Framework\Error\Warning');
        }
    }

    public function testNoTests()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'no_tests.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEmpty($config->testFiles);
    }

    public function testLogTargets()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'log_targets.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals($config->logTargets['coverage-html'], dirname(__FILE__) . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'coverage');
        $this->assertEquals($config->logTargets['coverage-clover'], dirname(__FILE__) . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'clover.xml');
        $this->assertEquals($config->logTargets['junit'], dirname(__FILE__) . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'junit.xml');
    }

    public function testCliOutputFile()
    {
        $args = array('', '--tddium-output-file', 'output.json');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals($config->outputFile, SolanoLabs_PHPUnit_Util::truepath('output.json'));
    }

    public function testDefaultOutputFile()
    {
        $args = array('');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        if ($outputFile = getenv('TDDIUM_OUTPUT_FILE')) {
            $this->assertEquals($config->outputFile, SolanoLabs_PHPUnit_Util::truepath($outputFile));
        } else {
            $this->assertEquals($config->outputFile, SolanoLabs_PHPUnit_Util::truepath('tddium_output.json'));
        }
    }

    public function testCliTempDirectory()
    {
        $args = array('', '--temp-dir', dirname(__FILE__));
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals($config->tempDir, dirname(__FILE__));
    }

    public function testIgnoreExclude()
    {
        $args = array('', '--ignore-exclude');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals($config->ignoreExclude, true);
    }
}
