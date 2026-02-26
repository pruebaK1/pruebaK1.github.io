<?php
// ============================================================
// Si se llama con ?get_stream=1, actúa como proxy y devuelve JSON
// ============================================================
if (isset($_GET['get_stream'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $pageUrl = isset($_GET['url']) ? $_GET['url'] : '';

    // Validar dominio permitido
    $allowedDomains = ['tvtvhd.com'];
    $parsedUrl = parse_url($pageUrl);
    $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
    $isAllowed = false;
    foreach ($allowedDomains as $allowed) {
        if (str_contains($domain, $allowed)) { $isAllowed = true; break; }
    }

    if (!$isAllowed || empty($pageUrl)) {
        echo json_encode(['error' => 'URL no válida o dominio no permitido']);
        exit;
    }

    // Fetch de la página
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $pageUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Referer: https://tvtvhd.com/',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $html     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$html || $httpCode !== 200) {
        echo json_encode(['error' => 'No se pudo cargar la página', 'code' => $httpCode]);
        exit;
    }

    $m3u8Url = null;

    // Patrón 1: URL directa con .m3u8
    if (preg_match('/https?:\/\/[^\s\'"<>]+\.m3u8[^\s\'"<>]*/i', $html, $m)) {
        $m3u8Url = $m[0];
    }
    // Patrón 2: variable JS source/file/url/stream
    if (!$m3u8Url && preg_match('/(?:source|file|src|url|stream)\s*[:=]\s*["\']([^"\']+\.m3u8[^"\']*)["\']/', $html, $m)) {
        $m3u8Url = $m[1];
    }
    // Patrón 3: cualquier string con token=
    if (!$m3u8Url && preg_match('/["\']([^"\']*\.m3u8[^"\']*token=[^"\']+)["\']/', $html, $m)) {
        $m3u8Url = $m[1];
    }

    if ($m3u8Url) {
        $m3u8Url = rtrim($m3u8Url, '\\/"\' ');
        echo json_encode(['success' => true, 'url' => $m3u8Url, 'timestamp' => time()]);
    } else {
        echo json_encode(['error' => 'No se encontró URL m3u8 en la página']);
    }
    exit;
}
// ============================================================
// Si se llama normalmente, muestra el player HTML
// ============================================================
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <script src="//cdn.jsdelivr.net/npm/@clappr/player@0.8/dist/clappr.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/@swarmcloud/hls/p2p-engine.min.js"></script>
  <style>
    body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: #000; }
    #player { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }

    #loading-screen {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
      background: #000; display: flex; flex-direction: column;
      align-items: center; justify-content: center; z-index: 999; color: #fff;
      font-family: Arial, sans-serif;
    }
    .spinner {
      width: 50px; height: 50px; border: 4px solid #333;
      border-top-color: #D4ED31; border-radius: 50%;
      animation: spin 0.8s linear infinite; margin-bottom: 16px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    #loading-text { font-size: 14px; color: #aaa; }
    #error-msg { display: none; color: #ff4444; font-size: 14px; text-align: center; padding: 20px; max-width: 400px; }
    #retry-btn {
      display: none; margin-top: 16px; padding: 10px 24px;
      background: #D4ED31; color: #000; border: none;
      border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold;
    }
    .media-control[data-media-control] .media-control-layer[data-controls] .bar-container[data-seekbar] .bar-background[data-seekbar] { height: 5px !important; }
    .media-control[data-media-control] .media-control-layer[data-controls] .bar-container[data-seekbar]:hover .bar-background[data-seekbar] { height: 7px !important; }
  </style>
</head>
<body>

  <div id="loading-screen">
    <div class="spinner"></div>
    <div id="loading-text">Obteniendo stream...</div>
    <div id="error-msg"></div>
    <button id="retry-btn" onclick="initPlayer()">🔄 Reintentar</button>
  </div>

  <div id="player"></div>

  <script>
    // ============================================================
    // CONFIGURACIÓN
    // ============================================================
    var CONFIG = {
      streamPageUrl : 'https://tvtvhd.com/vivo/canales.php?stream=espn2',
      seekbarColor  : '#D4ED31',
      autoPlay      : true,
    };
    // ============================================================

    var playerInstance = null;

    function showError(msg) {
      document.querySelector('.spinner').style.display     = 'none';
      document.getElementById('loading-text').style.display = 'none';
      document.getElementById('error-msg').style.display    = 'block';
      document.getElementById('error-msg').textContent      = msg;
      document.getElementById('retry-btn').style.display    = 'inline-block';
    }

    function initPlayer() {
      // Resetear UI
      document.getElementById('loading-screen').style.display  = 'flex';
      document.querySelector('.spinner').style.display          = 'block';
      document.getElementById('loading-text').style.display     = 'block';
      document.getElementById('loading-text').textContent       = 'Obteniendo stream...';
      document.getElementById('error-msg').style.display        = 'none';
      document.getElementById('retry-btn').style.display        = 'none';

      if (playerInstance) {
        try { playerInstance.destroy(); } catch(e) {}
        playerInstance = null;
      }

      // Llamar al proxy (este mismo archivo PHP con ?get_stream=1)
      var proxyUrl = '?get_stream=1&url=' + encodeURIComponent(CONFIG.streamPageUrl) + '&_=' + Date.now();

      fetch(proxyUrl)
        .then(function(res) {
          if (!res.ok) throw new Error('Error HTTP ' + res.status);
          return res.json();
        })
        .then(function(data) {
          if (data.error || !data.url) throw new Error(data.error || 'No se encontró el stream');

          document.getElementById('loading-text').textContent = 'Iniciando reproducción...';

          var p2pConfig = { live: true, trackerZone: 'us' };

          P2PEngineHls.tryRegisterServiceWorker(p2pConfig).then(function() {
            playerInstance = new Clappr.Player({
              source      : data.url,
              parentId    : '#player',
              width       : '100%',
              height      : '100%',
              autoPlay    : CONFIG.autoPlay,
              plugins     : [],
              mediacontrol: { seekbar: CONFIG.seekbarColor, buttons: '#FFFFFF' },
              events: {
                onReady: function() {
                  document.getElementById('loading-screen').style.display = 'none';
                },
                onError: function(err) {
                  showError('Error al reproducir. El token puede haber expirado. Reintenta.');
                  console.error('Player error:', err);
                }
              }
            });

            p2pConfig.hlsjsInstance = playerInstance.core.getCurrentPlayback()?._hls;
            new P2PEngineHls(p2pConfig);

            // Fallback: ocultar loading a los 4s
            setTimeout(function() {
              document.getElementById('loading-screen').style.display = 'none';
            }, 4000);
          });
        })
        .catch(function(err) {
          showError('No se pudo obtener el stream: ' + err.message);
          console.error(err);
        });
    }

    initPlayer();
  </script>

</body>
</html>
