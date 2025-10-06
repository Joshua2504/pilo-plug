<?php
/**
 * HomeWizard Energy — Single-file PHP interface (v1 + v2)
 *
 * Drop this file on a PHP-enabled server that can reach your device (same LAN).
 * Default device URL is http://172.16.0.189 for v1 and https://172.16.0.189 for v2.
 *
 * Covers v1 endpoints: /api, /api/v1/data, /api/v1/state (Energy Socket), /api/v1/telegram, /api/v1/identify, /api/v1/system.
 * Covers v2 endpoints: /api (device info), /api/measurement, /api/system (+ identify/reboot), /api/telegram, /api/batteries,
 * and /api/user (create/list/delete users for token auth).
 *
 * Notes:
 * - v2 uses HTTPS with a device certificate; for quick LAN tests you can disable SSL verification from the UI.
 * - Some endpoints depend on the device type/firmware; errors will show in the response panel.
 */

// --- tiny helper: read env/defaults
$defaultV1 = 'http://172.16.0.189';
$defaultV2 = 'https://172.16.0.189';

// state across requests (super simple)
session_start();

function value($key, $fallback = '') {
    if (isset($_POST[$key])) return trim((string)$_POST[$key]);
    if (isset($_SESSION[$key])) return $_SESSION[$key];
    return $fallback;
}

function keep($key, $val) { $_SESSION[$key] = $val; }

function pretty_json($str) {
    if ($str === '' || $str === null) return '';
    $json = json_decode($str, true);
    if ($json === null) return $str; // not JSON
    return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function http_call($method, $url, $headers = [], $body = null, $insecure = false, $timeout = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // capture headers
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    if ($body !== null && $body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $headers[] = 'Content-Type: application/json';
    }
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Allow local testing with self-signed device cert
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        return [
            'status' => 0,
            'headers' => '',
            'body' => json_encode(['curl_error' => $err, 'curl_errno' => $code])
        ];
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $raw_headers = substr($resp, 0, $header_size);
    $body = substr($resp, $header_size);

    curl_close($ch);
    return ['status' => $status_code, 'headers' => $raw_headers, 'body' => $body];
}

// --- handle form submit
$action = $_POST['action'] ?? '';

// remember basics
$api_version = value('api_version', 'v1'); // v1 or v2
$device_url = value('device_url', $api_version === 'v2' ? $defaultV2 : $defaultV1);
$token = value('token', '');
$insecure = value('insecure', '1') === '1';
$timeout = (int) (value('timeout', '10'));

keep('api_version', $api_version);
keep('device_url', $device_url);
keep('token', $token);
keep('insecure', $insecure ? '1' : '0');
keep('timeout', (string)$timeout);

$last_request = '';
$last_response_headers = '';
$last_response_body = '';
$last_status = '';

function do_call($method, $path, $body_json = null) {
    global $api_version, $device_url, $token, $insecure, $timeout, $last_request, $last_response_headers, $last_response_body, $last_status;

    // normalize URL (avoid dup slashes)
    $base = rtrim($device_url, '/');
    $url = $base . $path;

    $headers = [];
    if ($api_version === 'v2') {
        $headers[] = 'X-Api-Version: 2';
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    }

    $last_request = $method . ' ' . $url . "\n" . ($body_json ? pretty_json($body_json) : '');
    $resp = http_call($method, $url, $headers, $body_json, $insecure, $timeout);
    $last_status = (string)$resp['status'];
    $last_response_headers = $resp['headers'];
    $last_response_body = $resp['body'];
}

if ($action) {
    // V1 vs V2 path mapping
    switch ($action) {
        // --- common ---
        case 'device_info':
            do_call('GET', '/api');
            break;

        // --- v1 endpoints ---
        case 'v1_data':
            do_call('GET', '/api/v1/data');
            break;
        case 'v1_state_get':
            do_call('GET', '/api/v1/state');
            break;
        case 'v1_state_put':
            $payload = [
                'power_on' => isset($_POST['power_on']) ? ($_POST['power_on'] === '1') : null,
                'switch_lock' => isset($_POST['switch_lock']) ? ($_POST['switch_lock'] === '1') : null,
                'brightness' => isset($_POST['brightness']) && $_POST['brightness'] !== '' ? (int)$_POST['brightness'] : null,
            ];
            $payload = array_filter($payload, fn($v) => $v !== null);
            do_call('PUT', '/api/v1/state', json_encode($payload));
            break;
        case 'v1_telegram':
            do_call('GET', '/api/v1/telegram');
            break;
        case 'v1_identify':
            do_call('PUT', '/api/v1/identify');
            break;
        case 'v1_system_get':
            do_call('GET', '/api/v1/system');
            break;
        case 'v1_system_put':
            $payload = [ 'cloud_enabled' => ($_POST['cloud_enabled_v1'] ?? '') === '1' ];
            do_call('PUT', '/api/v1/system', json_encode($payload));
            break;

        // --- v2 endpoints ---
        case 'v2_measurement':
            do_call('GET', '/api/measurement');
            break;
        case 'v2_system_get':
            do_call('GET', '/api/system');
            break;
        case 'v2_system_put':
            $payload = [];
            if (isset($_POST['cloud_enabled_v2'])) $payload['cloud_enabled'] = $_POST['cloud_enabled_v2'] === '1';
            if (isset($_POST['status_led_brightness_pct']) && $_POST['status_led_brightness_pct'] !== '') $payload['status_led_brightness_pct'] = (int)$_POST['status_led_brightness_pct'];
            if (isset($_POST['api_v1_enabled'])) $payload['api_v1_enabled'] = $_POST['api_v1_enabled'] === '1';
            do_call('PUT', '/api/system', json_encode($payload));
            break;
        case 'v2_identify':
            do_call('PUT', '/api/system/identify');
            break;
        case 'v2_reboot':
            do_call('PUT', '/api/system/reboot');
            break;
        case 'v2_telegram':
            do_call('GET', '/api/telegram');
            break;
        case 'v2_batteries_get':
            do_call('GET', '/api/batteries');
            break;
        case 'v2_batteries_put':
            $payload = [];
            if (isset($_POST['battery_mode']) && $_POST['battery_mode'] !== '') $payload['mode'] = $_POST['battery_mode'];
            do_call('PUT', '/api/batteries', json_encode($payload));
            break;
        case 'v2_user_create':
            $name = trim($_POST['user_name'] ?? 'local/new_user');
            do_call('POST', '/api/user', json_encode(['name' => $name]));
            break;
        case 'v2_user_list':
            do_call('GET', '/api/user');
            break;
        case 'v2_user_delete':
            $name = trim($_POST['user_name_delete'] ?? '');
            do_call('DELETE', '/api/user', json_encode(['name' => $name]));
            break;
    }
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HomeWizard Energy — PHP Interface</title>
<style>
body{font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, sans-serif; margin:20px;}
.grid{display:grid; grid-template-columns: 1fr; gap:16px;}
.card{border:1px solid #ddd; border-radius:12px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,.04);} 
.row{display:flex; flex-wrap:wrap; gap:8px; align-items:center}
label{font-weight:600}
input[type=text], input[type=number]{padding:8px; border-radius:8px; border:1px solid #ccc; min-width:260px}
select{padding:8px; border-radius:8px; border:1px solid #ccc}
button{padding:8px 12px; border-radius:8px; border:1px solid #999; background:#f7f7f7; cursor:pointer}
button.primary{background:#0ea5e9; color:#fff; border-color:#0ea5e9}
pre{background:#0b1020; color:#e6f1ff; padding:12px; border-radius:8px; overflow:auto; max-height:400px}
small, .muted{color:#666}
hr{border:none; border-top:1px dashed #ddd; margin:12px 0}
.badge{font-size:12px; padding:2px 8px; border-radius:999px; background:#eef; border:1px solid #99c}
</style>
</head>
<body>
<h1>HomeWizard Energy — Single-file PHP UI</h1>
<form method="post" class="card">
  <div class="row">
    <label>API Version</label>
    <select name="api_version" onchange="this.form.submit()">
      <option value="v1" <?= $api_version==='v1'?'selected':''?>>v1 (no auth)</option>
      <option value="v2" <?= $api_version==='v2'?'selected':''?>>v2 (Bearer token)</option>
    </select>
    <label>Device URL</label>
    <input type="text" name="device_url" value="<?= htmlspecialchars($device_url) ?>" placeholder="http(s)://IP">
    <label>Timeout</label>
    <input type="number" name="timeout" value="<?= (int)$timeout ?>" min="1" max="60" style="width:80px">
    <label class="row"><input type="checkbox" name="insecure" value="1" <?= $insecure?'checked':''?>> Disable SSL verification (LAN/dev)</label>
  </div>
  <?php if ($api_version==='v2'): ?>
  <div class="row" style="margin-top:8px">
    <label>Bearer token</label>
    <input type="text" name="token" value="<?= htmlspecialchars($token) ?>" placeholder="32 hex chars">
    <span class="muted">You can create a token below via <code>/api/user</code> (press device button).</span>
  </div>
  <?php endif; ?>
  <div style="margin-top:12px">
    <button class="primary">Save</button>
  </div>
</form>

<div class="grid">
  <div class="card">
    <h2>Device Information <span class="badge">GET /api</span></h2>
    <form method="post" class="row">
      <input type="hidden" name="api_version" value="<?= $api_version ?>">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="insecure" value="<?= $insecure?'1':'0' ?>">
      <input type="hidden" name="timeout" value="<?= (int)$timeout ?>">
      <button name="action" value="device_info">Fetch</button>
    </form>
  </div>

  <?php if ($api_version==='v1'): ?>
  <div class="card">
    <h2>v1 — Measurement <span class="badge">GET /api/v1/data</span></h2>
    <form method="post" class="row">
      <input type="hidden" name="api_version" value="v1">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <button name="action" value="v1_data">Fetch</button>
    </form>
  </div>

  <div class="card">
    <h2>v1 — Energy Socket State <span class="badge">GET/PUT /api/v1/state</span></h2>
    <form method="post">
      <input type="hidden" name="api_version" value="v1">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <div class="row"><button name="action" value="v1_state_get">Get</button></div>
      <hr>
      <div class="row">
        <label><input type="checkbox" name="power_on" value="1"> power_on</label>
        <label><input type="checkbox" name="switch_lock" value="1"> switch_lock</label>
        <label>brightness <input type="number" name="brightness" min="0" max="255" style="width:100px"></label>
        <button name="action" value="v1_state_put">PUT</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>v1 — Telegram <span class="badge">GET /api/v1/telegram</span></h2>
    <form method="post" class="row">
      <input type="hidden" name="api_version" value="v1">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <button name="action" value="v1_telegram">Fetch</button>
    </form>
  </div>

  <div class="card">
    <h2>v1 — Identify <span class="badge">PUT /api/v1/identify</span></h2>
    <form method="post" class="row">
      <input type="hidden" name="api_version" value="v1">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <button name="action" value="v1_identify">Blink LED</button>
    </form>
  </div>

  <div class="card">
    <h2>v1 — System <span class="badge">GET/PUT /api/v1/system</span></h2>
    <form method="post">
      <input type="hidden" name="api_version" value="v1">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <div class="row"><button name="action" value="v1_system_get">Get</button></div>
      <hr>
      <div class="row">
        <label><input type="checkbox" name="cloud_enabled_v1" value="1"> cloud_enabled</label>
        <button name="action" value="v1_system_put">PUT</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($api_version==='v2'): ?>
  <div class="card">
    <h2>v2 — Measurement <span class="badge">GET /api/measurement</span></h2>
    <form method="post" class="row">
      <input type="hidden" name="api_version" value="v2">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="insecure" value="<?= $insecure?'1':'0' ?>">
      <input type="hidden" name="timeout" value="<?= (int)$timeout ?>">
      <button name="action" value="v2_measurement">Fetch</button>
    </form>
  </div>

  <div class="card">
    <h2>v2 — System <span class="badge">GET/PUT /api/system</span></h2>
    <form method="post">
      <input type="hidden" name="api_version" value="v2">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="row"><button name="action" value="v2_system_get">Get</button></div>
      <hr>
      <div class="row">
        <label><input type="checkbox" name="cloud_enabled_v2" value="1"> cloud_enabled</label>
        <label>status_led_brightness_pct <input type="number" name="status_led_brightness_pct" min="0" max="100" style="width:100px"></label>
        <label><input type="checkbox" name="api_v1_enabled" value="1"> api_v1_enabled</label>
        <button name="action" value="v2_system_put">PUT</button>
      </div>
      <hr>
      <div class="row">
        <button name="action" value="v2_identify">Identify (blink)</button>
        <button name="action" value="v2_reboot" onclick="return confirm('Reboot device now?')">Reboot</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>v2 — Telegram <span class="badge">GET /api/telegram</span></h2>
    <form method="post" class="row">
      <input type="hidden" name="api_version" value="v2">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <button name="action" value="v2_telegram">Fetch</button>
    </form>
  </div>

  <div class="card">
    <h2>v2 — Batteries <span class="badge">GET/PUT /api/batteries</span></h2>
    <form method="post">
      <input type="hidden" name="api_version" value="v2">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="row"><button name="action" value="v2_batteries_get">Get</button></div>
      <hr>
      <div class="row">
        <label>mode
          <select name="battery_mode">
            <option value="">—</option>
            <option value="off">off</option>
            <option value="charge">charge</option>
            <option value="discharge">discharge</option>
            <option value="auto">auto</option>
          </select>
        </label>
        <button name="action" value="v2_batteries_put">PUT</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>v2 — Users & Token <span class="badge">/api/user</span></h2>
    <form method="post">
      <input type="hidden" name="api_version" value="v2">
      <input type="hidden" name="device_url" value="<?= htmlspecialchars($device_url) ?>">
      <input type="hidden" name="insecure" value="<?= $insecure?'1':'0' ?>">
      <div class="row">
        <label>Create user name</label>
        <input type="text" name="user_name" placeholder="local/my-app">
        <button name="action" value="v2_user_create">POST (press device button)</button>
      </div>
      <hr>
      <div class="row">
        <button name="action" value="v2_user_list">GET list users</button>
      </div>
      <hr>
      <div class="row">
        <label>Delete user name</label>
        <input type="text" name="user_name_delete" placeholder="local/my-app or cloud/cloud_user">
        <button name="action" value="v2_user_delete" onclick="return confirm('Delete user? You may lose access.')">DELETE</button>
      </div>
    </form>
    <p class="muted">Tip: After you successfully POST and receive a token, paste it in the header field at the top and click Save.</p>
  </div>

  <div class="card">
    <h2>v2 — Live WebSocket (experimental)</h2>
    <p class="muted">Opens <code>wss://&lt;ip&gt;/api/ws</code> and subscribes to <code>measurement</code>. Requires a valid token and a browser that accepts the device certificate.</p>
    <div class="row">
      <button type="button" onclick="startWs()">Connect</button>
      <button type="button" onclick="stopWs()">Close</button>
    </div>
    <pre id="wslog"></pre>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Last Request / Response</h2>
    <div class="row"><span class="badge">HTTP <?= htmlspecialchars($last_status) ?></span></div>
    <h3>Request</h3>
    <pre><?= htmlspecialchars($last_request) ?></pre>
    <h3>Response Headers</h3>
    <pre><?= htmlspecialchars($last_response_headers) ?></pre>
    <h3>Response Body</h3>
    <pre><?= htmlspecialchars(pretty_json($last_response_body)) ?></pre>
  </div>
</div>

<?php if ($api_version==='v2'): ?>
<script>
const deviceUrl = "<?= htmlspecialchars($device_url) ?>";
const token = "<?= htmlspecialchars($token) ?>";
let ws;
function log(line){ const el=document.getElementById('wslog'); el.textContent += line + "\n"; el.scrollTop = el.scrollHeight; }
function startWs(){
  try{
    const wssUrl = deviceUrl.replace(/^http:/,'ws:').replace(/^https:/,'wss:').replace(/\/$/, '') + '/api/ws';
    ws = new WebSocket(wssUrl);
    ws.onopen = () => log('WS: opened ' + wssUrl);
    ws.onmessage = ev => {
      // auto respond with authorization when requested
      try{
        const msg = JSON.parse(ev.data);
        if (msg && msg.type === 'authorization_requested'){
          ws.send(JSON.stringify({type:'authorization', data: token}));
          log('WS→ authorization token sent');
          // subscribe to measurement by default
          setTimeout(()=> ws.send(JSON.stringify({type:'subscribe', data:'measurement'})), 50);
        }
      }catch(e){}
      log('WS← ' + ev.data);
    };
    ws.onerror = (e) => log('WS error: ' + (e.message||'unknown'));
    ws.onclose = () => log('WS: closed');
  }catch(err){ log('WS init error: ' + err.message); }
}
function stopWs(){ if(ws){ ws.close(); } }
</script>
<?php endif; ?>

</body>
</html>
