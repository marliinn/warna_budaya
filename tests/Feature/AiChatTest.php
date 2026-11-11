<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    public function test_it_returns_an_ai_answer_and_keeps_api_credentials_server_side(): void
    {
        config([
            'ai.chat.api_key' => 'test-secret-key',
            'ai.chat.model' => 'gemini-3.6-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Batik memiliki beragam motif dan makna lokal.']]]],
                ],
            ]),
        ]);

        $this->postJson(route('ai.chat'), [
            'message' => 'Apa makna batik?',
            'history' => [['role' => 'user', 'content' => 'Saya tertarik budaya Jawa.']],
        ])
            ->assertOk()
            ->assertExactJson(['answer' => 'Batik memiliki beragam motif dan makna lokal.'])
            ->assertDontSee('test-secret-key');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-secret-key')
                && isset($request['systemInstruction']['parts'][0]['text'])
                && $request['contents'][0]['role'] === 'user'
                && $request['contents'][0]['parts'][0]['text'] === 'Saya tertarik budaya Jawa.'
                && $request['contents'][1]['role'] === 'user'
                && $request['contents'][1]['parts'][0]['text'] === 'Apa makna batik?'
                && $request['generationConfig']['maxOutputTokens'] === 1200
                && str_contains($request['systemInstruction']['parts'][0]['text'], 'subjudul Markdown singkat');
        });
    }

    public function test_it_reports_when_the_ai_provider_has_not_been_configured(): void
    {
        config(['ai.chat.api_key' => null]);

        $this->postJson(route('ai.chat'), ['message' => 'Ceritakan tentang Borobudur.'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI Sahabat Budaya belum dikonfigurasi. Tambahkan GEMINI_API_KEY pada file .env.');
    }

    public function test_it_reports_when_the_gemini_quota_is_exhausted(): void
    {
        config(['ai.chat.api_key' => 'test-secret-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'status' => 'RESOURCE_EXHAUSTED',
                    'message' => 'Quota exceeded',
                ],
            ], 429),
        ]);

        $this->postJson(route('ai.chat'), ['message' => 'Ceritakan tentang Borobudur.'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'quota_exhausted')
            ->assertJsonPath('message', 'Kuota atau batas penggunaan Gemini API sedang habis. Periksa kuota dan billing project Google AI Studio, lalu coba lagi nanti.')
            ->assertDontSee('Quota exceeded');
    }

    public function test_it_maps_assistant_history_to_gemini_model_role(): void
    {
        config(['ai.chat.api_key' => 'test-secret-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Jawaban lanjutan.']]]],
                ],
            ]),
        ]);

        $this->postJson(route('ai.chat'), [
            'message' => 'Lanjutkan.',
            'history' => [
                ['role' => 'user', 'content' => 'Jelaskan batik.'],
                ['role' => 'assistant', 'content' => 'Batik adalah karya tekstil.'],
            ],
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['contents'][1]['role'] === 'model'
            && $request['contents'][1]['parts'][0]['text'] === 'Batik adalah karya tekstil.');
    }

    public function test_it_validates_message_length_and_conversation_history(): void
    {
        config(['ai.chat.api_key' => 'test-secret-key']);

        $this->postJson(route('ai.chat'), [
            'message' => str_repeat('a', 2001),
            'history' => array_fill(0, 13, ['role' => 'user', 'content' => 'Riwayat']),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'history']);
    }
}
