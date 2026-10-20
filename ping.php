<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-store");

require_once __DIR__ . "/tg.php";

$sid = "";
if (isset($_GET["sid"])) {
  $sid = $_GET["sid"];
} elseif (isset($_POST["sid"])) {
  $sid = $_POST["sid"];
}
$sid = preg_replace('/[^a-zA-Z0-9._-]/', "", (string)$sid);
if ($sid === "") {
  $sid = "web-" . substr(md5((isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "") . (isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "")), 0, 12);
}

juj_touch_online($sid);
echo json_encode(array("ok" => true));
