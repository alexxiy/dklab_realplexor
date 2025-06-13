#@
#@ Dklab Realplexor: Comet server which handles 1000000+ parallel browser connections
#@ Author: Dmitry Koterov, dkLab (C)
#@ License: GPL 2.0
#@
#@ 2025-* Contributor: Alexxiy
#@ GitHub: http://github.com/alexxiy/
#@

##
## Timers abstraction.
##
package Realplexor::Event::Timer;
use strict;
use EV;

sub create {
    my ($callback) = @_;
    return EV::timer_ns(0, 0, $callback);
}

sub start {
    my ($timer, $timeout) = @_;
    $timer->set($timeout, 0);
    $timer->start();
}

sub remove {
    my ($timer) = @_;
    # Nothing: removed by destructor.
}

return 1;
