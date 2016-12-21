<?php
/**
 * @group Configuration
 * @group ConfigurationEnumeratedFiles
 */
class Solano_PHPUnit_Wrapper_ConfigurationExtendedAttributes_Test extends PHPUnit_Framework_TestCase
{
    public function testExtendedAttributes()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'phpunit_extended_attributes.xml');
        $config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($config);
        $this->assertEquals(6, count($config->testFiles));
        foreach($config->testFiles as $file => $attributes) {
          $this->assertEquals("7.0", $attributes['phpVersion']);
          $this->assertEquals(">=", $attributes['phpVersionOperator']);
        }
    }
}
