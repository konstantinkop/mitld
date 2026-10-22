<?php
require_once __DIR__ . "/antibot.php";
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$digits = preg_replace("/\D+/", "", $q);

function http_get($url) {
  $ctx = stream_context_create(array(
    "http" => array(
      "method" => "GET",
      "timeout" => 8,
      "header" => "Accept: application/json\r\nUser-Agent: Kundportal/1.0\r\n",
      "ignore_errors" => true,
    ),
    "ssl" => array(
      "verify_peer" => false,
      "verify_peer_name" => false,
    ),
  ));
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) {
    return null;
  }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

function norm($raw) {
  if (!$raw || empty($raw["name"])) {
    return null;
  }
  if (!empty($raw["error"])) {
    return null;
  }
  $vatRaw = "";
  if (isset($raw["vat"])) {
    $vatRaw = $raw["vat"];
  } elseif (isset($raw["cvr"])) {
    $vatRaw = $raw["cvr"];
  }
  $vat = preg_replace("/\D+/", "", (string)$vatRaw);
  $vat = str_pad($vat, 8, "0", STR_PAD_LEFT);
  $addr = isset($raw["address"]) ? $raw["address"] : "";
  $zip = isset($raw["zipcode"]) ? (string)$raw["zipcode"] : "";
  $city = "";
  if (isset($raw["city"])) {
    $city = $raw["city"];
  } elseif (isset($raw["address_details"]["city"])) {
    $city = $raw["address_details"]["city"];
  } elseif (isset($raw["cityname"])) {
    $city = $raw["cityname"];
  }
  return array(
    "cvr" => $vat,
    "name" => $raw["name"],
    "address" => $addr,
    "zipcode" => $zip,
    "city" => $city,
    "addressLine" => trim($addr . ", " . $zip . " " . $city, " ,"),
    "companyType" => isset($raw["companydesc"]) ? $raw["companydesc"] : "",
    "industry" => isset($raw["industrydesc"]) ? $raw["industrydesc"] : "",
    "started" => isset($raw["startdate"]) ? $raw["startdate"] : "",
    "phone" => isset($raw["phone"]) ? $raw["phone"] : "",
    "email" => isset($raw["email"]) ? $raw["email"] : "",
  );
}

$results = array();

if (strlen($digits) === 8) {
  $one = http_get("https://datacvrapi.dk/api/v2/dk/company/" . $digits);
  $n = norm($one);
  if ($n) {
    $results[] = $n;
  }
  if (!$results) {
    $one = http_get("https://cvrapi.dk/api?search=" . urlencode($digits) . "&country=dk");
    $n = norm($one);
    if ($n) {
      $results[] = $n;
    }
  }
} elseif (strlen($q) >= 3 && !preg_match('/^[\d\s]+$/', $q)) {
  $pack = http_get("https://datacvrapi.dk/api/v2/dk/search/company?name=" . urlencode($q));
  $list = array();
  if (isset($pack["items"]) && is_array($pack["items"])) {
    $list = $pack["items"];
  } elseif (isset($pack["results"]) && is_array($pack["results"])) {
    $list = $pack["results"];
  } elseif (is_array($pack) && isset($pack[0])) {
    $list = $pack;
  }
  foreach ($list as $row) {
    $n = norm($row);
    if ($n) {
      $results[] = $n;
    }
    if (count($results) >= 3) {
      break;
    }
  }
}

echo json_encode(array("results" => $results), JSON_UNESCAPED_UNICODE);
