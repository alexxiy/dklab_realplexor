--TEST--
dklab_realplexor: cleanup queue if no data is pushed to it

--FILE--
<?php
$REALPLEXOR_CONF = "small_queue_sz.conf";
require dirname(__FILE__) . '/init.php';

send_in("identifier=abc,def", "
    a
");
send_in("identifier=abc,def", "
    bb
");
send_in(null, "stats");
send_in("identifier=abc,def", "
    ccc
");
send_in(null, "stats");


?>
--EXPECTF--
IN <== X-Realplexor: identifier=abc,def
IN <==
IN <== "a"
IN ==> HTTP/1.0 200 OK
IN ==> Content-Type: text/plain
IN ==> Content-Length: 46
IN ==>
IN ==> abc %d
IN ==> def %d
IN <== X-Realplexor: identifier=abc,def
IN <==
IN <== "bb"
IN ==> HTTP/1.0 200 OK
IN ==> Content-Type: text/plain
IN ==> Content-Length: 46
IN ==>
IN ==> abc %d
IN ==> def %d
IN <== stats
IN ==> HTTP/1.0 200 OK
IN ==> Content-Type: text/plain
IN ==> Content-Length: 231
IN ==>
IN ==> [data_to_send]
IN ==> abc => [*: 4b], [*: 3b]
IN ==> def => [*: 4b], [*: 3b]
IN ==>
IN ==> [connected_fhs]
IN ==>
IN ==> [online_timers]
IN ==>
IN ==> [cleanup_timers]
IN ==> abc => assigned
IN ==> def => assigned
IN ==>
IN ==> [pairs_by_fhs]
IN <== X-Realplexor: identifier=abc,def
IN <==
IN <== "ccc"
IN ==> HTTP/1.0 200 OK
IN ==> Content-Type: text/plain
IN ==> Content-Length: 46
IN ==>
IN ==> abc %d
IN ==> def %d
IN <== stats
IN ==> HTTP/1.0 200 OK
IN ==> Content-Type: text/plain
IN ==> Content-Length: 231
IN ==>
IN ==> [data_to_send]
IN ==> abc => [*: 5b], [*: 4b]
IN ==> def => [*: 5b], [*: 4b]
IN ==>
IN ==> [connected_fhs]
IN ==>
IN ==> [online_timers]
IN ==>
IN ==> [cleanup_timers]
IN ==> abc => assigned
IN ==> def => assigned
IN ==>
IN ==> [pairs_by_fhs]
#   [pairs_by_fhs=0 data_to_send=2 connected_fhs=0 online_timers=0 cleanup_timers=2 events=*]