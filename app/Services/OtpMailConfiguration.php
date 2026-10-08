<?php

namespace App\Services;

class OtpMailConfiguration
{
    public function canDeliver(string $name, array $seen = []): bool
    {
        if ($name === '' || in_array($name, $seen, true)) {
            return false;
        }
        $config = config('mail.mailers.'.$name, []);
        $transport = $config['transport'] ?? '';
        if (in_array($transport, ['', 'log', 'array'], true)) {
            return false;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $config['mailers'] ?? [];
            if (! $children) {
                return false;
            }
            foreach ($children as $child) {
                if (! $this->canDeliver($child, [...$seen, $name])) {
                    return false;
                }
            }
        }

        return true;
    }
}
