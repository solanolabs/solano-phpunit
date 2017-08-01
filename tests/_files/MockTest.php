<?php
class Solano_PHPUnit_Mock_Test extends Replace_TestCase
{
    public function testMock()
    {
        // For testing pre-test-phase functionality
        $this->assertEquals(2, 2);
    }
}