<?php

// Trigger a fatal error
echo("Setting memory_limit to 1M\n");
@ini_set('memory_limit', '1M');
echo("memory_limit = " . ini_get('memory_limit') . "\n");
$string = "0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF"; // 128 chars
while (1) {
    $string = $string . $string;
}