<?php
/* 
 * PHPUnit versions 6+ changed to using namespaced classes (i.e. 'PHPUnit_Framework_TestCase' to 'PHPUnit\Framework\TestCase')
 * https://github.com/sebastianbergmann/phpunit/wiki/Release-Announcement-for-PHPUnit-6.0.0#backwards-compatibility-issues
 * Use 'class_alias' to allow both PHPUnit versions 6+ and earlier versions to be tested
 * From https://github.com/symfony/symfony/issues/21534#issuecomment-278278352
 */

if (!class_exists('\PHPUnit\Framework\TestCase', true)) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
} elseif (!class_exists('\PHPUnit_Framework_TestCase', true)) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}
