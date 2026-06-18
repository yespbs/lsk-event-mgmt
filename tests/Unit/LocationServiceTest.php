<?php

use App\Services\LocationService;

it('resolves exact Tokyo coordinates to the correct city and IANA timezone', function () {
    $result = (new LocationService())->resolve(35.6762, 139.6503);

    expect($result['label'])->toBe('Tokyo, Japan');
    expect($result['city'])->toBe('Tokyo');
    expect($result['country'])->toBe('Japan');
    expect($result['timezone'])->toBe('Asia/Tokyo');
});

it('resolves coordinates with seeder-level jitter to the correct city', function () {
    $service = new LocationService();

    // Seeder jitters up to ±0.5°; confirm we still resolve correctly within that range
    expect($service->resolve(35.90, 139.90)['city'])->toBe('Tokyo');       // +0.22°, +0.25°
    expect($service->resolve(-34.10, 151.00)['city'])->toBe('Sydney');     // −0.23°, −0.21°
    expect($service->resolve(51.80, -0.30)['city'])->toBe('London');       // +0.29°, −0.17°
});

it('picks the nearest anchor when two candidates are close', function () {
    $service = new LocationService();

    // Sydney: -33.8688, 151.2093  |  Melbourne: -37.8136, 144.9631
    // (-34.5, 150.5) is ~75 km from Sydney and ~450 km from Melbourne
    expect($service->resolve(-34.5, 150.5)['city'])->toBe('Sydney');
});

it('returns anchor coordinates for a known city label', function () {
    $anchor = (new LocationService())->anchorFor('Tokyo, Japan');

    expect($anchor)->not->toBeNull();
    expect($anchor['lat'])->toBe(35.6762);
    expect($anchor['lng'])->toBe(139.6503);
});

it('returns null for an unknown city label', function () {
    expect((new LocationService())->anchorFor('Atlantis, Ocean'))->toBeNull();
});

it('returns a non-empty sorted list of city labels', function () {
    $cities = (new LocationService())->cities();

    expect($cities)->not->toBeEmpty();
    expect($cities)->toContain('Tokyo, Japan');
    expect($cities)->toContain('London, United Kingdom');
    expect($cities)->toContain('Sydney, Australia');

    $sorted = $cities;
    sort($sorted);
    expect($cities)->toBe($sorted);
});

it('returns no duplicate city labels', function () {
    $cities = (new LocationService())->cities();
    expect(array_unique($cities))->toHaveCount(count($cities));
});
