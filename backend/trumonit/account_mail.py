"""Explicit mail delivery: SMTP-over-TLS or private development-only outbox.

Never return recovery or verification bearer tokens through HTTP or logs.
Production refuses file mode and unconfigured SMTP.
"""
from __future__ import annotations

import os
import secrets
import smtplib
from email.message import EmailMessage
from pathlib import Path
from urllib.parse import urlparse


def delivery_config():
    environment = os.environ.get("TRUMONIT_ENV", "production").lower()
    mode = os.environ.get("TRUMONIT_MAIL_MODE", "disabled").lower()
    url = os.environ.get("TRUMONIT_PUBLIC_URL", "")
    parsed = urlparse(url)
    if (not url or parsed.scheme not in ("http", "https")
            or not parsed.hostname or parsed.path not in ("", "/")
            or parsed.query or parsed.fragment or parsed.username or parsed.password
            or parsed.port is not None):
        raise RuntimeError("Mail needs a configured public origin URL")
    if environment == "production" and parsed.scheme != "https":
        raise RuntimeError("Production requires HTTPS public URL")
    if mode == "file" and environment in ("development", "test"):
        path = os.environ.get("TRUMONIT_MAIL_OUTBOX", "")
        if not path or not Path(path).is_absolute():
            raise RuntimeError("Development mail outbox requires an absolute path")
        return mode, url.rstrip("/"), path
    if mode == "smtp_ssl":
        required = ("TRUMONIT_SMTP_HOST", "TRUMONIT_SMTP_USER",
                    "TRUMONIT_SMTP_PASSWORD", "TRUMONIT_MAIL_FROM")
        if not all(os.environ.get(key) for key in required):
            raise RuntimeError("SMTP mail delivery is missing required settings")
        return mode, url.rstrip("/"), ""
    raise RuntimeError("Secure mail delivery not configured")


def send_account_link(email: str, purpose: str, raw: str):
    mode, origin, outbox = delivery_config()
    if purpose not in ("verify", "reset"):
        raise ValueError("Unsupported account message")
    subject = "Verify your Trumonit email" if purpose == "verify" else "Reset your Trumonit password"
    link = origin + "/account#" + purpose + "=" + raw
    message = EmailMessage()
    message["Subject"] = subject
    message["To"] = email
    message["From"] = os.environ.get("TRUMONIT_MAIL_FROM", "Trumonit <noreply@trumonit.invalid>")
    message.set_content(
        "Use this private, one-time link to " +
        ("verify your work email" if purpose == "verify" else "reset your password") +
        ":\n\n" + link + "\n\n"
        + ("Expires in 24 hours." if purpose == "verify" else "Expires in 30 minutes.") +
        "\nIf you did not request this action, ignore this email.\n"
    )
    if mode == "file":
        folder = Path(outbox)
        folder.mkdir(mode=0o700, parents=True, exist_ok=True)
        folder.chmod(0o700)
        name = "message-" + secrets.token_hex(16) + ".eml"
        # Exclusive, permission 0600, no predictable user identifiers.
        flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL
        with os.fdopen(os.open(folder / name, flags, 0o600), "wb") as target:
            target.write(message.as_bytes())
    else:
        host = os.environ["TRUMONIT_SMTP_HOST"]
        user = os.environ["TRUMONIT_SMTP_USER"]
        password = os.environ["TRUMONIT_SMTP_PASSWORD"]
        port = int(os.environ.get("TRUMONIT_SMTP_PORT", "465"))
        with smtplib.SMTP_SSL(host, port, timeout=12) as smtp:
            smtp.login(user, password)
            smtp.send_message(message)
