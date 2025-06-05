#@
#@ Dklab Realplexor: Comet server which handles 1000000+ parallel browser connections
#@ Author: Dmitry Koterov, dkLab (C)
#@ License: GPL 2.0
#@
#@ 2025-* Contributor: Alexxiy
#@ GitHub: http://github.com/alexxiy/
#@

Build dklab_realplexor binary
-----------------------------

If dklab_realplexor binary is built with static libraries (see "-static" in Make.sh),
the resulting binary will be portable: it depends on no shared libraries, so you may
copy it to any Linux distribution and use there.

For Ubuntu 12.04, the steps are simple:
# apt-get install gcc libboost1.48 libev4 libev-dev
# bash ./Make.sh

For Ubuntu 20.04
# apt-get install build-essential libboost1.71-all-dev libev4 libev-dev

For RHEL (CentOS, Fedora, etc.):
# dnf install gcc gcc-c++ make boost boost-devel libev libev-devel
# bash ./Make.sh
