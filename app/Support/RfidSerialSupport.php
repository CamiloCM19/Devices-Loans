<?php

namespace App\Support;

final class RfidSerialSupport
{
    /**
     * @param  list<array{status?: string, port?: string, friendly_name?: string, instance_id?: string}>  $candidates
     * @return list<array{status?: string, port?: string, friendly_name?: string, instance_id?: string}>
     */
    public static function sortWindowsPortCandidates(array $candidates, string $readerProfile = 'auto'): array
    {
        usort($candidates, static function (array $left, array $right) use ($readerProfile): int {
            $leftScore = self::scoreWindowsPortCandidate($left, $readerProfile);
            $rightScore = self::scoreWindowsPortCandidate($right, $readerProfile);

            if ($leftScore !== $rightScore) {
                return $leftScore <=> $rightScore;
            }

            $leftPortNumber = self::portNumber((string) ($left['port'] ?? ''));
            $rightPortNumber = self::portNumber((string) ($right['port'] ?? ''));
            if ($leftPortNumber !== $rightPortNumber) {
                return $leftPortNumber <=> $rightPortNumber;
            }

            return strcmp((string) ($left['port'] ?? ''), (string) ($right['port'] ?? ''));
        });

        return $candidates;
    }

    public static function normalizeIncomingSerialChunk(string $chunk): string
    {
        $chunk = preg_replace('/^\xEF\xBB\xBF/', '', $chunk) ?? $chunk;

        if (!str_contains($chunk, "\0")) {
            return $chunk;
        }

        return str_replace("\0", '', $chunk);
    }

    public static function extractUidCandidate(string $line): ?string
    {
        $line = strtoupper(trim($line));

        if ($line === '') {
            return null;
        }

        if (preg_match('/(?:UID|TAG|CARD|SERIAL|SN|CSN|ID|NFCID1|NFCID2|NFCID3)\s*[:=#()\-\s]?\s*([0-9A-F:\-\s]{4,64})/', $line, $matches)) {
            $candidate = preg_replace('/[^0-9A-F]/', '', $matches[1]) ?? '';

            return $candidate !== '' ? $candidate : null;
        }

        if (preg_match('/^[0-9A-F:\-\s]{4,64}$/', $line) === 1) {
            $candidate = preg_replace('/[^0-9A-F]/', '', $line) ?? '';

            return strlen($candidate) >= 4 ? $candidate : null;
        }

        if (preg_match('/^[0-9]{6,20}$/', $line) === 1) {
            return $line;
        }

        return null;
    }

    public static function normalizeRemoteApiUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) !== 1) {
            $value = 'http://' . ltrim($value, '/');
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $path = trim((string) ($parts['path'] ?? ''));
        if ($path === '' || $path === '/') {
            return rtrim($value, '/') . '/inventory/scan/rfid';
        }

        if (preg_match('#/inventory/scan/rfid/?$#', $path) === 1) {
            return rtrim($value, '/');
        }

        return rtrim($value, '/') . '/inventory/scan/rfid';
    }

    /**
     * @param  array{status?: string, port?: string, friendly_name?: string, instance_id?: string}  $candidate
     */
    private static function scoreWindowsPortCandidate(array $candidate, string $readerProfile): int
    {
        $status = strtoupper(trim((string) ($candidate['status'] ?? '')));
        $friendlyName = strtoupper((string) ($candidate['friendly_name'] ?? ''));
        $instanceId = strtoupper((string) ($candidate['instance_id'] ?? ''));
        $details = trim($friendlyName . ' ' . $instanceId);

        $score = match ($status) {
            'OK' => 0,
            'UNKNOWN' => 100,
            default => 300,
        };

        if ($details === '') {
            $score += 50;
        }

        foreach (['USB-SERIAL CH340', 'CH340', 'CH341', 'CP210', 'FTDI', 'ARDUINO', 'USB SERIAL', 'USB\\VID_1A86&PID_7523'] as $keyword) {
            if (str_contains($details, $keyword)) {
                $score -= 40;
            }
        }

        if ($readerProfile === 'mfrc522') {
            foreach (['USB-SERIAL', 'CH340', 'CH341', 'ARDUINO', 'CP210', 'FTDI'] as $keyword) {
                if (str_contains($details, $keyword)) {
                    $score -= 20;
                }
            }
        }

        if (str_contains($details, 'BLUETOOTH')) {
            $score += 500;
        }

        return $score;
    }

    private static function portNumber(string $port): int
    {
        if (preg_match('/COM(\d+)/i', $port, $matches) !== 1) {
            return PHP_INT_MAX;
        }

        return (int) $matches[1];
    }
}
