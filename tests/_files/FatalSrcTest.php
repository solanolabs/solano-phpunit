<?php
class Solano_PHPUnit_Fatal_Src_Test extends Replace_TestCase
{
    public function testSrcFatalError()
    {
        SolanoLabs_PHPUnit_Trigger_Fatal_Error::triggerFatalError();
        $this->assertEquals(2, 2);
    }
}