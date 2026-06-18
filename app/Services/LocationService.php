<?php

namespace App\Services;

/**
 * Resolves lat/lng to a human-readable city label and IANA timezone using a
 * nearest-neighbour lookup against the same anchor list used by EventSeeder.
 * No external API calls — all seeded coordinates are within ~0.5° of a known
 * anchor, so the closest entry is always the correct city.
 */
class LocationService
{
    /**
     * [lat, lng, city, country, timezone]
     *
     * @var array<int, array{float, float, string, string, string}>
     */
    private const ANCHORS = [
        // United States
        [40.7128, -74.0060, 'New York', 'United States', 'America/New_York'],
        [34.0522, -118.2437, 'Los Angeles', 'United States', 'America/Los_Angeles'],
        [41.8781, -87.6298, 'Chicago', 'United States', 'America/Chicago'],
        [29.7604, -95.3698, 'Houston', 'United States', 'America/Chicago'],
        [33.4484, -112.0740, 'Phoenix', 'United States', 'America/Phoenix'],
        [39.9526, -75.1652, 'Philadelphia', 'United States', 'America/New_York'],
        [29.4241, -98.4936, 'San Antonio', 'United States', 'America/Chicago'],
        [32.7157, -117.1611, 'San Diego', 'United States', 'America/Los_Angeles'],
        [32.7767, -96.7970, 'Dallas', 'United States', 'America/Chicago'],
        [37.3382, -121.8863, 'San Jose', 'United States', 'America/Los_Angeles'],
        [30.2672, -97.7431, 'Austin', 'United States', 'America/Chicago'],
        [37.7749, -122.4194, 'San Francisco', 'United States', 'America/Los_Angeles'],
        [47.6062, -122.3321, 'Seattle', 'United States', 'America/Los_Angeles'],
        [39.7392, -104.9903, 'Denver', 'United States', 'America/Denver'],
        [42.3601, -71.0589, 'Boston', 'United States', 'America/New_York'],
        [36.1699, -115.1398, 'Las Vegas', 'United States', 'America/Los_Angeles'],
        [25.7617, -80.1918, 'Miami', 'United States', 'America/New_York'],
        [33.7490, -84.3880, 'Atlanta', 'United States', 'America/New_York'],
        [38.9072, -77.0369, 'Washington DC', 'United States', 'America/New_York'],
        [36.1627, -86.7816, 'Nashville', 'United States', 'America/Chicago'],
        [45.5152, -122.6784, 'Portland', 'United States', 'America/Los_Angeles'],
        [29.9511, -90.0715, 'New Orleans', 'United States', 'America/Chicago'],
        // Canada
        [43.6532, -79.3832, 'Toronto', 'Canada', 'America/Toronto'],
        [45.5019, -73.5674, 'Montreal', 'Canada', 'America/Toronto'],
        [49.2827, -123.1207, 'Vancouver', 'Canada', 'America/Vancouver'],
        [51.0447, -114.0719, 'Calgary', 'Canada', 'America/Edmonton'],
        [45.4215, -75.6972, 'Ottawa', 'Canada', 'America/Toronto'],
        [53.5461, -113.4938, 'Edmonton', 'Canada', 'America/Edmonton'],
        [46.8139, -71.2080, 'Quebec City', 'Canada', 'America/Toronto'],
        [49.8951, -97.1384, 'Winnipeg', 'Canada', 'America/Winnipeg'],
        // Mexico
        [19.4326, -99.1332, 'Mexico City', 'Mexico', 'America/Mexico_City'],
        [20.6597, -103.3496, 'Guadalajara', 'Mexico', 'America/Mexico_City'],
        [25.6866, -100.3161, 'Monterrey', 'Mexico', 'America/Monterrey'],
        [19.0414, -98.2063, 'Puebla', 'Mexico', 'America/Mexico_City'],
        [32.5149, -117.0382, 'Tijuana', 'Mexico', 'America/Tijuana'],
        [21.1619, -86.8515, 'Cancun', 'Mexico', 'America/Cancun'],
        [20.9674, -89.5926, 'Merida', 'Mexico', 'America/Merida'],
        // Europe
        [51.5074, -0.1278, 'London', 'United Kingdom', 'Europe/London'],
        [48.8566, 2.3522, 'Paris', 'France', 'Europe/Paris'],
        [52.5200, 13.4050, 'Berlin', 'Germany', 'Europe/Berlin'],
        [40.4168, -3.7038, 'Madrid', 'Spain', 'Europe/Madrid'],
        [41.9028, 12.4964, 'Rome', 'Italy', 'Europe/Rome'],
        [52.3676, 4.9041, 'Amsterdam', 'Netherlands', 'Europe/Amsterdam'],
        [41.3851, 2.1734, 'Barcelona', 'Spain', 'Europe/Madrid'],
        [48.1351, 11.5820, 'Munich', 'Germany', 'Europe/Berlin'],
        [45.4642, 9.1900, 'Milan', 'Italy', 'Europe/Rome'],
        [48.2082, 16.3738, 'Vienna', 'Austria', 'Europe/Vienna'],
        [50.0755, 14.4378, 'Prague', 'Czech Republic', 'Europe/Prague'],
        [38.7223, -9.1393, 'Lisbon', 'Portugal', 'Europe/Lisbon'],
        [53.3498, -6.2603, 'Dublin', 'Ireland', 'Europe/Dublin'],
        [55.6761, 12.5683, 'Copenhagen', 'Denmark', 'Europe/Copenhagen'],
        [59.3293, 18.0686, 'Stockholm', 'Sweden', 'Europe/Stockholm'],
        [59.9139, 10.7522, 'Oslo', 'Norway', 'Europe/Oslo'],
        [60.1699, 24.9384, 'Helsinki', 'Finland', 'Europe/Helsinki'],
        [50.8503, 4.3517, 'Brussels', 'Belgium', 'Europe/Brussels'],
        [47.3769, 8.5417, 'Zurich', 'Switzerland', 'Europe/Zurich'],
        [52.2297, 21.0122, 'Warsaw', 'Poland', 'Europe/Warsaw'],
        [47.4979, 19.0402, 'Budapest', 'Hungary', 'Europe/Budapest'],
        [37.9838, 23.7275, 'Athens', 'Greece', 'Europe/Athens'],
        [45.7640, 4.8357, 'Lyon', 'France', 'Europe/Paris'],
        [53.5511, 9.9937, 'Hamburg', 'Germany', 'Europe/Berlin'],
        [53.4808, -2.2426, 'Manchester', 'United Kingdom', 'Europe/London'],
        [55.9533, -3.1883, 'Edinburgh', 'United Kingdom', 'Europe/London'],
        [50.1109, 8.6821, 'Frankfurt', 'Germany', 'Europe/Berlin'],
        [50.0647, 19.9450, 'Krakow', 'Poland', 'Europe/Warsaw'],
        [41.1579, -8.6291, 'Porto', 'Portugal', 'Europe/Lisbon'],
        [40.8518, 14.2681, 'Naples', 'Italy', 'Europe/Rome'],
        // Global hubs
        [35.6762, 139.6503, 'Tokyo', 'Japan', 'Asia/Tokyo'],
        [37.5665, 126.9780, 'Seoul', 'South Korea', 'Asia/Seoul'],
        [1.3521, 103.8198, 'Singapore', 'Singapore', 'Asia/Singapore'],
        [-33.8688, 151.2093, 'Sydney', 'Australia', 'Australia/Sydney'],
        [-37.8136, 144.9631, 'Melbourne', 'Australia', 'Australia/Melbourne'],
        [25.2048, 55.2708, 'Dubai', 'UAE', 'Asia/Dubai'],
        [-23.5505, -46.6333, 'São Paulo', 'Brazil', 'America/Sao_Paulo'],
        [-34.6037, -58.3816, 'Buenos Aires', 'Argentina', 'America/Argentina/Buenos_Aires'],
    ];

    /**
     * Unique sorted city labels for use in filter dropdowns.
     *
     * @return list<string>
     */
    public function cities(): array
    {
        $seen = [];
        $cities = [];
        foreach (self::ANCHORS as [,, $city, $country]) {
            $label = "{$city}, {$country}";
            if (! isset($seen[$label])) {
                $seen[$label] = true;
                $cities[] = $label;
            }
        }
        sort($cities);

        return $cities;
    }

    /**
     * Return the lat/lng anchor for a city label, used to build a bounding-box
     * filter query. Returns null if the city is not in the list.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function anchorFor(string $cityLabel): ?array
    {
        foreach (self::ANCHORS as [$lat, $lng, $city, $country]) {
            if ("{$city}, {$country}" === $cityLabel) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return null;
    }

    /**
     * @return array{label: string, city: string, country: string, timezone: string}
     */
    public function resolve(float $lat, float $lng): array
    {
        $best = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach (self::ANCHORS as [$anchorLat, $anchorLng, $city, $country, $timezone]) {
            $dist = $this->haversine($lat, $lng, $anchorLat, $anchorLng);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = [
                    'label' => "{$city}, {$country}",
                    'city' => $city,
                    'country' => $country,
                    'timezone' => $timezone,
                ];
            }
        }

        return $best ?? [
            'label' => 'Unknown Location',
            'city' => 'Unknown',
            'country' => 'Unknown',
            'timezone' => 'UTC',
        ];
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
