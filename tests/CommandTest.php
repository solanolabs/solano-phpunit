<?php
class Solano_PHPUnit_Wrapper_Command_Test extends Replace_TestCase
{
    public function testUsage()
    {
        $args = array('', '--help');
        $command = SolanoLabs_PHPUnit_Command::run($args, false);
        $this->assertTrue($command);
    }
}
