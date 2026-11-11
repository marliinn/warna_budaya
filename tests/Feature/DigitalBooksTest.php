<?php

namespace Tests\Feature;

use App\Support\NusantaraDigitalBooks;
use Tests\TestCase;

class DigitalBooksTest extends TestCase
{
    public function test_digital_book_library_lists_five_editorial_knowledge_books(): void
    {
        $response = $this->get(route('digital-books.index'));

        $response
            ->assertOk()
            ->assertSee('Batik Indonesia')
            ->assertSee('Arsitektur Nusantara')
            ->assertSee('Rempah Nusantara')
            ->assertSee('Teknologi Tradisional')
            ->assertSee('Ekologi Nusantara')
            ->assertSee('Pilih sebuah')
            ->assertSee('Cari topik')
            ->assertSee('Semua edisi')
            ->assertSee('Baca buku');

        $this->assertCount(5, NusantaraDigitalBooks::all());
    }

    public function test_each_book_has_editorial_chapters_sources_and_reader_controls(): void
    {
        foreach (NusantaraDigitalBooks::all() as $book) {
            $response = $this->get(route('digital-books.show', $book['slug']));

            $response
                ->assertOk()
                ->assertSee($book['title'])
                ->assertSee($book['subtitle'])
                ->assertSee($book['origin'])
                ->assertSee($book['cover_alt'])
                ->assertSee($book['chapters'][0]['heading'])
                ->assertSee('Daftar isi buku')
                ->assertSee('Simpan halaman')
                ->assertSee('Mode nyaman')
                ->assertSee('Mode malam')
                ->assertSee('Bab berikutnya')
                ->assertSee('Bacaan dan sumber rujukan')
                ->assertSee('digital-books.js');

            $this->assertCount(4, $book['chapters']);
            $this->assertNotEmpty($book['references']);
            $this->assertNotEmpty($book['chapters'][0]['diagram']);
        }
    }

    public function test_book_content_covers_requested_topics_and_uses_verifiable_references(): void
    {
        $books = collect(NusantaraDigitalBooks::all())->keyBy('slug');

        $this->assertStringContainsString('2009', $books['batik-indonesia']['chapters'][3]['fact']['text']);
        $this->assertStringContainsString('pewarna alami', mb_strtolower(implode(' ', $books['batik-indonesia']['chapters'][2]['paragraphs'])));
        $this->assertStringContainsString('menyalurkan beban', mb_strtolower(implode(' ', $books['arsitektur-nusantara']['chapters'][1]['paragraphs'])));
        $this->assertStringContainsString('1602', $books['rempah-nusantara']['chapters'][2]['fact']['text']);
        $this->assertStringContainsString('Subak', implode(' ', array_column($books['teknologi-tradisional']['chapters'], 'heading')));
        $this->assertStringContainsString('keanekaragaman hayati', mb_strtolower(implode(' ', $books['ekologi-nusantara']['chapters'][0]['paragraphs'])));

        foreach ($books as $book) {
            foreach ($book['references'] as $reference) {
                $this->assertSame(1, preg_match('~^https://~', $reference['url']));
            }
        }
    }

    public function test_unknown_digital_book_returns_not_found(): void
    {
        $this->get('/cerita-nusantara/buku-tidak-ada')->assertNotFound();
    }
}
