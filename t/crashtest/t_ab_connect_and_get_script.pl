#!/usr/bin/perl -w
use lib '../../perl';
use Realplexor::Tools;
Realplexor::Tools::rerun_unlimited();

system('ab -c 500 -n 10000 "http://127.0.0.1:8088/?identifier=SCRIPT"');
