<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

header('Content-Type: application/json');

$debugInfo = [];

function sanitizeInput($input) {
    // Allow only valid characters for English and Spanish, including valid HTML tags and attributes
    // This regex removes any character that is not a valid ASCII character or part of HTML
    $input = preg_replace('/[^\x09\x0A\x0D\x20-\x7EáéíóúÁÉÍÓÚñÑüÜ.,;:?!\'"()\-–—_\/\[\]\{\}@&%$#*+=<>]|&(?![A-Za-z]{2,8};)/u', '', $input);

    // Add additional sanitization if needed
    return $input;
}

function extractImagesAndCleanText($html, &$debugInfo) {
    // 1. Sanitize the HTML content to remove unwanted characters, but allow valid HTML
    $html = sanitizeInput($html);
    $debugInfo['sanitizedHtml'] = $html; // Add to debug info

    // 2. Extract all image URLs
    preg_match_all('/<img[^>]+src="([^">]+)"/i', $html, $images);
    $imageList = $images[1];
    $debugInfo['imageList'] = $imageList; // Add to debug info

    // 3. Clean the text by removing unnecessary tags but preserving valid content
    // Remove script and style tags
    $cleanedHtml = preg_replace(['/<style\b[^>]*>(.*?)<\/style>/si', '/<script\b[^>]*>(.*?)<\/script>/si'], '', $html);
    $debugInfo['cleanedHtml'] = $cleanedHtml; // Add to debug info

    // Extract plain text from the cleaned HTML
    $plainText = strip_tags($cleanedHtml);
    $debugInfo['plainText'] = $plainText; // Add to debug info

    // Further cleanup if needed (e.g., specific patterns)
    $patterns = [
        '/^.*APP/',                          // Remove text starting with "APP"
        '/[A-Za-z0-9áéíóúÁÉÍÓÚñÑ\s]+’s.*$/',   // Remove text matching specific pattern
        '/Share.*/'                            // Remove text starting with "Share"
    ];
    $replacements = [
        '', // Remove the text matching the patterns
        '', // Remove the text matching "’s" pattern
        ''  // Remove text starting with "Share"
    ];
    // Apply all preg_replace operations in a single call
    $plainText = preg_replace($patterns, $replacements, $plainText);
    $debugInfo['plainTextAfterPatterns'] = $plainText; // Add to debug info

    // Remove additional spaces at the start and end of the text
    $plainText = trim($plainText);
    $debugInfo['finalPlainText'] = $plainText; // Add to debug info

    // Return the results
    return [
        'images' => $imageList,
        'plainText' => $plainText,
    ];
}

try {
    // Log the raw input for debugging
    $input = file_get_contents('php://input');
    file_put_contents('debug_log.txt', $input, FILE_APPEND);
    $debugInfo['rawInput'] = $input; // Add to debug info

    // First, check if data is sent via a standard POST form
    if (isset($_POST['html_content'])) {
        $htmlContent = $_POST['html_content'];
        $debugInfo['source'] = 'POST';
        $debugInfo['rawHtmlContent'] = $htmlContent; // Add to debug info
    } else {
        // If no POST data, try capturing the raw input (JSON)
        $data = json_decode($input, true);
        $debugInfo['source'] = 'Raw Input';

        if (isset($data['html_content'])) {
            $htmlContent = $data['html_content'];
            $debugInfo['rawHtmlContent'] = $htmlContent; // Add to debug info
        } else {
            // Respond with an error if HTML content is not found
            echo json_encode([
                'status' => 'error',
                'message' => 'No se encontró contenido HTML en la entrada.',
                'debug' => $debugInfo,
            ]);
            return;
        }
    }

    $result = extractImagesAndCleanText($htmlContent, $debugInfo);

    // Return the response as JSON with debugging
    echo json_encode([
        'status' => 'success',
        'data' => $result,
        'debug' => $debugInfo,
    ]);

} catch (Exception $e) {
    // Catch any exceptions and return an error response with debugging
    echo json_encode([
        'status' => 'error',
        'message' => 'Ocurrió un error en el procesamiento.',
        'debug' => $debugInfo,
        'error' => $e->getMessage(),
    ]);
}
