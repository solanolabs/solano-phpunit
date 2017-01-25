<?php
/**
 * @group Configuration
 */
class Solano_PHPUnit_Wrapper_ConfigurationAlpha_Test extends PHPUnit_Framework_TestCase
{
    // Test that --alpha sorts tests in alphabetical order
    public function testAlphaOrder()
    {
        $args = array('', '--alpha', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit_alpha.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals(1, $config->alphaOrder);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
        $this->assertEquals(array_keys($config->testFiles), array(SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php'), SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php')));
    }

    // Test that --rev-alpha sorts tests in alphabetical order
    public function testReverseAlphaOrder()
    {
        $args = array('', '--rev-alpha', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit_alpha.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals(-1, $config->alphaOrder);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
        $this->assertEquals(array_keys($config->testFiles), array(SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php'), SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php')));
    }

    // Test that --alpha sorts tests in alphabetical order when specified by --files
    public function testAlphaOrderCliFiles()
    {
        $args = array('', '--alpha', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit.xml', 
            '--files', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR .
            't2Test.php,tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR . 't1Test.php');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals(1, $config->alphaOrder);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
        $this->assertEquals(array_keys($config->testFiles), array(SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php'), SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php')));
    }

    // Test that --rev-alpha sorts tests in reverse alphabetical order when specified by --files
    public function testReverseAlphaOrderCliFiles()
    {
        $args = array('', '--rev-alpha', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit.xml', 
            '--files', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR .
            't1Test.php,tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'mock_tests' . DIRECTORY_SEPARATOR . 't2Test.php');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        $this->assertEquals(-1, $config->alphaOrder);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
        $this->assertEquals(array_keys($config->testFiles), array(SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php'), SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php')));
    }
}
