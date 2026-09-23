<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BackupRestoreController extends Controller
{
    /**
     * Display the protected backup catalog and safe operation workflows.
     */
    public function __invoke(Request $request, BackupCatalog $backupCatalog): View
    {
        $loadFailed = false;

        try {
            $capabilities = $backupCatalog->capabilities();
            $snapshots = $backupCatalog->snapshots()
                ->map(fn (array $snapshot): array => $this->snapshotViewModel($snapshot, $capabilities))
                ->sortByDesc('createdAtTimestamp')
                ->values();
        } catch (Throwable $exception) {
            report($exception);
            $loadFailed = true;
            $capabilities = $this->unavailableCapabilities();
            $snapshots = collect();
        }

        return view('admin.backup-restore.index', [
            'admin' => $request->user(),
            'snapshots' => $snapshots,
            'capabilities' => $capabilities,
            'loadFailed' => $loadFailed,
            'backendAvailable' => collect($capabilities)->contains(true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{create: bool, download: bool, restore: bool}  $capabilities
     * @return array<string, mixed>
     */
    private function snapshotViewModel(array $snapshot, array $capabilities): array
    {
        $createdAt = CarbonImmutable::parse((string) $snapshot['createdAt']);
        $status = in_array($snapshot['status'] ?? null, ['completed', 'running', 'failed', 'restoring'], true)
            ? $snapshot['status']
            : 'failed';
        $sizeBytes = isset($snapshot['sizeBytes']) ? max(0, (int) $snapshot['sizeBytes']) : null;
        $downloadUrl = $capabilities['download'] && isset($snapshot['downloadUrl'])
            ? (string) $snapshot['downloadUrl']
            : null;

        return [
            'id' => Str::limit((string) $snapshot['id'], 160, ''),
            'createdAt' => $createdAt,
            'createdAtTimestamp' => $createdAt->getTimestamp(),
            'createdAtLabel' => $createdAt->format('Y-m-d H:i'),
            'createdAtTitle' => $createdAt->format('F j, Y \a\t h:i A'),
            'createdAtIso' => $createdAt->toIso8601String(),
            'sizeBytes' => $sizeBytes,
            'sizeLabel' => $this->formatBytes($sizeBytes),
            'status' => $status,
            'statusLabel' => match ($status) {
                'completed' => 'Completed',
                'running' => 'Backup in progress',
                'restoring' => 'Restore in progress',
                default => 'Backup failed',
            },
            'summary' => $createdAt->format('Y-m-d H:i').' · '.$this->formatBytes($sizeBytes),
            'downloadUrl' => $downloadUrl,
            'canDownload' => $status === 'completed' && $downloadUrl !== null,
            'canRestore' => $status === 'completed' && $capabilities['restore'],
        ];
    }

    private function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'Size unavailable';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $precision = $unit === 0 ? 0 : 1;

        return number_format($value, $precision).' '.$units[$unit];
    }

    /**
     * @return array{create: bool, download: bool, restore: bool}
     */
    private function unavailableCapabilities(): array
    {
        return [
            'create' => false,
            'download' => false,
            'restore' => false,
        ];
    }
}
