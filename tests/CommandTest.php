<?php
class Solano_PHPUnit_Wrapper_Command_Test extends MapTestCase
{
    public function testUsage()
    {
        $args = array('', '--help');
        $command = SolanoLabs_PHPUnit_Command::run($args, false);
        $this->assertTrue($command);

        $args = array('', '-h');
        $command = SolanoLabs_PHPUnit_Command::run($args, false);
        $this->assertTrue($command);
    }
}
