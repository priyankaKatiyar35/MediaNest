<?php
require_once __DIR__ . '/../auth/auth.php';
requireLogin();

include 'connect.php';

if (!isset($_GET['file_id'])) {
    http_response_code(403);
    die('Access denied.');
}

$fileId = (int)$_GET['file_id'];

if ($fileId <= 0) {
    http_response_code(400);
    die('Invalid file ID.');
}

$res = mysqli_query($con, "SELECT * FROM files WHERE file_id = $fileId");
if (!$res) {
    http_response_code(500);
    error_log('DB error: ' . mysqli_error($con));
    die('Server error.');
}

$file = mysqli_fetch_assoc($res);
if (!$file) {
    http_response_code(404);
    die('File not found.');
}

$filePath = $file['file_path'];
$fileDesc = htmlspecialchars($file['file_desc'] ?? 'Document');
$ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

$isPDF    = ($ext === 'pdf');
$isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']);
$isOffice = in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','csv']);
$isText   = in_array($ext, ['txt','json','xml','html','htm','md','log','ini','env']);
$isVideo  = in_array($ext, ['mp4','webm','ogg','mov']);
$isAudio  = in_array($ext, ['mp3','wav','ogg','m4a']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WESEE — View: <?= $fileDesc ?></title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #002242;
            --accent:  #0057a8;
            --accent-lt: #e8f1fb;
            --bg:      #f5f7fa;
            --surface: #fff;
            --border:  #e2e8f0;
            --text:    #1a2332;
            --muted:   #6b7a90;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-touch-callout: none;
            user-select: none;
        }
        nav {
            background: var(--primary);
            height: 64px;
            display: flex;
            align-items: center;
            padding: 0 2rem;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        }
        .logo { font-size: 1.2rem; font-weight: 700; color: #fff; text-decoration: none; letter-spacing: 0.08em; }
        .logo em { color: #5bb8ff; font-style: normal; }

        .file-bar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }
        .back-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.38rem 0.85rem; border-radius: 8px; font-size: 0.8rem;
            font-weight: 600; color: var(--accent); background: var(--accent-lt);
            text-decoration: none; transition: background 0.18s; white-space: nowrap;
        }
        .back-btn:hover { background: #d2e5f8; }
        .file-info { display: flex; align-items: center; gap: 0.6rem; flex: 1; min-width: 0; }
        .file-icon {
            width: 34px; height: 34px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem; font-weight: 800; flex-shrink: 0;
        }
        .file-icon.pdf   { background: #fdecea; color: #c0392b; }
        .file-icon.word  { background: #e8f1fb; color: #1d6abf; }
        .file-icon.excel { background: #e8f7ee; color: #1a7a45; }
        .file-icon.ppt   { background: #fff3e0; color: #d97706; }
        .file-icon.img   { background: #f3e8ff; color: #7c3aed; }
        .file-icon.txt   { background: #f5f7fa; color: #6b7a90; }
        .file-icon.vid   { background: #fff0f0; color: #e53e3e; }
        .file-icon.aud   { background: #f0fff4; color: #276749; }
        .file-icon.other { background: #f5f7fa; color: #6b7a90; }
        .file-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-ext-badge {
            flex-shrink: 0; background: var(--bg); border: 1px solid var(--border);
            border-radius: 6px; padding: 2px 8px; font-size: 0.68rem;
            font-weight: 700; color: var(--muted); text-transform: uppercase;
        }

        /* ── PDF.js custom toolbar ── */
        .pdf-toolbar {
            background: #2a2a2a;
            padding: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        .pdf-toolbar button {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 0.3rem 0.85rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s;
        }
        .pdf-toolbar button:hover { background: rgba(255,255,255,0.25); }
        .pdf-toolbar .page-controls {
            display: flex; align-items: center; gap: 0.5rem;
            color: rgba(255,255,255,0.8); font-size: 0.82rem;
        }
        .pdf-toolbar input[type=number] {
            width: 48px; padding: 0.25rem 0.4rem; border-radius: 5px;
            border: 1px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            color: #fff; font-size: 0.8rem; text-align: center;
        }
        .pdf-toolbar input::-webkit-outer-spin-button,
        .pdf-toolbar input::-webkit-inner-spin-button { -webkit-appearance: none; }
        #page-count { color: rgba(255,255,255,0.6); font-size: 0.82rem; }

        /* ── VIEWER ── */
        .viewer {
            flex: 1;
            overflow: hidden;
            background: #525659;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        #pdf-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.2rem;
        }
        #pdf-container canvas {
            display: block;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            pointer-events: none;
        }
        #pdf-loading {
            color: rgba(255,255,255,0.7);
            font-size: 0.95rem;
            margin-top: 4rem;
            text-align: center;
            line-height: 2;
        }

        .viewer.img-viewer {
            display: flex; align-items: center; justify-content: center;
            padding: 2rem; overflow: auto; background: #525659;
        }
        .viewer.img-viewer img {
            max-width: 100%; max-height: 100%; object-fit: contain;
            border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            user-select: none; pointer-events: none; -webkit-user-drag: none;
        }
        .viewer.text-viewer { overflow: auto; padding: 2rem; background: var(--bg); }
        .viewer.text-viewer pre {
            background: #fff; border: 1px solid var(--border); border-radius: 10px;
            padding: 1.5rem; font-family: 'Courier New', monospace;
            font-size: 0.84rem; line-height: 1.65;
            white-space: pre-wrap; word-break: break-word; user-select: none;
        }
        .viewer.media-viewer {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 2rem; gap: 1rem;
        }
        .viewer.media-viewer video,
        .viewer.media-viewer audio {
            max-width: 860px; width: 100%; border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2); outline: none;
        }
        video::-webkit-media-controls-enclosure { overflow: hidden; }
        video::-internal-media-controls-download-button { display: none !important; }

        .viewer.unsupported {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 0.75rem; color: var(--muted); background: var(--bg);
        }
        .viewer.unsupported svg { width: 56px; height: 56px; opacity: 0.3; }
        .viewer.unsupported h3 { font-size: 1rem; color: var(--text); }
        .viewer.unsupported p  { font-size: 0.84rem; }

        /* ── Office viewer ── */
        .office-content {
            background: #fff;
            max-width: 860px;
            width: 100%;
            padding: 3rem 4rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            font-family: 'Segoe UI', sans-serif;
            font-size: 0.95rem;
            line-height: 1.7;
            color: #1a2332;
            user-select: none;
            pointer-events: none;
        }
        .office-content table { border-collapse: collapse; width: 100%; font-size: 0.82rem; }
        .office-content td, .office-content th { border: 1px solid #e2e8f0; padding: 6px 10px; }
        .office-content tr:nth-child(even) { background: #f5f7fa; }
        .office-content th { background: #002242; color: #fff; font-weight: 600; }

        .xlsx-content {
            background: #fff;
            width: 100%;
            max-width: 1100px;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            overflow-x: auto;
            user-select: none;
            pointer-events: none;
        }
        .xlsx-content table { border-collapse: collapse; width: 100%; font-size: 0.82rem; font-family: 'Segoe UI', sans-serif; }
        .xlsx-content td, .xlsx-content th { border: 1px solid #e2e8f0; padding: 6px 10px; }
        .xlsx-content tr:nth-child(even) { background: #f5f7fa; }
        .xlsx-content th { background: #002242; color: #fff; font-weight: 600; }

        @media print {
            body * { display: none !important; }
            body::after {
                display: block !important;
                content: 'Printing is disabled for this document.';
                text-align: center;
                margin-top: 40vh;
                font-size: 1.5rem;
                color: #c00;
                font-family: sans-serif;
            }
        }
/* AI Document Chat */
.ai-ask-btn { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px 6px 6px; border-radius: 999px; border: 1px solid rgba(168,85,247,.4); background: linear-gradient(135deg, rgba(168,85,247,.08), rgba(236,72,153,.08)); color: #a855f7; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; transition: all .2s; margin-left: auto; }
.ai-ask-btn:hover { background: linear-gradient(135deg, #a855f7, #ec4899); color: white; border-color: transparent; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(168,85,247,.35); }
.ai-ask-btn .ai-spark { width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; display: grid; place-items: center; font-size: 10px; }
.ai-ask-btn:hover .ai-spark { background: rgba(255,255,255,.25); }

.ai-chat { position: fixed; top: 0; right: 0; bottom: 0; width: min(420px, 92vw); background: #fff; border-left: 1px solid #e5e7eb; box-shadow: -12px 0 30px rgba(0,0,0,.08); z-index: 9000; display: flex; flex-direction: column; transform: translateX(100%); transition: transform .25s ease; }
.ai-chat.open { transform: translateX(0); }
.ai-chat-head { padding: 14px 18px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, rgba(168,85,247,.06), rgba(236,72,153,.05)); display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.ai-chat-head h3 { font-size: 15px; font-weight: 700; margin: 0; flex: 1; display: inline-flex; align-items: center; gap: 8px; color: #1e293b; }
.ai-chat-head h3 i { color: #a855f7; }
.ai-chat-close { width: 30px; height: 30px; border-radius: 8px; background: transparent; border: 1px solid #e5e7eb; color: #64748b; cursor: pointer; display: grid; place-items: center; }
.ai-chat-close:hover { color: #ef4444; border-color: #ef4444; }
.ai-chat-body { flex: 1; overflow-y: auto; padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; background: #f8fafc; }
.ai-chat-empty { text-align: center; padding: 36px 16px; color: #64748b; }
.ai-chat-empty i { font-size: 32px; color: #a855f7; opacity: .5; margin-bottom: 12px; display: block; }
.ai-chat-empty h4 { font-size: 14px; color: #1e293b; margin-bottom: 6px; font-weight: 700; }
.ai-chat-empty p { font-size: 13px; line-height: 1.5; max-width: 280px; margin: 0 auto; }
.ai-chat-empty .examples { margin-top: 18px; display: flex; flex-direction: column; gap: 6px; }
.ai-chat-empty .examples button { padding: 7px 12px; border-radius: 8px; border: 1px solid #e5e7eb; background: white; color: #1e293b; font: inherit; font-size: 12px; cursor: pointer; text-align: left; transition: all .15s; }
.ai-chat-empty .examples button:hover { border-color: #a855f7; color: #a855f7; }

.ai-msg { display: flex; gap: 10px; max-width: 90%; }
.ai-msg.user { flex-direction: row-reverse; align-self: flex-end; }
.ai-msg .av { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: 11px; flex-shrink: 0; }
.ai-msg.user .av { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: white; }
.ai-msg.assist .av { background: linear-gradient(135deg, #a855f7, #ec4899); color: white; }
.ai-msg .bubble { padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; }
.ai-msg.user .bubble { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: white; border-bottom-right-radius: 4px; }
.ai-msg.assist .bubble { background: white; border: 1px solid #e5e7eb; color: #1e293b; border-bottom-left-radius: 4px; }
.ai-msg.assist.error .bubble { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
.ai-msg .pages { margin-top: 8px; display: flex; gap: 4px; flex-wrap: wrap; }
.ai-msg .pages button { padding: 2px 9px; border-radius: 999px; border: 1px solid #e5e7eb; background: white; color: #a855f7; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
.ai-msg .pages button:hover { background: #a855f7; color: white; border-color: #a855f7; }

.ai-typing .bubble::after { content: '...'; animation: ai-dots 1.4s infinite; }
@keyframes ai-dots { 0%,20% { content: '.'; } 40% { content: '..'; } 60%,100% { content: '...'; } }

.ai-chat-input { padding: 14px 18px; border-top: 1px solid #e5e7eb; background: white; flex-shrink: 0; }
.ai-chat-row { display: flex; gap: 8px; align-items: flex-end; }
.ai-chat-row textarea { flex: 1; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 11px; background: #f8fafc; color: #1e293b; font: inherit; font-size: 13px; resize: none; min-height: 38px; max-height: 100px; outline: none; }
.ai-chat-row textarea:focus { border-color: #a855f7; box-shadow: 0 0 0 3px rgba(168,85,247,.12); }
.ai-chat-send { width: 38px; height: 38px; border-radius: 11px; border: 0; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; cursor: pointer; display: grid; place-items: center; flex-shrink: 0; }
.ai-chat-send:disabled { opacity: .5; cursor: not-allowed; }
.ai-chat-clear { background: transparent; border: 0; color: #94a3b8; font-size: 11px; padding: 4px 8px; margin-top: 8px; cursor: pointer; }
.ai-chat-clear:hover { color: #ef4444; }
@keyframes ai-spin-doc { to { transform: rotate(360deg); } }
.ai-chat-send .spin { animation: ai-spin-doc 1s linear infinite; }
    </style>
</head>

<body oncontextmenu="return false" onselectstart="return false" ondragstart="return false">

<nav>
    <a class="logo" href="index.php">WE<em>SEE</em></a>
</nav>

<div class="file-bar">
    <a class="back-btn" href="javascript:history.back()">
        <i class="fa-solid fa-caret-left"></i>
        Back
    </a>
    <div class="file-info">
        <?php
        if ($isPDF)                                        { $iconClass='pdf';   $iconLabel='PDF'; }
        elseif (in_array($ext,['doc','docx','odt']))       { $iconClass='word';  $iconLabel='DOC'; }
        elseif (in_array($ext,['xls','xlsx','ods','csv'])) { $iconClass='excel'; $iconLabel='XLS'; }
        elseif (in_array($ext,['ppt','pptx','odp']))       { $iconClass='ppt';   $iconLabel='PPT'; }
        elseif ($isImage)                                  { $iconClass='img';   $iconLabel=strtoupper($ext); }
        elseif ($isText)                                   { $iconClass='txt';   $iconLabel=strtoupper($ext); }
        elseif ($isVideo)                                  { $iconClass='vid';   $iconLabel=strtoupper($ext); }
        elseif ($isAudio)                                  { $iconClass='aud';   $iconLabel=strtoupper($ext); }
        else                                               { $iconClass='other'; $iconLabel=strtoupper($ext); }
        ?>
        <div class="file-icon <?= $iconClass ?>"><?= $iconLabel ?></div>
        <span class="file-name"><?= $fileDesc ?></span>
        <span class="file-ext-badge"><?= strtoupper($ext) ?></span>
    </div>
    <?php
      $bm_type = 'file';
      $bm_id   = (int)$fileId;
      include __DIR__ . '/../auth/bookmark_btn.php';
    ?>
    <?php if (in_array($ext, ['pdf','docx','txt','md','log','csv'])): ?>
    <button type="button" class="ai-ask-btn" id="aiAskOpenBtn">
      <span class="ai-spark"><i class="fas fa-wand-magic-sparkles"></i></span>
      Ask AI
    </button>
    <?php endif; ?>
</div>

<?php if ($isPDF): ?>
    <!-- ── PDF Viewer ── -->
    <div class="pdf-toolbar">
        <button onclick="prevPage()">&#9664; Prev</button>
        <div class="page-controls">
            Page <input type="number" id="page-num" value="1" min="1" onchange="goToPage(this.value)">
            <span id="page-count">/ —</span>
        </div>
        <button onclick="nextPage()">Next &#9654;</button>
        <button onclick="zoomOut()">&#8722; Zoom</button>
        <button onclick="zoomIn()">&#43; Zoom</button>
    </div>
    <div class="viewer">
        <div id="pdf-container">
            <div id="pdf-loading">&#128196; Loading document, please wait...</div>
        </div>
    </div>

<?php elseif ($isOffice): ?>
<?php
    $isDocx = in_array($ext, ['doc','docx','odt']);
    $isXlsx = in_array($ext, ['xls','xlsx','ods','csv']);
    $isPpt  = in_array($ext, ['ppt','pptx','odp']);
?>
    <!-- ── Office Viewer ── -->
    <div class="viewer">
        <div id="pdf-container">
            <div id="pdf-loading">&#128196; Loading document, please wait...</div>
        </div>
    </div>

    <?php if ($isDocx): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    <script>
        fetch('serve_file.php?file_id=<?= $fileId ?>', { credentials: 'same-origin' })
            .then(res => {
                if (!res.ok) throw new Error('Failed');
                return res.arrayBuffer();
            })
            .then(buffer => mammoth.convertToHtml({ arrayBuffer: buffer }))
            .then(result => {
                const container = document.getElementById('pdf-container');
                const div = document.createElement('div');
                div.className = 'office-content';
                div.innerHTML = result.value;
                container.innerHTML = '';
                container.appendChild(div);
            })
            .catch(() => {
                document.getElementById('pdf-loading').textContent = 'Error loading document.';
            });
    </script>

    <?php elseif ($isXlsx): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        fetch('serve_file.php?file_id=<?= $fileId ?>', { credentials: 'same-origin' })
            .then(res => {
                if (!res.ok) throw new Error('Failed');
                return res.arrayBuffer();
            })
            .then(buffer => {
                const workbook  = XLSX.read(new Uint8Array(buffer), { type: 'array' });
                const container = document.getElementById('pdf-container');
                container.innerHTML = '';

                workbook.SheetNames.forEach(sheetName => {
                    const html    = XLSX.utils.sheet_to_html(workbook.Sheets[sheetName]);
                    const section = document.createElement('div');
                    section.style.width    = '100%';
                    section.style.maxWidth = '1100px';

                    const label = document.createElement('div');
                    label.textContent   = '📋 ' + sheetName;
                    label.style.cssText = 'color:#fff;font-size:0.82rem;font-weight:600;margin-bottom:0.4rem;opacity:0.7;';

                    const wrap = document.createElement('div');
                    wrap.className = 'xlsx-content';
                    wrap.innerHTML = html;

                    section.appendChild(label);
                    section.appendChild(wrap);
                    container.appendChild(section);
                });
            })
            .catch(() => {
                document.getElementById('pdf-loading').textContent = 'Error loading spreadsheet.';
            });
    </script>

    <?php elseif ($isPpt): ?>
    <script>
        document.getElementById('pdf-loading').innerHTML =
            '&#128202; PowerPoint preview is not available offline.<br>' +
            '<small style="opacity:0.6;font-size:0.8rem;">Tip: Convert to PDF before uploading for full preview support.</small>';
    </script>
    <?php endif; ?>

<?php elseif ($isImage): ?>
    <!-- ── Image Viewer ── -->
    <div class="viewer img-viewer">
        <img src="serve_file.php?file_id=<?= $fileId ?>" alt="<?= $fileDesc ?>" draggable="false">
    </div>

<?php elseif ($isText): ?>
    <!-- ── Text Viewer ── -->
    <?php
        $rawText     = @file_get_contents(__DIR__ . '/../admin/' . $filePath);
        $textContent = $rawText !== false ? htmlspecialchars($rawText) : '(Could not load file content)';
    ?>
    <div class="viewer text-viewer">
        <pre><?= $textContent ?></pre>
    </div>

<?php elseif ($isVideo): ?>
    <!-- ── Video Viewer ── -->
    <div class="viewer media-viewer">
        <video controlsList="nodownload noremoteplayback" disablePictureInPicture controls>
            <source src="serve_file.php?file_id=<?= $fileId ?>" type="video/<?= $ext ?>">
            Your browser does not support video playback.
        </video>
    </div>

<?php elseif ($isAudio): ?>
    <!-- ── Audio Viewer ── -->
    <div class="viewer media-viewer">
        <audio controlsList="nodownload" controls>
            <source src="serve_file.php?file_id=<?= $fileId ?>" type="audio/<?= $ext ?>">
            Your browser does not support audio playback.
        </audio>
    </div>

<?php else: ?>
    <!-- ── Unsupported ── -->
    <div class="viewer unsupported">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3>Preview not available</h3>
        <p>The file type <strong>.<?= $ext ?></strong> cannot be previewed in the browser.</p>
    </div>
<?php endif; ?>

<?php if ($isPDF): ?>
<!-- ── PDF.js (local, offline) ── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    let pdfDoc      = null;
    let totalPages  = 0;
    let currentPage = 1;
    let scale       = 1.4;
    const container = document.getElementById('pdf-container');

    fetch('serve_file.php?file_id=<?= $fileId ?>', { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error('Failed to load file');
            return res.arrayBuffer();
        })
        .then(buffer => pdfjsLib.getDocument({ data: buffer }).promise)
        .then(pdf => {
            pdfDoc     = pdf;
            totalPages = pdf.numPages;
            document.getElementById('page-count').textContent = '/ ' + totalPages;
            document.getElementById('page-num').max = totalPages;
            const loading = document.getElementById('pdf-loading');
            if (loading) loading.remove();
            renderAllPages();
        })
        .catch(() => {
            const loading = document.getElementById('pdf-loading');
            if (loading) loading.textContent = 'Error loading document. Please try again.';
        });

    function renderAllPages() {
        container.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) renderPage(i);
    }

    function renderPage(num) {
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({ scale });
            const canvas   = document.createElement('canvas');
            canvas.height  = viewport.height;
            canvas.width   = viewport.width;
            canvas.id      = 'page-' + num;
            canvas.addEventListener('contextmenu', e => e.preventDefault());
            container.appendChild(canvas);
            page.render({ canvasContext: canvas.getContext('2d'), viewport });
        });
    }

    function prevPage() {
        if (currentPage <= 1) return;
        currentPage--;
        updatePageInput();
        scrollToPage(currentPage);
    }

    function nextPage() {
        if (currentPage >= totalPages) return;
        currentPage++;
        updatePageInput();
        scrollToPage(currentPage);
    }

    function goToPage(val) {
        const num = parseInt(val);
        if (num >= 1 && num <= totalPages) {
            currentPage = num;
            scrollToPage(num);
        }
    }

    function scrollToPage(num) {
        const el = document.getElementById('page-' + num);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function updatePageInput() {
        document.getElementById('page-num').value = currentPage;
    }

    function zoomIn()  { scale = Math.min(scale + 0.25, 3.0); renderAllPages(); }
    function zoomOut() { scale = Math.max(scale - 0.25, 0.5); renderAllPages(); }

    document.getElementById('pdf-container').addEventListener('scroll', function() {
        for (let i = totalPages; i >= 1; i--) {
            const el = document.getElementById('page-' + i);
            if (el && el.getBoundingClientRect().top <= 200) {
                currentPage = i;
                updatePageInput();
                break;
            }
        }
    });
</script>
<?php endif; ?>

<script>
    // Block keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (
            (e.ctrlKey && ['s','p','u','a'].includes(e.key.toLowerCase())) ||
            (e.ctrlKey && e.shiftKey && ['i','j','c'].includes(e.key.toLowerCase())) ||
            e.key === 'F12'
        ) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);

    document.addEventListener('contextmenu', e => e.preventDefault());

    window.onbeforeprint = function() {
        document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;"><h2 style="color:#c00;">&#128274; Printing is disabled.</h2></div>';
    };

    document.addEventListener('dragstart', e => e.preventDefault());

    setInterval(function() {
        if (window.outerWidth - window.innerWidth > 160 ||
            window.outerHeight - window.innerHeight > 160) {
            document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;"><h2 style="color:#c00;">&#128274; Access restricted.</h2></div>';
        }
    }, 1000);
</script>

<?php if (in_array($ext, ['pdf','docx','txt','md','log','csv'])): ?>
<!-- AI Document Chat sidebar -->
<aside class="ai-chat" id="aiChat" aria-hidden="true">
  <div class="ai-chat-head">
    <h3><i class="fas fa-wand-magic-sparkles"></i> Ask this document</h3>
    <button type="button" class="ai-chat-close" id="aiChatClose"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="ai-chat-body" id="aiChatBody">
    <div class="ai-chat-empty" id="aiChatEmpty">
      <i class="fas fa-comments"></i>
      <h4>Chat with this document</h4>
      <p>Ask anything and I'll answer using only the contents of this file, with page citations.</p>
      <div class="examples">
        <button type="button" data-q="Summarize this document in 3 bullet points.">📝 Summarize this document in 3 bullet points</button>
        <button type="button" data-q="What are the key takeaways?">💡 What are the key takeaways?</button>
        <button type="button" data-q="What does it say about the main topic?">🔎 What does it say about the main topic?</button>
      </div>
    </div>
  </div>
  <div class="ai-chat-input">
    <div class="ai-chat-row">
      <textarea id="aiChatTxt" placeholder="Ask anything about this file…" rows="1"></textarea>
      <button type="button" class="ai-chat-send" id="aiChatSend"><i class="fas fa-paper-plane"></i></button>
    </div>
    <button type="button" class="ai-chat-clear" id="aiChatClear" style="display:none;"><i class="fas fa-rotate-left"></i> Clear conversation</button>
  </div>
</aside>

<script>
(function() {
  const openBtn = document.getElementById('aiAskOpenBtn');
  const chat    = document.getElementById('aiChat');
  const closeBtn = document.getElementById('aiChatClose');
  const body    = document.getElementById('aiChatBody');
  const emptyEl = document.getElementById('aiChatEmpty');
  const txt     = document.getElementById('aiChatTxt');
  const send    = document.getElementById('aiChatSend');
  const clearBtn = document.getElementById('aiChatClear');
  if (!openBtn) return;

  const FID = <?= (int)$fileId ?>;
  let history = [];
  let pending = false;

  openBtn.addEventListener('click', () => { chat.classList.add('open'); txt.focus(); });
  closeBtn.addEventListener('click', () => chat.classList.remove('open'));

  // Example chips
  emptyEl.querySelectorAll('button[data-q]').forEach(b => {
    b.addEventListener('click', () => { txt.value = b.getAttribute('data-q'); ask(); });
  });

  // Auto-grow textarea
  txt.addEventListener('input', () => {
    txt.style.height = 'auto';
    txt.style.height = Math.min(100, txt.scrollHeight) + 'px';
  });

  txt.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(); }
  });
  send.addEventListener('click', ask);
  clearBtn.addEventListener('click', () => {
    if (!confirm('Clear this conversation?')) return;
    history = [];
    body.innerHTML = '';
    body.appendChild(emptyEl);
    emptyEl.style.display = '';
    clearBtn.style.display = 'none';
  });

  function esc(s) { return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  function appendMsg(role, text, opts) {
    opts = opts || {};
    if (emptyEl && emptyEl.parentNode === body) emptyEl.style.display = 'none';
    const cls = role === 'user' ? 'user' : 'assist';
    const avIc = role === 'user' ? 'fa-user' : 'fa-wand-magic-sparkles';
    const div = document.createElement('div');
    div.className = 'ai-msg ' + cls + (opts.error ? ' error' : '') + (opts.typing ? ' ai-typing' : '');
    div.innerHTML = `
      <div class="av"><i class="fas ${avIc}"></i></div>
      <div class="bubble">${opts.typing ? '' : esc(text)}</div>
    `;
    body.appendChild(div);
    if (opts.pages && opts.pages.length) {
      const p = document.createElement('div');
      p.className = 'pages';
      p.innerHTML = '<span style="font-size:11px;color:#94a3b8;margin-right:4px;">Refs:</span>' + opts.pages.map(n => `<button type="button" data-jump="${n}">p.${n}</button>`).join('');
      div.querySelector('.bubble').appendChild(p);
      p.querySelectorAll('[data-jump]').forEach(b => b.addEventListener('click', () => jumpToPage(parseInt(b.getAttribute('data-jump'), 10))));
    }
    body.scrollTop = body.scrollHeight;
    return div;
  }

  // Try to scroll PDF viewer to a page if pdf.js is loaded on this page
  function jumpToPage(n) {
    if (typeof window.goToPage === 'function') { window.goToPage(n); return; }
    // Common pdf.js render: find page element
    const el = document.querySelector('[data-page-number="' + n + '"]') || document.getElementById('page-' + n);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  async function ask() {
    if (pending) return;
    const q = txt.value.trim();
    if (!q) return;
    pending = true;
    send.disabled = true;
    send.innerHTML = '<i class="fas fa-spinner spin"></i>';
    appendMsg('user', q);
    history.push({ role: 'user', content: q });
    txt.value = '';
    txt.style.height = 'auto';
    clearBtn.style.display = 'inline-block';
    const placeholder = appendMsg('assistant', '', { typing: true });

    try {
      const fd = new FormData();
      fd.append('file_id', FID);
      fd.append('question', q);
      fd.append('history', JSON.stringify(history.slice(0, -1)));
      const r = await fetch('ask_document.php', { method: 'POST', body: fd });
      const js = await r.json();
      placeholder.remove();
      if (!js.ok) { appendMsg('assistant', js.error || 'Something went wrong.', { error: true }); }
      else {
        appendMsg('assistant', js.answer, { pages: js.page_refs });
        history.push({ role: 'assistant', content: js.answer });
      }
    } catch (e) {
      placeholder.remove();
      appendMsg('assistant', e.message || 'Network error.', { error: true });
    } finally {
      pending = false;
      send.disabled = false;
      send.innerHTML = '<i class="fas fa-paper-plane"></i>';
      txt.focus();
    }
  }
})();
</script>
<?php endif; ?>
</body>
</html>