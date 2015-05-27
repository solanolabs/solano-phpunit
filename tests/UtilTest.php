<?php
class Solano_PHPUnit_Wrapper_Util_Test extends PHPUnit_Framework_TestCase
{
    public function testTruePath()
    {
        $testPath = SolanoLabs_PHPUnit_Util::truepath(basename(__FILE__), dirname(__FILE__));
        $this->assertEquals(realpath(__FILE__), $testPath);

        $testPath = SolanoLabs_PHPUnit_Util::truepath(basename(__FILE__), dirname(__FILE__) . DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR);
        $this->assertEquals(realpath(__FILE__), $testPath);

        $testPath = SolanoLabs_PHPUnit_Util::truepath(basename(__FILE__), dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . basename(dirname(__FILE__)));
        $this->assertEquals(realpath(__FILE__), $testPath);

        $testPath = SolanoLabs_PHPUnit_Util::truepath(dirname(__FILE__));
        $this->assertEquals(dirname(__FILE__), $testPath);

        $testPath = SolanoLabs_PHPUnit_Util::truepath(sys_get_temp_dir());
        $this->assertEquals(sys_get_temp_dir(), $testPath);
    }

    public function testIsRootRelativePath()
    {
        $this->assertTrue(SolanoLabs_PHPUnit_Util::isRootRelativePath(__FILE__));
        $this->assertFalse(SolanoLabs_PHPUnit_Util::isRootRelativePath(basename(__FILE__)));
    }
}