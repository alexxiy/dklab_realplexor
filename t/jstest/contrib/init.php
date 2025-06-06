<?php
    // change it to yours
    const REALPLEXOR_URL = 'https://rpl.domain.com';
?>

<meta http-equiv="Content-type" content="text/html; charset=UTF-8">

<!-- Include testing suite. -->
<link rel="stylesheet" type="text/css" href="JsTest/JsTest.css?<?php echo time(); ?>"/>
<script type="text/javascript" language="JavaScript" src="JsTest/JsTest.js?<?php echo time(); ?>"></script>

<div style='clear:both; margin-bottom: 13px'>
    <?php $_SERVER['REQUEST_URI'] = preg_replace('/([&?]\d+)+$/', '', $_SERVER['REQUEST_URI']); ?>
    <a href="<?php echo $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?')? '&' : '?') . time(); ?>">Refresh</a> | <a href=".">Index</a>
</div>

<!-- Common code used for all test scripts. -->
<script type="text/javascript" src="<?php echo REALPLEXOR_URL; ?>/?identifier=SCRIPT"></script>
<script>
var realplexor = new Dklab_Realplexor(
    '<?php echo REALPLEXOR_URL; ?>',
    window.NAMESPACE
);
</script>

<script>
var xhr_stub = {};

function form() {
    return parent.document.getElementById('form');
}

function hasCookies() {
    document.cookie = 'testcookie=1';
    return (document.cookie+'').match(/testcookie=1/);
}

function makeResponseText(resp) {
    if (resp == null) {
        return ' ';
    }
    if (typeof(resp) == "string") {
        return resp;
    }
    var text = ' \n';
    text += '[\n';
    for (var i = 0; i < resp.length; i++) {
        text += '  {\n';
        if (resp[i].identifier) {
            var pairs = resp[i].identifier.split(",");
            var hash = {};
            for (var j = 0; j < pairs.length; j++) {
                if (pairs[j].match(/^(.*):(.*)$/)) {
                    pairs[j] = '"' + RegExp.$2 + '": "' + RegExp.$1 + '"';
                } else {
                    pairs[j] = '"' + pairs[j] + '": "1"';;
                }
            }
            text += '    "ids": { ' + pairs.join(",") + ' },\n';
        }
        text += '    "data": ' + resp[i].data + '\n';
        text += '  }' + (i != resp.length - 1? "," : "") + '\n';
    }
    text += ']\n';
    return text;
}

function substituteResponse(resps) {
    var n = 0;
    xhr_stub = function() {
        var xhr = {
            open: function(method, url) {
                try {
                    JsTest.write("Request: " + (method != 'GET'? method + " " : "") + url.replace(/&ncrnd.*/, '') + "\r\n");
                } catch (e) {
                    // caused on window unload
                }
            },
            send: function(data) {
                if (data) {
                    JsTest.write("POST data length: " + data.length + "\n");
                }
                if (n > resps.length - 1) return;
                var resp = resps[n++];
                setTimeout(function() {
                    xhr.readyState = 4;
                    xhr.responseText = makeResponseText(resp);
                    xhr.onreadystatechange();
                }, 10);
            },
            abort: function() {
                xhr.readyState = 4;
                xhr.responseText = "";
                xhr.onreadystatechange();
                n--;
            }
        }
        return xhr;
    }
    if (realplexor._loader) realplexor._loader._getXmlHttp = xhr_stub;
}

function execute(func) {
    if (!realplexor._loader) {
        // Wait for Realplexor object presence.
        setTimeout(function() { execute(func) }, 50);
        return;
    }
    realplexor._loader._getXmlHttp = xhr_stub;
    setTimeout(func, 50);
}
JsTest.initialize();
</script>
