--TEST--
dklab_realplexor: data is passed to connected client with multi-ids

--FILE--
<?php
require dirname(__FILE__) . '/init.php';

send_wait("
    identifier=abc,def
");
send_in("identifier=def", "
    aaa
");
recv_wait();

?>
--EXPECTF--
WA <-- identifier=abc,def
IN <== X-Realplexor: identifier=def
IN <==
IN <== "aaa"
IN ==> HTTP/1.0 200 OK
IN ==> Content-Type: text/plain
IN ==> Content-Length: %d
IN ==>
IN ==> def %d
WA --> HTTP/1.1 200 OK
WA --> Connection: close
WA --> Cache-Control: no-store, no-cache, must-revalidate
WA --> Expires: ***
WA --> Content-Type: text/javascript; charset=utf-8
WA -->
WA -->
WA --> [
WA -->   {
WA -->     "ids": { "def": <cursor> },
WA -->     "data": "aaa"
WA -->   }
WA --> ]
WA :: Disconnecting.
#   [pairs_by_fhs=0 data_to_send=1 connected_fhs=0 online_timers=2 cleanup_timers=1 events=*]