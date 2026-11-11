<?php

namespace Tests\Feature;

use App\Support\IndonesianDestinations;
use Illuminate\Support\Str;
use Tests\TestCase;

class DestinationDetailsTest extends TestCase
{
    public function test_each_destination_opens_its_matching_detail_and_photo(): void
    {
        foreach (IndonesianDestinations::all() as $destination) {
            $response = $this->get(route('destinations.show', Str::slug($destination['name'])));

            $response
                ->assertOk()
                ->assertSee($destination['name'])
                ->assertSee($destination['region'])
                ->assertSee($destination['description'])
                ->assertSee($destination['alt'])
                ->assertSee($destination['credit'])
                ->assertSee($destination['source'])
                ->assertSee('data-destination-photo-open')
                ->assertSee('data-destination-photo-dialog');

            $this->assertFileExists(public_path($destination['image']));
        }
    }

    public function test_unknown_destination_returns_not_found(): void
    {
        $this->get('/wisata/tempat-yang-tidak-ada')->assertNotFound();
    }
}
