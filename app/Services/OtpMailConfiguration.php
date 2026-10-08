<?php

namespace App\Services;

class OtpMailConfiguration
{
    public function demoEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('otp.demo_mode', false);
    }

    public function usesResendApi(): bool
    {
        return ! app()->environment(['local', 'testing']) || config('otp.delivery_driver') !== 'laravel';
    }

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
