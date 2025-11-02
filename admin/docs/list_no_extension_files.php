<?php

// Definir o Content-Type para HTML caso seja acessado via navegador
header('Content-Type: text/html; charset=utf-8');

function listFilesWithoutExtension(string $startPath): string {
    $startPath = realpath($startPath);
    if (!$startPath || !is_dir($startPath)) {
        return "Error: Path is not valid or accessible: " . htmlspecialchars($startPath) . "\n";
    }

    $output = "<h1>Files without extension in: " . htmlspecialchars($startPath) . "</h1>\n";
    $output .= "<ul>\n";
    $count = 0;

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($startPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY // Only get files
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
                if (empty($extension)) { // Extension is empty
                    $output .= "<li>" . htmlspecialchars($file->getPathname()) . "</li>\n";
                    $count++;
                }
            }
        }
    } catch (Exception $e) {
        return "An error occurred during scan: " . htmlspecialchars($e->getMessage()) . "\n";
    }

    $output .= "</ul>\n";
    $output .= "<p>Total files without extension found: " . $count . "</p>\n";
    return $output;
}

// --- CONFIGURATION ---
// This should be the root of your project where you found the '6' files without extension.
$scanRoot = '/home4/chefej82/bacosearch.com'; 

echo listFilesWithoutExtension($scanRoot);

?>