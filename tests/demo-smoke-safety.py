#!/usr/bin/env python3
"""Exercise the Demo smoke script against real local HTTP and HTTPS fixtures."""

from __future__ import annotations

from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import os
from pathlib import Path
import ssl
import subprocess
import tempfile
from threading import Thread
from urllib.parse import urlsplit


ROOT = Path(__file__).resolve().parents[1]


class DemoHandler(BaseHTTPRequestHandler):
    subscriptions = 30
    leak_icon = False
    broken_home = False

    def log_message(self, *_args: object) -> None:
        pass

    def do_GET(self) -> None:
        path = urlsplit(self.path).path
        status, body = 200, ""
        if path in ("/", "/index.php"):
            if self.broken_home:
                status = 500
            body = '<div class="demo-banner">Synthetic Demo</div>'
        elif path == "/subscriptions.php":
            body = "".join(f'data-testid="dial-subscription-{index}"'
                           for index in range(1, self.subscriptions + 1))
        elif path == "/categories.php":
            body = 'data-testid="dial-category-readonly"'
        elif path == "/service-worker.php":
            body = 'static-cache-v5.8.3-dial-fixture'
        elif path in ("/calendar.php", "/stats.php"):
            body = "Synthetic read-only page"
        elif path == "/images/dial-catalog/chatgpt.svg":
            body = '<svg xmlns="http://www.w3.org/2000/svg"></svg>'
        elif path in ("/endpoints/dial/icon.php", "/endpoints/dial/icons.php"):
            status = 200 if self.leak_icon else 404
            body = "private icon data" if self.leak_icon else ""
        else:
            status = 404
        self.send_response(status)
        self.send_header("Content-Type", "text/html")
        self.end_headers()
        self.wfile.write(body.encode())

    def do_POST(self) -> None:
        self.send_response(404)
        self.end_headers()


def server(handler: type[DemoHandler], certificate: Path | None = None,
           key: Path | None = None) -> tuple[ThreadingHTTPServer, str]:
    httpd = ThreadingHTTPServer(("127.0.0.1", 0), handler)
    scheme, host = "http", "127.0.0.1"
    if certificate and key:
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.load_cert_chain(str(certificate), str(key))
        httpd.socket = context.wrap_socket(httpd.socket, server_side=True)
        scheme, host = "https", "localhost"
    thread = Thread(target=httpd.serve_forever, daemon=True)
    thread.start()
    return httpd, f"{scheme}://{host}:{httpd.server_port}/"


def run(origin: str, edge: str, public: str = "", extra_env: dict[str, str] | None = None) -> subprocess.CompletedProcess[str]:
    arguments = ["bash", str(ROOT / "demo/smoke.sh"), origin, edge]
    if public:
        arguments.append(public)
    return subprocess.run(arguments, capture_output=True, text=True, timeout=30,
                          env={**os.environ, **(extra_env or {})})


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="dial-demo-smoke-") as temporary:
        scratch = Path(temporary)
        cert, key = scratch / "cert.pem", scratch / "key.pem"
        subprocess.run(["openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes",
                        "-keyout", str(key), "-out", str(cert), "-days", "1",
                        "-subj", "/CN=localhost", "-addext", "subjectAltName=DNS:localhost"],
                       check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        origin_handler = type("OriginHandler", (DemoHandler,), {})
        edge_handler = type("EdgeHandler", (DemoHandler,), {})
        public_handler = type("PublicHandler", (DemoHandler,), {})
        origin_server, origin = server(origin_handler)
        edge_server, edge = server(edge_handler)
        public_server, public = server(public_handler, cert, key)
        try:
            edge_handler.subscriptions = 29
            result = run(origin, edge)
            assert result.returncode != 0 and "30 fictional subscriptions" in result.stderr, result

            edge_handler.subscriptions = 30
            edge_handler.leak_icon = True
            result = run(origin, edge)
            assert result.returncode != 0 and "My Icons" in result.stderr, result

            edge_handler.leak_icon = False
            result = run(origin, edge, edge)
            assert result.returncode != 0 and "HTTPS" in result.stderr, result

            public_handler.broken_home = True
            result = run(origin, edge, public, {"CURL_CA_BUNDLE": str(cert)})
            assert result.returncode != 0 and "HTTPS" in result.stderr, result

            public_handler.broken_home = False
            result = run(origin, edge, public, {"CURL_CA_BUNDLE": str(cert)})
            assert result.returncode == 0, result
        finally:
            for current in (origin_server, edge_server, public_server):
                current.shutdown()
                current.server_close()
    print("Demo smoke checks 30 records, private routes, and verified HTTPS target")


if __name__ == "__main__":
    main()
