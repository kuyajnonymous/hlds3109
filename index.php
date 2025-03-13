<?php
session_start();

// -------------------------------
// Helper Functions for Folders
// -------------------------------
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        return false;
    }
    $items = scandir($dirPath);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDir($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dirPath);
}

function copyDir($src, $dst) {
    $dir = opendir($src);
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    while(false !== ($file = readdir($dir))) {
        if ($file != '.' && $file != '..') {
            if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                copyDir($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
            } else {
                copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
            }
        }
    }
    closedir($dir);
    return true;
}

// -------------------------------
// Authentication
// -------------------------------
$adminPassword = "csplay"; // Change this password!
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['authenticated'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "❌ Incorrect password. Try again.";
    }
}
if (!isset($_SESSION['authenticated'])) {
    ?>
    

      <h2>🔒 Enter Password</h2>
      <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
      <form method="post">
          <input type="password" name="password" required autofocus>
          <button type="submit">Login</button>
      </form>

    <?php
    exit;
}

// -------------------------------
// Server Paths & Config Files Arrays
// -------------------------------
$serverPaths = [
    "cstechpinoy"   => "hlds_l/cstrike/"
//    "cstechpinoy15" => "hlds_l/cstrike15/",
//    "hlserver_bots_cstrk15"   => "hlds_l/cstrk15/"
];

$configFiles = [
    "server.cfg",
    "listenserver.cfg",
    "autoexec.cfg",
    "motd.txt",
    "mapcycle.txt",
    "addons/podbot/podbot.cfg",
    "addons/podbot/botnames.txt",
    "addons/metamod/plugins.ini",
    "addons/amxmodx/configs/amxx.cfg",
    "addons/yapb/conf/lang/en_names.cfg",
    "liblist.gam"
];

// -------------------------------
// File Browser Setup
// -------------------------------
$uploadDir = __DIR__ . '/hlds_l/';
$selectedServer = isset($_GET['s']) && isset($serverPaths[$_GET['s']])
    ? $_GET['s']
    : array_key_first($serverPaths);
$baseDir = dirname(__DIR__ . '/' . $serverPaths[$selectedServer]);
$currentSubPath = isset($_GET['path']) ? trim($_GET['path'], "/\\") : '';
$currentDir = $baseDir;
if ($currentSubPath !== '') {
    $tempDir = realpath($baseDir . DIRECTORY_SEPARATOR . $currentSubPath);
    if ($tempDir && strpos($tempDir, $baseDir) === 0) {
        $currentDir = $tempDir;
    }
}

// -------------------------------
// Extraction Handler
// -------------------------------
if (isset($_GET['action']) && $_GET['action'] == "extractFile" && isset($_GET['file'])) {
    $fileToExtract = realpath($currentDir . DIRECTORY_SEPARATOR . $_GET['file']);
    if (!$fileToExtract || strpos($fileToExtract, $currentDir) !== 0 || !is_file($fileToExtract)) {
        echo "Invalid file.";
        exit;
    }
    $filename = $_GET['file'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $log = "";
    if ($ext == "zip") {
        $zip = new ZipArchive;
        if ($zip->open($fileToExtract) === TRUE) {
            $log .= "ZIP archive contains " . $zip->numFiles . " files.\n";
            $zip->extractTo($currentDir);
            $zip->close();
            $log .= "ZIP extraction successful for " . $filename . "\n";
            echo $log;
        } else {
            echo "ZIP extraction failed for " . $filename;
        }
    } elseif ($ext == "tar") {
        try {
            $phar = new PharData($fileToExtract);
            $phar->extractTo($currentDir, null, true);
            $log .= "TAR extraction successful for " . $filename . "\n";
            echo $log;
        } catch (Exception $e) {
            echo "TAR extraction failed for " . $filename . ": " . $e->getMessage();
        }
    } elseif ($ext == "gz") {
        if (preg_match('/\.tar\.gz$/i', $filename) || preg_match('/\.tgz$/i', $filename)) {
            try {
                $phar = new PharData($fileToExtract);
                $tarPath = str_replace(array('.tar.gz', '.tgz'), '.tar', $fileToExtract);
                $phar->decompress();
                $pharTar = new PharData($tarPath);
                $pharTar->extractTo($currentDir, null, true);
                if (file_exists($tarPath)) {
                    unlink($tarPath);
                    $log .= "Removed intermediate tar file: " . basename($tarPath) . "\n";
                }
                $log .= "TAR.GZ extraction successful for " . $filename . "\n";
                echo $log;
            } catch (Exception $e) {
                echo "TAR.GZ extraction failed for " . $filename . ": " . $e->getMessage();
            }
        } else {
            echo "Unsupported gzip format for " . $filename;
        }
    } else {
        echo "Unsupported archive format for " . $filename;
    }
    exit;
}

// -------------------------------
// CHUNKED UPLOAD HANDLER
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && isset($_POST["chunk"])) {
    if ($_FILES['file']['size'] > 200 * 1024 * 1024) {
        echo json_encode(["status" => "error", "message" => "File too large. Maximum size is 200 MB."]);
        exit;
    }
    $chunk = intval($_POST["chunk"]);
    $chunks = intval($_POST["chunks"]);
    $origFilename = isset($_POST["filename"]) ? $_POST["filename"] : "";
    if (empty($origFilename)) {
        echo json_encode(["status" => "error", "message" => "No filename provided"]);
        exit;
    }
    $origFilename = preg_replace('/[^\w\._-]/', '', $origFilename);
    $tempFile = $currentDir . DIRECTORY_SEPARATOR . $origFilename . ".part";
    if (isset($_FILES["file"]["tmp_name"]) && is_uploaded_file($_FILES["file"]["tmp_name"])) {
        file_put_contents($tempFile, file_get_contents($_FILES["file"]["tmp_name"]), FILE_APPEND);
    } else {
        echo json_encode(["status" => "error", "message" => "No uploaded file"]);
        exit;
    }
    if ($chunk + 1 >= $chunks) {
        $finalFile = $currentDir . DIRECTORY_SEPARATOR . $origFilename;
        rename($tempFile, $finalFile);
        echo json_encode(["status" => "success", "message" => "File uploaded successfully", "filename" => $origFilename]);
    } else {
        echo json_encode(["status" => "chunk_received", "message" => "Chunk " . $chunk . " received"]);
    }
    exit;
}

// -------------------------------
// AJAX: Load File List for File Manager
// -------------------------------
if (isset($_GET['action']) && $_GET['action'] == "loadFileList") {
    if ($currentDir !== $baseDir) {
        echo '<div class="file-box"><span class="up-link" onclick="goUp()">[..]</span></div>';
    }
    $items = scandir($currentDir);
    $items = array_diff($items, ['.', '..']);
    foreach ($items as $item):
        $fullPath = $currentDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)):
            echo '<div class="file-box">';
            echo '<span class="folder-link" onclick="openFolder(\''.htmlspecialchars($item, ENT_QUOTES).'\')">📁 '.htmlspecialchars($item).'</span>';
            echo '</div>';
        else:
            echo '<div class="file-box">';
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $allowedExtensions = ['txt', 'cfg', 'ini', 'gam'];
            if (in_array($ext, $allowedExtensions)) {
                echo '<span class="file-link" onclick="openEditableFile(\''.htmlspecialchars($item, ENT_QUOTES).'\')">📄 '.htmlspecialchars($item).'</span>';
            } else {
                echo '<span>📄 '.htmlspecialchars($item).'</span>';
            }
            echo ' <a href="javascript:void(0)" class="delete-btn" onclick="deleteFile(\''.htmlspecialchars($item, ENT_QUOTES).'\')">Delete</a>';
            if ($ext == "zip" || $ext == "tar" || $ext == "gz" || preg_match('/\.tar\.gz$/i', $item) || preg_match('/\.tgz$/i', $item)) {
                echo ' <button onclick="extractFile(\''.htmlspecialchars($item, ENT_QUOTES).'\')">Extract</button>';
            }
            echo '</div>';
        endif;
    endforeach;
    exit;
}

// -------------------------------
// File Browser Actions: Upload, Delete, New Folder
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && !isset($_POST["chunk"])) {
    if ($_FILES['file']['size'] > 200 * 1024 * 1024) {
        echo json_encode(["status" => "error", "message" => "File too large. Maximum size is 200 MB."]);
        exit;
    }
    $targetFile = $currentDir . DIRECTORY_SEPARATOR . basename($_FILES['file']['name']);
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "File upload error: " . $_FILES['file']['error']]);
        exit;
    }
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
        echo json_encode(["status" => "success", "message" => "File uploaded successfully to $currentDir"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file"]);
    }
    exit;
}

if (isset($_GET['delete'])) {
    $fileToDelete = realpath($currentDir . DIRECTORY_SEPARATOR . $_GET['delete']);
    if ($fileToDelete && strpos($fileToDelete, $currentDir) === 0 && is_file($fileToDelete)) {
        unlink($fileToDelete);
    }
    exit;
}

if (isset($_POST['newfolder'])) {
    $newFolder = $currentDir . DIRECTORY_SEPARATOR . basename($_POST['newfolder']);
    if (!is_dir($newFolder)) {
        mkdir($newFolder, 0755);
    }
    exit;
}

// -------------------------------
// NEW: Load File from File Manager for Editing
// -------------------------------
if (isset($_GET['action']) && $_GET['action'] == "loadManagerFile" && isset($_GET['file'])) {
    $fileToLoad = realpath($currentDir . DIRECTORY_SEPARATOR . $_GET['file']);
    if (!$fileToLoad || strpos($fileToLoad, $currentDir) !== 0 || !is_file($fileToLoad)) {
         echo "Invalid file.";
         exit;
    }
    echo file_get_contents($fileToLoad);
    exit;
}

// -------------------------------
// NEW: Save Edited File from File Manager
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['managerFile'], $_POST['fileContent'])) {
    $fileName = basename($_POST['managerFile']);
    $filePath = $currentDir . DIRECTORY_SEPARATOR . $fileName;
    if (file_exists($filePath) && is_writable($filePath)) {
         copy($filePath, $filePath . '.bak');
    }
    if (file_put_contents($filePath, $_POST['fileContent']) !== false) {
         echo "File saved successfully!";
    } else {
         echo "Error: Cannot write to file. Check permissions.";
    }
    exit;
}

// -------------------------------
// Config Editor & AJAX Handlers for Mapped Server Files
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['folder'], $_POST['file'], $_POST['fileContent'])) {
    $folderKey   = $_POST['folder'];
    $fileName    = $_POST['file'];
    $fileContent = $_POST['fileContent'];
    if (!isset($serverPaths[$folderKey])) {
        echo "Invalid folder.";
        exit;
    }
    $filePath = $serverPaths[$folderKey] . $fileName;
    if (file_exists($filePath) && is_writable($filePath)) {
        copy($filePath, $filePath . '.bak');
    }
    if (file_put_contents($filePath, $fileContent) !== false) {
        echo "File saved successfully!";
    } else {
        echo "Error: Cannot write to file. Check permissions.";
    }
    exit;
}

if (isset($_GET['folder'], $_GET['file'])) {
    $folderKey = $_GET['folder'];
    $fileName  = $_GET['file'];
    if (!isset($serverPaths[$folderKey])) {
        echo "Invalid folder.";
        exit;
    }
    $filePath = $serverPaths[$folderKey] . $fileName;
    if (!file_exists($filePath)) {
        echo "File does not exist.";
        exit;
    }
    if (is_readable($filePath)) {
        echo file_get_contents($filePath);
    } else {
        echo "";
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == "loadService" && isset($_GET['folder'])) {
    $folderKey = $_GET['folder'];
    $serviceMapping = [
         "hlserver_cstrike"      => "/etc/systemd/system/start_cs13.service",
         "hlserver_cstrk15"      => "/etc/systemd/system/start_cs15.service",
         "cstechpinoy" => "/etc/systemd/system/start_cs13bots.service",
         "hlserver_bots_cstrk15" => "/etc/systemd/system/start_cs15bots.service",
    ];
    if (!isset($serviceMapping[$folderKey])) {
        echo "Invalid folder for service file.";
        exit;
    }
    $serviceFile = $serviceMapping[$folderKey];
    if (!file_exists($serviceFile)) {
        echo "Service file not found.";
        exit;
    }
    if (is_readable($serviceFile)) {
        echo file_get_contents($serviceFile);
    } else {
        echo "";
    }
    exit;
}
?>
<?php
$server_id = "6bd4532a7f96"; // Change this to your game server container ID

// Function to check the game server status
function getServerStatus($server_id) {
    $output = shell_exec("/usr/bin/docker ps -q -f id=$server_id");
    return trim($output) ? "running" : "stopped";
}

// Function to get server logs with newest logs on top
function getServerLogs($server_id, $lines = 20) {
    $output = shell_exec("/usr/bin/docker logs --tail $lines $server_id 2>&1");
    $logLines = explode("\n", trim($output));
    $logLines = array_reverse($logLines); // Reverse to show new logs on top
    $reversedOutput = implode("\n", $logLines);
    return nl2br(htmlspecialchars($reversedOutput)); // Prevent XSS
}

// If logs are requested via AJAX, return only logs and exit
if (isset($_GET["logs"])) {
    echo getServerLogs($server_id);
    exit;
}

// Handle button clicks
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"];
    if ($action === "start") {
        shell_exec("/usr/bin/docker start $server_id");
    } elseif ($action === "stop") {
        shell_exec("/usr/bin/docker stop $server_id");
    } elseif ($action === "restart") {
        shell_exec("/usr/bin/docker restart $server_id");
    }
    // Refresh status after action
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get current status and logs
$status = getServerStatus($server_id);
$logs = getServerLogs($server_id);
$light_color = $status === "running" ? "🟢" : "🔴";
?>
  <style>
    /* Responsive, full-page layout */
    html, body {
      margin: 0;
      padding: 0;
      height: 100%;
      overflow: hidden;
      font-family: "MS Sans Serif", Arial, sans-serif;
    }
    /* Theme wrapper */
    #themeWrapper {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    /* Top bar */
    .top-bar {
      flex: 0 0 auto;
      padding: 5px 10px;
      background: #ccc;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .top-bar select, .top-bar label, .top-bar a {
      font-size: 12px;
    }
    /* Main container: Two columns */
    .main-container {
      flex: 1;
      display: flex;
      width: 100%;
      overflow: hidden;
    }
    /* Left Column: File Manager Container */
    .left-column {
      width: 35%;
      height: 100%;
      overflow-y: auto;
      box-sizing: border-box;
    }
    /* Right Column: Config Editor (Top half) and Logs (Bottom half) */
    .right-column {
      width: 65%;
      height: 100%;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    /* Config Editor Container (Top half of Right Column) */
    .config-editor-container {
      flex: 1;
      display: flex;
      flex-direction: column;
      box-sizing: border-box;
      padding: 10px;
      border-bottom: 1px solid #999;
      overflow: hidden;
    }
    #configEditor {
      flex: 1;
      width: 100%;
      font-family: monospace;
      border: 1px solid #ccc;
      padding: 10px;
      box-sizing: border-box;
      background: #fff;
      font-size: 12px;
      resize: none;
    }
    /* Config Editor Tabs */
    #tabs {
      border-bottom: 1px solid #ccc;
      margin-bottom: 10px;
      display: none;
    }
    .tab {
      display: inline-block;
      padding: 5px 10px;
      cursor: pointer;
      border: 1px solid #ccc;
      border-bottom: none;
      background: #f9f9f9;
      margin-right: 2px;
      font-size: 12px;
    }
    .tab.active {
      background: #fff;
      border-top: 2px solid #007bff;
      font-weight: bold;
    }
    /* Logs Container (Bottom half of Right Column) */
    .logs-container {
      flex: 1;
      background: #f0f0f0;
      border-top: 1px solid #999;
      overflow-y: auto;
      box-sizing: border-box;
      font-family: "Courier New", Courier, monospace;
      font-size: 12px;
      padding: 5px;
    }
    /* File Manager Container (Left Column content) */
    .file-manager-container {
      box-sizing: border-box;
      padding: 10px;
      overflow: hidden;
    }
    .file-manager-container form {
      margin-bottom: 5px;
    }
    #drop-area {
      margin-top: 10px;
      border: 2px dashed #aaa;
      padding: 10px;
      text-align: center;
      cursor: pointer;
      background: #e0e0e0;
    }
    .file-list {
      margin-top: 10px;
      border: 1px solid #ccc;
      background: #f9f9f9;
      padding: 5px;
      height: calc(100% - 100px);
      overflow-y: auto;
    }
    .file-box {
      border: 1px solid #ddd;
      padding: 5px;
      margin-bottom: 5px;
      display: flex;
      justify-content: space-between;
      background: #e0e0e0;
      font-size: 12px;
    }
    .file-box:hover {
      background: #f1f1f1;
    }
    .delete-btn {
      background: #a00;
      color: #fff;
      padding: 2px 5px;
      text-decoration: none;
      font-size: 10px;
    }
    .folder-link {
      cursor: pointer;
      color: blue;
      text-decoration: underline;
    }
    .up-link {
      font-style: italic;
      cursor: pointer;
      color: blue;
    }
    .file-link {
      cursor: pointer;
      color: blue;
      text-decoration: underline;
    }
    form, button, input, select, a {
      font-size: 12px;
    }
    .button-group {
      margin-top: 5px;
    }
    .button-group button {
      margin-right: 5px;
    }
    footer {
      text-align: center;
      font-size: 12px;
      padding: 5px 10px;
      background: #eee;
      border-top: 1px solid #ccc;
    }
    /* THEME STYLES */
    .theme-default .top-bar {
      background: #ccc;
      color: #000;
    }
    .theme-classic .top-bar {
      background: #000080;
      color: white;
      font-family: "MS Sans Serif", sans-serif;
      border: 2px outset #fff;
    }
    .theme-classic .file-box {
      background: #c0c0c0;
      border: 2px inset #808080;
    }
    .theme-flat .top-bar {
      background: #f9f9f9;
      color: #333;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .theme-flat .file-box {
      border-radius: 3px;
      background: #fff;
      border: 1px solid #ddd;
    }
    .theme-material .top-bar {
      background: #009688;
      color: #fff;
    }
    .theme-material .delete-btn {
      background: #e91e63;
    }
    .theme-mac .top-bar {
      background: #eee;
      color: #000;
      font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
      border-bottom: 1px solid #ccc;
    }
    .theme-mac .file-box {
      background: #f5f5f5;
      border-radius: 4px;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // LOGGING
    function logMessage(message) {
      const logDiv = document.getElementById("log");
      const timestamp = new Date().toLocaleString();
      const newLog = `[${timestamp}] ${message}<br>`;
      logDiv.innerHTML = newLog + logDiv.innerHTML;
      logDiv.scrollTop = 0;
      let stored = localStorage.getItem('logHistory') || "";
      stored = newLog + stored;
      localStorage.setItem('logHistory', stored);
    }
    window.addEventListener("load", function() {
      const logDiv = document.getElementById("log");
      logDiv.innerHTML = localStorage.getItem('logHistory') || "";
      logDiv.scrollTop = 0;
    });

    // THEME SWITCHER
    function setTheme(theme) {
      const wrapper = document.getElementById('themeWrapper');
      wrapper.className = 'theme-' + theme;
      logMessage("Theme changed to: " + theme);
    }

    // CONFIG EDITOR: Load file from mapped server folder.
    function loadTabContent(folder, file) {
      const tabId = file + "_tab";
      const tabs = document.getElementById("tabs");
      let existingTab = document.getElementById(tabId);
      if (!existingTab) {
          existingTab = document.createElement("div");
          existingTab.className = "tab";
          existingTab.id = tabId;
          existingTab.textContent = file;
          // Set onclick so clicking the preloaded tab reloads its content.
          existingTab.onclick = function() { loadTabContent(folder, file); };
          tabs.appendChild(existingTab);
      }
      Array.from(tabs.children).forEach(tab => tab.classList.remove("active"));
      existingTab.classList.add("active");
      document.getElementById("selectedFolder").value = folder;
      document.getElementById("selectedFile").value = file;
      
      logMessage(`Loading ${file} from folder ${folder}...`);
      const url = `${window.location.origin}/index.php?folder=${encodeURIComponent(folder)}&file=${encodeURIComponent(file)}`;
      fetch(url)
        .then(response => {
          logMessage(`HTTP status for ${file}: ${response.status}`);
          if (!response.ok) {
            return response.text().then(text => { throw new Error(`Error ${response.status}: ${text}`); });
          }
          return response.text();
        })
        .then(data => {
          document.getElementById("configEditor").value = data;
          logMessage(`Loaded ${file} successfully. (Snippet: "${data.substring(0, 50)}...")`);
        })
        .catch(error => {
          console.error("Error loading file:", error);
          logMessage(`Error loading ${file}: ${error.message}`);
        });
    }

    // OPEN EDITABLE FILE FROM FILE MANAGER
    // If a custom tab exists from a previous file, it is removed before creating a new one.
    function openEditableFile(fileName) {
      const tabs = document.getElementById("tabs");
      const managerTabId = "manager_tab_" + fileName.replace(/[^\w]/g, "_");
      const preloadedTabId = fileName + "_tab";
      let existingTab = document.getElementById(managerTabId) || document.getElementById(preloadedTabId);
      if (existingTab) {
          Array.from(tabs.children).forEach(tab => tab.classList.remove("active"));
          existingTab.classList.add("active");
          logMessage(`Tab for ${fileName} is already open. Switching to it.`);
          if(existingTab.id === preloadedTabId) {
              const serverFolder = document.getElementById("serverFolder").value;
              document.getElementById("selectedFolder").value = serverFolder;
              document.getElementById("selectedFile").value = fileName;
              loadTabContent(serverFolder, fileName);
          } else {
              document.getElementById("selectedFolder").value = "";
              document.getElementById("selectedFile").value = fileName;
              loadEditableFileContent(fileName);
          }
          return;
      }
      // Remove any existing custom manager tabs before creating a new one.
      let tempTabs = tabs.querySelectorAll("[id^='manager_tab_']");
      tempTabs.forEach(tab => tab.remove());
      
      const newTab = document.createElement("div");
      newTab.className = "tab active";
      newTab.id = managerTabId;
      newTab.textContent = fileName;
      // Attach onclick so that clicking the custom tab reloads its content.
      newTab.onclick = function() { openEditableFile(fileName); };
      tabs.insertBefore(newTab, tabs.firstChild);
      Array.from(tabs.children).forEach(tab => {
         if (tab.id !== managerTabId) {
             tab.classList.remove("active");
         }
      });
      document.getElementById("selectedFolder").value = "";
      document.getElementById("selectedFile").value = fileName;
      loadEditableFileContent(fileName);
    }

    // LOAD FILE CONTENT FROM FILE MANAGER
    function loadEditableFileContent(fileName) {
      const urlParams = new URLSearchParams(window.location.search);
      urlParams.set("action", "loadManagerFile");
      urlParams.set("file", fileName);
      urlParams.delete("folder");
      fetch(window.location.pathname + "?" + urlParams.toString())
        .then(response => response.text())
        .then(data => {
            document.getElementById("configEditor").value = data;
            logMessage("Loaded " + fileName + " from file manager.");
        })
        .catch(error => {
            logMessage("Error loading file: " + error.message);
        });
    }

    // SAVE FILE
    function saveFile() {
      const folder  = document.getElementById("selectedFolder").value;
      const file    = document.getElementById("selectedFile").value;
      const content = document.getElementById("configEditor").value;
      if (!file) {
        alert("No file selected!");
        return;
      }
      logMessage("Saving " + file + (folder ? " in folder " + folder : " from file manager") + "...");
      const params = new URLSearchParams();
      if (folder) {
        params.append('folder', folder);
        params.append('file', file);
      } else {
        params.append('managerFile', file);
      }
      params.append('fileContent', content);
      fetch(window.location.origin + "/index.php", {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
      })
      .then(response => {
        logMessage("HTTP status for saving " + file + ": " + response.status);
        if (!response.ok) {
          return response.text().then(text => { throw new Error("Error " + response.status + ": " + text); });
        }
        return response.text();
      })
      .then(data => {
        alert(data);
        logMessage("Save response: " + data);
      })
      .catch(error => {
        console.error("Error saving file:", error);
        logMessage("Error saving " + file + ": " + error.message);
      });
    }

    // EXTRACTION FUNCTION
    function extractFile(fileName) {
      if(confirm("Extract " + fileName + " ?")) {
          let urlParams = new URLSearchParams(window.location.search);
          urlParams.set('action', 'extractFile');
          urlParams.set('file', fileName);
          fetch(window.location.pathname + "?" + urlParams.toString())
            .then(response => response.text())
            .then(result => {
                logMessage("Extraction result for " + fileName + ": " + result.replace(/\n/g, "<br>"));
                loadFileList();
            })
            .catch(error => {
                logMessage("Extraction error for " + fileName + ": " + error.message);
            });
      }
    }

    // FILE BROWSER (AJAX NAVIGATION)
    function loadFileList() {
      let urlParams = new URLSearchParams(window.location.search);
      urlParams.set('action', 'loadFileList');
      fetch(window.location.pathname + "?" + urlParams.toString())
        .then(response => response.text())
        .then(html => {
           document.querySelector('.file-list').innerHTML = html;
        })
        .catch(err => { logMessage("Error loading file list: " + err); });
    }

    function openFolder(folderName) {
      let urlParams = new URLSearchParams(window.location.search);
      let currentPath = urlParams.get('path') || "";
      currentPath = currentPath ? currentPath + "/" + folderName : folderName;
      urlParams.set('path', currentPath);
      history.pushState(null, "", window.location.pathname + "?" + urlParams.toString());
      logMessage("Opening folder: " + currentPath);
      loadFileList();
    }

    function goUp() {
      let urlParams = new URLSearchParams(window.location.search);
      let currentPath = urlParams.get('path') || "";
      if (!currentPath) return;
      let parts = currentPath.split("/");
      parts.pop();
      urlParams.set('path', parts.join("/"));
      history.pushState(null, "", window.location.pathname + "?" + urlParams.toString());
      logMessage("Going up to folder: " + parts.join("/"));
      loadFileList();
    }

    function deleteFile(fileName) {
      let urlParams = new URLSearchParams(window.location.search);
      urlParams.set('delete', fileName);
      fetch(window.location.pathname + "?" + urlParams.toString())
        .then(response => {
          logMessage("File deleted: " + fileName);
          loadFileList();
        });
    }

    // CHUNKED UPLOAD WITH PROGRESS
    function uploadFile(file) {
      const chunkSize = 1024 * 1024;
      const totalChunks = Math.ceil(file.size / chunkSize);
      let currentChunk = 0;
      function uploadNextChunk() {
        const start = currentChunk * chunkSize;
        const end = Math.min(file.size, start + chunkSize);
        const chunk = file.slice(start, end);
        let formData = new FormData();
        formData.append("file", chunk);
        formData.append("chunk", currentChunk);
        formData.append("chunks", totalChunks);
        formData.append("filename", file.name);
        let xhr = new XMLHttpRequest();
        let urlParams = new URLSearchParams(window.location.search);
        xhr.open("POST", window.location.pathname + "?" + urlParams.toString(), true);
        xhr.upload.onprogress = function(e) {
          if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            logMessage("Uploading chunk " + (currentChunk + 1) + " of " + totalChunks + " (" + percentComplete.toFixed(2) + "%)");
          }
        };
        xhr.onload = function() {
          if (xhr.status == 200) {
            const resp = JSON.parse(xhr.responseText);
            currentChunk++;
            if (currentChunk < totalChunks) {
              uploadNextChunk();
            } else {
              logMessage("Upload complete: " + file.name);
              loadFileList();
            }
          } else {
            alert("Upload error: " + xhr.status);
            logMessage("Upload error: " + xhr.status);
          }
        };
        xhr.onerror = function() {
          alert("Upload error");
          logMessage("Upload error");
        };
        xhr.send(formData);
      }
      uploadNextChunk();
    }

    // INITIALIZE FILE BROWSER
    function initFileBrowser() {
      let dropArea = document.getElementById("drop-area");
      dropArea.addEventListener("dragover", function(e) {
        e.preventDefault();
        dropArea.style.background = "#e8e8e8";
      });
      dropArea.addEventListener("dragleave", function(e) {
        dropArea.style.background = "white";
      });
      dropArea.addEventListener("drop", function(e) {
        e.preventDefault();
        dropArea.style.background = "white";
        let file = e.dataTransfer.files[0];
        logMessage("Uploading file: " + file.name);
        uploadFile(file);
      });
      dropArea.addEventListener("click", function() {
        document.getElementById("fileInput").click();
      });
      document.getElementById("fileInput").addEventListener("change", function() {
        let file = this.files[0];
        logMessage("Uploading file: " + file.name);
        uploadFile(file);
      });
    }

    window.addEventListener("load", function() {
      const serverFolderSelect = document.getElementById("serverFolder");
      if (serverFolderSelect.value) {
         document.getElementById("tabs").style.display = "block";
         const editorContent = document.getElementById("configEditor").value.trim();
         if (!editorContent) {
             loadTabContent(serverFolderSelect.value, "server.cfg");
         }
      }
      initFileBrowser();
      loadFileList();
    });

    function folderChanged() {
      const serverFolderSelect = document.getElementById("serverFolder");
      const selected = serverFolderSelect.value;
      let urlParams = new URLSearchParams(window.location.search);
      urlParams.set('s', selected);
      urlParams.delete('path');
      history.pushState(null, "", window.location.pathname + "?" + urlParams.toString());
      logMessage("Server changed to: " + selected);
      loadFileList();
    }
  </script>
      <script>
        function refreshLogs() {
            fetch("<?php echo $_SERVER['PHP_SELF']; ?>?logs=1")
                .then(response => response.text())
                .then(data => {
                    let logDiv = document.getElementById("logs");
                    logDiv.innerHTML = data;
                    logDiv.scrollTop = 0; // Scroll to the top to show newest logs
                });
        }
        setInterval(refreshLogs, 5000); // Refresh logs every 5 seconds
        window.onload = refreshLogs; // Load logs immediately
    </script>
<body>
<div id="themeWrapper" class="theme-default">
  <!-- Top Bar -->
  <div class="top-bar">
    <div>
      <label for="themeSelect">Theme:</label>
      <select id="themeSelect" onchange="setTheme(this.value)">
        <option value="default">Default</option>
        <option value="classic">Classic</option>
        <option value="flat">Flat</option>
        <option value="material">Material</option>
        <option value="mac">Mac</option>
      </select>
    </div>
    <div>
      <a href="?logout=true">Logout</a> &nbsp;|&nbsp;
      <label for="serverFolder">Select Server Folder:</label>
      <select id="serverFolder" onchange="folderChanged()">
        <option value="">-- Select --</option>
        <?php foreach ($serverPaths as $key => $path): ?>
          <option value="<?= $key ?>" <?= ($selectedServer == $key ? "selected" : "") ?>>
            <?= $path ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Main Container: Left Column (File Manager) and Right Column (Config Editor + Logs) -->
  <div class="main-container">
    <!-- Left Column: File Manager Container -->
    <div class="left-column">
      <div class="file-manager-container">
    <h2>Game Server Status: <?php echo $light_color . " " . ucfirst($status); ?></h2>
    <form method="post">
        <button type="submit" name="action" value="start">Start Server</button>
        <button type="submit" name="action" value="stop">Stop Server</button>
        <button type="submit" name="action" value="restart">Restart Server</button>
    </form>
        <div id="drop-area">
          <p>Drag & Drop files here or click to upload</p>
          <input type="file" id="fileInput" style="display: none;">
        </div>
        <div class="file-list">
          <!-- File listing loaded via AJAX -->
        </div>
      </div>
    </div>

    <!-- Right Column: Config Editor (Top half) and Logs (Bottom half) -->
    <div class="right-column">
      <div class="config-editor-container">
        <div id="tabs">
          <?php 
          if ($selectedServer) {
              $basePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $serverPaths[$selectedServer];
              foreach ($configFiles as $file) {
                  if (file_exists($basePath . $file)) {
                      echo '<div class="tab" id="' . $file . '_tab" onclick="loadTabContent(document.getElementById(\'serverFolder\').value, \'' . $file . '\')">' . $file . '</div>';
                  }
              }
          }
          ?>
        </div>
        <textarea id="configEditor" placeholder="File content will appear here..."></textarea>
        <br>
        <input type="hidden" id="selectedFolder" value="">
        <input type="hidden" id="selectedFile" value="">
        <button onclick="saveFile()">Save Changes</button>
      </div>
      <div class="logs-container">
	  <div id="logs"></div>
        <div id="log"></div>
		
      </div>
    </div>
  </div>

  <footer>
    Game File Manager by jnonymous of TechPinoy v1. Developed with AI Technologics. CHATGPT
  </footer>
</div>
</body>
</html>
