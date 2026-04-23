<?php

namespace Tests\Unit;

use App\Support\RfidSerialSupport;
use PHPUnit\Framework\TestCase;

class RfidSerialSupportTest extends TestCase
{
    public function test_it_normalizes_utf16_like_serial_chunks(): void
    {
        $chunk = implode("\0", str_split("UID: 0443D502612090\r\n")) . "\0";

        $normalized = RfidSerialSupport::normalizeIncomingSerialChunk($chunk);

        $this->assertSame("UID: 0443D502612090\r\n", $normalized);
    }

    public function test_it_extracts_hex_uid_candidates_from_reader_output(): void
    {
        $this->assertSame(
            '0443D502612090',
            RfidSerialSupport::extractUidCandidate('Card UID: 04 43 D5 02 61 20 90')
        );

        $this->assertSame(
            '0443D502612090',
            RfidSerialSupport::extractUidCandidate('0443D502612090')
        );
    }

    public function test_it_prioritizes_healthy_usb_serial_ports_for_mfrc522(): void
    {
        $sorted = RfidSerialSupport::sortWindowsPortCandidates([
            [
                'status' => 'OK',
                'port' => 'COM5',
                'friendly_name' => 'Serie estandar sobre el vinculo Bluetooth (COM5)',
                'instance_id' => 'BTHENUM\\DEVICE',
            ],
            [
                'status' => 'UNKNOWN',
                'port' => 'COM8',
                'friendly_name' => 'USB-SERIAL CH340 (COM8)',
                'instance_id' => 'USB\\VID_1A86&PID_7523\\PORT2',
            ],
            [
                'status' => 'OK',
                'port' => 'COM7',
                'friendly_name' => 'USB-SERIAL CH340 (COM7)',
                'instance_id' => 'USB\\VID_1A86&PID_7523\\PORT1',
            ],
        ], 'mfrc522');

        $this->assertSame('COM7', $sorted[0]['port']);
        $this->assertSame('COM8', $sorted[1]['port']);
        $this->assertSame('COM5', $sorted[2]['port']);
    }

    public function test_it_normalizes_remote_rfid_api_urls_from_base_app_url(): void
    {
        $this->assertSame(
            'http://10.0.0.25:8000/inventory/scan/rfid',
            RfidSerialSupport::normalizeRemoteApiUrl('http://10.0.0.25:8000')
        );
    }

    public function test_it_normalizes_remote_rfid_api_urls_without_scheme(): void
    {
        $this->assertSame(
            'http://10.0.0.25:8000/inventory/scan/rfid',
            RfidSerialSupport::normalizeRemoteApiUrl('10.0.0.25:8000')
        );
    }

    public function test_it_keeps_full_remote_rfid_endpoint_urls(): void
    {
        $this->assertSame(
            'http://10.0.0.25:8000/inventory/scan/rfid',
            RfidSerialSupport::normalizeRemoteApiUrl('http://10.0.0.25:8000/inventory/scan/rfid')
        );
    }
}
