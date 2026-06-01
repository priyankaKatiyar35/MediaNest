<?php
/**
 * MediaNest AI — Library
 * --------------------------------------------------------------
 * Thin wrappers over Groq's OpenAI-compatible REST API.
 *
 * Public functions:
 *   groq_transcribe($audio_path)  → array (text + segments + lang + duration)
 *   groq_chat($messages, $opts)   → string (assistant reply)
 *   extract_audio($video_path)    → string (path to extracted mp3) | throws
 */

require_once __DIR__ . '/admin_auth.php';
$AI_CFG = require __DIR__ . '/ai_config.php';

function ai_cfg($key) {
    global $AI_CFG;
    return $AI_CFG[$key] ?? null;
}

/**
 * Extract audio from a video to a compressed mp3 (smaller upload).
 * Uses FFmpeg via exec(). Throws RuntimeException on failure.
 */
function extract_audio($video_path) {
    if (!is_file($video_path)) throw new RuntimeException("Video file not found: $video_path");
    $ffmpeg = ai_cfg('ffmpeg_path') ?: 'ffmpeg';

    $audio_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mn_' . md5($video_path . microtime(true)) . '.mp3';

    // -y overwrite, -vn no video, -ar 16000 mono 16kHz (plenty for Whisper), -b:a 32k
 $cmd = 'LD_LIBRARY_PATH= ' . escapeshellcmd($ffmpeg)
     . ' -y -i ' . escapeshellarg($video_path)
     . ' -vn -ar 16000 -ac 1 -b:a 32k '
     . escapeshellarg($audio_path)
     . ' 2>&1';

    @exec($cmd, $output, $code);
    if ($code !== 0 || !is_file($audio_path)) {
        throw new RuntimeException("FFmpeg failed (code $code). Output: " . implode("\n", array_slice($output, -5)));
    }
    return $audio_path;
}

/**
 * Transcribe an audio file via Groq's Whisper endpoint.
 * Returns ['text' => '…', 'segments' => [...], 'language' => 'en', 'duration' => 123.4]
 */
function groq_transcribe($audio_path) {
    $key = ai_cfg('groq_api_key');
    if (!$key || strpos($key, 'PASTE_') === 0) {
        throw new RuntimeException('Groq API key not configured. Edit admin/ai_config.php first.');
    }
    if (!is_file($audio_path)) throw new RuntimeException("Audio file missing: $audio_path");

    $size_mb = filesize($audio_path) / 1024 / 1024;
    $max = (float) ai_cfg('max_audio_mb');
    if ($size_mb > $max) {
        throw new RuntimeException(sprintf("Audio file is %.1f MB but Groq limit is %s MB.", $size_mb, $max));
    }

    $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
        CURLOPT_POSTFIELDS     => [
            'file'                     => new CURLFile($audio_path, 'audio/mpeg', basename($audio_path)),
            'model'                    => ai_cfg('whisper_model'),
            'response_format'          => 'verbose_json',
            'timestamp_granularities[]'=> 'segment',
        ],
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)                throw new RuntimeException("Network error: $err");
    if ($http !== 200)       throw new RuntimeException("Groq returned HTTP $http: $raw");

    $data = json_decode($raw, true);
    if (!$data || !isset($data['text'])) throw new RuntimeException("Bad response: $raw");

    return [
        'text'     => $data['text'],
        'segments' => $data['segments'] ?? [],
        'language' => $data['language'] ?? 'en',
        'duration' => (float)($data['duration'] ?? 0),
    ];
}

/**
 * Generic chat completion. $messages = OpenAI-style array.
 * Returns assistant message text.
 */
function groq_chat($messages, $opts = []) {
    $key = ai_cfg('groq_api_key');
    if (!$key || strpos($key, 'PASTE_') === 0) {
        throw new RuntimeException('Groq API key not configured.');
    }

    $body = [
        'model'       => $opts['model'] ?? ai_cfg('chat_model'),
        'messages'    => $messages,
        'temperature' => $opts['temperature'] ?? 0.3,
        'max_tokens'  => $opts['max_tokens']  ?? 1024,
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) throw new RuntimeException("Groq returned HTTP $http: $raw");
    $data = json_decode($raw, true);
    return $data['choices'][0]['message']['content'] ?? '';
}