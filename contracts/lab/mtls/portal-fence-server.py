#!/usr/bin/env python3
"""F1-21 CI ONLY: mTLS-fenced synthetic Portal grant reader on 127.0.0.1.

Do not deploy. The source PostgreSQL is a different CI service container from
the Performance destination, but both share one runner and test CA. This is NOT
a workload identity service, real Portal, PKI, HTTPS ingress, or production SLA.
"""
import http.server
import json
import os
import ssl
import subprocess
import sys


def deny_if_not_ci():
    expected = {
        "APP_ENV": "testing",
        "PERF_F21_MTLS_LAB": "1",
        "PORTAL_F20_PORT": "5432",
        "PERF_F20_PORT": "5433",
    }
    for key, value in expected.items():
        if os.getenv(key) != value:
            raise SystemExit("Refusing F21 outside disposable CI")
    for key in ("PERF_F21_CA", "PERF_F21_SERVER_CERT", "PERF_F21_SERVER_KEY"):
        path = os.getenv(key)
        if not path or not os.path.isfile(path):
            raise SystemExit("Missing test-only TLS certificate path")


class Handler(http.server.BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"
    server_version = "Imagine-F21-test-only"

    def respond(self, code, value):
        body = json.dumps({"result": value}, separators=(",", ":")).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path != "/_synthetic/portal/fence/v1/svc-demo-b":
            self.respond(404, "not_found")
            return
        cert = self.connection.getpeercert()
        if not cert:
            self.respond(403, "client_not_verified")
            return
        subject = {
            key: value
            for group in cert.get("subject", ())
            for key, value in group
        }
        if subject.get("commonName") != "performance-reader.lab":
            self.respond(403, "client_identity_denied")
            return
        env = os.environ.copy()
        env["PGPASSWORD"] = "ci-only-source-reader-f20"
        args = [
            "psql", "-X", "-A", "-t", "-F", "|",
            "-h", "127.0.0.1", "-p", "5432",
            "-U", "portal_f20_reader", "-d", "portal_f20_test",
            "-c",
            "SELECT service_ref,organization_ref,tenant_ref,"
            "current_revision,recovery_epoch,active::text "
            "FROM portal_f20.mock_authority "
            "WHERE service_ref='svc-demo-b'",
        ]
        try:
            process = subprocess.run(
                args, env=env, capture_output=True, text=True, timeout=3,
                check=False,
            )
        except (OSError, subprocess.TimeoutExpired):
            self.respond(503, "source_unavailable")
            return
        if process.returncode != 0:
            self.respond(503, "source_unavailable")
            return
        rows = process.stdout.strip().splitlines()
        if len(rows) != 1:
            self.respond(503, "source_unavailable")
            return
        fields = rows[0].split("|")
        if len(fields) != 6:
            self.respond(503, "source_unavailable")
            return
        service, org, tenant, revision, epoch, active = fields
        if (
            service != "svc-demo-b"
            or org != "org-demo-b"
            or tenant != "perf-demo-b"
            or not revision.isdecimal()
            or not epoch.isdecimal()
            or active not in ("true", "false")
        ):
            self.respond(503, "source_unavailable")
            return
        payload = {
            "version": "perf.mtls.fence.synthetic.v1",
            "service_ref": service,
            "organization_ref": org,
            "tenant_ref": tenant,
            "revision": int(revision),
            "recovery_epoch": int(epoch),
            "active": active == "true",
        }
        raw = json.dumps(payload, separators=(",", ":")).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def do_POST(self):
        self.respond(405, "method_not_allowed")

    def log_message(self, fmt, *args):
        # No client certificate, password or event payload in CI output.
        pass


def main():
    deny_if_not_ci()
    ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    ctx.minimum_version = ssl.TLSVersion.TLSv1_2
    ctx.verify_mode = ssl.CERT_REQUIRED
    ctx.load_cert_chain(
        os.environ["PERF_F21_SERVER_CERT"],
        os.environ["PERF_F21_SERVER_KEY"],
    )
    ctx.load_verify_locations(cafile=os.environ["PERF_F21_CA"])
    server = http.server.HTTPServer(("127.0.0.1", 18785), Handler)
    server.socket = ctx.wrap_socket(server.socket, server_side=True)
    print("F1-21 test-only TLS listener on loopback ready", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
