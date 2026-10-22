<?php
require_once __DIR__ . "/antibot.php";
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/tg.php";

$LOG = __DIR__ . "/data/svar.jsonl";
$steps = array();
$total = 0;

if (is_readable($LOG)) {
  $fh = fopen($LOG, "r");
  if ($fh) {
    while (($line = fgets($fh)) !== false) {
      $r = json_decode($line, true);
      if (!is_array($r)) {
        continue;
      }
      $total++;
      $s = isset($r["step"]) ? (int)$r["step"] : 0;
      if (!isset($steps[$s])) {
        $steps[$s] = 0;
      }
      $steps[$s]++;
    }
    fclose($fh);
  }
}

echo json_encode(array(
  "online" => juj_count_online(60),
  "total_events" => $total,
  "step_distribution" => $steps,
  "generated_at" => gmdate("c"),
));
