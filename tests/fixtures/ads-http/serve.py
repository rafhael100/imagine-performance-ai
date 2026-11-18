"""Synthetic TLS peer. Emits only request counts and assertion booleans."""
from __future__ import annotations

import http.server
import json
import pathlib
import ssl
import sys
import threading
import time
import urllib.parse

ROOT = pathlib.Path(sys.argv[1])
LOCK = threading.Lock()
STATE: dict[str, dict[str, int | bool]] = {}


def control() -> dict:
    return json.loads((ROOT / "control.json").read_text())


def update(case_id: str, *, tls: bool = False, matched: bool | None = None, redirect: bool = False) -> int:
    with LOCK:
        item = STATE.setdefault(case_id, {
            "tls_connections": 0, "requests": 0,
            "expectations_ok": True, "redirect_hits": 0,
        })
        if tls:
            item["tls_connections"] += 1
        if matched is not None:
            item["requests"] += 1
            item["expectations_ok"] = item["expectations_ok"] and matched
            item["redirect_hits"] += int(redirect)
        pending = ROOT / "state.next"
        pending.write_text(json.dumps(STATE, separators=(",", ":")))
        pending.replace(ROOT / "state.json")
        return int(item["requests"])


def request_number(case_id: str) -> int:
    with LOCK:
        return int(STATE.get(case_id, {}).get("requests", 0))


def context(prefix: str) -> ssl.SSLContext:
    result = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    result.minimum_version = ssl.TLSVersion.TLSv1_2
    result.load_cert_chain(str(ROOT / (prefix + ".pem")), str(ROOT / (prefix + ".key")))
    return result


CONTEXTS = {mode: context(mode) for mode in ["trusted", "wrong-host", "untrusted"]}


def choose_certificate(socket: ssl.SSLSocket, _server_name: str | None, _initial: ssl.SSLContext) -> None:
    config = control()
    update(config["case_id"], tls=True)
    socket.context = CONTEXTS[config.get("tls_mode", "trusted")]


CONTEXTS["trusted"].set_servername_callback(choose_certificate)


class Peer(http.server.BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, _format: str, *args: object) -> None:
        pass

    def do_GET(self) -> None:
        self.respond()

    def do_POST(self) -> None:
        self.respond()

    def matches(self, expected: dict) -> bool:
        try:
            target = urllib.parse.urlsplit(self.path)
            target_base = urllib.parse.urlsplit(expected["url"])
            if self.command != expected["method"] or target.path != target_base.path:
                return False
            if self.headers.get("Host") != target_base.hostname:
                return False
            for name, value in expected.get("headers", {}).items():
                if self.headers.get(name) != value:
                    return False
            for name in expected.get("absent_headers", []):
                if self.headers.get(name) is not None:
                    return False
            content_length = int(self.headers.get("Content-Length", "0"))
            if not 0 <= content_length <= 16384:
                return False
            body = self.rfile.read(content_length)
            if "json" in expected:
                return not target.query and json.loads(body) == expected["json"]
            query = urllib.parse.parse_qs(target.query, keep_blank_values=True)
            wanted = {key: [str(value)] for key, value in expected["query"].items()}
            return body == b"" and query == wanted
        except (ValueError, KeyError, TypeError, UnicodeError):
            return False

    def respond(self) -> None:
        config = control()
        case_id = config["case_id"]
        index = request_number(case_id)
        expectations = config.get("expected_requests", [])
        matched = index < len(expectations) and self.matches(expectations[index])
        update(case_id, matched=matched, redirect=self.path.startswith("/redirect-target"))
        responses = config.get("responses", [])
        response = responses[index] if index < len(responses) else {"status": 500, "body": "{}"}
        try:
            time.sleep(response.get("delay_headers_ms", 0) / 1000)
            body = response.get("body", "{}").encode()
            mode = response.get("body_mode", "normal")
            size = 1048577 if mode in ("oversize-length", "oversize-chunked") else len(body)
            chunked = mode == "oversize-chunked"
            self.send_response(response.get("status", 200))
            self.send_header("Content-Type", response.get("content_type", "application/json"))
            self.send_header("Connection", "close")
            if response.get("encoding") is not None:
                self.send_header("Content-Encoding", response["encoding"])
            if response.get("location") is not None:
                self.send_header("Location", response["location"])
            if response.get("header_padding_bytes"):
                self.send_header("X-Synthetic-Padding", "x" * response["header_padding_bytes"])
            if chunked:
                self.send_header("Transfer-Encoding", "chunked")
            else:
                self.send_header("Content-Length", str(size))
            self.end_headers()
            self.wfile.flush()
            time.sleep(response.get("delay_body_ms", 0) / 1000)
            if mode == "normal":
                self.wfile.write(body)
            else:
                remaining = size
                while remaining:
                    data = b"x" * min(32768, remaining)
                    if chunked:
                        self.wfile.write(("%x\r\n" % len(data)).encode() + data + b"\r\n")
                    else:
                        self.wfile.write(data)
                    remaining -= len(data)
                if chunked:
                    self.wfile.write(b"0\r\n\r\n")
            self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError, ssl.SSLError, OSError):
            pass
        finally:
            self.close_connection = True


class QuietServer(http.server.ThreadingHTTPServer):
    daemon_threads = True

    def handle_error(self, _request: object, _client_address: object) -> None:
        # No request, path, credential, or raw exception is printed.
        pass


server = QuietServer(("127.0.0.1", 443), Peer)
server.socket = CONTEXTS["trusted"].wrap_socket(server.socket, server_side=True)
(ROOT / "ready").write_text("ready\n")
server.serve_forever(poll_interval=0.05)
