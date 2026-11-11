<?php

namespace App\Support;

class IndonesianDestinations
{
    public static function all(): array
    {
        return [
            [
                'name' => 'Raja Ampat',
                'region' => 'Papua Barat Daya',
                'image' => 'images/travel/raja-ampat-piaynemo.jpg',
                'alt' => 'Gugusan pulau karst Piaynemo di Raja Ampat',
                'description' => 'Gugusan pulau karst dan perairan kaya terumbu karang di jantung Segitiga Terumbu Karang.',
                'credit' => 'Rizalubun · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Pulau_Piaynemo,_Raja_Ampat.jpg',
            ],
            [
                'name' => 'Labuan Bajo',
                'region' => 'Nusa Tenggara Timur',
                'image' => 'images/travel/labuan-bajo-harbor.jpg',
                'alt' => 'Pelabuhan Labuan Bajo di pesisir Flores',
                'description' => 'Kota pelabuhan di Flores yang menjadi pintu menuju Taman Nasional Komodo dan pulau-pulau sekitarnya.',
                'credit' => 'prilfish · CC BY 2.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Labuan_Bajo_Harbor.jpg',
            ],
            [
                'name' => 'Borobudur',
                'region' => 'Jawa Tengah',
                'image' => 'images/travel/borobudur-northwest.jpg',
                'alt' => 'Candi Borobudur dilihat dari sisi barat laut',
                'description' => 'Monumen Buddha abad ke-8 hingga ke-9 dengan relief yang menggambarkan ajaran dan kehidupan pada masanya.',
                'credit' => 'Gunawan Kartapranata · domain publik',
                'source' => 'https://commons.wikimedia.org/wiki/File:Borobudur-Nothwest-view.jpg',
            ],
            [
                'name' => 'Bromo',
                'region' => 'Jawa Timur',
                'image' => 'images/travel/bromo-sunrise.jpg',
                'alt' => 'Matahari terbit di kawasan Tengger dengan Gunung Bromo dan Semeru',
                'description' => 'Lanskap kaldera Tengger dan lautan pasir berada dalam kawasan Taman Nasional Bromo Tengger Semeru.',
                'credit' => 'Thomas Hirsch · CC BY-SA 3.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Mount_Bromo_at_sunrise,_showing_its_volcanoes_and_Mount_Semeru_(background).jpg',
            ],
            [
                'name' => 'Danau Toba',
                'region' => 'Sumatra Utara',
                'image' => 'images/travel/danau-toba-tele.jpg',
                'alt' => 'Danau Toba dan perbukitan di sekitarnya dilihat dari Tele, Samosir',
                'description' => 'Danau vulkanik luas dengan Pulau Samosir di tengahnya, serta budaya Batak yang kaya di sekelilingnya.',
                'credit' => 'Bisajunisa · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Lake_Toba_and_the_surrounding_hills.jpg',
            ],
        ];
    }
}
