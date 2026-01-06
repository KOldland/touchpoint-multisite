<?php
/**
 * Test PDF Generation
 * Run: php wp-content/plugins/khm-plugin/test-pdf.php
 */

require_once dirname(__DIR__, 3) . '/wp-load.php';

$post_id = 161; // Beyond Cost-Plus article
$user_id = 1;

echo "=== KHM PDF Generation Test ===\n\n";

// Get post info
$post = get_post($post_id);
if (!$post) {
    echo "ERROR: Post {$post_id} not found\n";
    exit(1);
}

echo "Post: {$post->post_title}\n";
echo "Status: {$post->post_status}\n";
echo "Content length: " . strlen($post->post_content) . " chars\n\n";

// Test PDFService
echo "Generating PDF...\n";

$pdfService = new \KHM\Services\PDFService();
$result = $pdfService->generateArticlePDF($post_id, $user_id);

if ($result['success']) {
    echo "\n✅ PDF Generated Successfully!\n";
    echo "   Filename: " . $result['filename'] . "\n";
    echo "   Size: " . number_format($result['size']) . " bytes\n";
    
    // Save to uploads for inspection
    $upload_dir = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/test-pdf-output.pdf';
    file_put_contents($path, $result['pdf_data']);
    echo "   Saved to: {$path}\n";
    echo "\n   You can open this file to verify the PDF looks correct.\n";
} else {
    echo "\n❌ PDF Generation Failed!\n";
    echo "   Error: " . $result['error'] . "\n";
}

echo "\n=== END TEST ===\n";
