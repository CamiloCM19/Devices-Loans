<?php

namespace App\Console\Commands;

use App\Services\RfidScanProcessor;
use App\Support\HardwareTelemetryRecorder;
use App\Support\RfidSerialSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ListenMfrc522Serial extends Command
{
    protected $signature = 'rfid:listen-serial
        {--device= : Puerto serial del lector, o "auto" para autodeteccion}
        {--baud= : Baud rate del lector NFC/RFID}
        {--reader= : Perfil del lector NFC (auto|mfrc522|pn532)}
        {--source= : Nombre del origen que se vera en telemetria}
        {--dedupe-ms=1200 : Ignora lecturas repetidas dentro de esta ventana}
        {--frame-idle-ms=150 : Cierra una trama serial corta si no llegan mas bytes}
        {--reconnect-delay=2 : Segundos de espera antes de reintentar}
        {--debug : Muestra lineas seriales descartadas}';

    protected $aliases = [
        'rfid:listen-mfrc522',
        'rfid:listen-pn532',
    ];

    protected $description = 'Escucha un lector NFC/RFID por puerto serial y procesa el flujo RFID en Laravel.';

    private string $sessionUuid;

    private string $source;

    private string $readerProfile = 'auto';

    /**
     * @var resource|null
     */
    private $serialHandle = null;

    /**
     * @var resource|null
     */
    private $serialProcess = null;

    private string $serialBuffer = '';

    private ?string $lastUid = null;

    private int $lastUidAtMs = 0;

    private int $lastByteAtMs = 0;

    private string $deviceSelectionMode = 'manual';

    public function __construct(
        private readonly RfidScanProcessor $processor,
        private readonly HardwareTelemetryRecorder $telemetry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->sessionUuid = (string) Str::uuid();
        $this->readerProfile = $this->resolveReaderProfile();
        $this->source = $this->resolveSource();
        $this->registerSignalHandlers();

        $device = $this->resolveDevice();
        $baud = $this->resolveBaud();
        $readerLabel = $this->readerLabel();
        $reconnectDelay = max(1, (int) $this->option('reconnect-delay'));

        $this->recordBridgeEvent(
            'bridge.started',
            "Listener serial de {$readerLabel} iniciado.",
            [
                'device' => $device,
                'baud' => $baud,
                'reader_model' => $this->readerProfile,
                'device_selection' => $this->deviceSelectionMode,
                'bridge_transport' => 'serial',
                'bridge_mode' => 'direct',
                'bridge_host' => gethostname() ?: php_uname('n'),
                'bridge_pid' => getmypid(),
            ]
        );

        $this->components->info("Escuchando {$readerLabel} en {$device} a {$baud} baudios.");
        $this->line("Fuente: {$this->source}");
        $this->line("Perfil lector: {$this->readerProfile}");
        $this->line("Sesion: {$this->sessionUuid}");

        while (true) {
            try {
                $this->openSerialPort($device, $baud);
                $this->readSerialLoop();
            } catch (\Throwable $exception) {
                $this->components->error($exception->getMessage());
                $this->recordBridgeEvent(
                    'bridge.serial_connection_failed',
                    "El listener serial de {$readerLabel} encontro un error y va a reintentar.",
                    [
                        'device' => $device,
                        'baud' => $baud,
                        'reader_model' => $this->readerProfile,
                        'exception' => $exception->getMessage(),
                    ],
                    'error'
                );
            } finally {
                $this->closeSerialPort();
            }

            sleep($reconnectDelay);
        }
    }

    private function resolveDevice(): string
    {
        $configured = trim((string) ($this->option('device') ?: env('RFID_SERIAL_DEVICE', 'auto')));
        if ($configured !== '' && strcasecmp($configured, 'auto') !== 0) {
            $this->deviceSelectionMode = 'manual';

            return $configured;
        }

        $detected = $this->autodetectSerialDevice();
        if ($detected !== null) {
            $this->deviceSelectionMode = 'autodetected';

            return $detected;
        }

        $this->deviceSelectionMode = 'fallback';

        return DIRECTORY_SEPARATOR === '\\' ? 'COM7' : '/dev/ttyUSB0';
    }

    private function resolveBaud(): int
    {
        return max(1200, (int) ($this->option('baud') ?: env('RFID_SERIAL_BAUD', 115200)));
    }

    private function resolveReaderProfile(): string
    {
        $reader = trim((string) (
            $this->option('reader')
            ?: env('RFID_READER_DRIVER', env('RFID_READER_MODEL', 'mfrc522'))
        ));
        $reader = strtolower($reader);

        return match ($reader) {
            'mfrc522', 'pn532' => $reader,
            default => 'auto',
        };
    }

    private function resolveSource(): string
    {
        $defaultPrefix = $this->readerProfile === 'auto' ? 'nfc-reader' : $this->readerProfile;
        $default = $defaultPrefix . '-' . (gethostname() ?: 'reader');
        $source = trim((string) ($this->option('source') ?: env('RFID_READER_SOURCE', $default)));

        return $source !== '' ? $source : $default;
    }

    private function readerLabel(): string
    {
        return match ($this->readerProfile) {
            'mfrc522' => 'MFRC522',
            'pn532' => 'PN532',
            default => 'lector NFC',
        };
    }

    private function autodetectSerialDevice(): ?string
    {
        return DIRECTORY_SEPARATOR === '\\'
            ? $this->autodetectWindowsSerialDevice()
            : $this->autodetectUnixSerialDevice();
    }

    private function autodetectWindowsSerialDevice(): ?string
    {
        $command = <<<'PS'
powershell -NoProfile -ExecutionPolicy Bypass -Command "$utf8NoBom = New-Object System.Text.UTF8Encoding($false); $OutputEncoding = [Console]::OutputEncoding = $utf8NoBom; Get-PnpDevice -Class Ports | Where-Object { $_.FriendlyName -and $_.FriendlyName -notmatch 'Bluetooth' } | ForEach-Object { if ($_.FriendlyName -match '\((COM\d+)\)') { '{0}`t{1}`t{2}`t{3}' -f $_.Status, $Matches[1], $_.FriendlyName, $_.InstanceId } }"
PS;

        $output = shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        $candidates = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$status, $port, $friendlyName, $instanceId] = array_pad(explode("\t", $line, 4), 4, '');
            $port = strtoupper(trim($port));
            if (preg_match('/^COM\d+$/', $port) !== 1) {
                continue;
            }

            $candidates[] = [
                'status' => trim($status),
                'port' => $port,
                'friendly_name' => trim($friendlyName),
                'instance_id' => trim($instanceId),
            ];
        }

        foreach (RfidSerialSupport::sortWindowsPortCandidates($candidates, $this->readerProfile) as $candidate) {
            if (!empty($candidate['port'])) {
                return $candidate['port'];
            }
        }

        return null;
    }

    private function autodetectUnixSerialDevice(): ?string
    {
        $patterns = [
            '/dev/serial/by-id/*',
            '/dev/ttyUSB*',
            '/dev/ttyACM*',
            '/dev/serial0',
        ];

        foreach ($patterns as $pattern) {
            $matches = glob($pattern) ?: [];
            sort($matches);
            foreach ($matches as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function openSerialPort(string $device, int $baud): void
    {
        $normalizedDevice = $this->normalizeDevicePath($device);

        if (DIRECTORY_SEPARATOR === '\\') {
            $this->openWindowsSerialProcess($device, $baud);
        } else {
            $this->configureSerialPort($normalizedDevice, $baud);

            $handle = @fopen($normalizedDevice, 'r+b');
            if (!is_resource($handle)) {
                throw new \RuntimeException("No se pudo abrir el puerto serial {$device}.");
            }

            stream_set_blocking($handle, false);
            stream_set_timeout($handle, 0, 200000);

            $this->serialHandle = $handle;
        }
        $this->serialBuffer = '';
        $this->lastByteAtMs = 0;

        $this->recordBridgeEvent(
            'bridge.serial_connected',
            "Puerto serial del {$this->readerLabel()} abierto.",
            [
                'device' => $device,
                'device_path' => $normalizedDevice,
                'baud' => $baud,
                'reader_model' => $this->readerProfile,
                'device_selection' => $this->deviceSelectionMode,
            ]
        );
    }

    private function normalizeDevicePath(string $device): string
    {
        $trimmed = trim($device);
        if ($trimmed === '') {
            return $device;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '\\\\.\\')) {
            return $trimmed;
        }

        if (preg_match('/^COM\d+$/i', $trimmed) === 1) {
            return '\\\\.\\' . strtoupper($trimmed);
        }

        return $trimmed;
    }

    private function openWindowsSerialProcess(string $device, int $baud): void
    {
        $escapedDevice = $this->escapePowerShellLiteral($device);
        $escapedBaud = max(1200, $baud);
        $script = <<<'PS'
$port = New-Object System.IO.Ports.SerialPort '__DEVICE__',__BAUD__,'None',8,'one'
$port.ReadTimeout = 200
$port.Encoding = [System.Text.Encoding]::ASCII
# Many RC522/CH340 bridges reset or behave erratically when DTR/RTS are asserted.
$port.DtrEnable = $false
$port.RtsEnable = $false
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stdout = New-Object System.IO.StreamWriter([Console]::OpenStandardOutput(), $utf8NoBom)
$stdout.AutoFlush = $true
try {
    $port.Open()
    while ($true) {
        $chunk = $port.ReadExisting()
        if ($chunk) {
            $stdout.Write($chunk)
            $stdout.Flush()
        }
        Start-Sleep -Milliseconds 20
    }
} finally {
    if ($port.IsOpen) {
        $port.Close()
    }
}
PS;

        $script = str_replace(
            ['__DEVICE__', '__BAUD__'],
            [$escapedDevice, (string) $escapedBaud],
            $script
        );

        $encodedScript = base64_encode(iconv('UTF-8', 'UTF-16LE', $script));
        $command = [
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-EncodedCommand',
            $encodedScript,
        ];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException("No se pudo abrir el puerto serial {$device}.");
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        usleep(400000);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $errorOutput = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            $cleanError = $this->cleanPowerShellError($errorOutput);
            $suffix = trim($cleanError) !== '' ? ' ' . trim($cleanError) : '';
            throw new \RuntimeException("No se pudo abrir el puerto serial {$device}.{$suffix}");
        }

        $this->serialProcess = $process;
        $this->serialHandle = $pipes[1];
    }

    private function escapePowerShellLiteral(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function cleanPowerShellError(string $errorOutput): string
    {
        $errorOutput = preg_replace('/#<\s*CLIXML.*/s', '', $errorOutput) ?? $errorOutput;
        $errorOutput = preg_replace('/\s+/', ' ', $errorOutput) ?? $errorOutput;

        return trim($errorOutput);
    }

    private function configureSerialPort(string $device, int $baud): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return;
        }

        $escapedDevice = escapeshellarg($device);
        $escapedBaud = escapeshellarg((string) $baud);
        $commands = [
            "stty -F {$escapedDevice} {$escapedBaud} cs8 -cstopb -parenb -icanon min 1 time 1",
            "stty -f {$escapedDevice} {$escapedBaud} cs8 -cstopb -parenb -icanon min 1 time 1",
        ];

        foreach ($commands as $command) {
            @exec($command, $output, $code);
            if ($code === 0) {
                return;
            }
        }

        $this->recordBridgeEvent(
            'bridge.serial_config_warning',
            'No se pudo configurar el puerto serial con stty; se intentara con la configuracion actual del sistema.',
            [
                'device' => $device,
                'baud' => $baud,
            ],
            'warning'
        );
    }

    private function readSerialLoop(): void
    {
        while (is_resource($this->serialHandle)) {
            $chunk = @fread($this->serialHandle, 4096);
            if ($chunk === false) {
                throw new \RuntimeException('La lectura del puerto serial fallo.');
            }

            if ($chunk === '') {
                $this->flushBufferedFrameIfIdle();
                usleep(100000);
                continue;
            }

            $chunk = RfidSerialSupport::normalizeIncomingSerialChunk($chunk);
            if ($chunk === '') {
                continue;
            }

            $this->serialBuffer .= $chunk;
            $this->lastByteAtMs = (int) floor(microtime(true) * 1000);

            while (($lineBreakPosition = $this->findLineBreakPosition($this->serialBuffer)) !== null) {
                $line = substr($this->serialBuffer, 0, $lineBreakPosition);
                $this->serialBuffer = ltrim(substr($this->serialBuffer, $lineBreakPosition + 1), "\r\n");
                $this->processSerialLine($line);
            }
        }
    }

    private function flushBufferedFrameIfIdle(): void
    {
        if ($this->serialBuffer === '' || $this->lastByteAtMs === 0) {
            return;
        }

        $frameIdleMs = max(20, (int) $this->option('frame-idle-ms'));
        $nowMs = (int) floor(microtime(true) * 1000);
        if (($nowMs - $this->lastByteAtMs) < $frameIdleMs) {
            return;
        }

        $line = $this->serialBuffer;
        $this->serialBuffer = '';
        $this->lastByteAtMs = 0;
        $this->processSerialLine($line);
    }

    private function findLineBreakPosition(string $buffer): ?int
    {
        $positions = array_filter([
            strpos($buffer, "\n"),
            strpos($buffer, "\r"),
        ], static fn ($position) => $position !== false);

        if ($positions === []) {
            return null;
        }

        return min($positions);
    }

    private function processSerialLine(string $line): void
    {
        $rawLine = trim($line);
        if ($rawLine === '') {
            return;
        }

        if (!$this->looksPrintable($rawLine)) {
            if ((bool) $this->option('debug')) {
                $hexPreview = strtoupper(bin2hex(substr($rawLine, 0, 16)));
                $this->line("Descartado binario: {$hexPreview}");
            }

            return;
        }

        $uid = RfidSerialSupport::extractUidCandidate($rawLine);
        if ($uid === null) {
            if ((bool) $this->option('debug')) {
                $this->line("Descartado: {$rawLine}");
            }

            return;
        }

        $nowMs = (int) floor(microtime(true) * 1000);
        $dedupeWindowMs = max(0, (int) $this->option('dedupe-ms'));
        if ($this->lastUid === $uid && ($nowMs - $this->lastUidAtMs) < $dedupeWindowMs) {
            if ((bool) $this->option('debug')) {
                $this->line("Duplicado ignorado: {$uid}");
            }

            return;
        }

        $this->lastUid = $uid;
        $this->lastUidAtMs = $nowMs;

        $this->recordBridgeEvent(
            'bridge.uid_read',
            "UID leido desde {$this->readerLabel()} por serial.",
            [
                'uid_raw' => $rawLine,
                'uid' => $uid,
                'reader_model' => $this->readerProfile,
            ]
        );

        $result = $this->processor->process($uid, [
            'source' => $this->source,
            'reader_model' => $this->readerProfile,
            'bridge_session_uuid' => $this->sessionUuid,
            'bridge_transport' => 'serial',
            'bridge_mode' => 'direct',
            'bridge_host' => gethostname() ?: php_uname('n'),
            'bridge_pid' => getmypid(),
            'ip' => 'cli',
        ]);

        $status = (string) ($result['body']['status'] ?? 'unknown');
        $message = (string) ($result['body']['message'] ?? '');
        $this->line("[{$status}] {$uid} {$message}");
    }
    private function looksPrintable(string $line): bool
    {
        return preg_match('/^[\x20-\x7E]+$/', $line) === 1;
    }

    private function closeSerialPort(): void
    {
        if (is_resource($this->serialHandle)) {
            fclose($this->serialHandle);
        }

        if (is_resource($this->serialProcess)) {
            @proc_terminate($this->serialProcess);
            @proc_close($this->serialProcess);
        }

        $this->serialHandle = null;
        $this->serialProcess = null;
        $this->serialBuffer = '';
        $this->lastByteAtMs = 0;
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn () => $this->shutdownGracefully());
        pcntl_signal(SIGTERM, fn () => $this->shutdownGracefully());
    }

    private function shutdownGracefully(): never
    {
        $this->recordBridgeEvent(
            'bridge.stopped',
            "Listener serial de {$this->readerLabel()} detenido.",
            [],
            'info',
            'closed'
        );

        $this->closeSerialPort();
        exit(self::SUCCESS);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordBridgeEvent(
        string $eventType,
        string $message,
        array $payload = [],
        string $level = 'info',
        string $sessionStatus = 'active',
    ): void {
        $metadata = [
            'bridge_transport' => 'serial',
            'bridge_mode' => 'direct',
            'bridge_host' => gethostname() ?: php_uname('n'),
            'bridge_pid' => getmypid(),
            'reader_model' => $this->readerProfile,
        ];

        $this->telemetry->recordEvent([
            'session_uuid' => $this->sessionUuid,
            'session_type' => 'bridge',
            'source' => $this->source,
            'status' => $sessionStatus,
            'timeout_seconds' => 60,
            'channel' => 'bridge',
            'event_type' => $eventType,
            'level' => $level,
            'message' => $message,
            'payload' => $payload,
            'metadata' => $metadata,
        ]);

        $this->appendBridgeLog([
            'timestamp' => now()->toIso8601String(),
            'event_type' => $eventType,
            'level' => $level,
            'message' => $message,
            'source' => $this->source,
            'bridge_session_uuid' => $this->sessionUuid,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function appendBridgeLog(array $event): void
    {
        $path = storage_path('logs/rfid-bridge-telemetry.jsonl');
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }
}
