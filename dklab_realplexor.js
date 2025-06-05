// Create new Dklab_Realplexor object.
class Dklab_Realplexor {
    constructor(fullUrl, namespace) {
        if (!/^\w+:\/\/([^/]+)/.test(fullUrl)) {
            throw new Error(`Dklab_Realplexor constructor argument must be fully-qualified URL, ${fullUrl} given.`);
        }

        // Assign initial properties.
        this._map = {};
        this._loader = new Dklab_Realplexor_Loader(fullUrl);
        this._namespace = namespace;
        this._login = null;
        this._executeTimer = null;
    }

    // Set active login.
    logon(login) {
        this._login = login;
    }

    // Set the position from which we need to listen a specified ID.
    setCursor(id, cursor) {
        if (!this._map[id]) {
            this._map[id] = { cursor: null, callbacks: [] };
        }
        this._map[id].cursor = cursor;
        return this;
    }

    // Subscribe a new callback to specified ID.
    // To apply changes and reconnect to the server, call execute()
    // after a sequence of subscribe() calls.
    subscribe(id, callback) {
        if (!this._map[id]) {
            this._map[id] = { cursor: null, callbacks: [] };
        }
        const chain = this._map[id].callbacks;
        if (!chain.includes(callback)) {
            chain.push(callback);
        }
        return this;
    }

    // Unsubscribe a callback from the specified ID.
    // You do not need to reconnect to the server (see execute())
    // to stop calling of this callback.
    unsubscribe(id, callback) {
        if (!this._map[id]) return this;

        const chain = this._map[id].callbacks;
        if (callback == null) {
            this._map[id].callbacks = [];
        } else {
            const index = chain.indexOf(callback);
            if (index !== -1) {
                chain.splice(index, 1);
            }
        }
        return this;
    }

    // Reconnect to the server and listen for all specified IDs.
    // You should call this method after a number of calls to subscribe().
    execute() {
        if (this._executeTimer) {
            clearTimeout(this._executeTimer);
            this._executeTimer = null;
        }

        const namespace = this._namespace ?? '';
        const login = this._login ? `${this._login}_` : '';

        // Realplexor loader is ready, run it.
        this._loader.execute(
            this._map,
            `${login}${namespace}`
        );
    }
}

class Dklab_Realplexor_Loader {
    constructor(fullUrl) {
        let urlObj = new URL(fullUrl);
        this._host = urlObj.origin;

        this._uri = urlObj.pathname.replace(/\/{2,}/g, '/');
        if (!this._uri.startsWith('/')) this._uri = '/' + this._uri;
        if (!this._uri.endsWith('/')) this._uri += '/';
        this._uri = this._uri + urlObj.search + urlObj.hash;
    }
    // Maximum bounce count.
    static JS_MAX_BOUNCES = $JS_MAX_BOUNCES;
    // Reconnect delay.
    static JS_WAIT_RECONNECT_DELAY = $JS_WAIT_RECONNECT_DELAY;
    // Realplexor normal WAIT timeout (seconds).
    static JS_WAIT_TIMEOUT = $WAIT_TIMEOUT;
    static JS_IDENTIFIER = '$IDENTIFIER';
    // Is debug mode turned on?
    static JS_DEBUG = $JS_DEBUG;

    // Count of sequential bounces.
    _bounceCount = 0;
    // Namespace to use.
    _namespace = null;
    // Previous request time.
    _prevReqTime = null;
    // Previously used xmlhttp.
    _lastXmlhttp = null;
    // Pairs of [cursor, [ callback1, callback2, ... ]] for each ID.
    // Callbacks will be called on data ready.
    _ids = {};

    // Return the document.
    _doc() {
        return document;
    }

    // Create a new XMLHttpRequest object.
    _getXmlHttp() {
        return window.XMLHttpRequest ? new XMLHttpRequest() : null;
    }

    // Log a debug message.
    _log(msg, func = "log") {
        if (!this.constructor.JS_DEBUG || !window.console) return;

        const match = ("" + msg).match(/^([^\r\n]+)\r?\n([\s\S]*)$/);
        if (match) {
            const [_, first, second] = match;
            if (console.groupCollapsed) {
                console.groupCollapsed(first);
                console[func](second + "\n");
                console.groupEnd();
            } else {
                console.info(first);
                console[func](second + "\n");
            }
        } else {
            console[func](msg);
        }
    }

    // Log an error message.
    _error(prefix, msg) {
        this._log(prefix, "error");
        this._log(msg);
    }

    // Process a single part.
    _processPart(part) {
        let errors = 0;

        // Extract IDs
        const pairs = part.ids;
        if (!pairs) throw 'Cannot find "ids" property within the response part';

        // Extract data.
        const data = part.data;
        if (data === undefined) throw 'Cannot find "data" property within the response part';

        // Process parts one after another.
        for (const idKey in pairs) {
            if (!pairs.hasOwnProperty(idKey)) continue;

            let id = idKey;
            const cursor = pairs[id];

            // Strip namespace prefix.
            if (this._namespace && id.startsWith(this._namespace)) {
                id = id.slice(this._namespace.length);
            }

            if (!this._ids[id]) {
                this._ids[id] = { cursor: null, callbacks: [] };
            }

            const item = this._ids[id];
            item.cursor = cursor;

            for (let j = 0; j < item.callbacks.length; j++) {
                try {
                    item.callbacks[j](data, id, item.cursor);
                } catch (e) {
                    this._error(`Error executing callback #${j} for ID ${id}: ${e}`, `Data:\n${data}`);
                    errors++;
                }
            }
        }

        return errors;
    }

    // Parse multipart response text and return list of parts.
    _parseResponseTextIntoParts(text) {
        if (!/^\s*\[[\s\S]*\]\s*$/i.test(text)) {
            throw "Response is not a complete JSON";
        }
        return JSON.parse(text);
    }

    // Process the response data.
    _processResponseText(text) {
        // Safari bug: responseText sometimes contain headers+body, not only body!
        // So cat before the first "[".
        text = text.replace(/^[\s\S]*?(?=\[)/g, '');
        this._log("Received response:\n" + text);
        // Parse.
        const parts = this._parseResponseTextIntoParts(text);
        // Process.
        let errors = 0;
        for (const part of parts) {
            errors += this._processPart(part);
        }
        return errors;
    }

    // Called on response arrival.
    _onresponse(text) {
        let nextQueryDelay = Math.round(this.constructor.JS_WAIT_RECONNECT_DELAY * 1000);

        // Work-around to handle page unload. In case of this handler is executed after
        // the page is partly unloaded, do nothing, just return.
        try {
            if (!this._doc().body) return;
        } catch {
            return;
        }

        // Run the query.
        try {
            // Empty response typically means that there's no error, but
            // server WAIT timeout expired, and we need to reconnect.
            // But we exit via exception to check: is it a bounce or not.
            if (/^\s*$/.test(text)) {
                text = "";
                throw "Empty response";
            }
            this._processResponseText(text);
            this._bounceCount = 0;
        } catch (e) {
            const t = Date.now();
            if (t - this._prevReqTime < this.constructor.JS_WAIT_TIMEOUT / 2 * 1000) {
                // This is an unexpected disconnect (bounce).
                this._bounceCount++;
                this._log(`Bounce detected (bounceCount = ${this._bounceCount})`);
            } else {
                this._log("Disconnect detected");
            }

            if (text !== "") {
                this._error(e.message || e, "Response:\n" + text);
            }
            this._prevReqTime = t;
        }

        // Calculate next query delay.
        if (this._bounceCount > this.constructor.JS_MAX_BOUNCES) {
            // Progressive delay.
            const progressive = this._bounceCount - this.constructor.JS_MAX_BOUNCES + 2;
            nextQueryDelay = 1000 + 500 * progressive * progressive;
            nextQueryDelay = Math.min(nextQueryDelay, 60000);
        }

        // Schedule next query, but only if there was no other request
        // performed (e.g. via execute() call) within the callback.
        if (!this._lastXmlhttp) {
            this._log(`Next query in ${nextQueryDelay} ms`);
            setTimeout(() => this._loopFunc(), nextQueryDelay);
        }
    }

    // Make value for identifier=... argument.
    _makeRequestId() {
        const parts = [];
        for (const id in this._ids) {
            if (!this._ids.hasOwnProperty(id)) continue;
            const v = this._ids[id];
            if (!v.callbacks.length) continue;
            parts.push(
                (v.cursor !== null ? v.cursor + ":" : "") +
                (this._namespace ?? "") + id
            );
        }
        return parts.join(",");
    }

    // Loop function.
    _loopFunc() {
        const requestId = this._makeRequestId();
        if (!requestId.length) return;

        const idParam = `${this.constructor.JS_IDENTIFIER}=${requestId}`;
        let url, postData = null;

        if ((idParam.length + this._uri.length) < 1700) {
            // GET method is only for not too long URLs.
            url = `${this._uri}?${idParam}&ncrnd=${Date.now()}`; // ncrnd is for stupid IE
        } else {
            // For very long IDs list - use POST method (always trail
            // the data with "\n", because else identifier=... will not
            // be recognized).
            url = this._uri;
            postData = idParam + "\n";
        }

        const xmlhttp = this._getXmlHttp();
        if (!xmlhttp) {
            this._error("No XMLHttpRequest found!");
            return;
        }

        xmlhttp.open(postData ? 'POST' : 'GET', this._host + url, true);
        xmlhttp.onreadystatechange = () => {
                                            // abort() called
            if (xmlhttp.readyState !== 4 || !this._lastXmlhttp) return;
            this._lastXmlhttp = null;
            this._onresponse("" + xmlhttp.responseText);
        };
        xmlhttp.send(postData);
        this._prevReqTime = Date.now();
        this._lastXmlhttp = xmlhttp;
    }

    // Run the polling process.
    // Argument structure: { id: { cursor: NNN, callbacks: [ callback1, callback2, ... ] } }
    // Second parameter must accept a function which will be called to
    // call parent's callbacks (it is needed for IE, to not lose
    // exceptions thrown from a different frame).
    execute(callbacks, namespace) {
        window.addEventListener('unload', () => {
            // This is for IE7: it does not abort the connection on unload
            // and reaches the connection limit.
            try {
                if (this._lastXmlhttp) {
                    this._lastXmlhttp.onreadystatechange = () => {};
                    this._lastXmlhttp.abort();
                    this._lastXmlhttp = null;
                }
            } catch (e) {
                // Silently ignore
            }
        });

        if (this._lastXmlhttp) {
            const xhr = this._lastXmlhttp;
            this._lastXmlhttp = null;
            xhr.onreadystatechange = () => {};
            xhr.abort(); // abort() does not make bounce if this._lastXmlhttp is null
        }

        this._namespace = namespace?.length ? namespace : null;
        this._ids = callbacks;
        this._loopFunc();
    }
}
