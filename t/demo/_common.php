<?php declare(strict_types=1);

require_once '../../api/php/Dklab/Realplexor.php';
$mpl = new Dklab_Realplexor('127.0.0.1', 10010, 'demo_');

// on which subdomain.domain.com is Realplexor listening
$subdomain = 'rpl.';
