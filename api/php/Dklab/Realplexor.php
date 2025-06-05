<?php declare(strict_types=1);
/**
 * Dklab_Realplexor PHP API.
 *
 * @version 2.0
 */
class Dklab_Realplexor
{
    private int $timeout = 5;
    private string $host;
    private int $port;
    private string $identifier;
    private string $login;
    private string $password;
    private string $namespace;

    /**
     * Create new Realplexor API instance.
     *
     * @param string $host        Host of IN line.
     * @param integer $port       Port of IN line (if 443, SSL is used).
     * @param string $namespace   Namespace to use.
     * @param string $identifier  Use this "identifier" marker instead of the default one.
     */
    public function __construct(string $host, int $port, string $namespace = '', string $identifier = 'identifier')
    {
        $this->host = $host;
        $this->port = $port;
        $this->namespace = $namespace;
        $this->identifier = $identifier;
    }

    /**
     * Set login and password to access Realplexor (if the server needs it).
     * This method does not check credentials correctness.
     *
     * @param string $login
     * @param string $password
     * @return void
     */
    public function logon(string $login, string $password) : void
    {
        $this->login = $login;
        $this->password = $password;
        // All keys must always be login-prefixed!
        $this->namespace = $this->login . '_' . $this->namespace;
    }

    /**
     * Send data to realplexor.
     * @throws Dklab_Realplexor_Exception in case of error.
     *
     * @param array $idsAndCursors    Target IDs in form of: array(id1 => cursor1, id2 => cursor2, ...)
     *                               of array(id1, id2, id3, ...).
     * @param mixed $data            Data to be sent (any format, e.g. nested arrays are OK).
     * @param array $showOnlyForIds  Send this message to only those who also listen any of these IDs.
     *                               This parameter may be used to limit the visibility to a closed
     *                               number of clients: give each client a unique ID and enumerate
     *                               client IDs in $showOnlyForIds to not send messages to others.
     * @return array                 If no cursor(s) provided, newly generated cursor(s) is(are) returned
     *                               in form of: array(id1 => cursor1, id2 => cursor2, ...)
     */
    public function send(array $idsAndCursors, mixed $data, array $showOnlyForIds = []) : array
    {
        try {
            $data = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            throw new Dklab_Realplexor_Exception('Wrong input data is given');
        }

        $pairs = [];
        foreach ($idsAndCursors as $id => $cursor) {
            if (is_int($id)) {
                $id = $cursor; // this is NOT cursor, but ID!
                $cursor = null;
            }
            if (!preg_match('/^\w+$/', $id)) {
                throw new Dklab_Realplexor_Exception('Identifier must be alphanumeric, "' . $id . '" given');
            }
            $id = $this->namespace . $id;
            if ($cursor !== null) {
                if (!is_int($cursor)) {
                    throw new Dklab_Realplexor_Exception('Cursor must be integer, "' . $cursor . '" given');
                }
                $pairs[] = $cursor . ':' . $id;
            } else {
                $pairs[] = $id;
            }
        }
        foreach ($showOnlyForIds as $id) {
            $pairs[] = '*' . $this->namespace . $id;
        }

        $resp = $this->internalSend(implode(',', $pairs), $data);

        // Parse the result and trim namespace.
        $result = [];
        foreach (explode("\n", $resp) as $line) {
            @[$id, $cursor] = explode(' ', $line);
            if ($id === '') {
                continue;
            }
            if ($this->namespace !== '' && str_starts_with($id, $this->namespace)) {
                $id = substr($id, strlen($this->namespace));
            }
            $result[$id] = (int)$cursor;
        }
        return $result;
    }

    /**
     * Return list of online IDs (keys) and number of online browsers
     * for each ID. (Now "online" means "connected just now", it is
     * very approximate; more precision is in TODO.)
     *
     * @throws Dklab_Realplexor_Exception in case of error.
     *
     * @param array $idPrefixes   If set, only online IDs with these prefixes are returned.
     * @return array              List of matched online IDs (keys) and online counters (values).
     */
    public function cmdOnlineWithCounters(array $idPrefixes = []) : array
    {
        // Add namespace.
        if ($this->namespace !== '') {
            if ($idPrefixes === []) {
                $idPrefixes = ['']; // if no prefix passed, we still need namespace prefix
            }
            foreach ($idPrefixes as $i => $idp) {
                $idPrefixes[$i] = $this->namespace . $idp;
            }
        }
        // Send command.
        $resp = $this->sendCmd('online' . ($idPrefixes !== [] ? ' ' . implode(' ', $idPrefixes) : ''));
        if (trim($resp) === '') {
            return [];
        }
        // Parse the result and trim namespace.
        $result = [];
        foreach (explode("\n", $resp) as $line) {
            @[$id, $counter] = explode(' ', $line);
            if ($id === '') {
                continue;
            }
            if ($this->namespace !== '' && str_starts_with($id, $this->namespace)) {
                $id = substr($id, strlen($this->namespace));
            }
            $result[$id] = (int)$counter;
        }
        return $result;
    }

    /**
     * Return list of online IDs.
     *
     * @throws Dklab_Realplexor_Exception in case of error.
     *
     * @param array $idPrefixes   If set, only online IDs with these prefixes are returned.
     * @return array              List of matched online IDs.
     */
    public function cmdOnline(array $idPrefixes = []) : array
    {
        return array_keys($this->cmdOnlineWithCounters($idPrefixes));
    }

    /**
     * Return all Realplexor events (e.g. ID offline/offline changes)
     * happened after $fromPos cursor.
     * @throws Dklab_Realplexor_Exception in case of error.
     *
     * @param integer $fromPos         Start watching from this cursor.
     * @param array $idPrefixes        Watch only changes of IDs with these prefixes.
     * @return array                   List of array("event" => ..., "cursor" => ..., "id" => ...).
     */
    public function cmdWatch(int $fromPos, array $idPrefixes = []) : array
    {
        if ($fromPos < 0) {
            throw new Dklab_Realplexor_Exception('Position value must be positive integer, "' . $fromPos .  '" given');
        }
        // Add namespaces.
        if ($this->namespace !== '') {
            if ($idPrefixes === []) {
                $idPrefixes = ['']; // if no prefix passed, we still need namespace prefix
            }
            foreach ($idPrefixes as $i => $idp) {
                $idPrefixes[$i] = $this->namespace . $idp;
            }
        }
        // Execute.
        $resp = $this->sendCmd('watch ' . $fromPos . ($idPrefixes !== [] ? ' ' . implode(' ', $idPrefixes) : ''));
        $resp = trim($resp);
        if ($resp === '') {
            return [];
        }
        $resp = explode("\n", $resp);
        // Parse.
        $events = [];
        foreach ($resp as $line) {
            if (!preg_match('/^ (\w+) \s+ ([^:]+):(\S+) \s* $/x', $line, $m)) {
                trigger_error('Cannot parse the event: "' . $line . '"');
                continue;
            }
            [$event, $pos, $id] = [$m[1], (int)$m[2], $m[3]];
            if ($fromPos > 0 && $this->namespace !== '' && str_starts_with($id, $this->namespace)) {
                $id = substr($id, strlen($this->namespace));
            }
            $events[] = [
                'event' => $event,
                'pos'   => $pos,
                'id'    => $id,
            ];
        }
        return $events;
    }

    /**
     * Internal method.
     * Send IN command.
     * @throws Dklab_Realplexor_Exception in case of error.
     *
     * @param string $cmd   Command to send.
     * @return string       Server IN response.
     */
    private function sendCmd(string $cmd) : string
    {
        return $this->internalSend('', $cmd . "\n");
    }

    /**
     * Internal method.
     * Send specified data to IN channel. Return response data.
     * Throw Dklab_Realplexor_Exception in case of error.
     *
     * @throws Dklab_Realplexor_Exception in case of error.
     *
     * @param string $identifier  If set, pass this identifier string.
     * @param string $body        Data to be sent.
     * @return string             Response from IN line.
     */
    private function internalSend(string $identifier, string $body) : string
    {
        // Build HTTP request.
        $headers = 'X-Realplexor: ' . $this->identifier . '='
            . (isset($this->login) ? $this->login . ':' . $this->password . '@' : '')
            . $identifier
            . "\r\n";
        $data = 'POST / HTTP/1.1' . "\r\n"
            . 'Host: ' . $this->host . "\r\n"
            . 'Content-Length: ' . mb_strlen($body) . "\r\n"
            . $headers
            . "\r\n"
            . $body;

        // Proceed with sending.
        $host = $this->port === 443 ? 'ssl://' . $this->host : $this->host;
        $f = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
        if (!$f) {
            throw new Dklab_Realplexor_Exception('Error #' . $errno . ': ' . $errstr);
        }
        if (@fwrite($f, $data) === false) {
            throw new Dklab_Realplexor_Exception('Error #fwrite');
        }
        if (!@stream_socket_shutdown($f, STREAM_SHUT_WR)) {
            throw new Dklab_Realplexor_Exception('Error #stream_socket_shutdown');
        }
        $result = @stream_get_contents($f);
        if ($result === false) {
            throw new Dklab_Realplexor_Exception('Error #stream_get_contents');
        }
        if (!@fclose($f)) {
            throw new Dklab_Realplexor_Exception('Error #fclose');
        }

        // Analyze the result.
        if ($result) {
            @[$headers, $body] = preg_split('/\r?\n\r?\n/', $result, 2);
            if (!preg_match('{^HTTP/[\d.]+ \s+ ((\d+) [^\r\n]*)}ix', $headers, $m)) {
                throw new Dklab_Realplexor_Exception('Non-HTTP response received:' . "\n" . $result);
            }
            if ((int)$m[2] !== 200) {
                throw new Dklab_Realplexor_Exception('Request failed: ' . $m[1] . "\n" . $body);
            }
            if (!preg_match('/^Content-Length: \s* (\d+)/mix', $headers, $m)) {
                throw new Dklab_Realplexor_Exception('No Content-Length header in response headers:' . "\n" . $headers);
            }
            $needLen = (int)$m[1];
            $recvLen = mb_strlen($body);
            if ($needLen !== $recvLen) {
                throw new Dklab_Realplexor_Exception('Response length (' . $recvLen . ') is different than specified in Content-Length header (' . $needLen . '): possibly broken response' . "\n");
            }
            return $body;
        }
        return $result;
    }
}

/**
 * Realplexor-dedicated exception class.
 */
class Dklab_Realplexor_Exception extends Exception
{
}
