<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counter-Strike 1.3 & 1.5 HLDS Docker Image</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #121212;
            color: #ffffff;
            padding: 20px;
        }
        h1, h2, h3 {
            color: #ffcc00;
        }
        pre {
            background-color: #222;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        code {
            color: #ffcc00;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        a {
            color: #00aaff;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Counter-Strike 1.3 & 1.5 HLDS Docker Image</h1>
    
    <p>This Docker image allows you to run a Counter-Strike 1.3 or 1.5 **Half-Life Dedicated Server (HLDS 3109)** 
    using **protocol 45**. It includes support for the following plugins:</p>
    
    <ul>
        <li><strong>AMX Mod X</strong> - Server administration and scripting</li>
        <li><strong>Metamod</strong> - Plugin management</li>
        <li><strong>Podbot</strong> - AI bots for offline play</li>
    </ul>
    
    <h2>🛠 Docker Image Details</h2>
    <p>The Docker image is available on **Docker Hub**:</p>
    <pre><code>docker pull jnonymous420/hlds3109:v1</code></pre>
    
    <h2>🚀 Running the Server with Docker Compose</h2>
    <p>The following <code>docker-compose.yml</code> file sets up the HLDS server:</p>
    
    <pre><code>version: '3.8'

services:
  hlds_server:
    image: jnonymous420/hlds3109:v1
    container_name: counter-strike-server
    restart: unless-stopped
    user: "0:0"
    tty: true
    stdin_open: true
    ports:
      - "27015:27015/udp"
      - "27015:27015/tcp"  # For RCON access
    volumes:
      - /opt/hlds_l/cstrike:/server/hlds_l/cstrike
      - /opt/hlds_l/cstrk15:/server/hlds_l/cstrk15
    environment:
      - HLDS_MAP=de_dust2
      - HLDS_MAXPLAYERS=32
      - HLDS_IP=0.0.0.0
      - HLDS_PORT=27015
      - HLDS_GAME=cstrike  # Change to "cstrk15" for CS 1.5
    command: >
      ./hlds_run +ip $HLDS_IP +port $HLDS_PORT -game $HLDS_GAME +map $HLDS_MAP +maxplayers $HLDS_MAXPLAYERS -noauth -insecure +sv_lan 1
    security_opt:
      - no-new-privileges:true
    </code></pre>

    <h2>🎮 How to Use</h2>
    <h3>Start the server</h3>
    <pre><code>docker-compose up -d</code></pre>

    <h3>Stop the server</h3>
    <pre><code>docker-compose down</code></pre>

    <h3>Check logs</h3>
    <pre><code>docker logs -f counter-strike-server</code></pre>

    <h2>🌍 Game Modes</h2>
    <p>You can switch between **Counter-Strike 1.3** and **Counter-Strike 1.5**:</p>
    
    <ul>
        <li>For <strong>CS 1.3</strong>, set: <code>HLDS_GAME=cstrike</code></li>
        <li>For <strong>CS 1.5</strong>, set: <code>HLDS_GAME=cstrk15</code></li>
    </ul>

    <p>Change the <code>HLDS_GAME</code> environment variable and restart the server:</p>
    <pre><code>docker-compose down && docker-compose up -d</code></pre>

    <h2>🔗 Live Demo Servers</h2>
    <ul>
        <li><strong>CS 1.3 Server:</strong> <code>cs.techpinoy.net:27015</code></li>
        <li><strong>CS 1.5 Server:</strong> <code>cs.techpinoy.net:27016</code></li>
    </ul>

    <h2>📜 Notes</h2>
    <ul>
        <li>This server runs **HLDS 3109** with **Protocol 45**.</li>
        <li>For better performance, consider mounting volumes for custom maps and configurations.</li>
        <li>Always run the container with `-insecure` to avoid authentication issues.</li>
    </ul>

    <h2>💡 More Information</h2>
    <p>Visit <a href="http://cs.techpinoy.net">cs.techpinoy.net</a> for more details.</p>
</div>

</body>
</html>
