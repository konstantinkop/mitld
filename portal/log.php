<!DOCTYPE html>
<html lang="da">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aktivitetslog</title>
  <style>
    :root { --bg:#0f172a; --card:#1e293b; --line:#334155; --text:#e2e8f0; --muted:#94a3b8; --accent:#0f766e; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: Arial, Helvetica, sans-serif; background:var(--bg); color:var(--text); }
    header { display:flex; gap:12px; align-items:center; justify-content:space-between; padding:16px 18px; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--bg); }
    h1 { margin:0; font-size:18px; }
    .muted { color:var(--muted); font-size:12px; }
    button, input { font: inherit; }
    button { background:var(--accent); color:#fff; border:0; border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer; }
    input { background:#0b1220; border:1px solid var(--line); color:var(--text); border-radius:10px; padding:10px 12px; min-width:220px; }
    .wrap { padding:16px 18px 40px; }
    table { width:100%; border-collapse:collapse; background:var(--card); border-radius:14px; overflow:hidden; }
    th, td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; font-size:13px; }
    th { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.04em; background:#111827; }
    tr:hover td { background:rgba(255,255,255,.03); }
    .actor { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .actor-admin { background:#1d4ed8; }
    .actor-user { background:#0f766e; }
    .actor-system { background:#475569; }
    .sid { font-family: ui-monospace, Menlo, Consolas, monospace; font-size:11px; color:var(--muted); }
    .err { color:#fca5a5; padding:20px; }
    .tools { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  </style>
</head>
<body>
  <header>
    <div>
      <h1>Aktivitetslog</h1>
      <div class="muted">Permanent log — slettes ikke når sessioner fjernes</div>
    </div>
    <div class="tools">
      <input id="q" type="search" placeholder="Søg session / event / IP…" />
      <button type="button" id="reload">Opdater</button>
    </div>
  </header>
  <div class="wrap">
    <div id="status" class="muted" style="margin-bottom:12px">Henter…</div>
    <div style="overflow:auto">
      <table>
        <thead>
          <tr>
            <th>Tid</th>
            <th>Hvem</th>
            <th>Event</th>
            <th>Detalje</th>
            <th>Session</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody id="rows"></tbody>
      </table>
    </div>
  </div>
  <script>
    (function () {
      var all = [];
      function token() {
        try { return localStorage.getItem("admin_token") || ""; } catch (e) { return ""; }
      }
      function fmt(iso) {
        if (!iso) return "—";
        try {
          return new Date(iso).toLocaleString("da-DK", { hour12: false });
        } catch (e) {
          return iso;
        }
      }
      function actorClass(a) {
        if (a === "admin") return "actor actor-admin";
        if (a === "user") return "actor actor-user";
        return "actor actor-system";
      }
      function render(list) {
        var tb = document.getElementById("rows");
        tb.innerHTML = "";
        list.forEach(function (row) {
          var tr = document.createElement("tr");
          tr.innerHTML =
            "<td>" + fmt(row.createdAt) + "</td>" +
            '<td><span class="' + actorClass(row.actor) + '">' + (row.actor || "—") + "</span></td>" +
            "<td>" + (row.eventType || "—") + "</td>" +
            "<td>" + (row.detail || "—") + "</td>" +
            '<td class="sid">' + (row.sessionId ? String(row.sessionId).slice(0, 16) + "…" : "—") + "</td>" +
            "<td>" + (row.ip || "—") + "</td>";
          tb.appendChild(tr);
        });
        document.getElementById("status").textContent = list.length + " hændelser";
      }
      function applyFilter() {
        var q = (document.getElementById("q").value || "").toLowerCase().trim();
        if (!q) { render(all); return; }
        render(all.filter(function (r) {
          return [r.sessionId, r.actor, r.eventType, r.detail, r.ip]
            .join(" ")
            .toLowerCase()
            .indexOf(q) !== -1;
        }));
      }
      function load() {
        var t = token();
        if (!t) {
          document.getElementById("status").innerHTML = '<div class="err">Log ind i admin først (#admin), så åbn denne side igen.</div>';
          return;
        }
        document.getElementById("status").textContent = "Henter…";
        fetch("/api/admin/audit?limit=500", {
          headers: { Authorization: "Bearer " + t }
        })
          .then(function (r) {
            if (!r.ok) throw new Error("Auth/API fejl " + r.status);
            return r.json();
          })
          .then(function (data) {
            all = (data && data.logs) || [];
            applyFilter();
          })
          .catch(function (e) {
            document.getElementById("status").innerHTML = '<div class="err">' + (e.message || "Fejl") + "</div>";
          });
      }
      document.getElementById("reload").onclick = load;
      document.getElementById("q").oninput = applyFilter;
      load();
      setInterval(load, 15000);
    })();
  </script>
</body>
</html>
