<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $apiKey = config('ai.chat.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return response()->json([
                'message' => 'AI Sahabat Budaya belum dikonfigurasi. Tambahkan GEMINI_API_KEY pada file .env.',
            ], 503);
        }

        $systemInstruction = implode(' ', [
            'Anda adalah AI Sahabat Budaya, asisten yang ramah dan informatif tentang Indonesia.',
            'Jawab dalam bahasa yang digunakan pengguna; gunakan bahasa Indonesia jika tidak jelas.',
            'Bantu topik sejarah, masyarakat, bahasa, seni, tradisi, kuliner, tempat, dan warisan budaya Indonesia.',
            'Utamakan informasi yang dapat dipertanggungjawabkan; jangan mengarang nama, tanggal, peristiwa, atau sumber.',
            'Jika fakta atau konteks tidak pasti, nyatakan ketidakpastian dan jelaskan apa yang perlu diverifikasi.',
            'Bedakan fakta sejarah dari legenda atau tradisi lisan, serta akui adanya variasi lokal dan perbedaan pandangan.',
            'Sebutkan rujukan tepercaya yang relevan hanya jika Anda yakin rujukan itu benar; jangan membuat URL.',
            'Susun jawaban dengan rapi: awali jawaban langsung dalam 1-2 kalimat, lalu gunakan subjudul Markdown singkat dan poin-poin bila membantu.',
            'Gunakan paragraf pendek, maksimal sekitar 150 kata kecuali pengguna meminta penjelasan mendalam. Hindari pengulangan, tabel, simbol dekoratif, dan Markdown yang tidak ditutup.',
            'Akhiri kalimat dan setiap poin dengan lengkap; jangan memotong jawaban. Tawarkan pertanyaan lanjutan hanya bila relevan.',
        ]);
        $contents = [];

        foreach ($validated['history'] ?? [] as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $turn['content']]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $validated['message']]],
        ];

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout((int) config('ai.chat.timeout', 35))
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/'
                        .rawurlencode((string) config('ai.chat.model'))
                        .':generateContent',
                    [
                        'systemInstruction' => [
                            'parts' => [['text' => $systemInstruction]],
                        ],
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.5,
                            'maxOutputTokens' => 1200,
                        ],
                    ],
                );
        } catch (ConnectionException $exception) {
            Log::warning('AI Sahabat Budaya provider connection failed.', [
                'exception' => $exception::class,
                'reason' => mb_substr($exception->getMessage(), 0, 300),
            ]);

            return response()->json([
                'message' => 'Layanan AI sedang sulit dihubungi. Silakan coba beberapa saat lagi.',
            ], 502);
        }

        $providerError = $response->json('error.status');

        if ($response->status() === 429 || $providerError === 'RESOURCE_EXHAUSTED') {
            Log::warning('Gemini API quota or rate limit was reached.', [
                'status' => $response->status(),
                'provider_status' => $providerError,
            ]);

            return response()->json([
                'code' => 'quota_exhausted',
                'message' => 'Kuota atau batas penggunaan Gemini API sedang habis. Periksa kuota dan billing project Google AI Studio, lalu coba lagi nanti.',
            ], 429);
        }

        if (! $response->successful()) {
            Log::warning('AI Sahabat Budaya provider returned an unsuccessful response.', [
                'status' => $response->status(),
                'provider_status' => $providerError,
            ]);

            if (in_array($response->status(), [401, 403], true)) {
                return response()->json([
                    'message' => 'Gemini API menolak akses. Periksa GEMINI_API_KEY dan izin API key pada project Google AI Studio.',
                ], 502);
            }

            return response()->json([
                'message' => 'Layanan AI belum dapat menjawab saat ini. Silakan coba kembali.',
            ], 502);
        }

        $parts = $response->json('candidates.0.content.parts', []);
        $answer = is_array($parts)
            ? collect($parts)
                ->pluck('text')
                ->filter(fn ($text): bool => is_string($text) && trim($text) !== '')
                ->implode("\n")
            : '';

        if (! is_string($answer) || trim($answer) === '') {
            Log::warning('AI Sahabat Budaya provider response did not contain a text answer.');

            return response()->json([
                'message' => 'Layanan AI mengirim jawaban yang tidak dapat dibaca. Silakan coba kembali.',
            ], 502);
        }

        return response()->json(['answer' => trim($answer)]);
    }
}
