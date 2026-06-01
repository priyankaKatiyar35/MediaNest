<?php
/**
 * MediaNest AI — Extract document text for Q&A
 * --------------------------------------------------------------
 * POST: file_id, csrf
 * Returns: JSON {ok, words, pages, preview}
 *
 * Supported:
 *  - PDF  via Smalot/PdfParser (drop pdfparser.php into admin/lib/)
 *  - DOCX via PHP's ZipArchive (no external lib needed)
 *  - TXT  via file_get_contents
 *
 * Caches extracted text in `document_extracts`. Re-running overwrites.
 */
require_once __DIR__ . '/admin_auth.php';
requireAdmin();
global $conn;

header('Content-Type: application/json');
set_time_limit(120);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST only.');
    if (!csrfCheck($_POST['csrf'] ?? '')) throw new RuntimeException('Session expired.');

    $fid = intval($_POST['file_id'] ?? 0);
    if ($fid <= 0) throw new RuntimeException('Missing file_id.');

    // Look up the file
    $stmt = mysqli_prepare($conn, "SELECT file_name, file_path FROM files WHERE file_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $fid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) throw new RuntimeException('File not found in DB.');

    // Resolve the disk path (Documents files live relative to Documents/ historically,
    // but uploaded via admin/uploadfiles.php they go in admin/uploads/).
    $candidates = [
        __DIR__ . '/../' . $row['file_path'],
        __DIR__ . '/' . $row['file_path'],
        __DIR__ . '/../Documents/' . $row['file_path'],
    ];
    $disk_path = null;
    foreach ($candidates as $c) if (is_file($c)) { $disk_path = $c; break; }
    if (!$disk_path) throw new RuntimeException('File not on disk: ' . $row['file_path']);

    $ext = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
    $text = ''; $pages = 0; $extractor = '';

    if ($ext === 'pdf') {
        $extractor = 'pdfparser';
        $lib = __DIR__ . '/lib/pdfparser.php';
        if (!is_file($lib)) {
            throw new RuntimeException(
                'PdfParser library missing. Download admin/lib/pdfparser.php from '
                . 'https://github.com/smalot/pdfparser/releases (or use composer-built file). '
                . 'See README for one-line setup.'
            );
        }
        require_once $lib;
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($disk_path);
        $pages  = count($pdf->getPages());
        // page-by-page so we can keep [Page N] markers for citations
        $chunks = [];
        $i = 1;
        foreach ($pdf->getPages() as $p) {
            $t = trim($p->getText());
            if ($t !== '') $chunks[] = "[Page $i]\n$t";
            $i++;
        }
        $text = implode("\n\n", $chunks);
    }
    elseif ($ext === 'docx') {
        $extractor = 'zip-xml';
        $zip = new ZipArchive;
        if ($zip->open($disk_path) !== true) throw new RuntimeException('Cannot open DOCX file.');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) throw new RuntimeException('DOCX missing document.xml.');
        // Replace paragraph and break tags with newlines, then strip remaining tags
        $xml = preg_replace('#</w:p>#', "\n", $xml);
        $xml = preg_replace('#<w:br/>#', "\n", $xml);
        $text = trim(strip_tags($xml));
        // Light cleanup
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $pages = max(1, (int) round(str_word_count($text) / 300)); // approx 300 words per page
    }
    elseif (in_array($ext, ['txt','md','log','csv'])) {
        $extractor = 'plain';
        $text = file_get_contents($disk_path);
        if ($text === false) throw new RuntimeException('Could not read file.');
        $pages = max(1, (int) round(strlen($text) / 1500));
    }
    else {
        throw new RuntimeException("Unsupported file type: .$ext (supported: PDF, DOCX, TXT, MD)");
    }

    $text = trim($text);
    if ($text === '') throw new RuntimeException('No readable text was found in this file.');

    $words = str_word_count($text);

    // Save (upsert)
    $stmt = mysqli_prepare($conn,
        "INSERT INTO document_extracts (file_id, full_text, page_count, word_count, extractor)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            full_text=VALUES(full_text),
            page_count=VALUES(page_count),
            word_count=VALUES(word_count),
            extractor=VALUES(extractor),
            created_at=CURRENT_TIMESTAMP");
    mysqli_stmt_bind_param($stmt, 'isiis', $fid, $text, $pages, $words, $extractor);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('DB error: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    adminAuditLog('doc_extract', "File #$fid: $words words, $pages pages ($extractor)");

    echo json_encode([
        'ok'      => true,
        'file_id' => $fid,
        'words'   => $words,
        'pages'   => $pages,
        'preview' => mb_substr($text, 0, 200),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}