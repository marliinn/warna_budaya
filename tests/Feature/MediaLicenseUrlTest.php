<?php

namespace Tests\Feature;

use App\Models\Media;
use Tests\TestCase;

class MediaLicenseUrlTest extends TestCase
{
    public function test_supported_open_licenses_link_to_their_terms_or_policy(): void
    {
        $expectedUrls = [
            'CC BY-SA 4.0' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'CC BY-SA 3.0' => 'https://creativecommons.org/licenses/by-sa/3.0/',
            'CC BY 4.0' => 'https://creativecommons.org/licenses/by/4.0/',
            'CC0 1.0' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'Public domain' => 'https://commons.wikimedia.org/wiki/Commons:Licensing#Public_domain',
        ];

        foreach ($expectedUrls as $license => $url) {
            $media = new Media;
            $media->license = $license;

            $this->assertSame($url, $media->licenseUrl());
        }

        $unknownLicense = new Media;
        $unknownLicense->license = 'Unknown';

        $this->assertNull($unknownLicense->licenseUrl());
    }
}
