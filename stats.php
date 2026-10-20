<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-store");

require_once __DIR__ . "/tg.php";

echo json_encode(array(
  "online" => juj_count_online(90),
  "total_events" => juj_event_total(),
  "step_distribution" => array(),
  "generated_at" => gmdate("c"),
));
