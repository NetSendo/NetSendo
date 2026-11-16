"""
A mail server that accepts everything and writes down what it saw. Test tool for the
NetSendo MTA (docker/haraka/test/e2e.sh), never part of an installation.

For every message one JSON line is appended to SINK_LOG: the address the MTA connected
from, its HELO name, the envelope, the headers, and whether the DKIM signature verifies
against the TXT records in SINK_DNS (a JSON object of name -> record, written by the
test, standing in for public DNS).
"""
import asyncio
import email
import json
import os

import dkim
from aiosmtpd.controller import Controller

LOG = os.environ.get("SINK_LOG", "/data/sink.jsonl")
DNS = os.environ.get("SINK_DNS", "/data/dns.json")


def txt_record(name, timeout=5):
    name = name.decode() if isinstance(name, bytes) else name
    try:
        with open(DNS) as handle:
            records = json.load(handle)
    except (OSError, ValueError):
        records = {}
    value = records.get(name.rstrip("."))
    return value.encode() if value else None


class Sink:
    async def handle_EHLO(self, server, session, envelope, hostname, responses):
        session.host_name = hostname
        return responses

    async def handle_DATA(self, server, session, envelope):
        raw = envelope.original_content or envelope.content
        message = email.message_from_bytes(raw)

        entry = {
            "peer": session.peer[0],
            "helo": session.host_name,
            "mail_from": envelope.mail_from,
            "rcpt_to": envelope.rcpt_tos,
            "subject": message.get("Subject"),
            "header_names": sorted({name.lower() for name in message.keys()}),
            "dkim_signature": message.get("DKIM-Signature"),
            "dkim_valid": None,
            "dkim_error": None,
        }

        if entry["dkim_signature"]:
            try:
                entry["dkim_valid"] = bool(dkim.verify(raw, dnsfunc=txt_record))
            except Exception as error:  # a malformed signature is a result, not a crash
                entry["dkim_valid"] = False
                entry["dkim_error"] = str(error)

        with open(LOG, "a") as handle:
            handle.write(json.dumps(entry) + "\n")

        return "250 OK"


async def main():
    controller = Controller(Sink(), hostname="0.0.0.0", port=25)
    controller.start()
    print("sink listening on :25", flush=True)
    await asyncio.Event().wait()


if __name__ == "__main__":
    asyncio.run(main())
