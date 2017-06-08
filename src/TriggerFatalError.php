<?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

/**
 * Trigger a fatal error in phpunit, so solano-phpunit can attempt to rerun
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_Trigger_Fatal_Error
{
    public static function triggerFatalError()
    {
        // Trigger a fatal error
        echo("Setting memory_limit to 1M\n");
        @ini_set('memory_limit', '1M');
        echo("memory_limit = " . ini_get('memory_limit') . "\n");
        $string = "0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF"; // 128 chars
        while (1) {
            $string = $string . $string;
        }
    }
}