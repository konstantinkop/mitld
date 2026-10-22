<?php
/**
 * Antibot — block AI crawlers, scrapers, and scanners by User-Agent.
 * Include at the top of every PHP entry: require_once __DIR__ . "/antibot.php";
 */

if (!function_exists("juj_antibot_list")) {
  function juj_antibot_list() {
    return array(
      // AI / LLM crawlers
      "GPTBot",
      "ChatGPT-User",
      "OAI-SearchBot",
      "ClaudeBot",
      "Claude-Web",
      "anthropic-ai",
      "Claude-User",
      "Claude-SearchBot",
      "PerplexityBot",
      "Perplexity-User",
      "Google-Extended",
      "Google-CloudVertexBot",
      "Gemini-Deep-Research",
      "Applebot-Extended",
      "Meta-ExternalAgent",
      "meta-externalagent",
      "FacebookBot",
      "facebookexternalhit",
      "Bytespider",
      "CCBot",
      "cohere-training-data-crawler",
      "Diffbot",
      "Amazonbot",
      "Applebot",
      "Kimi-User",
      "DeepSeekBot",
      "MistralAI-User",
      "PhindBot",
      "Timpibot",
      "YouBot",
      "TavilyBot",
      "TwinAgent",
      "Thinkbot",
      // Search / SEO crawlers
      "Bingbot",
      "YandexBot",
      "Baiduspider",
      "DuckDuckBot",
      "Slurp",
      "AhrefsBot",
      "SemrushBot",
      "MJ12bot",
      "DotBot",
      "PetalBot",
      "MauiBot",
      "Sogou",
      "rogerbot",
      "archive.org_bot",
      // Scraper frameworks
      "Scrapy",
      "Crawl4AI",
      "FirecrawlAgent",
      "ApifyBot",
      "ImagesiftBot",
      "img2dataset",
      "omgili",
      "omgilibot",
      "ShapBot",
      "TerraCotta",
      "VelenPublicWebCrawler",
      "WARDBot",
      "wpbot",
      "YaK",
      "ZanistaBot",
      // Security scanners / exploit tools
      "sqlmap",
      "Nikto",
      "nmap",
      "masscan",
      "nuclei",
      "gobuster",
      "wfuzz",
      "metasploit",
      "acunetix",
      "netsparker",
      "OpenVAS",
      "w3af",
      "ZmEu",
      "Havij",
      "sqlninja",
      "dirbuster",
      "arachni",
      "vega",
    );
  }

  function juj_antibot_check() {
    $ua = isset($_SERVER["HTTP_USER_AGENT"]) ? (string)$_SERVER["HTTP_USER_AGENT"] : "";
    if ($ua === "") {
      // Empty UA is often a scanner — block
      juj_antibot_block("(empty)");
    }
    $uaLower = strtolower($ua);
    foreach (juj_antibot_list() as $bot) {
      if ($bot !== "" && strpos($uaLower, strtolower($bot)) !== false) {
        juj_antibot_block($bot);
      }
    }
  }

  function juj_antibot_block($matched) {
    $logDir = __DIR__ . "/data";
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0755, true);
    }
    $ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "-";
    $uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "-";
    $ua = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
    @file_put_contents(
      $logDir . "/antibot.log",
      gmdate("c") . "\t" . $ip . "\t" . $matched . "\t" . $uri . "\t" . $ua . "\n",
      FILE_APPEND
    );
    http_response_code(403);
    header("Content-Type: text/plain; charset=utf-8");
    header("X-Robots-Tag: noindex, nofollow");
    echo "Forbidden";
    exit;
  }
}

juj_antibot_check();
