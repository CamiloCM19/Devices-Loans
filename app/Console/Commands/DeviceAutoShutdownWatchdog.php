<?php

namespace App\Console\Commands;

use App\Models\HardwareTelemetryEvent;
use App\Models\HardwareTelemetrySession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

class DeviceAutoShutdownWatchdog extends Command
{
    protected $signature = 'device:auto-shutdown
        {--idle-minutes= : Minutos maximos sin actividad antes de apagar}
        {--poll-seconds= : Segundos entre verificaciones}
        {--boot-grace-minutes= : Minutos minimos de gracia al arrancar}
        {--shutdown-command= : Comando del sistema para apagar el equipo}
        {--dry-run : Reporta el apagado pero no ejecuta el comando}
        {--once : Ejecuta una sola verificacion y termina}';

    protected $description = 'Apaga automaticamente el dispositivo cuando la app no registra actividad durante un tiempo configurable.';

    public function handle(): int
    {
        $startedAt = now();
        $idleMinutes = max(1, $this->resolveIntOption('idle-minutes', 'DEVICE_AUTO_SHUTDOWN_IDLE_MINUTES', 30));
        $pollSeconds = max(5, $this->resolveIntOption('poll-seconds', 'DEVICE_AUTO_SHUTDOWN_POLL_SECONDS', 30));
        $bootGraceMinutes = max(0, $this->resolveIntOption('boot-grace-minutes', 'DEVICE_AUTO_SHUTDOWN_BOOT_GRACE_MINUTES', 10));
        $shutdownCommand = trim((string) $this->resolveStringOption(
            'shutdown-command',
            'DEVICE_AUTO_SHUTDOWN_COMMAND',
            $this->defaultShutdownCommand()
        ));
        $dryRun = (bool) $this->option('dry-run');
        $runOnce = (bool) $this->option('once');

        $this->components->info('Watchdog de autoapagado iniciado.');
        $this->line("Inactividad maxima: {$idleMinutes} min");
        $this->line("Sondeo: {$pollSeconds} s");
        $this->line("Gracia de arranque: {$bootGraceMinutes} min");
        $this->line('Modo: ' . ($dryRun ? 'dry-run' : 'activo'));

        do {
            $evaluation = $this->evaluate($startedAt, $idleMinutes, $bootGraceMinutes);

            if ($evaluation['status'] === 'missing_tables') {
                $this->warn('Las tablas de telemetria aun no existen; se omite esta verificacion.');
            } elseif ($evaluation['status'] === 'grace_period') {
                $remaining = $evaluation['grace_seconds_remaining'] ?? 0;
                $this->line('En gracia de arranque. Falta ' . $this->humanizeSeconds($remaining) . '.');
            } elseif ($evaluation['should_shutdown']) {
                $idleFor = $this->humanizeSeconds((int) ($evaluation['idle_seconds'] ?? 0));
                $latest = $evaluation['latest_activity_at']
                    ? Carbon::parse($evaluation['latest_activity_at'])->toDateTimeString()
                    : 'sin actividad aun';

                $this->warn("Dispositivo inactivo por {$idleFor}. Ultima actividad: {$latest}.");

                if ($dryRun) {
                    $this->components->info("Dry-run: se habria ejecutado `{$shutdownCommand}`.");
                    return self::SUCCESS;
                }

                $result = Process::timeout(15)->run($shutdownCommand);
                if ($result->successful()) {
                    $this->components->info('Comando de apagado enviado correctamente.');
                    return self::SUCCESS;
                }

                $this->components->error('No se pudo ejecutar el apagado automatico.');
                if (trim($result->errorOutput()) !== '') {
                    $this->line(trim($result->errorOutput()));
                }
            } else {
                $idleFor = $this->humanizeSeconds((int) ($evaluation['idle_seconds'] ?? 0));
                $latest = $evaluation['latest_activity_at']
                    ? Carbon::parse($evaluation['latest_activity_at'])->toDateTimeString()
                    : 'sin actividad aun';

                $this->line("Actividad reciente detectada. Idle actual: {$idleFor}. Ultima actividad: {$latest}.");
            }

            if ($runOnce) {
                return self::SUCCESS;
            }

            sleep($pollSeconds);
        } while (true);
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    private function evaluate(Carbon $startedAt, int $idleMinutes, int $bootGraceMinutes): array
    {
        if (!Schema::hasTable('hardware_telemetry_sessions') || !Schema::hasTable('hardware_telemetry_events')) {
            return [
                'status' => 'missing_tables',
                'should_shutdown' => false,
            ];
        }

        $now = now();
        $graceEndsAt = (clone $startedAt)->addMinutes($bootGraceMinutes);
        if ($bootGraceMinutes > 0 && $now->lt($graceEndsAt)) {
            return [
                'status' => 'grace_period',
                'should_shutdown' => false,
                'grace_seconds_remaining' => $now->diffInSeconds($graceEndsAt),
            ];
        }

        $latestEventAt = HardwareTelemetryEvent::query()->max('occurred_at');
        $latestSessionAt = HardwareTelemetrySession::query()->max('last_seen_at');

        $latestActivityAt = collect([$latestEventAt, $latestSessionAt])
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->sortByDesc(fn (Carbon $value) => $value->timestamp)
            ->first();

        $reference = $latestActivityAt ?? $startedAt;
        $idleSeconds = $reference->diffInSeconds($now);

        return [
            'status' => 'evaluated',
            'should_shutdown' => $idleSeconds >= ($idleMinutes * 60),
            'idle_seconds' => $idleSeconds,
            'latest_activity_at' => $latestActivityAt?->toIso8601String(),
        ];
    }

    private function defaultShutdownCommand(): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return 'shutdown /s /t 0';
        }

        return is_file('/sbin/shutdown') ? '/sbin/shutdown -h now' : 'shutdown -h now';
    }

    private function humanizeSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $remainingSeconds);
        }

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    }

    private function resolveIntOption(string $option, string $envKey, int $default): int
    {
        $value = $this->option($option);
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        return (int) env($envKey, $default);
    }

    private function resolveStringOption(string $option, string $envKey, string $default): string
    {
        $value = $this->option($option);
        if ($value !== null && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return trim((string) env($envKey, $default));
    }
}
