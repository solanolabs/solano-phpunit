<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

/**
 * Generates PHPUnit XML configuration files for Solano-PHPUnit
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_XmlGenerator
{
    /**
     * Generate XML file, replacing node/attribute values with those supplied by $config.
     *
     * @param SolanoLabs_PHPUnit_Configuration $config
     */
    public static function GenerateXml($config)
    {
        $domDoc = new DOMDocument();
        $domDoc->formatOutput = true;
        $domDoc->preserveWhiteSpace = false;
        $domDoc->loadXML($config->domDoc->saveXML());

        // Add prefixes to filter paths
        $filterNodes = $domDoc->getElementsByTagName('filter');
        if (count($filterNodes)) {
            foreach($filterNodes as $filterNode) {
                $whitelistNodes = $filterNode->getElementsByTagName('whitelist');
                if (count($whitelistNodes)) {
                    foreach($whitelistNodes as $whitelistNode) {
                        // whitelist include and eclude directories
                        $directoryNodes = $whitelistNode->getElementsByTagName('directory');
                        if (count($directoryNodes)) {
                            foreach($directoryNodes as $directoryNode) {
                                $directoryNode->nodeValue = dirname($config->domDoc->documentURI) . DIRECTORY_SEPARATOR . $directoryNode->nodeValue;
                            }
                        }
                        // whitelist include and exclude files
                        $fileNodes = $whitelistNode->getElementsByTagName('file');
                        if (count($fileNodes)) {
                            foreach($fileNodes as $fileNode) {
                                $fileNode->nodeValue = dirname($config->domDoc->documentURI) . DIRECTORY_SEPARATOR . $fileNode->textContent;
                            }
                        }
                    }
                }
            }
        }
        
        // Remove <testsuites/> and standalone <testsuite/> nodes
        $nodes = $domDoc->getElementsByTagName('testsuites');
        if (count($nodes)) {
            foreach($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        $nodes = $domDoc->getElementsByTagName('testsuite');
        if (count($nodes)) {
            foreach($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        // Add testsuites node
        $testSuitesNode = $domDoc->createElement('testsuites');
        foreach($config->testFiles as $file => $attributes) {
            $testSuiteNode = $domDoc->createElement('testsuite');
            $testSuiteNode->setAttribute('name', $file);
            $testNode = $domDoc->createElement('file', $file);
            if (is_array($attributes)) {
                foreach ($attributes as $key => $value) {
                    if ($key != 'suffix') {
                        $testNode->setAttribute($key, $value);
                    }
                }
            }
            $testSuiteNode->appendChild($testNode);
            $testSuitesNode->appendChild($testSuiteNode);
        }
        $domDoc->documentElement->appendChild($testSuitesNode);

        if (getenv('TDDIUM')) {
            // Add <listener /> node
            $listenerNode = $domDoc->createElement('listener');
            $listenerNode->setAttribute('class', 'SolanoLabs_PHPUnit_Listener');
            $listenerNode->setAttribute('file', dirname(__FILE__) . DIRECTORY_SEPARATOR . "Listener.php");

            $argumentsNode = $domDoc->createElement('arguments');
            $argumentNode = $domDoc->createElement('string', $config->outputFile);
            $argumentsNode->appendChild($argumentNode);

            if (count($config->excludeFiles)) {
                $argumentNode = $domDoc->createElement('string', implode(',', array_keys($config->excludeFiles)));
                $argumentsNode->appendChild($argumentNode);
            }

            $listenerNode->appendChild($argumentsNode);

            $listeners = $domDoc->getElementsByTagName('listeners');
            if($listeners->length) {
                $listeners->item(0)->appendChild($listenerNode);
            } else {
                $listenersNode = $domDoc->createElement('listeners');
                $listenersNode->appendChild($listenerNode);
                $domDoc->documentElement->appendChild($listenersNode);
            }

            // Set <printer /> path
            $domDoc->documentElement->setAttribute('printerClass', 'SolanoLabs_PHPUnit_Printer');
            if ($domDoc->documentElement->hasAttribute('printerFile')) {
                $domDoc->documentElement->setAttribute('printerFile', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Printer.php');
            }
        }

        // Set <log target=""> values to be specific
        if (count($config->logTargets)) {
            $xpath = new DOMXPath($domDoc);
            foreach($config->logTargets as $type => $target) {
                $pathinfo = pathinfo($target);
                $nodes = $xpath->query("//logging/log[@type='" . $type . "']");
                if ($nodes->length) {
                    foreach ($nodes as $node) {
                        if ($tid = getenv('TDDIUM_TEST_EXEC_ID')) {
                            $newTarget = $pathinfo['dirname'] . DIRECTORY_SEPARATOR .
                            $pathinfo['basename'] . '-' . $tid;
                            if (!empty($pathinfo['extension'])) {
                                $newTarget .= '.' . $pathinfo['extension'];
                            }
                        } else {
                            $newTarget = $target;
                        }
                        $node->setAttribute('target', $newTarget);
                    }
                }
            }
        }

        // Set bootstrap path
        if ($config->bootstrap) {
            $domDoc->documentElement->setAttribute('bootstrap', $config->bootstrap);
        }

        // Set includePath path
        foreach ($config->includePaths as $includePath) {
            $nodes = $domDoc->getElementsByTagName('includePath');
            if ($nodes->length > 0) {
                $nodes->item(0)->nodeValue = $includePath;
            } else {
                $nodes = $domDoc->getElementsByTagName('php');
                if ($nodes->length > 0) {
                    $node = $domDoc->createElement('includePath', $includePath);
                    $nodes->item(0)->appendChild($node);
                } else {
                    $phpNode = $domDoc->createElement('php');
                    $node = $domDoc->createElement('includePath', $includePath);
                    $phpNode->appendChild($node);
                    $domDoc->documentElement->appendChild($phpNode);
                }
            }
        }

        // Return XML
        return $domDoc->saveXML();
    }
}
