<?php
function test_port($host, $port) {
    $errno = $errstr = 0;
    $t0 = microtime(true);
    $conn = @fsockopen($host, $port, $errno, $errstr, 10);
    $elapsed = round((microtime(true) - $t0)*1000);
    if ($conn) {
        fclose($conn);
        echo "OK: $host:$port (".$elapsed."ms)\n";
    } else {
        echo "BLOCKED: $host:$port ($errno: $errstr)\n";
    }
}
header('Content-Type: text/plain');
test_port('smtp.gmail.com', 587);
test_port('smtp.gmail.com', 465);
