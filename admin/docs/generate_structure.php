<?php
// Definir o Content-Type para HTML antes de qualquer saída
header('Content-Type: text/html; charset=utf-8');
/**
 * Lists the directory structure of a given path,
 * returning it as an array suitable for hierarchical rendering.
 * Ensures directories are listed before files at each level, and then sorted alphabetically.
 *
 * @param string $path The base path to start scanning from.
 * @return array An array representing the directory tree, or an empty array with an error.
 */
function getDirectoryTree(string $path): array {
    $basePath = realpath($path);
    if (!$basePath || !is_dir($basePath)) {
        return ['error' => "Error: The specified path is not a valid directory."];
    }
    $allFilesCount = 0;
    $allDirsCount = 0;
    // Recursive helper function to build the tree
    $buildTree = function(string $currentDir) use ($basePath, &$allFilesCount, &$allDirsCount, &$buildTree): array {
        $nodes = [];
        $directories = [];
        $files = [];
        $items = @scandir($currentDir);
        if ($items === false) {
            return [];
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $currentDir . DIRECTORY_SEPARATOR . $item;
            $relativePath = str_replace($basePath, '', $itemPath);
            if (is_dir($itemPath)) {
                $allDirsCount++;
                $directories[] = [
                    'name' => htmlspecialchars($item),
                    'type' => 'dir',
                    'path' => htmlspecialchars($relativePath),
                    'children' => $buildTree($itemPath)
                ];
            } else {
                $allFilesCount++;
                $files[] = [
                    'name' => htmlspecialchars($item),
                    'type' => 'file',
                    'path' => htmlspecialchars($relativePath)
                ];
            }
        }
        usort($directories, function($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
        usort($files, function($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
        return array_merge($directories, $files);
    };
    $treeChildren = $buildTree($basePath);
    $finalTree = [
        'name' => htmlspecialchars(basename($basePath)),
        'type' => 'dir',
        'path' => '/',
        'children' => $treeChildren
    ];
    return [
        'tree' => $finalTree,
        'summary' => ['directories' => $allDirsCount, 'files' => $allFilesCount]
    ];
}
/**
 * Generates CSV content from the directory tree.
 *
 * @param array $tree The directory tree array.
 * @return string CSV content as a string.
 */
function generateCsv(array $tree): string {
    $csv = "Type,Name,Path\n";
    $processNode = function(array $node, string $prefix = '') use (&$processNode, &$csv) {
        $csv .= ($node['type'] === 'dir' ? 'Directory' : 'File') . ',' . 
                '"' . str_replace('"', '""', $node['name']) . '",' . 
                '"' . str_replace('"', '""', $node['path']) . '"' . "\n";
        if (isset($node['children']) && !empty($node['children'])) {
            foreach ($node['children'] as $child) {
                $processNode($child, $prefix . $node['name'] . '/');
            }
        }
    };
    $processNode($tree['tree']);
    return $csv;
}
/**
 * Renders the directory tree as an HTML unordered list.
 *
 * @param array $node The current node in the tree.
 * @param int $level The current nesting level.
 * @return string The HTML string for this node and its children.
 */
function renderTreeHtml(array $node, int $level = 0): string {
    $html = '';
    $isDir = $node['type'] === 'dir';
    $hasChildren = isset($node['children']) && !empty($node['children']);
    $itemClass = $isDir ? 'dir-item' : 'file-item';
    $folderIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#e5c07b"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';
    $fileIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#98c379"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>';
    $icon = $isDir ? '<span class="icon folder-icon" style="background-image: url(\'data:image/svg+xml;utf8,' . rawurlencode($folderIconSvg) . '\');"></span>' : '<span class="icon file-icon" style="background-image: url(\'data:image/svg+xml;utf8,' . rawurlencode($fileIconSvg) . '\');"></span>';
    $toggle = $isDir && $hasChildren ? '<span class="toggle-icon">+</span>' : '';
    $html .= '<li class="' . $itemClass . '" data-path="' . $node['path'] . '">';
    $html .= $toggle . $icon . '<span class="item-name">' . $node['name'] . ($isDir ? '/' : '') . '</span>';
    if ($isDir && $hasChildren) {
        $html .= '<ul class="dir-children' . ($level == 0 ? ' initial-expanded' : '') . '">';
        foreach ($node['children'] as $child) {
            $html .= renderTreeHtml($child, $level + 1);
        }
        $html .= '</ul>';
    }
    $html .= '</li>';
    return $html;
}
// --- CONFIGURATION ---
$targetDirectory = '/home4/chefej82/bacosearch.com';
$externalFiles = [
    '/home4/chefej82/config.php',
    '/home4/chefej82/.env'
];
$fileIconSvgForExternal = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#98c379"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>';
// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $result = getDirectoryTree($targetDirectory);
    if (!isset($result['error'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="directory_structure.csv"');
        echo generateCsv($result);
        exit;
    }
}
// --- EXECUTION ---
$result = getDirectoryTree($targetDirectory);
$htmlOutput = "<!DOCTYPE html>\n";
$htmlOutput .= "<html lang=\"en\">\n";
$htmlOutput .= "<head>\n";
$htmlOutput .= " <meta charset=\"UTF-8\">\n";
$htmlOutput .= " <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
$htmlOutput .= " <title>Professional Directory Structure</title>\n";
$htmlOutput .= " <style>\n";
$htmlOutput .= " body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #282c34; color: #abb2bf; margin: 20px; }\n";
$htmlOutput .= " .container { max-width: 90%; margin: 0 auto; background-color: #333842; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); }\n";
$htmlOutput .= " h1 { color: #61afef; text-align: center; margin-bottom: 30px; }\n";
$htmlOutput .= " h2 { color: #56b6c2; text-align: center; margin-top: 30px; margin-bottom: 15px; }\n";
$htmlOutput .= " .external-files-list { list-style-type: none; padding-left: 0; text-align: center; margin-bottom: 30px; }\n";
$htmlOutput .= " .external-files-list li { line-height: 1.6; color: #abb2bf; display: inline-block; margin: 0 10px; padding: 5px 10px; background-color: #3e4451; border-radius: 5px; }\n";
$htmlOutput .= " .external-files-list li .icon { vertical-align: text-bottom; margin-right: 5px; }\n";
$htmlOutput .= " .controls { text-align: center; margin-bottom: 20px; }\n";
$htmlOutput .= " .controls button { background-color: #61afef; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-size: 0.9em; margin: 0 5px; transition: background-color 0.3s ease; }\n";
$htmlOutput .= " .controls button:hover { background-color: #569bd5; }\n";
$htmlOutput .= " ul { list-style-type: none; padding-left: 20px; margin: 0; }\n";
$htmlOutput .= " li { line-height: 1.8; cursor: pointer; user-select: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 2px 0; }\n";
$htmlOutput .= " li:hover .item-name { color: #c678dd; }\n";
$htmlOutput .= " .dir-item > .item-name { font-weight: bold; color: #e5c07b; }\n";
$htmlOutput .= " .file-item .item-name { color: #98c379; }\n";
$htmlOutput .= " .toggle-icon { display: inline-block; width: 15px; text-align: center; color: #56b6c2; font-weight: bold; transform: rotate(0deg); transition: transform 0.2s ease-in-out; }\n";
$htmlOutput .= " .dir-expanded > .toggle-icon { transform: rotate(90deg); }\n";
$htmlOutput .= " .dir-children { display: none; margin-left: 15px; border-left: 1px dashed #4b5263; padding-left: 5px; }\n";
$htmlOutput .= " .dir-expanded > .dir-children, ul.initial-expanded { display: block; }\n";
$htmlOutput .= " .icon { display: inline-block; width: 16px; height: 16px; margin-right: 5px; vertical-align: middle; background-size: contain; background-repeat: no-repeat; background-position: center; }\n";
$htmlOutput .= " .summary { margin-top: 30px; padding: 15px; border-top: 1px solid #4b5263; text-align: center; font-size: 1.1em; color: #61afef; }\n";
$htmlOutput .= " </style>\n";
$htmlOutput .= "</head>\n";
$htmlOutput .= "<body>\n";
$htmlOutput .= " <div class=\"container\">\n";
$htmlOutput .= " <h1>Directory Structure</h1>\n";
if (isset($result['error'])) {
    $htmlOutput .= " <p class=\"error\">" . $result['error'] . "</p>\n";
} else {
    if (!empty($externalFiles)) {
        $htmlOutput .= " <h2>External Configuration Files</h2>\n";
        $htmlOutput .= " <ul class=\"external-files-list\">\n";
        foreach ($externalFiles as $filePath) {
            if (file_exists($filePath)) {
                $htmlOutput .= " <li><span class=\"icon file-icon\" style=\"background-image: url('data:image/svg+xml;utf8," . rawurlencode($fileIconSvgForExternal) . "');\"></span>" . htmlspecialchars($filePath) . "</li>\n";
            }
        }
        $htmlOutput .= " </ul>\n";
    }
    $htmlOutput .= " <div class=\"controls\">\n";
    $htmlOutput .= " <button id=\"expand-all\">Expandir Tudo</button>\n";
    $htmlOutput .= " <button id=\"collapse-all\">Colapsar Tudo</button>\n";
    $htmlOutput .= " <button id=\"download-csv\">Download CSV</button>\n";
    $htmlOutput .= " </div>\n";
    $htmlOutput .= " <ul id=\"directory-tree\">\n";
    $htmlOutput .= renderTreeHtml($result['tree'], 0);
    $htmlOutput .= " </ul>\n";
    $htmlOutput .= " <p class=\"summary\">" . $result['summary']['directories'] . " directories, " . $result['summary']['files'] . " files</p>\n";
}
$htmlOutput .= " </div>\n";
$htmlOutput .= " <script>\n";
$htmlOutput .= " document.addEventListener('DOMContentLoaded', function() {\n";
$htmlOutput .= " const tree = document.getElementById('directory-tree');\n";
$htmlOutput .= " const expandAllBtn = document.getElementById('expand-all');\n";
$htmlOutput .= " const collapseAllBtn = document.getElementById('collapse-all');\n";
$htmlOutput .= " const downloadCsvBtn = document.getElementById('download-csv');\n";
$htmlOutput .= " \n";
$htmlOutput .= " if (!tree) return;\n";
$htmlOutput .= "\n";
$htmlOutput .= " // Function to handle individual item clicks\n";
$htmlOutput .= " tree.addEventListener('click', function(event) {\n";
$htmlOutput .= " let target = event.target;\n";
$htmlOutput .= " let listItem = null;\n";
$htmlOutput .= " if (target.classList.contains('item-name') || target.classList.contains('toggle-icon') || target.classList.contains('icon')) {\n";
$htmlOutput .= " listItem = target.closest('li.dir-item');\n";
$htmlOutput .= " } else if (target.tagName === 'LI' && target.classList.contains('dir-item')) {\n";
$htmlOutput .= " listItem = target;\n";
$htmlOutput .= " }\n";
$htmlOutput .= "\n";
$htmlOutput .= " if (listItem) {\n";
$htmlOutput .= " const childrenUl = listItem.querySelector('.dir-children');\n";
$htmlOutput .= " if (childrenUl) {\n";
$htmlOutput .= " listItem.classList.toggle('dir-expanded');\n";
$htmlOutput .= " }\n";
$htmlOutput .= " }\n";
$htmlOutput .= " });\n";
$htmlOutput .= "\n";
$htmlOutput .= " // Event listener for Expand All button\n";
$htmlOutput .= " if (expandAllBtn) {\n";
$htmlOutput .= " expandAllBtn.addEventListener('click', function() {\n";
$htmlOutput .= " const dirItems = tree.querySelectorAll('li.dir-item');\n";
$htmlOutput .= " dirItems.forEach(item => {\n";
$htmlOutput .= " if (item.classList.contains('dir-item') && item.querySelector('.dir-children')) {\n";
$htmlOutput .= " item.classList.add('dir-expanded');\n";
$htmlOutput .= " }\n";
$htmlOutput .= " });\n";
$htmlOutput .= " const initialExpandedUls = tree.querySelectorAll('ul.initial-expanded');\n";
$htmlOutput .= " initialExpandedUls.forEach(ul => ul.classList.remove('initial-expanded'));\n";
$htmlOutput .= " });\n";
$htmlOutput .= " }\n";
$htmlOutput .= "\n";
$htmlOutput .= " // Event listener for Collapse All button\n";
$htmlOutput .= " if (collapseAllBtn) {\n";
$htmlOutput .= " collapseAllBtn.addEventListener('click', function() {\n";
$htmlOutput .= " const dirItems = tree.querySelectorAll('li.dir-item');\n";
$htmlOutput .= " dirItems.forEach(item => {\n";
$htmlOutput .= " if (item.classList.contains('dir-expanded')) {\n";
$htmlOutput .= " item.classList.remove('dir-expanded');\n";
$htmlOutput .= " }\n";
$htmlOutput .= " });\n";
$htmlOutput .= " const firstLevelUl = tree.querySelector('ul.dir-children');\n";
$htmlOutput .= " if (firstLevelUl) {\n";
$htmlOutput .= " firstLevelUl.classList.add('initial-expanded');\n";
$htmlOutput .= " }\n";
$htmlOutput .= " });\n";
$htmlOutput .= " }\n";
$htmlOutput .= "\n";
$htmlOutput .= " // Event listener for Download CSV button\n";
$htmlOutput .= " if (downloadCsvBtn) {\n";
$htmlOutput .= " downloadCsvBtn.addEventListener('click', function() {\n";
$htmlOutput .= " window.location.href = '?download=csv';\n";
$htmlOutput .= " });\n";
$htmlOutput .= " }\n";
$htmlOutput .= " });\n";
$htmlOutput .= " </script>\n";
$htmlOutput .= "</body>\n";
$htmlOutput .= "</html>";
echo $htmlOutput;
?>