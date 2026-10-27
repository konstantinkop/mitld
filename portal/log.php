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
    header { display:flex; gap:12px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; padding:16px 18px; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--bg); z-index:2; }
    h1 { margin:0; font-size:18px; }
    .muted { color:var(--muted); font-size:12px; }
    button, input { font: inherit; }
    button { background:var(--accent); color:#fff; border:0; border-radius:10px; padding:12px 14px; font-weight:700; cursor:pointer; min-height:44px; touch-action:manipulation; }
    button.ghost { background:#334155; }
    input { background:#0b1220; border:1px solid var(--line); color:var(--text); border-radius:10px; padding:12px; min-width:0; width:100%; font-size:16px; }
    .wrap { padding:16px 18px 40px; }
    .table-scroll { overflow:auto; -webkit-overflow-scrolling:touch; max-width:100%; }
    table { width:100%; min-width:640px; border-collapse:collapse; background:var(--card); border-radius:14px; overflow:hidden; }
    th, td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; font-size:13px; }
    @media (max-width:640px) {
      header { padding:12px 14px; }
      .tools { width:100%; }
      .tools input { flex:1 1 100%; }
    }
    th { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.04em; background:#111827; }
    tr:hover td { background:rgba(255,255,255,.03); }
    .actor { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .actor-admin { background:#1d4ed8; }
    .actor-user { background:#0f766e; }
    .actor-system { background:#475569; }
    .sid { font-family: ui-monospace, Menlo, Consolas, monospace; font-size:11px; color:var(--muted); }
    .err { color:#fca5a5; padding:8px 0; font-size:13px; }
    .tools { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    #gate {
      position:fixed; inset:0; z-index:100000; background:rgba(2,6,23,.82);
      display:flex; align-items:center; justify-content:center; padding:16px;
    }
    #gate .box {
      width:100%; max-width:380px; background:var(--card); border-radius:16px; padding:20px 18px;
      box-shadow:0 20px 60px rgba(0,0,0,.45);
    }
    #gate label { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; color:var(--muted); margin-bottom:8px; }
    #gate .actions { display:flex; gap:8px; margin-top:14px; }
    #gate .actions button { flex:1; }
    #app { display:none; }
  </style>
</head>
<body>
  <div id="gate">
    <div class="box">
      <div style="font-weight:700;font-size:18px;margin-bottom:6px">Aktivitetslog</div>
      <div class="muted" style="margin-bottom:16px">Indtast adgangskode for at fortsætte</div>
      <label for="pw">ADGANGSKODE</label>
      <input id="pw" type="password" autocomplete="current-password" autofocus />
      <div id="gate-err" class="err" style="display:none"></div>
      <div class="actions">
        <button type="button" class="ghost" id="gate-cancel">Afbryd</button>
        <button type="button" id="gate-ok">Åbn</button>
      </div>
    </div>
  </div>

  <div id="app">
    <header>
      <div>
        <h1>Aktivitetslog</h1>
        <div class="muted">Permanent log — slettes ikke når sessioner fjernes</div>
      </div>
      <div class="tools">
        <input id="q" type="search" placeholder="Søg session / event / IP…" />
        <button type="button" id="reload">Opdater</button>
        <button type="button" class="ghost" id="lock">Lås</button>
      </div>
    </header>
    <div class="wrap">
      <div id="status" class="muted" style="margin-bottom:12px">Henter…</div>
      <div class="table-scroll">
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
  </div>

  <script>
    (function () {
      var KEY = "juj_audit_pw";
      var all = [];
      var auditPw = "";

      function token() {
        try { return localStorage.getItem("admin_token") || ""; } catch (e) { return ""; }
      }
      function savedPw() {
        try { return localStorage.getItem(KEY) || ""; } catch (e) { return ""; }
      }
      function savePw(v) {
        try { localStorage.setItem(KEY, v); } catch (e) {}
      }
      function clearPw() {
        try { localStorage.removeItem(KEY); } catch (e) {}
      }

      function fmt(iso) {
        if (!iso) return "—";
        try { return new Date(iso).toLocaleString("da-DK", { hour12: false }); }
        catch (e) { return iso; }
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

      function showApp() {
        document.getElementById("gate").style.display = "none";
        document.getElementById("app").style.display = "block";
      }
      function showGate(msg) {
        document.getElementById("app").style.display = "none";
        document.getElementById("gate").style.display = "flex";
        var err = document.getElementById("gate-err");
        if (msg) {
          err.style.display = "block";
          err.textContent = msg;
        } else {
          err.style.display = "none";
          err.textContent = "";
        }
        setTimeout(function () {
          var el = document.getElementById("pw");
          if (el) { el.value = ""; el.focus(); }
        }, 50);
      }

      function load() {
        var t = token();
        if (!t) {
          showGate("Log ind i admin først (#admin), og åbn Log igen.");
          return;
        }
        if (!auditPw) {
          showGate();
          return;
        }
        document.getElementById("status").textContent = "Henter…";
        fetch("/api/admin/audit?limit=500", {
          headers: {
            Authorization: "Bearer " + t,
            "X-Audit-Password": auditPw
          }
        })
          .then(function (r) {
            if (r.status === 403) {
              clearPw();
              auditPw = "";
              throw new Error("Forkert adgangskode");
            }
            if (!r.ok) throw new Error("Auth/API fejl " + r.status);
            return r.json();
          })
          .then(function (data) {
            savePw(auditPw);
            showApp();
            all = (data && data.logs) || [];
            applyFilter();
          })
          .catch(function (e) {
            showGate(e.message || "Fejl");
          });
      }

      function tryUnlock() {
        auditPw = (document.getElementById("pw").value || "").trim();
        if (!auditPw) {
          showGate("Indtast adgangskode");
          return;
        }
        load();
      }

      document.getElementById("gate-ok").onclick = tryUnlock;
      document.getElementById("pw").addEventListener("keydown", function (e) {
        if (e.key === "Enter") tryUnlock();
      });
      document.getElementById("gate-cancel").onclick = function () {
        window.location.href = "/#admin";
      };
      // Phone-friendly: larger tap targets already; ensure gate input uses 16px (no iOS zoom)
      document.getElementById("reload").onclick = load;
      document.getElementById("q").oninput = applyFilter;
      document.getElementById("lock").onclick = function () {
        clearPw();
        auditPw = "";
        showGate();
      };

      auditPw = savedPw();
      if (auditPw) load();
      else showGate();

      setInterval(function () {
        if (auditPw && document.getElementById("app").style.display !== "none") load();
      }, 15000);
    })();
  </script>
</body>
</html>
