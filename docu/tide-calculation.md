# Tide Calculation

This document explains how the tide heights and daily extrema displayed on the sea state page are computed.

---

## Overview

The implementation uses the **harmonic method**, which is the industry-standard approach for tide prediction. The water level at any point in time is expressed as the superposition of a small number of periodic oscillations (constituents), each driven by a specific astronomical cycle. The parameters for each constituent at each port (amplitude and phase) are stored in a JSON constants file, and the tide height is computed by evaluating the harmonic sum at the requested time.

---

## Harmonic Constituents

Four tidal constituents are used:

| Symbol | Name | Speed (°/hour) | Period | Driven by |
|--------|------|---------------|--------|-----------|
| **M2** | Principal lunar semidiurnal | 28.9841042 | ~12 h 25 min | Moon's gravity |
| **S2** | Principal solar semidiurnal  | 30.0000000 | exactly 12 h  | Sun's gravity |
| **K1** | Lunisolar diurnal            | 15.0410686 | ~23 h 56 min  | Moon + Sun declination |
| **O1** | Principal lunar diurnal      | 13.9430356 | ~25 h 49 min  | Moon's declination |

M2 and S2 together produce the **spring–neap cycle**: when they are in phase (new/full moon) tides are higher (spring), when out of phase (quarter moon) tides are lower (neap). K1 and O1 introduce the **diurnal inequality** — the difference in height between the two daily high tides.

Four constituents capture the dominant behaviour of Portuguese Atlantic tides, which are predominantly semidiurnal (two highs and two lows per day). Higher-order constituents (M4, MS4, etc.) and nodal modulation corrections (`f`, `u`) are omitted; their combined effect is small relative to the 4-constituent truncation error.

---

## Port Constants File

Port-specific harmonic constants are **not included in the repository** for licence reasons. You must supply your own file and point to it via the `TIDE_CONSTANTS_FILE` environment variable:

```dotenv
TIDE_CONSTANTS_FILE=/path/to/tide_constants.json
```

When the variable is unset or empty, the tide panels are silently hidden and the rest of the sea-state page continues to work normally.

### File format

The file must be a JSON object. The top-level keys are URL-friendly port slugs. The current UTC year is used.

```json
{
  "lisboa": {
    "name": "Lisboa",
    "lat": 38.7073,
    "lon": -9.1363,
    "z0": 2.08,
    "constants": {
      "M2": { "H": 0.893, "G": 84.3 },
      "S2": { "H": 0.317, "G": 114.1 },
      "K1": { "H": 0.071, "G": 77.1 },
      "O1": { "H": 0.059, "G": 336.6 }
    }
  }
}
```

Each port entry has:

- **`lat`** / **`lon`** — geographic coordinates used for nearest-port lookup.
- **`z0`** — mean water level above chart datum (zero hidrográfico) in metres. Adding `z0` makes computed heights comparable to the official Portuguese tide tables, which reference chart datum (CD).
- **`constants`** — per-constituent harmonic parameters:
  - **`H`** — amplitude in metres (half the range of that constituent's contribution).
  - **`G`** — **Greenwich phase lag** in degrees: the phase delay between the equilibrium tide argument and the observed tide at this port, referred to the Greenwich meridian.

---

## The Height Formula

The tide height `η(t)` at UTC instant `t` is:

```
η(t) = z0 + Σᵢ Hᵢ · cos( ωᵢ · Δt + V₀ᵢ − Gᵢ )
```

Where:

- **`z0`** — port's mean level above chart datum (metres).
- **`Hᵢ`** — amplitude of constituent `i` (metres).
- **`ωᵢ`** — speed of constituent `i` in degrees per hour.
- **`Δt`** — elapsed hours since the **epoch** (1 January of the current year, 00:00 UTC).
- **`V₀ᵢ`** — **equilibrium tide argument** of constituent `i` at the epoch (degrees). This is the phase the constituent would have at Greenwich if the ocean responded instantaneously to the tide-generating force. It corrects for the starting position of the Sun and Moon at the beginning of the year.
- **`Gᵢ`** — Greenwich phase lag from the constants table (degrees).

The argument inside the cosine, `ωᵢ · Δt + V₀ᵢ − Gᵢ`, advances continuously with time. When it equals zero (mod 360°) the constituent is at its maximum contribution.

In code (`TideCalculator::heightForNearLocationAt`):

```php
$dtHours = ($tUtc->getTimestamp() - $this->epoch->getTimestamp()) / 3600.0;

$eta = $this->ports[$slug]['z0'];
foreach ($this->ports[$slug]['constants'] as $name => $c) {
    $angleDeg = self::SPEED_DEG_PER_HOUR[$name] * $dtHours + $v0 - $c['G'];
    $eta += $c['H'] * cos(deg2rad($angleDeg));
}
```

---

## Equilibrium Tide Arguments (V₀)

The equilibrium arguments are computed once per run from Sun and Moon mean longitudes at the epoch, following Doodson's classical combinations and the IAU Simon et al. (1994) coefficients:

```
T  = (JD − 2451545.0) / 36525         # Julian centuries since J2000.0
s  = 218.3164477 + 481267.88123421·T  # Moon's mean longitude
h  = 280.46646   + 36000.76983·T      # Sun's mean longitude
Th = 180° + 15°·hour_of_day           # Hour angle of the mean Sun (0° at noon, 180° at midnight)
```

Doodson argument combinations:

| Constituent | V₀ |
|-------------|----|
| M2 | `2·Th + 2·h − 2·s` |
| S2 | `2·Th` |
| K1 | `Th + h − 90°` |
| O1 | `Th − 2·s + h + 90°` |

The ±90° offsets on K1 and O1 arise from the standard sign convention: equilibrium theory expresses these diurnal constituents as sine functions, while the harmonic method uses cosines, requiring a 90° phase shift.

By applying V₀, the M2 and S2 constituents are correctly phased relative to each other at the start of the year, which ensures the spring–neap cycle timing is accurate.

**Nodal modulation** (`f`, `u` corrections from the 18.6-year lunar node cycle) is deliberately omitted. Its effect on amplitude and phase is typically less than 5%, which is well within the uncertainty already introduced by using only 4 constituents.

---

## Nearest-Port Selection

When a request arrives with a latitude and longitude (taken from the sea forecast data), the calculator selects the port whose coordinates are closest to the request point using the **Haversine formula**:

```
a = sin²(ΔΦ/2) + cos(Φ₁)·cos(Φ₂)·sin²(Δλ/2)
d = 2·R·arcsin(√a)          # R = 6371 km
```

This correctly accounts for the spherical geometry of the Earth; a flat-Earth distance approximation would be inaccurate at the scale of Portugal's coastline.

---

## Daily Range and Extrema

### Sampling — `TideDailyRange`

To produce a tide curve for a given day, `TideDailyRange::forDay()` samples the height every **10 minutes** across a 24-hour window starting at local midnight (144 samples). Each sample is recorded as `{t, h}` where `t` is the Unix timestamp in milliseconds (ready for charting libraries) and `h` is the height in metres rounded to 4 decimal places.

The 10-minute interval is a good compromise:
- It is fine enough to locate high/low water times to within ±5 minutes.
- It is coarse enough that the 144 evaluations of the harmonic sum complete in negligible time.

The series minimum and maximum are returned alongside the series itself, providing the day's tidal range at a glance.

### Extrema Detection — `TideExtrema`

`TideExtrema::findInSeries()` finds local maxima (high tides) and local minima (low tides) in the sampled series using a simple **three-point comparison**:

- Point `i` is a **high** if `h[i] > h[i−1]` and `h[i] > h[i+1]`.
- Point `i` is a **low** if `h[i] < h[i−1]` and `h[i] < h[i+1]`.

For a semidiurnal tidal regime (the norm on the Portuguese coast) this produces 4 extrema per day — 2 highs and 2 lows. The timing accuracy is bounded by the sampling interval (±5 minutes).

---

## Accuracy and Limitations

| Factor | Impact |
|--------|--------|
| 4-constituent model | ±15–30 cm amplitude error near spring tides; timing error ±15–30 min |
| No nodal modulation | ±5% amplitude error over the 18.6-year node cycle |
| Nearest-port assignment | Correct for open-coast sea areas; may be less representative in estuaries |
| Extrema timing via sampling | ±5 minutes (half the 10-minute sample interval) |
| Meteorological effects | Not modelled — storm surge, seiches, and atmospheric pressure are ignored |

The implementation is intended as an **indicative guide** for recreational sea users, not for navigation or safety-critical decisions.
