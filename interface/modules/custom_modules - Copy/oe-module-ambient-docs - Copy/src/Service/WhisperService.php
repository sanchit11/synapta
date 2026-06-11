<?php

namespace Clinic\OeModuleAmbientDocs\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * WhisperService
 *
 * Sends audio to the Python FastAPI backend (/transcribe_chunk)
 * and returns a normalised transcript result.
 *
 * The FastAPI server (main.py) handles routing to the correct
 * transcription engine (OpenAI Whisper or local Whisper) based
 * on its own USE_OPENAI env var.
 *
 * Start the FastAPI server:
 *   uvicorn main:app --reload --port 8000
 */
class WhisperService
{
    private Client $http;
    private string $fastApiUrl;

    // Audio formats we accept from the browser
    private const SUPPORTED_FORMATS = [
        'audio/webm'  => 'webm',
        'audio/mp4'   => 'mp4',
        'audio/mpeg'  => 'mp3',
        'audio/wav'   => 'wav',
        'audio/ogg'   => 'ogg',
        'video/webm'  => 'webm',
    ];

    private const MAX_FILE_SIZE_MB = 45;

    public function __construct()
    {
        $this->http = new Client([
            'timeout'         => 400,
            'connect_timeout' => 30,
        ]);

        $this->fastApiUrl = rtrim($_ENV['FASTAPI_URL'] ?? 'http://localhost:8000', '/');
    }

    /**
     * Transcribe an audio file to text via FastAPI /transcribe_chunk.
     *
     * @param  string $audioFilePath  Absolute path to the audio file
     * @return array{
     *   raw_transcript:      string,
     *   diarized_transcript: array,
     *   word_count:          int,
     *   duration_seconds:    float,
     *   segment_count:       int,
     *   error:               string|null
     * }
     */
    public function transcribe(string $audioFilePath): array
    {
        // Validate file
        if (!file_exists($audioFilePath)) {
            return $this->errorResult("Audio file not found: {$audioFilePath}");
        }

        $fileSizeMB = filesize($audioFilePath) / (1024 * 1024);
        if ($fileSizeMB > self::MAX_FILE_SIZE_MB) {
            return $this->errorResult(
                "Audio file too large ({$fileSizeMB}MB). Max is "
                . self::MAX_FILE_SIZE_MB . "MB."
            );
        }

        $url      = $this->fastApiUrl . '/transcribe_chunk';
        $mimeType = mime_content_type($audioFilePath) ?: 'audio/webm';
        $ext      = self::SUPPORTED_FORMATS[$mimeType] ?? 'webm';

        try {
            $response = $this->http->post($url, [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($audioFilePath, 'r'),
                        'filename' => 'chunk.' . $ext,
                        'headers'  => ['Content-Type' => $mimeType],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // FastAPI returns: {"text": "transcript..."}
            $rawTranscript = trim($data['text'] ?? '');
            $wordCount     = str_word_count($rawTranscript);

            return [
                'raw_transcript'      => $rawTranscript,
                'diarized_transcript' => [],
                'word_count'          => $wordCount,
                'duration_seconds'    => 0.0,
                'segment_count'       => 0,
                'error'               => null,
            ];

        } catch (RequestException $e) {
            $status  = $e->getResponse()?->getStatusCode() ?? 0;
            $body    = $e->getResponse()?->getBody()->getContents() ?? '';
            $errData = json_decode($body, true);
            $message = $errData['detail'] ?? $errData['message'] ?? $e->getMessage();

            $friendly = $status === 0
                ? "Cannot reach FastAPI server at {$this->fastApiUrl}. "
                  . "Start it with: uvicorn main:app --reload --port 8000"
                : "FastAPI /transcribe_chunk error (HTTP {$status}): {$message}";

            error_log("WhisperService error: {$friendly}");
            return $this->errorResult($friendly);

        } catch (\Exception $e) {
            error_log("WhisperService unexpected error: " . $e->getMessage());
            return $this->errorResult($e->getMessage());
        }
    }

    private function errorResult(string $message): array
    {
        return [
            'raw_transcript'      => '',
            'diarized_transcript' => [],
            'word_count'          => 0,
            'duration_seconds'    => 0.0,
            'segment_count'       => 0,
            'error'               => $message,
        ];
    }
}
