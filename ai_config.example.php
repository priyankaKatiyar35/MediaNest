<?php
/**
 * MediaNest AI — Configuration
 * --------------------------------------------------------------
 * Paste your Groq API key below. The key starts with "gsk_".
 * Get a free key at https://console.groq.com/keys
 *
 * SECURITY: This file contains a secret. Do NOT commit it to public
 * git repositories. If you ever push to GitHub, add /admin/ai_config.php
 * to .gitignore first.
 */

return [
    // ───── REPLACE THIS WITH YOUR REAL GROQ KEY ─────
    'groq_api_key' => 'PASTE_YOUR_KEY_HERE',

    // Models — these are reasonable defaults, no need to change
    'whisper_model' => 'whisper-large-v3-turbo', // fastest Whisper variant on Groq
    'chat_model'    => 'llama-3.1-8b-instant',   // fast, free, good enough for summaries

    // Safety: max file size that we'll attempt to transcribe (Groq limit is 25 MB)
    'max_audio_mb'  => 25,

    // FFmpeg binary path — usually 'ffmpeg' works if on PATH.
    // On XAMPP Windows you may need full path e.g. 'C:\\ffmpeg\\bin\\ffmpeg.exe'
    'ffmpeg_path'   => 'ffmpeg',
];