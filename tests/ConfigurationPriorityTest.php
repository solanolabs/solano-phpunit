<?php
/**
 * @group Configuration
 */
class Solano_PHPUnit_Wrapper_ConfigurationPriority_Test extends PHPUnit_Framework_TestCase
{
    // Test that 'priority' attributes in supplied phpunit.xml file result in proper ordering of tests
    public function testXmlDefinedPriority()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit_priority.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
        $this->assertEquals(array_keys($config->testFiles), array(SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php'), SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php')));
        $this->assertEquals($config->testFiles[SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php')]['priority'], "0");
        $this->assertEquals($config->testFiles[SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php')]['priority'], "1");
    }

    // Test that priorities supplied in --priority-file result in proper ordering of tests
    public function testFileDefinedPriority()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit_priority_set_in_separate_file.xml', '--priority-file', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit_priority_separate_file.txt');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(2, count($config->testFiles));
        $this->assertEquals(array_keys($config->testFiles), array(SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php'), SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php')));
        $this->assertEquals($config->testFiles[SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t2Test.php')]['priority'], "1");
        $this->assertEquals($config->testFiles[SolanoLabs_PHPUnit_Util::truepath('tests/_files/mock_tests/t1Test.php')]['priority'], "2");
    }
}
