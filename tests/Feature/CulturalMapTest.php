<?php

namespace Tests\Feature;

use App\Support\NusantaraCulturalMap;
use Tests\TestCase;

class CulturalMapTest extends TestCase
{
    public function test_interactive_cultural_map_shows_controls_and_at_least_fifteen_cultures(): void
    {
        $response = $this->get(route('cultural-map.index'));

        $response
            ->assertOk()
            ->assertSee('Peta interaktif Nusantara')
            ->assertSee('culture-map-search')
            ->assertSee('culture-map-island')
            ->assertSee('culture-map-province')
            ->assertSee('culture-map-category')
            ->assertSee('culture-map-fit')
            ->assertSee('leaflet.js')
            ->assertSee('cultural-map.js')
            ->assertSee('Jelajahi seluruh peta');

        $entries = NusantaraCulturalMap::entries();
        $this->assertGreaterThanOrEqual(38, count($entries));
        $this->assertSame(38, count(array_unique(array_column($entries, 'province'))));
    }

    public function test_cultural_map_entries_have_geographic_coordinates_and_local_artwork(): void
    {
        $entries = NusantaraCulturalMap::entries();
        $islands = [];
        $categories = [];

        foreach ($entries as $entry) {
            $this->assertNotEmpty($entry['name']);
            $this->assertNotEmpty($entry['region']);
            $this->assertNotEmpty($entry['province']);
            $this->assertNotEmpty($entry['description']);
            $this->assertNotEmpty($entry['history']);
            $this->assertNotEmpty($entry['unique']);
            $this->assertCount(2, $entry['coordinates']);
            $this->assertGreaterThanOrEqual(-11.5, $entry['coordinates'][0]);
            $this->assertLessThanOrEqual(7, $entry['coordinates'][0]);
            $this->assertGreaterThanOrEqual(94, $entry['coordinates'][1]);
            $this->assertLessThanOrEqual(142, $entry['coordinates'][1]);

            if ($entry['image']) {
                $this->assertFileExists(public_path($entry['image']));
            }

            $islands[$entry['island']] = true;
            $categories[$entry['category']] = true;
        }

        $this->assertGreaterThanOrEqual(6, count($islands));
        $this->assertGreaterThanOrEqual(5, count($categories));
    }
}
