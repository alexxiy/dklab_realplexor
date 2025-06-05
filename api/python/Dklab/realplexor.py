import socket
import json
import re
from typing import Union, Optional, List, Dict

class Dklab_Realplexor:
    """Dklab_Realplexor python API v2.0."""

    def __init__(self, host: str, port: int, namespace: str = '', identifier: str = 'identifier'):
        """
        Create new Realplexor API instance.

        Keyword arguments:
        host -- Host of IN line.
        port -- Port of IN line (if 443, SSL is used).
        namespace -- Namespace to use.
        identifier -- Use this "identifier" marker instead of the default one.
        """
        self._login: Optional[str] = None
        self._password: Optional[str] = None
        self._timeout: int = 5
        self._host: str = host
        self._port: int = port
        self._namespace: str = namespace
        self._identifier: str = identifier

    def logon(self, login: str, password: str) -> None:
        """
        Set login and password to access Realplexor (if the server needs it).
        This method does not check credentials correctness.
        """
        self._login = login
        self._password = password
        # All keys must always be login-prefixed!
        self._namespace = f"{login}_{self._namespace}"

    def send(
            self,
            ids_and_cursors: Union[List[str], Dict[str, Union[int, None]]],
            data: dict,
            show_only_for_ids: Optional[List[str]] = None
    ) -> Dict[str, int]:
        """
        Send data to realplexor.
        Throw Dklab_Realplexor_Exception in case of error.

        idsAndCursors -- Target IDs in form of: dictionary(id1 => cursor1, id2 => cursor2, id3 => None...)
                                     of list[id1, id2, id3, ...].
        data -- Data to be sent (any format, e.g. nested dictionaries are OK).
        showOnlyForIds  -- Send this message to only those who also listen any of these IDs.
                                     This parameter may be used to limit the visibility to a closed
                                     number of clients: give each client a unique ID and enumerate
                                     client IDs in $showOnlyForIds to not send messages to others.
        """
        payload = json.dumps(data)
        pairs = []

        if isinstance(ids_and_cursors, list):
            # List of ID strings
            ids_and_cursors = {id_: None for id_ in ids_and_cursors}
        elif not isinstance(ids_and_cursors, dict):
            raise Dklab_Realplexor_Exception("ids_and_cursors must be a list of strings or a dict of id => cursor")

        for id_, cursor in ids_and_cursors.items():
            if not re.fullmatch(r"\w+", id_):
                raise Dklab_Realplexor_Exception(f"Identifier must be alphanumeric, \"{id_}\" given")

            full_id = f"{self._namespace or ''}{id_}"

            if cursor is not None:
                if not isinstance(cursor, int):
                    raise Dklab_Realplexor_Exception(f"Cursor must be an integer, \"{cursor}\" given")
                pairs.append(f"{cursor}:{full_id}")
            else:
                pairs.append(full_id)

        if show_only_for_ids:
            pairs += [f"*{self._namespace or ''}{id_}" for id_ in show_only_for_ids]

        resp = self._send(",".join(pairs), payload)
        if not resp.strip():
            return {}

        # Parse the result and trim namespace.
        result = {}
        for line in resp.strip().splitlines():
            try:
                id_, cursor = line.strip().split()
                if self._namespace and id_.startswith(self._namespace):
                    id_ = id_.removeprefix(self._namespace)
                result[id_] = int(cursor)
            except ValueError:
                continue

        return result

    def cmdOnlineWithCounters(self, id_prefixes: Optional[List[str]] = None) -> Dict[str, str]:
        """
        Return list of online IDs (keys) and number of online browsers
        for each ID. (Now "online" means "connected just now", it is
        very approximate; more precision is in TODO.)

        idPrefixes -- If set, only online IDs with these prefixes are returned.
        """
        # Add namespace.
        if id_prefixes is None:
            id_prefixes = []

        if self._namespace:
            id_prefixes = [f"{self._namespace}{prefix or ''}" for prefix in (id_prefixes or [""])]

        # Send command.
        resp = self._sendCmd("online" + (" " + " ".join(id_prefixes) if id_prefixes else ""))
        if not resp.strip():
            return {}

        # Parse the result and trim namespace.
        result = {}
        for line in resp.strip().splitlines():
            try:
                id_, counter = line.strip().split()
                if self._namespace and id_.startswith(self._namespace):
                    id_ = id_.removeprefix(self._namespace)
                result[id_] = int(counter)
            except ValueError:
                continue

        return result

    def cmdOnline(self, id_prefixes: Optional[List[str]] = None) -> List[str]:
        """
        Return list of online IDs.

        idPrefixes --  If set, only online IDs with these prefixes are returned.
        """
        return list(self.cmdOnlineWithCounters(id_prefixes).keys())

    def cmdWatch(self, from_pos: int, id_prefixes: Optional[List[str]] = None) -> List[dict]:
        """
        Return all Realplexor events (e.g. ID offline/offline changes)
        happened after fromPos cursor.

        fromPos -- Start watching from this cursor.
        idPrefixes -- Watch only changes of IDs with these prefixes.
        Returns list of dict("event": ..., "pos": ..., "id": ...).
        """
        id_prefixes = id_prefixes or []

        if not re.fullmatch(r"[\d]+", str(from_pos)):
            raise Dklab_Realplexor_Exception(f"Position value must be integer, \"{from_pos}\" given")

        # Add namespaces.
        if self._namespace:
            id_prefixes = [f"{self._namespace}{prefix or ''}" for prefix in (id_prefixes or [""])]

        # Execute.
        resp = self._sendCmd(f"watch {from_pos} {' '.join(id_prefixes)}")
        if not resp.strip():
            return []

        # Parse.
        events = []
        for line in resp.strip().splitlines():
            m = re.match(r'^(\w+)\s+([^:]+):(\S+)\s*$', line)
            if m:
                event, pos, id_ = m.groups()
                if self._namespace and id_.startswith(self._namespace):
                    id_ = id_.removeprefix(self._namespace)
                events.append({"event": event, "pos": int(pos), "id": id_})
        return events

    def _sendCmd(self, cmd: str) -> str:
        return self._send(None, f"{cmd}\n")

    def _send(self, identifier: Optional[str], body: str) -> str:
        """
        Internal method.
        Send specified data to IN channel. Return response data.
        Throw Dklab_Realplexor_Exception in case of error.

        Keyword arguments:
        identifier -- If set, pass this identifier string.
        data -- Data to be sent.

         Returns response from IN line.
         """
        # Build HTTP request.
        headers = f"X-Realplexor: {self._identifier}="
        if self._login:
            headers += f"{self._login}:{self._password}@"
        headers += f"{identifier or ''}\r\n"

        request = (
            f"POST / HTTP/1.1\r\n"
            f"Host: {self._host}\r\n"
            f"Content-Length: {len(body)}\r\n"
            f"{headers}\r\n"
            f"{body}"
        )

        # Proceed with sending.
        result = b''
        host = self._host
        if self._port == 443:
            host = f"ssl://{self._host}"

        with socket.create_connection((self._host, self._port), timeout=self._timeout) as s:
            s.sendall(request.encode())
            s.shutdown(socket.SHUT_WR)
            while chunk := s.recv(4096):
                result += chunk

        result_str = result.decode()
        # Analyze the result.
        if result_str:
            try:
                headers, body = re.split(r"\r?\n\r?\n", result_str, maxsplit=1)
            except ValueError:
                raise Dklab_Realplexor_Exception("Non-HTTP response received:\n" + result_str)

            m = re.match(r'^HTTP/[\d.]+\s+((\d+)[^\r\n]*)', headers)
            if not m:
                raise Dklab_Realplexor_Exception("Non-HTTP response received:\n" + result_str)

            if m.group(2) != "200":
                raise Dklab_Realplexor_Exception(f"Request failed: {m.group(1)}\n{body}")

            m = re.search(r'Content-Length:\s*(\d+)', headers, re.IGNORECASE)
            if not m:
                raise Dklab_Realplexor_Exception("No Content-Length header in response headers:\n" + headers)

            need_len = int(m.group(1))
            if len(body) != need_len:
                raise Dklab_Realplexor_Exception(
                    f"Response length ({len(body)}) is different than specified in Content-Length header ({need_len})"
                )
            return body
        return ""


class Dklab_Realplexor_Exception(Exception):
    """Realplexor-dedicated exception class."""
    pass
