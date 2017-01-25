<?php
class Solano_PHPUnit_Wrapper_XmlGenerator_Test extends PHPUnit_Framework_TestCase
{
    private $domDoc;
    private $xpath;
    private $config;

    public function __construct()
    {
        $args = array('', '--configuration', 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'xml_generator.xml');
        $this->config = SolanoLabs_PHPUnit_Configuration::parseArgs($args);
        SolanoLabs_PHPUnit_TestFileEnumerator::EnumerateTestFiles($this->config);
        $xml = SolanoLabs_PHPUnit_XmlGenerator::GenerateXml($this->config);
        $this->domDoc = new DOMDocument();
        $this->domDoc->loadXML($xml);
        $this->xpath = new DOMXpath($this->domDoc);
    }

    public function testXmlGeneratorFiles()
    {
        $nodes = $this->xpath->query('//testsuites/testsuite/file');
        $this->assertEquals(4, $nodes->length);
        foreach($nodes as $node) {
            $this->assertTrue(file_exists($node->nodeValue));
            $this->assertTrue(in_array($node->nodeValue, array_keys($this->config->testFiles)));
        }
    }
    
    public function testXmlGeneratorFilter()
    {
        $nodes = $this->xpath->query('//filter/whitelist/directory | //filter/whitelist/file | //filter/whitelist/exclude/directory | //filter/whitelist/exclude/file');
        $this->assertEquals(4, $nodes->length);
        foreach($nodes as $node) {
            $this->assertEquals(preg_match("#^" . dirname($this->domDoc->documentURI) . DIRECTORY_SEPARATOR . "(.+)$#", $node->nodeValue), 1);
        }
    }

    public function testXmlGeneratorLogging()
    {
        $nodes = $this->xpath->query('//logging/log[@type="junit"]');
        $this->assertEquals(1, $nodes->length);
        $this->assertTrue($nodes->item(0)->hasAttribute('target'));
        if (getenv('TDDIUM_TEST_EXEC_ID')) {
            $this->assertEquals($nodes->item(0)->getAttribute('target'), $this->config->logTargets['junit'] . '-' . getenv('TDDIUM_TEST_EXEC_ID') . '.xml');
        } else {
            $this->assertEquals($nodes->item(0)->getAttribute('target'), $this->config->logTargets['junit']);
        }
    }

    public function textXmlGeneratorIncludePath()
    {
        $nodes = $this->xpath->query('//php/includePath');
        $this->assertEqual(1, $nodes->length);
        $this->assertTrue(in_array($nodes->item(0)->nodeValue, $config->includePaths));
    }

    public function testXmlGeneratorBootstrap()
    {
        $this->assertTrue($this->domDoc->documentElement->hasAttribute('bootstrap'));
        $this->assertEquals($this->domDoc->documentElement->getAttribute('bootstrap'), $this->config->bootstrap);
    }

    public function testXmlGeneratorPrinter()
    {
        if (getenv('TDDIUM')) {
            $this->assertTrue($this->domDoc->documentElement->hasAttribute('printerClass'));
            $this->assertEquals('SolanoLabs_PHPUnit_Printer', $this->domDoc->documentElement->getAttribute('printerClass'));
        }
    }

    public function testXmlGeneratorListener()
    {
        if (getenv('TDDIUM')) {
            $nodes = $this->xpath->query('//listeners/listener[@class="SolanoLabs_PHPUnit_Listener"]');
            $this->assertEquals(1, $nodes->length);
            $this->assertTrue($nodes->item(0)->hasAttribute('file'));
            $this->assertTrue(file_exists($nodes->item(0)->getAttribute('file')));
            $this->assertEquals($nodes->item(0)->getAttribute('file'), dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Listener.php');

            $nodes = $this->xpath->query('//listeners/listener[@class="SolanoLabs_PHPUnit_Listener"]/arguments/string');
            $this->assertGreaterThanOrEqual(1, $nodes->length);
            $this->assertEquals($nodes->item(0)->nodeValue, $this->config->outputFile);
        }
    }
}
