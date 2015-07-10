<?php
/**
 * @group Configuration
 * @group ConfigurationEnumeratedFiles
 */
class Solano_PHPUnit_Wrapper_ConfigurationEnumeratedFiles_Test extends PHPUnit_Framework_TestCase
{
    public function testEnumerateTestFiles()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(6, count($config->testFiles));

        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'exclude_dir.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(4, count($config->testFiles));
        # Re-enable for parallel testing (enumerated --files will only include excluded files)
        #$this->assertEquals(2, count($config->excludeFiles));
	
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'exclude_file.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(5, count($config->testFiles));
        # Re-enable for parallel testing (enumerated --files will only include excluded files)
        #$this->assertEquals(1, count($config->excludeFiles));

        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'exclude_wildcard.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(5, count($config->testFiles));
        # Re-enable for parallel testing (enumerated --files will only include excluded files)
        #$this->assertEquals(1, count($config->excludeFiles));

        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'exclude_wildcard_file_node.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(5, count($config->testFiles));
        # Re-enable for parallel testing (enumerated --files will only include excluded files)
        #$this->assertEquals(1, count($config->excludeFiles));

        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'files_and_dir.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(4, count($config->testFiles));

        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'no_tests.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(0, count($config->testFiles));

        $args = array('', 
                      '--testsuite', 'testsuite_2', 
                      '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'named_testsuites.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
    }

    public function testEnumerateTestFilesIgnoreExclude()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'exclude_dir.xml',
                      '--ignore-exclude');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(6, count($config->testFiles));
        $this->assertEquals(0, count($config->excludeFiles));

	$args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'exclude_file.xml',
                      '--ignore-exclude');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(6, count($config->testFiles));
        $this->assertEquals(0, count($config->excludeFiles));
    }

    public function testCliEnumerateTestFiles()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit.xml', 
            '--files', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR .
            'mock_tests' . DIRECTORY_SEPARATOR . 't1Test.php');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(1, count($config->testFiles));

        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit.xml', 
            '--files', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR .
            't1Test.php,tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR . 't2Test.php');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
    }

    public function testEnumerateTestsNoConfigFile()
    {
        $wd = getcwd();
        chdir(dirname(__FILE__));
        $args = array('', '--files', '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR . 't1Test.php,' . 
                      '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR . 't2Test.php');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
        chdir($wd);
    }
}
