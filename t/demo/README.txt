#@
#@ Dklab Realplexor: Comet server which handles 1000000+ parallel browser connections
#@ Author: Dmitry Koterov, dkLab (C)
#@ License: GPL 2.0
#@
#@ 2025-* Contributor: Alexxiy
#@ GitHub: http://github.com/alexxiy/
#@

For basic usage you just need to do:
- spin up the Realplexor daemon
- on frontend, include JS script using URL of your Realplexor:
  <script type="text/javascript" src="https://rpl.domain.com/?identifier=SCRIPT"></script>
- init the Realplexor obj
  var realplexor = new Dklab_Realplexor(
      "https://rpl.domain.com/",  // URL of engine
      "demo_" // namespace
  );
- subscribe to channel(s):
  realplexor.subscribe(channel_id, callback_function);
- start the listener
  realplexor.execute();

NOTE: Take look on contrib/nginx* files and configure your web server properly
