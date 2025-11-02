<?php

// Definir o Content-Type para HTML antes de qualquer saída
header('Content-Type: text/html; charset=utf-8');

/**
 * Scans a directory for PHP, JS, and CSS files and identifies their dependencies
 * and potential orphan files.
 *
 * @param string $projectRoot The absolute path to the project root.
 * @return array A structured array containing analysis details.
 */
function analyzeOrphanFiles(string $projectRoot): array {
    $projectRoot = realpath($projectRoot);
    if (!$projectRoot || !is_dir($projectRoot)) {
        error_log("ERROR: Project root path is not valid or accessible: " . $projectRoot);
        return ['error' => "Error: Project root path is not valid or accessible. (Path: " . htmlspecialchars($projectRoot) . ")"];
    }

    if (!is_readable($projectRoot)) {
        error_log("ERROR: Project root directory is not readable: " . $projectRoot);
        return ['error' => "Error: Project root directory is not readable. Check permissions for: " . htmlspecialchars($projectRoot)];
    }

    $allFiles = [];         // All files of interest (PHP, JS, CSS, HTML) - absolute paths
    $fileAnalysis = [];     // Detailed analysis for each file
    $fileTypeCounts = [];   // Counts of files by extension
    
    $directoriesScannedCount = 0;
    $filesFoundCount = 0;

    // Regexes for dependency detection (Capture the full content within quotes)
    $phpIncludeRegex = '/(?:include|require)(?:_once)?\s*\(?[\'"]([^\'"]+\.php)[\'"]\)?/i';
    
    // HTML script/link tags: Captures anything inside src="" or href=""
    $htmlScriptCssLinkRegex = '/(?:<script[^>]*src=[\'"]([^\'"]+)[\'"]|<link[^>]*href=[\'"]([^\'"]+)[\'"][^>]*rel=[\'"]stylesheet[\'"])/i';
    
    // CSS @import and url(): Captures anything inside @import or url().
    $cssImportUrlRegex = '/@import\s*(?:url\()?[\'"]([^\'"]+)[\'"]\)?;|url\([\'"]?([^\'"]+)[\'"]?\)/i';
    
    // JS imports/requires/fetches: Captures anything inside import/require/fetch.
    $jsDependencyRegex = '/(?:import|require)\s*\(?[\'"]([^\'"]+)[\'"]\)?;|fetch\([\'"]([^\'"]+)[\'"]\)/i'; 
    

    // --- Recursive helper function to collect all files and initialize analysis structure ---
    $collectFilesAndInitializeAnalysis = function(string $currentDir) 
        use ($projectRoot, &$allFiles, &$fileAnalysis, 
             &$directoriesScannedCount, &$filesFoundCount, &$fileTypeCounts, &$collectFilesAndInitializeAnalysis) 
    {
        $directoriesScannedCount++;
        $items = @scandir($currentDir);
        if ($items === false) {
            error_log("Permission denied or cannot read directory: " . $currentDir);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $currentDir . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($itemPath)) {
                if (is_readable($itemPath)) {
                    $collectFilesAndInitializeAnalysis($itemPath); // Recurse for subdirectories
                } else {
                    error_log("Permission denied for directory: " . $itemPath);
                }
            } elseif (is_file($itemPath)) {
                $filesFoundCount++; // Count all files found

                $extension = pathinfo($itemPath, PATHINFO_EXTENSION); 
                $lowerExtension = strtolower($extension);
                $fileTypeCounts[$lowerExtension] = ($fileTypeCounts[$lowerExtension] ?? 0) + 1; 

                // Only track specific file types for detailed analysis (PHP, JS, CSS, HTML for references)
                if (in_array($lowerExtension, ['php', 'js', 'css', 'html'])) { 
                    $allFiles[] = $itemPath; // Add absolute path
                    $relativePath = str_replace($projectRoot, '', $itemPath); 
                    if (!str_starts_with($relativePath, '/')) {
                        $relativePath = '/' . $relativePath;
                    }

                    // Initialize analysis structure for this file
                    $fileAnalysis[$itemPath] = [ // Key by absolute path for easier lookup
                        'path' => $relativePath, // Store relative path for display
                        'type' => $lowerExtension, // Store file type
                        'includes' => [],          // Files this file explicitly includes/references
                        'is_included_by' => [],    // Files that explicitly include/reference this file
                        'status_message' => ''
                    ];
                }
            }
        }
    };

    // --- Phase 1: Call the recursive function to collect all files and initialize analysis ---
    $collectFilesAndInitializeAnalysis($projectRoot);

    // --- Phase 2: Populate 'includes' and 'is_included_by' ---
    foreach ($allFiles as $scannerFilePath) {
        $content = @file_get_contents($scannerFilePath); 
        if ($content === false) {
            error_log("Cannot read file content: " . $scannerFilePath);
            continue; 
        }

        $scannerFileType = strtolower(pathinfo($scannerFilePath, PATHINFO_EXTENSION));

        // Function to process a matched path snippet
        $processIncludedPath = function($includedPathSnippet, $scannerFilePath, $projectRoot, &$fileAnalysis) {
            // *** DEBUG: Log the raw snippet captured by regex ***
            error_log("DEBUG RAW SNIPPET: From " . $scannerFilePath . " -> Raw: '" . $includedPathSnippet . "'");

            $resolvedPath = null;
            $isExternalUrl = false; 
            $finalPathForResolution = ''; 

            // --- Robust Extraction of the Pure Path String from various PHP/HTML/CSS/JS constructs ---
            // This is a heuristic. We try to find patterns that look like file paths.
            
            // Pattern 1: '/path/to/file.ext' or '../path/to/file.ext' inside any quotes, possibly with PHP around
            if (preg_match('/[\'"](\/?(?:\.\.?\/)*[^\'"]+\.(?:php|js|mjs|css|png|jpg|jpeg|gif|svg|woff2|ttf|otf))[\'"]/', $includedPathSnippet, $m)) {
                $finalPathForResolution = $m[1];
            } 
            // Pattern 2: Specific for SITE_URL . '/path'
            elseif (preg_match('/(?:SITE_URL|SITE_ROOT_URL)\s*\.\s*[\'"]([^\'"]+)[\'"]/', $includedPathSnippet, $m)) {
                $finalPathForResolution = $m[1];
            }
            // Pattern 3: Specific for htmlspecialchars(SITE_URL . '/path')
            elseif (preg_match('/htmlspecialchars\s*\(\s*(?:SITE_URL|SITE_ROOT_URL)\s*\.\s*[\'"]([^\'"]+)[\'"]/', $includedPathSnippet, $m)) {
                $finalPathForResolution = $m[1];
            }
            // Pattern 4: Direct absolute URL (e.g., https://example.com/script.js or //example.com/style.css)
            elseif (str_contains($includedPathSnippet, '://') || str_starts_with($includedPathSnippet, '//')) {
                $finalPathForResolution = strtok($includedPathSnippet, '?'); // Remove query string
                $isExternalUrl = true; 
            }
            // Pattern 5: Absolute system path for PHP includes (e.g. /home4/chefej82/config.php)
            elseif (str_starts_with($includedPathSnippet, '/home4/chefej82/')) {
                $finalPathForResolution = $includedPathSnippet;
            }
            // Fallback: Use the raw snippet if no specific pattern matched (after basic cleanup)
            else {
                $finalPathForResolution = preg_replace('/<\?php[^>]*\?>/i', '', $includedPathSnippet); // Remove any stray PHP tags
                $finalPathForResolution = trim($finalPathForResolution, "'\" \t\n\r\0\x0B"); // Clean whitespace and quotes
                $finalPathForResolution = strtok($finalPathForResolution, '?'); 
                $finalPathForResolution = strtok($finalPathForResolution, '#');
            }


            // --- Now attempt to resolve the cleaned path ---
            // If it was marked as external, skip internal resolution
            if ($isExternalUrl) {
                // Do nothing, already marked as external
            }
            // Resolve based on type of path extracted
            elseif (str_starts_with($finalPathForResolution, '/home4/chefej82/')) { // System absolute path
                $resolvedPath = realpath($finalPathForResolution);
            } 
            elseif (str_starts_with($finalPathForResolution, '/')) { // Web root absolute path
                $resolvedPath = realpath($projectRoot . $finalPathForResolution);
            } 
            elseif (!empty($finalPathForResolution)) { // Relative path (only if something was actually extracted)
                $resolvedPath = realpath(dirname($scannerFilePath) . DIRECTORY_SEPARATOR . $finalPathForResolution);
            }
            
            // Log for debugging final resolution
            error_log("DEBUG RESOLUTION: Scanner: " . $scannerFilePath . 
                      " -> Original Snippet: '" . $includedPathSnippet . 
                      "' -> Final Path for Resolution: '" . $finalPathForResolution . 
                      "' -> Resolved: '" . ($resolvedPath ?: 'FAILED') . "'" .
                      " (Exists: " . (file_exists((string)$resolvedPath) ? 'Yes' : 'No') . ")" . 
                      " (In root: " . ($resolvedPath && str_starts_with((string)$resolvedPath, $projectRoot) ? 'Yes' : 'No') . ")" .
                      " (Is tracked: " . ($resolvedPath && isset($fileAnalysis[$resolvedPath]) ? 'Yes' : 'No') . ")" .
                      " (Is External URL: " . ($isExternalUrl ? 'Yes' : 'No') . ")"
            );

            // If path resolved, is within project root, is a file we're tracking, AND NOT an external URL
            if ($resolvedPath && str_starts_with((string)$resolvedPath, $projectRoot) && isset($fileAnalysis[$resolvedPath]) && !$isExternalUrl) {
                $relativePathForDisplay = str_replace($projectRoot, '', $resolvedPath);
                if (!str_starts_with($relativePathForDisplay, '/')) {
                    $relativePathForDisplay = '/' . $relativePathForDisplay;
                }

                if (!in_array($relativePathForDisplay, $fileAnalysis[$scannerFilePath]['includes'])) {
                    $fileAnalysis[$scannerFilePath]['includes'][] = $relativePathForDisplay;
                }
                
                $scannerRelativePath = str_replace($projectRoot, '', $scannerFilePath);
                if (!str_starts_with($scannerRelativePath, '/')) {
                    $scannerRelativePath = '/' . $scannerRelativePath;
                }
                if (!in_array($scannerRelativePath, $fileAnalysis[$resolvedPath]['is_included_by'])) {
                     $fileAnalysis[$resolvedPath]['is_included_by'][] = $scannerRelativePath;
                }
            } 
        };


        // Detect dependencies based on file type
        switch ($scannerFileType) {
            case 'php':
                if (preg_match_all($phpIncludeRegex, $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $includedPathSnippet = $match[1] ?? null; 
                        if ($includedPathSnippet) {
                            $processIncludedPath($includedPathSnippet, $scannerFilePath, $projectRoot, $fileAnalysis);
                        }
                    }
                }
                // Fall through to scan for HTML tags in PHP files (which often output HTML)
            case 'html': 
                if (preg_match_all($htmlScriptCssLinkRegex, $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $includedPathSnippet = $match[1] ?? $match[2] ?? null; 
                        if ($includedPathSnippet) {
                           $processIncludedPath($includedPathSnippet, $scannerFilePath, $projectRoot, $fileAnalysis);
                        }
                    }
                }
                break;
            case 'css':
                if (preg_match_all($cssImportUrlRegex, $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $includedPathSnippet = $match[1] ?? $match[2] ?? null; 
                        if ($includedPathSnippet) {
                           $processIncludedPath($includedPathSnippet, $scannerFilePath, $projectRoot, $fileAnalysis);
                        }
                    }
                }
                break;
            case 'js':
                if (preg_match_all($jsDependencyRegex, $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $includedPathSnippet = $match[1] ?? $match[2] ?? null; 
                        if ($includedPathSnippet) {
                           $processIncludedPath($includedPathSnippet, $scannerFilePath, $projectRoot, $fileAnalysis);
                        }
                    }
                }
                break;
        }
    }

    // --- Phase 3: Determine Status Message for each file (based purely on includes/is_included_by) ---
    foreach ($fileAnalysis as $filePath => &$data) { 
        sort($data['includes']);
        sort($data['is_included_by']);

        $isIncluded = !empty($data['is_included_by']);
        $includesOtherFiles = !empty($data['includes']);

        $data['status_message'] = "Desconhecido"; // Default status

        if (!$isIncluded && !$includesOtherFiles) {
            $data['status_message'] = "POTENCIAL ÓRFÃO (Não é chamado e não chama)";
        } elseif (!$isIncluded && $includesOtherFiles) {
            $data['status_message'] = "Arquivo Iniciador (Chama outros, não é chamado)";
        } elseif ($isIncluded && !$includesOtherFiles) {
            $data['status_message'] = "Módulo/Dependência (É chamado, não chama)";
        } elseif ($isIncluded && $includesOtherFiles) {
            $data['status_message'] = "Módulo/Dependência (É chamado e chama outros)";
        }
    }
    unset($data); 

    $sortedFileAnalysis = array_values($fileAnalysis); 
    usort($sortedFileAnalysis, function($a, $b) {
        return strnatcasecmp($a['path'], $b['path']);
    });

    // Format file type counts for display
    $formattedFileTypeCounts = [];
    arsort($fileTypeCounts); 
    foreach ($fileTypeCounts as $ext => $count) {
        $formattedFileTypeCounts[] = "{$count} {$ext}";
    }
    $fileTypeCountsString = implode(', ', $formattedFileTypeCounts);

    return [
        'analysis' => $sortedFileAnalysis,
        'summary' => [
            'total_files_analyzed_for_dependencies' => count($allFiles), 
            'potential_orphans_count' => count(array_filter($fileAnalysis, function($item) {
                return str_contains($item['status_message'], 'ÓRFÃO');
            })),
            'directories_scanned' => $directoriesScannedCount,
            'total_files_found' => $filesFoundCount,
            'file_type_counts_string' => $fileTypeCountsString,
            'file_type_counts_array' => $fileTypeCounts 
        ]
    ];
}


// --- CONFIGURATION ---
// IMPORTANT: This path is CRUCIAL. It should be the absolute path to the ROOT
// of your web application.
//
// You confirmed '/home4/chefej82/bacosearch.com' is the existing root.
$projectToScan = '/home4/chefej82/bacosearch.com'; 


// --- EXECUTION ---
$analysisResult = analyzeOrphanFiles($projectToScan);

$htmlOutput = "<!DOCTYPE html>\n";
$htmlOutput .= "<html lang=\"en\">\n";
$htmlOutput .= "<head>\n";
$htmlOutput .= "    <meta charset=\"UTF-8\">\n";
$htmlOutput .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
$htmlOutput .= "    <title>PHP, JS, CSS Code Dependency & Orphan Analyzer</title>\n"; // Updated title
$htmlOutput .= "    <style>\n";
$htmlOutput .= "        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #282c34; color: #abb2bf; margin: 20px; }\n";
$htmlOutput .= "        .container { max-width: 95%; margin: 0 auto; background-color: #333842; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }\n";
$htmlOutput .= "        h1 { color: #61afef; text-align: center; margin-bottom: 30px; }\n";
$htmlOutput .= "        h2 { color: #56b6c2; margin-top: 25px; margin-bottom: 10px; border-bottom: 1px solid #4b5263; padding-bottom: 5px; }\n";
$htmlOutput .= "        .section { margin-bottom: 20px; padding: 15px; background-color: #2d3035; border-radius: 8px; }\n";
$htmlOutput .= "        .info { color: #98c379; margin-bottom: 10px; }\n";
$htmlOutput .= "        .warning { color: #e5c07b; margin-bottom: 10px; }\n";
$htmlOutput .= "        .error { color: #ff6666; }\n";
$htmlOutput .= "        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }\n";
$htmlOutput .= "        th, td { border: 1px solid #4b5263; padding: 8px 12px; text-align: left; vertical-align: top; }\n";
$htmlOutput .= "        th { background-color: #3e4451; color: #fff; font-weight: bold; }\n";
$htmlOutput .= "        tr:nth-child(even) { background-color: #2f343a; }\n";
$htmlOutput .= "        tr:hover { background-color: #3a3f47; }\n";
$htmlOutput .= "        td ul { margin: 0; padding-left: 20px; list-style: none; }\n"; 
$htmlOutput .= "        td li { line-height: 1.3; margin-bottom: 2px; }\n";
$htmlOutput .= "        td code { background-color: #21252b; padding: 2px 5px; border-radius: 3px; font-size: 0.85em; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; }\n"; 
$htmlOutput .= "        .status-orphan { color: #ff6666; font-weight: bold; }\n";
$htmlOutput .= "        .status-entry { color: #61afef; font-weight: bold; }\n";
$htmlOutput .= "        .status-dependency { color: #98c379; }\n";
$htmlOutput .= "        .status-warning { color: #e5c07b; }\n";
    $htmlOutput .= "    </style>\n";
$htmlOutput .= "</head>\n";
$htmlOutput .= "<body>\n";
$htmlOutput .= "    <div class=\"container\">\n";
$htmlOutput .= "        <h1>PHP, JS, CSS Code Dependency & Orphan Analyzer</h1>\n";

if (isset($analysisResult['error'])) {
    $htmlOutput .= "        <p class=\"error\">" . htmlspecialchars($analysisResult['error']) . "</p>\n";
} else {
    $htmlOutput .= "        <p class=\"info\">Scanning project root: <code>" . htmlspecialchars($projectToScan) . "</code></p>\n";
    $htmlOutput .= "        <p class=\"info\">Total directories scanned: " . $analysisResult['summary']['directories_scanned'] . "</p>\n";
    $htmlOutput .= "        <p class=\"info\">Total files found: " . $analysisResult['summary']['total_files_found'] . "</p>\n";
    $htmlOutput .= "        <p class=\"info\">File types found: " . htmlspecialchars($analysisResult['summary']['file_type_counts_string']) . "</p>\n";
    $htmlOutput .= "        <p class=\"info\">Files analyzed for dependencies (PHP, JS, CSS, HTML): <span class=\"status-entry\">" . $analysisResult['summary']['total_files_analyzed_for_dependencies'] . "</span></p>\n"; 
    $htmlOutput .= "        <p class=\"info\">Potential orphan files identified: <span class=\"status-orphan\">" . $analysisResult['summary']['potential_orphans_count'] . "</span></p>\n";
    $htmlOutput .= "        <p class=\"warning\"><strong>NOTE:</strong> This analysis is based on explicit <code>include/require</code> statements for PHP, and <code>&lt;script src=&gt;</code> / <code>&lt;link href=&gt;</code> tags in HTML/PHP for JS/CSS. Dynamic loading (e.g., JS AJAX calls to APIs, Composer autoloading, or framework routing) cannot be reliably detected and may result in 'Arquivo Principal/Iniciador' or 'POTENCIAL ÓRFÃO' statuses. Always verify manually.</p>\n"; // Updated warning message

    $htmlOutput .= "        <div class=\"section\">\n";
    $htmlOutput .= "            <h2>Detailed File Analysis</h2>\n";
    $htmlOutput .= "            <table>\n";
    $htmlOutput .= "                <thead>\n";
    $htmlOutput .= "                    <tr>\n";
    $htmlOutput .= "                        <th>Arquivo</th>\n";
    $htmlOutput .= "                        <th>Chama (Includes/References)</th>\n"; 
    $htmlOutput .= "                        <th>É Chamado Por (Is Included/Referenced By)</th>\n"; 
    $htmlOutput .= "                        <th>Status</th>\n";
    $htmlOutput .= "                    </tr>\n";
    $htmlOutput .= "                </thead>\n";
    $htmlOutput .= "                <tbody>\n";

    foreach ($analysisResult['analysis'] as $fileData) {
        $htmlOutput .= "                    <tr>\n";
        $htmlOutput .= "                        <td><code>" . htmlspecialchars($fileData['path']) . "</code></td>\n";
        
        // Coluna "Chama"
        $htmlOutput .= "                        <td>";
        if (empty($fileData['includes'])) {
            $htmlOutput .= "-";
        } else {
            $htmlOutput .= "<ul>";
            foreach ($fileData['includes'] as $included) {
                $htmlOutput .= "<li><code>" . htmlspecialchars($included) . "</code></li>";
            }
            $htmlOutput .= "</ul>";
        }
        $htmlOutput .= "</td>\n";

        // Coluna "É Chamado Por"
        $htmlOutput .= "                        <td>";
        if (empty($fileData['is_included_by'])) {
            $htmlOutput .= "-";
        } else {
            $htmlOutput .= "<ul>";
            foreach ($fileData['is_included_by'] as $includer) {
                $htmlOutput .= "<li><code>" . htmlspecialchars($includer) . "</code></li>";
            }
            $htmlOutput .= "</ul>";
        }
        $htmlOutput .= "</td>\n";

        // Coluna "Status" com classes de estilo
        $statusClass = '';
        if (str_contains($fileData['status_message'], 'ÓRFÃO')) {
            $statusClass = 'status-orphan';
        } elseif (str_contains($fileData['status_message'], 'Principal/Iniciador')) { 
            $statusClass = 'status-entry';
        } elseif (str_contains($fileData['status_message'], 'Módulo/Dependência')) {
            $statusClass = 'status-dependency';
        } else {
             $statusClass = 'status-warning'; 
        }

        $htmlOutput .= "                        <td class=\"" . $statusClass . "\">" . htmlspecialchars($fileData['status_message']) . "</td>\n";
        $htmlOutput .= "                    </tr>\n";
    }

    $htmlOutput .= "                </tbody>\n";
    $htmlOutput .= "            </table>\n";
    $htmlOutput .= "        </div>\n";
}

$htmlOutput .= "    </div>\n";
$htmlOutput .= "</body>\n";
$htmlOutput .= "</html>";

echo $htmlOutput;

?>