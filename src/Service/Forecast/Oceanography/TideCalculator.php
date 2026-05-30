<?php

declare(strict_types=1);

namespace App\Service\Forecast\Oceanography;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Approximate tide height for Portuguese ports, using a 4-constituent
 * harmonic model (M2, S2, K1, O1).
 *
 * The published Greenwich phase lags (`G` in the constants table) are
 * combined with each constituent's equilibrium-tide argument `V_0`,
 * evaluated at the epoch from the Sun and Moon mean longitudes. That
 * makes the spring–neap relation between M2 and S2 correct for the year,
 * and lines the times of high water up with the official tide tables to
 * within a few minutes. Nodal modulation (`f`, `u`) is omitted — its
 * effect is small compared to the 4-constituent truncation.
 *
 * Output is in meters above chart datum (zero hidrográfico) when each
 * port carries its `z0` offset; otherwise relative to whatever datum
 * the supplied constants reference.
 */
final class TideCalculator
{
    private const SPEED_DEG_PER_HOUR = [
        'M2' => 28.9841042,
        'S2' => 30.0000000,
        'K1' => 15.0410686,
        'O1' => 13.9430356,
    ];

    /** @var array<string, float> Equilibrium argument V_0 at the epoch, in degrees, per constituent. */
    private readonly array $equilibriumArg;

    /**
     * @param array<string, array{name: string, lat: float, lon: float, z0: float, constants: array<string, array{H: float, G: float}>}> $ports
     */
    public function __construct(
        private readonly array $ports,
        private readonly DateTimeImmutable $epoch,
    ) {
        $this->equilibriumArg = self::equilibriumArguments($epoch);
    }

    /**
     * Load constants from an explicit file path supplied at runtime (e.g. via
     * the TIDE_CONSTANTS_FILE environment variable).
     *
     * The file must be a JSON object whose top-level keys are port slugs.
     * current UTC year is used.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(
                sprintf('Tide constants file not found: %s', $path),
            );
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Failed to read tide constants from %s.', $path));
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Malformed tide constants in %s: %s', $path, $e->getMessage()), 0, $e);
        }

        $year = isset($decoded['year']) && is_int($decoded['year'])
            ? $decoded['year']
            : (int) (new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');

        unset($decoded['year']);

        /** @var array<string, array{name: string, lat: float, lon: float, z0: float, constants: array<string, array{H: float, G: float}>}> $ports */
        $ports = $decoded;

        $epoch = new DateTimeImmutable(sprintf('%d-01-01T00:00:00Z', $year));

        return new self($ports, $epoch);
    }

    /**
     * Load constants for the given year from a directory of bundled JSON files.
     * Intended for tests and local development; production should use fromFile()
     * with a path supplied via the TIDE_CONSTANTS_FILE environment variable.
     */
    public static function fromYear(int $year, ?string $dataDir = null): self
    {
        $dataDir ??= __DIR__ . '/data';
        $path = sprintf('%s/tide_constants_%d.json', rtrim($dataDir, '/'), $year);

        return self::fromFile($path);
    }

    /**
     * Approximate tide height (meters above chart datum / zero hidrográfico)
     * at the port nearest the given coordinates, for the given UTC instant.
     *
     * eta(t) = z0 + sum_i H_i * cos((omega_i * dt_hours + V_0_i - g_i) * pi/180)
     *
     * V_0 is the equilibrium-tide argument at the epoch, computed once
     * in the constructor; g_i is the Greenwich phase lag from the
     * constants table (the "G" field); z0 is the port's mean water above
     * chart datum.
     */
    public function heightForNearLocationAt(float $lat, float $lon, DateTimeImmutable $tUtc): float
    {
        $slug = $this->nearestPort($lat, $lon);
        if ($slug === null) {
            throw new RuntimeException('No tide ports available.');
        }

        $dtHours = ($tUtc->getTimestamp() - $this->epoch->getTimestamp()) / 3600.0;

        $eta = $this->ports[$slug]['z0'];
        foreach ($this->ports[$slug]['constants'] as $name => $c) {
            if (!isset(self::SPEED_DEG_PER_HOUR[$name])) {
                continue;
            }
            $v0 = $this->equilibriumArg[$name] ?? 0.0;
            $angleDeg = self::SPEED_DEG_PER_HOUR[$name] * $dtHours + $v0 - $c['G'];
            $eta += $c['H'] * cos(deg2rad($angleDeg));
        }

        return $eta;
    }

    private function nearestPort(float $lat, float $lon): ?string
    {
        $bestSlug = null;
        $bestDistance = INF;

        foreach ($this->ports as $slug => $port) {
            $d = self::haversineKm($lat, $lon, $port['lat'], $port['lon']);
            if ($d < $bestDistance) {
                $bestDistance = $d;
                $bestSlug = $slug;
            }
        }

        return $bestSlug;
    }

    private static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadiusKm * asin(sqrt($a));
    }

    /**
     * Equilibrium tide arguments V_0 (degrees) at the given UTC instant.
     * Derived from the Sun/Moon mean longitudes via Doodson's relations;
     * nodal modulation is omitted.
     *
     * @return array<string, float>
     */
    private static function equilibriumArguments(DateTimeImmutable $epoch): array
    {
        // Julian Date (UT) and Julian centuries since J2000.0.
        $jd = 2440587.5 + $epoch->getTimestamp() / 86400.0;
        $T = ($jd - 2451545.0) / 36525.0;

        // Mean longitudes (degrees, mod 360) — Schureman / Simon et al.
        $s = self::mod360(218.3164477 + 481267.88123421 * $T - 0.0015786 * ($T ** 2));
        $h = self::mod360(280.46646   + 36000.76983    * $T + 0.0003032 * ($T ** 2));

        // Hour angle of mean Sun: 180° at midnight UTC, advances 15°/hour.
        $hourOfDay = (float) $epoch->format('H')
                   + (float) $epoch->format('i') / 60.0
                   + (float) $epoch->format('s') / 3600.0;
        $Th = self::mod360(180.0 + 15.0 * $hourOfDay);

        // Doodson combinations: K1/O1 carry ±90° from the sin→cos sign convention.
        return [
            'M2' => self::mod360(2 * $Th + 2 * $h - 2 * $s),
            'S2' => self::mod360(2 * $Th),
            'K1' => self::mod360($Th + $h - 90.0),
            'O1' => self::mod360($Th - 2 * $s + $h + 90.0),
        ];
    }

    private static function mod360(float $x): float
    {
        $x = fmod($x, 360.0);

        return $x < 0.0 ? $x + 360.0 : $x;
    }
}
