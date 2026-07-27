<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Carer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'age',
        'phone',
        'email',
        'color_hex',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'carer_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function pocketForMonth(?Carbon $month = null): float
    {
        $month ??= now();

        return (float) $this->services()
            ->where('status', 'DONE')
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->sum('hourly_rate');
    }

    public function getCurrentStatusAttribute(): string
    {
        $active = $this->services()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->where('status', '!=', 'CANCELLED')
            ->exists();

        return $active ? 'Em serviço' : 'Livre';
    }

    public static function generateUniqueColor(): string
    {
        $index = static::count();
        $goldenAngle = 137.508;

        for ($attempt = 0; $attempt < 720; $attempt++) {
            $hue = fmod(($index + $attempt) * $goldenAngle, 360);
            $color = static::hslToHex($hue, 68, 46);

            if (! static::where('color_hex', $color)->exists()) {
                return $color;
            }
        }

        throw new \RuntimeException('Nao foi possivel gerar uma cor unica.');
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }
}
