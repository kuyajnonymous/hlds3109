<?php
session_start();

// ========================
// Configuration
// ========================
$base_dir = '/server/hlds_l/cstrike/';
$map_dir = realpath($base_dir . 'maps') . '';
$addons_dir = realpath($base_dir . 'addons') . '';
$config_files = ['server.cfg', 'liblist.gam', "motd.txt", "mapcycle.txt", "addons/metamod/plugins.ini", "addons/podbot/podbot.cfg", "addons/podbot/botnames.txt", 'autoexec.cfg'];
//$configFiles = ["server.cfg", "listenserver.cfg", "autoexec.cfg", "motd.txt", "mapcycle.txt", "addons/podbot/podbot.cfg", "addons/podbot/botnames.txt", "addons/metamod/plugins.ini", "liblist.gam"];

// ========================
// API Endpoints
// ========================
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['api']) {
case 'get_config':
    $file = $_GET['file']; 
    $real_path = realpath($base_dir . $file);

    // Ensure file is within allowed directories
    if (!$real_path || strpos($real_path, $base_dir) !== 0) {
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!file_exists($real_path)) {
        echo json_encode(['error' => 'File not found']);
        exit;
    }

    echo json_encode([
        'content' => file_get_contents($real_path) ?: ''
    ]);
    break;


            case 'save_config':
                $file = basename($_POST['file']);
                if (in_array($file, $config_files)) {
                    file_put_contents($base_dir . $file, $_POST['content']);
                    echo json_encode(['status' => 'success']);
                }
                break;

            case 'list_files':
                $type = $_GET['type'];
                $relative_path = $_GET['path'] ?? '';
                $real_path = realpath($base_dir . $relative_path);

                // Security check: Ensure path stays within allowed directories
                if (($type === 'maps' && strpos($real_path, $map_dir) !== 0) ||
                    ($type === 'addons' && strpos($real_path, $addons_dir) !== 0)) {
                    throw new Exception('Access denied');
                }

                echo json_encode(listDirectory($real_path, $relative_path));
                break;

            case 'upload':
                // This endpoint remains for potential future use.
                $type = $_POST['type'];
                $relative_path = $_POST['path'] ?? '';
                $target_dir = realpath($base_dir . $relative_path) . '/';

                if (($type === 'maps' && strpos($target_dir, $map_dir) !== 0) ||
                    ($type === 'addons' && strpos($target_dir, $addons_dir) !== 0)) {
                    throw new Exception('Access denied');
                }

                move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . basename($_FILES['file']['name']));
                echo json_encode(['status' => 'success']);
                break;

            case 'delete':
                $relative_path = $_POST['path'] ?? '';
                $real_path = realpath($base_dir . $relative_path);

                if ((strpos($real_path, $map_dir) !== 0) && (strpos($real_path, $addons_dir) !== 0)) {
                    throw new Exception('Access denied');
                }

                is_dir($real_path) ? deleteDirectory($real_path) : unlink($real_path);
                echo json_encode(['status' => 'success']);
                break;
        }
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ========================
// Helper Functions
// ========================
function listDirectory($path, $relative_path) {
    $items = [];
    foreach (scandir($path) as $file) {
        if ($file === '.' || $file === '..') continue;
        $fullpath = $path . '/' . $file;
        $items[] = [
            'name' => $file,
            'type' => is_dir($fullpath) ? 'directory' : 'file',
            'path' => $relative_path . '/' . $file
        ];
    }
    return $items;
}

function deleteDirectory($dir) {
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CS Configuration Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .directory-item { cursor: pointer; }
        .editor-container { max-height: 70vh; overflow: auto; }
        /* Set the same window size for maps and addons managers as the config editor */
        #maps-list, #addons-list {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
        }
        /* Log area styling */
        #log {
            margin-top: 20px;
            padding: 10px;
            background: #f0f0f0;
            border: 1px solid #ccc;
            height: 150px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body class="container-fluid py-4">
    <div class="row">
        <!-- Configuration Files -->
        <div class="col-md-4">
            <h2>Configuration Files</h2>
            <div class="list-group mb-3">
                <?php foreach ($config_files as $file): ?>
                    <button class="list-group-item list-group-item-action"
                            onclick="loadEditor('<?= $file ?>')">
                        <?= $file ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div id="editor-container" class="d-none">
                <textarea id="editor" class="form-control mb-2" style="height: 300px"></textarea>
                <button class="btn btn-primary" onclick="saveConfig()">Save</button>
            </div>
        </div>

        <!-- Maps Manager -->
        <div class="col-md-4">
<h2>Maps Manager</h2>
<input type="file" id="upload-maps" accept=".zip" class="form-control mb-2">
<button class="btn btn-success" onclick="uploadZip('maps')">Upload ZIP</button>
<div id="maps-list"></div>

        </div>

        <!-- Addons Manager -->
        <div class="col-md-4">
<h2>Addons Manager</h2>
<input type="file" id="upload-addons" accept=".zip" class="form-control mb-2">
<button class="btn btn-success" onclick="uploadZip('addons')">Upload ZIP</button>
<div id="addons-list"></div>
        </div>
    </div>

    <script>
        let currentMapPath = 'maps';
        let currentAddonPath = 'addons';

        // Initialize file lists for maps and addons when the page loads
        window.addEventListener('DOMContentLoaded', () => {
            loadFileList('maps', currentMapPath);
            loadFileList('addons', currentAddonPath);
        });

        // Function to load a configuration file into the editor
        async function loadEditor(file) {
            logMessage(`Loading config file: ${file}`);
            try {
                const res = await fetch(`?api=get_config&file=${encodeURIComponent(file)}`);
                const data = await res.json();
                document.getElementById("editor-container").classList.remove("d-none");
                document.getElementById("editor").value = data.content;
                document.getElementById("editor-container").dataset.currentFile = file;
                logMessage(`Loaded config file: ${file}`);
            } catch (err) {
                logMessage(`Error loading config file: ${file} - ${err.message}`);
            }
        }

        // Function to save the configuration file from the editor
        async function saveConfig() {
            const file = document.getElementById("editor-container").dataset.currentFile;
            if (!file) {
                logMessage("No file loaded for saving.");
                return;
            }
            const content = document.getElementById("editor").value;
            logMessage(`Saving config file: ${file}`);
            try {
                const res = await fetch(`?api=save_config`, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({ file, content })
                });
                const result = await res.json();
                if (result.status === "success") {
                    logMessage(`Config file saved successfully: ${file}`);
                } else {
                    logMessage(`Error saving config file: ${file}`);
                }
            } catch (err) {
                logMessage(`Error saving config file: ${file} - ${err.message}`);
            }
        }

        // Function to load the file list for a given type and path using pure JS DOM methods
        async function loadFileList(type, path) {
            try {
                const res = await fetch(`?api=list_files&type=${type}&path=${encodeURIComponent(path)}`);
                const items = await res.json();

                const container = document.createElement('div');

                // Create Back Button
                const backBtn = document.createElement('button');
                backBtn.className = "btn btn-sm btn-outline-secondary";
                backBtn.textContent = "⬆ Back";
                backBtn.addEventListener('click', () => navigateUp(type));
                container.appendChild(backBtn);
                container.appendChild(document.createElement('br'));

                // Build the file list
                items.forEach(item => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = "d-flex justify-content-between align-items-center mt-2";

                    const span = document.createElement('span');
                    span.className = "directory-item";
                    span.textContent = (item.type === 'directory' ? '📂 ' : '📄 ') + item.name;
                    if (item.type === 'directory') {
                        span.addEventListener('click', () => {
                            if (type === 'maps') {
                                currentMapPath = item.path;
                                loadFileList('maps', item.path);
                            } else {
                                currentAddonPath = item.path;
                                loadFileList('addons', item.path);
                            }
                        });
                    }
                    itemDiv.appendChild(span);

                    // Delete button (placeholder)
 //                    const delBtn = document.createElement('button');
 //                    delBtn.className = "btn btn-sm btn-outline-danger";
 //                    delBtn.textContent = "Delete";
 //                    delBtn.addEventListener('click', (e) => {
 //                        e.stopPropagation();
 //                        deleteItem(type, item.path);
 //                    });
 //                    itemDiv.appendChild(delBtn);

                    container.appendChild(itemDiv);
                });

                const listContainer = document.getElementById(`${type}-list`);
                listContainer.innerHTML = "";
                listContainer.appendChild(container);
                logMessage(`Loaded ${type} list for path: ${path}`);
            } catch (err) {
                logMessage(`Error loading ${type} list: ${err.message}`);
            }
        }

        // Function to navigate up one level in the directory structure
        function navigateUp(type) {
            let currentPath = type === 'maps' ? currentMapPath : currentAddonPath;
            let parts = currentPath.split('/');
            parts.pop();
            let newPath = parts.join('/');
            if (!newPath) {
                newPath = type; // default to base folder (e.g., "maps")
            }
            if (type === 'maps') {
                currentMapPath = newPath;
            } else {
                currentAddonPath = newPath;
            }
            loadFileList(type, newPath);
        }

        // Log function to append messages to the log area
        function logMessage(message) {
            const logDiv = document.getElementById("log");
            const timestamp = new Date().toLocaleTimeString();
            logDiv.innerHTML += `[${timestamp}] ${message}<br>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        // Placeholder function for delete action (for both managers)
        async function deleteItem(type, path) {
            logMessage(`Delete action for ${type} item at path ${path} not implemented in this demo.`);
        }

        // Initialize managers on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadFileList('maps', currentMapPath);
            loadFileList('addons', currentAddonPath);
        });
    </script>

    <!-- Log area at the bottom -->
    <div id="log"></div>
</body>
</html>
