import argparse
import json
import os
import re
import socket
import sys
import time
from urllib import error as urllib_error
from urllib import request as urllib_request

import serial
from serial.tools import list_ports


BLUETOOTH_HINTS = ("bluetooth", "bthenum")
USB_HINTS = ("usb", "ch340", "cp210", "ftdi", "arduino", "uart", "serial")
LOW_CONFIDENCE_DEVICE_HINTS = ("/dev/ttys", "/dev/ttyama", "/dev/ttyths", "/dev/ttyprintk")


def is_bluetooth_port(port_info):
    text = f"{port_info.device} {port_info.description} {port_info.hwid}".lower()
    return any(h in text for h in BLUETOOTH_HINTS)


def list_candidate_ports(include_bluetooth=False):
    ranked = []
    for p in list_ports.comports():
        text = f"{p.device} {p.description} {p.hwid}".lower()
        is_bt = is_bluetooth_port(p)
        if is_bt and not include_bluetooth:
            continue

        score = 0
        if "usb" in text:
            score += 100
        for hint in USB_HINTS:
            if hint in text:
                score += 20
        if getattr(p, "vid", None) is not None:
            score += 10
        if getattr(p, "pid", None) is not None:
            score += 10
        if is_bt:
            score -= 120
        if any(device_hint in p.device.lower() for device_hint in LOW_CONFIDENCE_DEVICE_HINTS):
            score -= 80

        ranked.append((score, p.device, p.description, p.hwid, is_bt))

    ranked.sort(key=lambda x: x[0], reverse=True)
    return ranked


def print_ports_snapshot(preferred_port=None, include_bluetooth=False):
    ports = list(list_ports.comports())
    if preferred_port:
        print(f"Waiting for configured port: {preferred_port}")
        if not ports:
            print("No serial ports detected.")
        else:
            print("Detected ports:")
            for p in ports:
                print(f"  - {p.device} | {p.description} | {p.hwid}")
        return

    candidates = list_candidate_ports(include_bluetooth=include_bluetooth)
    if candidates:
        print("Detected candidate serial ports (best first):")
        for score, device, desc, hwid, is_bt in candidates:
            bt_note = " [bluetooth]" if is_bt else ""
            print(f"  - {device} (score={score}){bt_note} | {desc} | {hwid}")
    elif not ports:
        print("No serial ports detected.")
    else:
        if any(is_bluetooth_port(p) for p in ports):
            print("Only Bluetooth COM ports detected. Skipping them in automatic mode.")
            print("If your reader is USB-Serial, verify driver/cable.")
            print("If you really need Bluetooth serial, run with --include-bluetooth.")
        else:
            print("Detected serial ports, but none look like a valid RFID serial device:")
        for p in ports:
            print(f"  - {p.device} | {p.description} | {p.hwid}")


def extract_uid(line):
    text = line.strip()
    if not text:
        return None

    lower = text.lower()
    upper = text.upper()

    # Try pair-based UID first: "UID: 04 2A 5C 9E" or "04-2A-5C-9E".
    pairs = re.findall(r"\b[0-9A-F]{2}\b", upper)
    if len(pairs) >= 4:
        return "".join(pairs)

    # Fallback for compact hex outputs: "042A5C9E".
    compact = re.sub(r"[^0-9A-F]", "", upper)
    if (
        8 <= len(compact) <= 32
        and len(compact) % 2 == 0
        and ("uid" in lower or "tag" in lower or "card" in lower or re.fullmatch(r"[0-9a-f]+", text))
    ):
        return compact

    return None


def post_uid_http(uid, args):
    payload = {
        "uid": uid,
        "source": args.source,
    }
    if args.token:
        payload["token"] = args.token

    body = json.dumps(payload).encode("utf-8")
    req = urllib_request.Request(
        args.api_url,
        data=body,
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    try:
        with urllib_request.urlopen(req, timeout=args.http_timeout) as response:
            raw = response.read().decode("utf-8", errors="ignore")
            short = raw.strip().replace("\n", " ")
            if len(short) > 220:
                short = short[:220] + "..."
            print(f"POST {response.status} -> {short}")
            return True
    except urllib_error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="ignore")
        short = raw.strip().replace("\n", " ")
        if len(short) > 220:
            short = short[:220] + "..."
        print(f"POST failed HTTP {exc.code}: {short}")
    except Exception as exc:
        print(f"POST failed: {exc}")

    return False


def send_uid_keyboard(uid):
    try:
        import pyautogui  # optional dependency used only in keyboard mode
    except Exception as exc:
        print(f"Keyboard mode requires pyautogui: {exc}")
        return False

    try:
        pyautogui.write(uid)
        pyautogui.press("enter")
        print(f"Sent (keyboard): {uid}")
        return True
    except Exception as exc:
        print(f"Keyboard send failed: {exc}")
        return False


def open_serial(args, avoid_port=None):
    if args.baud is not None:
        baud_targets = [args.baud]
    else:
        baud_targets = [int(b.strip()) for b in args.baud_candidates.split(",") if b.strip()]

    if args.port:
        targets = [args.port]
    else:
        ranked = list_candidate_ports(include_bluetooth=args.include_bluetooth)
        if args.allow_low_score:
            targets = [d for _, d, _, _, _ in ranked]
        else:
            targets = [d for score, d, _, _, _ in ranked if score > 0]
            if not targets and ranked:
                print("Only low-confidence ports detected. Waiting for USB-serial reader...")
                print("Use --allow-low-score to force trying these ports.")
        if avoid_port and avoid_port in targets and len(targets) > 1:
            targets = [d for d in targets if d != avoid_port] + [avoid_port]

    if not targets:
        return None, None, None

    for device in targets:
        for baud in baud_targets:
            try:
                ser = serial.Serial(device, baud, timeout=0.2)
                time.sleep(1.0)
                ser.reset_input_buffer()
                print(f"Connected to {device} @ {baud}")
                return ser, device, baud
            except serial.SerialException as exc:
                print(f"Could not open {device} @ {baud}: {exc}")
    return None, None, None


def main():
    default_mode = os.environ.get("RFID_BRIDGE_MODE", "api").strip().lower() or "api"
    default_api_url = os.environ.get("RFID_BRIDGE_API_URL", "http://127.0.0.1:8000/inventory/scan/esp").strip()
    default_source = os.environ.get("RFID_BRIDGE_SOURCE", f"usb-bridge-{socket.gethostname()}").strip()
    default_token = os.environ.get("RFID_BRIDGE_TOKEN", os.environ.get("RFID_ESP_TOKEN", "")).strip()

    parser = argparse.ArgumentParser(description="NFC Serial Bridge")
    parser.add_argument(
        "--mode",
        type=str,
        choices=["api", "keyboard"],
        default=default_mode,
        help="Output mode: api (POST to backend) or keyboard (type + Enter)",
    )
    parser.add_argument("--port", type=str, default=None, help="COM port (e.g., COM3). If omitted, auto-detects.")
    parser.add_argument("--baud", type=int, default=None, help="Fixed baud rate. If omitted, auto-tries common rates.")
    parser.add_argument(
        "--baud-candidates",
        type=str,
        default="115200,57600,38400,19200,9600",
        help="Comma-separated baud rates to try when --baud is not set",
    )
    parser.add_argument("--retry-seconds", type=float, default=2.0, help="Retry interval when disconnected")
    parser.add_argument(
        "--snapshot-seconds",
        type=float,
        default=15.0,
        help="How often to print port snapshots while waiting for a valid device",
    )
    parser.add_argument(
        "--rotate-seconds",
        type=float,
        default=12.0,
        help="In auto mode, switch to another port after this many seconds without any UID",
    )
    parser.add_argument("--debug", action="store_true", help="Print raw serial lines")
    parser.add_argument(
        "--api-url",
        type=str,
        default=default_api_url,
        help="Backend endpoint for mode=api, e.g. http://IP:8000/inventory/scan/esp",
    )
    parser.add_argument(
        "--source",
        type=str,
        default=default_source,
        help="Reader source identifier sent to backend",
    )
    parser.add_argument(
        "--token",
        type=str,
        default=default_token,
        help="Optional token for backend RFID_ESP_TOKEN validation",
    )
    parser.add_argument("--http-timeout", type=float, default=4.0, help="HTTP timeout for mode=api")
    parser.add_argument(
        "--dedupe-seconds",
        type=float,
        default=1.0,
        help="Ignore repeated same UID inside this time window",
    )
    parser.add_argument(
        "--include-bluetooth",
        action="store_true",
        help="Also try Bluetooth COM ports in automatic mode (disabled by default)",
    )
    parser.add_argument(
        "--allow-low-score",
        action="store_true",
        help="Allow trying low-confidence ports (e.g. onboard ttyS/ttyAMA) in auto mode",
    )
    args = parser.parse_args()

    print("NFC bridge started. Press Ctrl+C to exit.")
    print(f"Output mode: {args.mode}")
    if args.mode == "api":
        print(f"API URL: {args.api_url}")
        print(f"Source: {args.source}")
        print(f"Token: {'set' if args.token else 'empty'}")
    if args.port:
        print(f"Port mode: fixed ({args.port})")
    else:
        bt_mode = "enabled" if args.include_bluetooth else "disabled"
        print(f"Port mode: automatic (bluetooth {bt_mode})")
    if args.baud is not None:
        print(f"Baud mode: fixed ({args.baud})")
    else:
        print(f"Baud mode: automatic ({args.baud_candidates})")

    ser = None
    current_port = None
    current_baud = None
    avoid_port = None
    last_uid_at = time.monotonic()
    last_snapshot_at = 0.0
    last_sent_uid = None
    last_sent_at = 0.0

    try:
        while True:
            if ser is None:
                now = time.monotonic()
                if now - last_snapshot_at >= args.snapshot_seconds:
                    print_ports_snapshot(args.port, include_bluetooth=args.include_bluetooth)
                    last_snapshot_at = now

                ser, current_port, current_baud = open_serial(args, avoid_port=avoid_port)
                if ser is None:
                    time.sleep(args.retry_seconds)
                    continue
                avoid_port = None
                last_uid_at = time.monotonic()

            try:
                raw = ser.readline()
                if not raw:
                    if not args.port and args.rotate_seconds > 0 and (time.monotonic() - last_uid_at) >= args.rotate_seconds:
                        print(
                            f"No UID on {current_port} @ {current_baud} for {args.rotate_seconds:.0f}s. Trying another port..."
                        )
                        avoid_port = current_port
                        ser.close()
                        ser = None
                        current_port = None
                        current_baud = None
                    continue

                line = raw.decode("utf-8", errors="ignore").strip()
                if args.debug and line:
                    print(f"RAW[{current_port}]: {line}")

                uid = extract_uid(line)
                if not uid:
                    continue

                last_uid_at = time.monotonic()
                if uid == last_sent_uid and (last_uid_at - last_sent_at) < args.dedupe_seconds:
                    print(f"Ignoring duplicate UID: {uid}")
                    continue

                print(f"Read UID: {uid}")
                if args.mode == "api":
                    sent_ok = post_uid_http(uid, args)
                else:
                    sent_ok = send_uid_keyboard(uid)

                if sent_ok:
                    last_sent_uid = uid
                    last_sent_at = time.monotonic()
            except serial.SerialException as exc:
                print(f"Serial connection lost on {current_port} @ {current_baud}: {exc}")
                try:
                    ser.close()
                except Exception:
                    pass
                ser = None
                current_port = None
                current_baud = None
                time.sleep(args.retry_seconds)
    except KeyboardInterrupt:
        print("\nExiting...")
        if ser and ser.is_open:
            ser.close()
        sys.exit(0)


if __name__ == "__main__":
    main()
