<?php

namespace App\Services;

use Illuminate\Support\Collection;

class BackupCatalog
{
    /**
     * Return snapshots from the configured backup provider.
     *
     * COMPASS does not have a reviewed backup provider yet. This explicit
     * empty adapter keeps the UI honest and provides one replacement point
     * when generation, protected downloads, and restoration are approved.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function snapshots(): Collection
    {
        return collect();
    }

    /**
     * Operations remain unavailable until a real provider and authorization
     * policy are connected.
     *
     * @return array{create: bool, download: bool, restore: bool}
     */
    public function capabilities(): array
    {
        return [
            'create' => false,
            'download' => false,
            'restore' => false,
        ];
    }
}
