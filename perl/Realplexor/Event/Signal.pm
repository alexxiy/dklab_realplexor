#@
#@ Dklab Realplexor: Comet server which handles 1000000+ parallel browser connections
#@ Author: Dmitry Koterov, dkLab (C)
#@ License: GPL 2.0
#@
#@ 2025-* Contributor: Alexxiy
#@ GitHub: http://github.com/alexxiy/
#@

##
## Server abstraction.
##
## Object of this class listens for incoming connection, accepts
## it and creates corresponding Realplexor::Event::Connection object.
##
package Realplexor::Event::Signal;
use strict;
use EV;

my @signals = ();

# Static function.
# Assigns signal handler.
sub create {
    my ($type, $sub) = @_;
    my $signal = EV::signal($type, $sub);
    push @signals, $signal; # save ref
}

return 1;
