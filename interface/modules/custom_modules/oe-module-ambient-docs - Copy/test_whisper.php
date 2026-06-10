<?php
/**
 * Ambient Docs — Setup & Connectivity Test
 *
 * Open in browser:
 *   http://localhost/Synapta_EMR/interface/modules/custom_modules/oe-module-ambient-docs/test_whisper.php
 *
 * DELETE this file before deploying to production.
 *
 * Tests:
 *   1. Composer autoloader + .env loaded
 *   2. DB tables exist
 *   3. Storage directory writable
 *   4. Transcription service reachable (local Whisper or OpenAI)
 *   5. LLM service reachable (Ollama or OpenAI)
 *   6. End-to-end: transcribe silence → structure note
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────
$globalsPath = realpath(__DIR__ . '/../../../../../../../../interface/globals.php')
    ?: realpath(__DIR__ . '/../../../../../interface/globals.php')
    ?: realpath(__DIR__ . '/../../../../../../interface/globals.php');

if (!$globalsPath) {
    die('<pre>❌ Cannot find globals.php — adjust path in this file</pre>');
}
require_once $globalsPath;

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('<pre>❌ vendor/autoload.php missing. Run: composer install</pre>');
}
require_once $autoloadPath;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Clinic\OeModuleAmbientDocs\Service\WhisperService;
use Clinic\OeModuleAmbientDocs\Service\NoteStructuringService;
use GuzzleHttp\Client;

$useOpenAI = ($_ENV['USE_OPENAI'] ?? 'false') === 'true';
$mode      = $useOpenAI ? 'OpenAI (cloud)' : 'Local (Ollama + faster-whisper)';

// ── HTML output helpers ───────────────────────────────────────────────────────
function ok(string $label, string $detail = ''): void {
    echo "✅ <strong>{$label}</strong>" . ($detail ? " — {$detail}" : '') . "\n";
}
function fail(string $label, string $detail = ''): void {
    echo "❌ <strong>{$label}</strong>" . ($detail ? " — {$detail}" : '') . "\n";
}
function info(string $msg): void {
    echo "ℹ️  {$msg}\n";
}
function section(string $title): void {
    echo "\n<strong style='font-size:1.1em;'>── {$title} ──</strong>\n";
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Ambient Docs — Setup Test</title>
<style>
  body { font-family: monospace; font-size: 14px; padding: 24px; background: #0f1117; color: #e2e8f0; }
  pre  { line-height: 1.8; white-space: pre-wrap; }
  h1   { color: #1D9E75; font-family: sans-serif; }
  a    { color: #63b3ed; }
</style>
</head>
<body>
<h1>🩺 Ambient Docs — Setup Test</h1>
<pre>
<?php

section('1. Configuration');
info("USE_OPENAI = " . ($_ENV['USE_OPENAI'] ?? 'not set') . "  →  mode: {$mode}");

if ($useOpenAI) {
    $key = $_ENV['OPENAI_API_KEY'] ?? '';
    if (!empty($key) && str_starts_with($key, 'sk-')) {
        ok('OPENAI_API_KEY', 'set (' . strlen($key) . ' chars)');
    } else {
        fail('OPENAI_API_KEY', 'not set or invalid (must start with sk-)');
    }
    info('OPENAI_MODEL = ' . ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o'));
} else {
    $whisperUrl = $_ENV['LOCAL_WHISPER_URL']   ?? 'http://http://localhost:8000';
    $ollamaUrl  = $_ENV['OLLAMA_BASE_URL']     ?? 'http://localhost:11434';
    $model      = $_ENV['OLLAMA_MODEL']        ?? 'llama3.2';
    $whisperMod = $_ENV['LOCAL_WHISPER_MODEL'] ?? 'Systran/faster-whisper-base';
    info("LOCAL_WHISPER_URL   = {$whisperUrl}");
    info("LOCAL_WHISPER_MODEL = {$whisperMod}");
    info("OLLAMA_BASE_URL     = {$ollamaUrl}");
    info("OLLAMA_MODEL        = {$model}");
}

// ── 2. DB Tables ─────────────────────────────────────────────────────────────
section('2. Database Tables');
$tables = ['ambient_recording_sessions', 'ambient_ai_notes', 'ambient_clinician_notes', 'ambient_audit_log'];
foreach ($tables as $tbl) {
    $exists = sqlQuery(
        "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
        [$tbl]
    );
    if ($exists) {
        ok($tbl);
    } else {
        fail($tbl, 'Run sql/install.sql in phpMyAdmin');
    }
}

// ── 3. Storage ───────────────────────────────────────────────────────────────
section('3. Storage Directory');
$storageDir = __DIR__ . '/storage/chunks';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}
if (is_writable($storageDir)) {
    ok('storage/chunks', 'writable');
} else {
    fail('storage/chunks', 'not writable — grant write permission to Apache user');
}

// ── 4. Transcription Service ─────────────────────────────────────────────────
section('4. Transcription Service');
$http = new Client(['timeout' => 5, 'connect_timeout' => 3]);

if ($useOpenAI) {
    info('Using OpenAI Whisper cloud API');
    $key = $_ENV['OPENAI_API_KEY'] ?? '';
    if (!empty($key) && str_starts_with($key, 'sk-')) {
        ok('OpenAI API key looks valid');
    } else {
        fail('OpenAI API key missing/invalid — set OPENAI_API_KEY in .env');
    }
} else {
    $whisperUrl = rtrim($_ENV['LOCAL_WHISPER_URL'] ?? 'http://http://localhost:8000', '/');
    info("Testing {$whisperUrl}/health ...");
    try {
        $resp   = $http->get($whisperUrl . '/health', ['http_errors' => false]);
        $status = $resp->getStatusCode();
        if ($status >= 200 && $status < 500) {
            ok("faster-whisper-server reachable at {$whisperUrl}", "HTTP {$status}");
        } else {
            fail("faster-whisper-server at {$whisperUrl}", "HTTP {$status}");
        }
    } catch (\Exception $e) {
        fail("faster-whisper-server not reachable at {$whisperUrl}");
        echo "\n";
        info("To start it, run ONE of:");
        info("  pip install faster-whisper-server");
        info("  uvicorn faster_whisper_server.main:app --port 9000");
        info("  -- OR --");
        info("  docker run -p 9000:8000 ghcr.io/fedirz/faster-whisper-server:latest-cpu");
    }
}

// ── 5. LLM Service ───────────────────────────────────────────────────────────
section('5. LLM / Note-Structuring Service');

if ($useOpenAI) {
    info('Using OpenAI GPT-4o cloud API (same key as above)');
    $key = $_ENV['OPENAI_API_KEY'] ?? '';
    if (!empty($key) && str_starts_with($key, 'sk-')) {
        ok('OpenAI GPT-4o ready (key already validated)');
    } else {
        fail('OpenAI API key missing — SOAP notes will fail');
    }
} else {
    $ollamaUrl = rtrim($_ENV['OLLAMA_BASE_URL'] ?? 'http://localhost:11434', '/');
    $model     = $_ENV['OLLAMA_MODEL'] ?? 'llama3.2';
    info("Testing {$ollamaUrl}/api/tags ...");
    try {
        $resp   = $http->get($ollamaUrl . '/api/tags', ['http_errors' => false]);
        $status = $resp->getStatusCode();
        if ($status === 200) {
            $tags       = json_decode($resp->getBody()->getContents(), true);
            $names      = array_column($tags['models'] ?? [], 'name');
            $modelFound = !empty(array_filter($names, fn($n) => str_starts_with($n, $model)));
            if ($modelFound) {
                ok("Ollama reachable, model '{$model}' found");
            } else {
                fail("Ollama is running but model '{$model}' is NOT pulled");
                info("Fix: ollama pull {$model}");
                if (!empty($names)) {
                    info("Available models: " . implode(', ', $names));
                }
            }
        } else {
            fail("Ollama at {$ollamaUrl}", "HTTP {$status}");
        }
    } catch (\Exception $e) {
        fail("Ollama not reachable at {$ollamaUrl}");
        echo "\n";
        info("To start Ollama:");
        info("  1. Download: https://ollama.com/download");
        info("  2. It starts automatically, or run: ollama serve");
        info("  3. Pull model: ollama pull {$model}");
    }
}

// ── 6. End-to-end Transcription Test ─────────────────────────────────────────
section('6. End-to-End: Transcribe 1 Second of Silence');
info('Creating minimal WAV file (1s silence, 16kHz mono)...');

$testAudioPath = $storageDir . '/test_silence.wav';
$sampleRate    = 16000;
$numSamples    = $sampleRate;
$audioData     = str_repeat("\x00\x00", $numSamples);
$dataSize      = strlen($audioData);
$wav  = "RIFF" . pack('V', $dataSize + 36) . "WAVE";
$wav .= "fmt " . pack('V', 16) . pack('v', 1) . pack('v', 1);
$wav .= pack('V', $sampleRate) . pack('V', $sampleRate * 2);
$wav .= pack('v', 2) . pack('v', 16);
$wav .= "data" . pack('V', $dataSize) . $audioData;
file_put_contents($testAudioPath, $wav);
info('Test file created: ' . $testAudioPath . ' (' . strlen($wav) . ' bytes)');

$whisper = new WhisperService();
$result  = $whisper->transcribe($testAudioPath);
@unlink($testAudioPath);

if ($result['error']) {
    fail('WhisperService', $result['error']);
} else {
    ok('WhisperService transcribed successfully');
    info('Transcript: "' . ($result['raw_transcript'] ?: '(empty — expected for silence)') . '"');
    info('Duration:   ' . $result['duration_seconds'] . 's');
    info('Segments:   ' . $result['segment_count']);
}

// ── 7. Summary ────────────────────────────────────────────────────────────────
section('Summary');
echo "\n";
if ($useOpenAI) {
    info('Mode: OpenAI cloud (paid)');
    info('Make sure OPENAI_API_KEY is set in .env and has Whisper + GPT-4o access.');
} else {
    info('Mode: Local (free, 100% on-machine)');
    info('Both faster-whisper-server (port 9000) AND Ollama (port 11434) must be running.');
    info('');
    info('Quick-start commands:');
    $model = $_ENV['OLLAMA_MODEL'] ?? 'llama3.2';
    info("  # Terminal 1 — Whisper:");
    info("  pip install faster-whisper-server");
    info("  uvicorn faster_whisper_server.main:app --port 9000");
    info("  # Terminal 2 — Ollama (download from https://ollama.com/download first):");
    info("  ollama pull {$model}");
    info("  # (Ollama starts automatically after install)");
}
?>

</pre>
<p style="color:#4A5568; font-size:12px; font-family:sans-serif;">
  ⚠️ Delete this file before deploying to production — it exposes system configuration.
</p>
</body>
</html>
