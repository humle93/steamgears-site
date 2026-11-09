(function () {
  var C = SGAP_NICHE, out = document.getElementById("sgap_niche_out");
  var planBtn = document.getElementById("sgap_niche_plan"), applyBtn = document.getElementById("sgap_niche_apply");
  var hint = document.getElementById("sgap_niche_hint");

  function post(action, data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fd.append("action", action);
    fd.append("nonce", C.nonce);
    return fetch(C.ajax, { method: "POST", credentials: "same-origin", body: fd }).then(function (r) { return r.json(); });
  }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
  }

  function table(rows, head) {
    var h = '<table class="widefat striped"><thead><tr>';
    head.forEach(function (c) { h += "<th>" + esc(c) + "</th>"; });
    h += "</tr></thead><tbody>";
    rows.forEach(function (r) {
      h += "<tr>";
      r.forEach(function (c) { h += "<td>" + esc(c) + "</td>"; });
      h += "</tr>";
    });
    return h + "</tbody></table>";
  }

  function pl(n, one, many) { return n + " " + (Number(n) === 1 ? one : many); }

  function niche() { return document.getElementById("sgap_niche").value.trim(); }
  function count() { return document.getElementById("sgap_niche_count").value; }
  function margin() { return document.getElementById("sgap_niche_margin").value; }

  function render(payload) {
    var p = payload.plan || payload, r = payload.result || {}, c = p.counts || {};
    var html = "<h3>" + esc(p.brand.suggested) + "</h3>";
    html += '<p class="sgap-sub">' + esc(p.brand.tagline) + " · " + esc(p.archetype.label) + " (" + esc(p.archetype.confidence) + " % match) · key <code>" + esc(p.plan_key) + "</code></p>";
    html += "<p>" + esc(pl(c.products, "product", "products")) + " · " + esc(pl(c.collections, "collection", "collections")) + " · " + esc(pl(c.pages, "page", "pages")) + " · " + esc(pl(c.owner_steps, "owner step", "owner steps")) + " · " + esc(c.owner_fields) + " fields marked [EJER] · ~" + esc(c.estimated_hours) + " h of setup left</p>";
    if (r.already_applied) {
      // The same plan key does not run twice: say so instead of claiming work that did not happen.
      html += '<div class="notice notice-info"><p>This plan was already applied on ' + esc(r.created_at) + " — nothing new was written. Roll back below to undo it, or change the count or the margin for a different plan.</p></div>";
    } else if (r.created) {
      var made = r.counts || {};
      html += '<div class="notice notice-success"><p>Created ' + esc(pl(made.products, "product", "product drafts")) + ", " + esc(pl(made.collections, "collection", "collections")) + ", " + esc(pl(made.pages, "page draft", "page drafts")) + (made.menus ? " and " + esc(pl(made.menus, "menu", "menus")) : "") + ". Roll back below if it is not right.</p></div>";
    }
    html += "<h4>Products</h4>" + table((p.products || []).map(function (x) {
      return [x.title, x.collection, x.price, x.cost, x.margin_percent + " %"];
    }), ["Product", "Collection", "Price", "Cost", "Margin"]);
    html += "<h4>Pages</h4><p>" + (p.pages || []).map(esc).join(" · ") + "</p>";
    html += "<h4>Menu</h4><p>" + (p.menu || []).map(esc).join(" · ") + "</p>";
    html += "<h4>What only the owner can do</h4>" + table((p.readiness || []).map(function (x) {
      return [x.step, x.why];
    }), ["Step", "Why"]);
    out.innerHTML = html;
    applyBtn.disabled = false;
  }

  planBtn.addEventListener("click", function () {
    if (niche().length < 2) { alert("Write a niche first."); return; }
    planBtn.disabled = true;
    hint.textContent = "Building the plan…";
    post("sgap_niche_run", { niche: niche(), count: count(), margin: margin() }).then(function (j) {
      planBtn.disabled = false;
      if (!j.success) { alert(j.data || "Could not build the plan"); hint.textContent = ""; return; }
      render(j.data);
      hint.textContent = "Plan only — nothing written. Press Create drafts to save it as drafts.";
    }).catch(function () {
      planBtn.disabled = false;
      hint.textContent = "Could not reach the site. Nothing was written — try again.";
    });
  });

  applyBtn.addEventListener("click", function () {
    applyBtn.disabled = true;
    hint.textContent = "Creating drafts…";
    post("sgap_niche_run", { niche: niche(), count: count(), margin: margin(), apply: 1 }).then(function (j) {
      if (!j.success) { alert(j.data || "Could not create the drafts"); applyBtn.disabled = false; hint.textContent = ""; return; }
      render(j.data);
      hint.textContent = (j.data.result && j.data.result.already_applied)
        ? "This plan is already in the shop — nothing was written again."
        : "Drafts created. Review them under Products, then publish — or roll back.";
    }).catch(function () {
      applyBtn.disabled = false;
      hint.textContent = "Could not reach the site. Nothing new is known to exist — check Runs before trying again.";
    });
  });

  Array.prototype.forEach.call(document.querySelectorAll(".sgap-niche-undo"), function (b) {
    b.addEventListener("click", function () {
      if (!confirm("Delete the drafts this run created? Published items are kept.")) return;
      b.disabled = true;
      post("sgap_niche_rollback", { plan_key: b.dataset.key }).then(function (j) {
        if (!j.success) { alert(j.data || "Could not roll back"); b.disabled = false; return; }
        location.reload();
      });
    });
  });
})();
