(function(){
  var out = document.getElementById('sgap-seo-site-out');
  if(!out){ return; }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function post(task, limit){
    var b = new URLSearchParams();
    b.append('action','sgap_seo_site'); b.append('task',task); b.append('nonce',SGAP_SEO.nonce); b.append('limit',limit||8);
    return fetch(SGAP_SEO.ajax, {method:'POST', credentials:'same-origin', body:b}).then(function(r){ return r.json(); });
  }
  function pills(ids, kind){ return (ids||[]).map(function(id){ return '<span class="sgap-pill '+kind+'">'+esc(id)+'</span>'; }).join(' '); }
  function render(d){
    var t = d.totals||{};
    var html = '<div class="sgap-card"><h2>Site score: '+esc(d.score)+' / 100</h2>'
      + '<p>'+esc(t.items||0)+' URLs scanned &middot; '+esc(t.needs_work||0)+' need work &middot; '+esc(t.fail||0)+' failing checks &middot; '+esc(t.warn||0)+' warnings</p>';
    var site = (d.site||[]).map(function(s){ return '<li>'+(s.status==='pass'?'&#10003; ':(s.status==='fail'?'&#10007; ':'&#8226; '))+esc(s.label)+': '+esc(s.detail)+'</li>'; }).join('');
    if(site){ html += '<h3>Whole-domain checks</h3><ul style="list-style:disc;margin-left:18px">'+site+'</ul>'; }
    html += '<table class="widefat striped" style="margin-top:10px"><thead><tr><th style="width:60px">Score</th><th style="width:38%">Page</th><th>Missing or weak</th></tr></thead><tbody>';
    (d.items||[]).forEach(function(it){
      html += '<tr><td><b>'+esc(it.score)+'</b></td><td><a href="'+esc(it.url)+'" target="_blank" rel="noopener">'+esc(it.title)+'</a><br><span class="description">'+esc(it.type)+'</span></td><td>'
        + ((it.fails&&it.fails.length)||(it.warns&&it.warns.length) ? pills(it.fails,'bad')+' '+pills(it.warns,'warn') : '<span class="description">no issues</span>')
        + '</td></tr>';
    });
    html += '</tbody></table></div>';
    return html;
  }
  function renderFix(d){
    var html = '<div class="sgap-card"><h2>'+esc(d.fixed||0)+' page(s) got new meta</h2>';
    if(d.errors && d.errors.length){ html += '<p class="description">'+d.errors.map(esc).join('<br>')+'</p>'; }
    if(d.items && d.items.length){
      html += '<ul style="list-style:disc;margin-left:18px">';
      d.items.forEach(function(it){ html += '<li><b>'+esc(it.title)+'</b>: '+esc(it.meta_title)+' &mdash; '+esc(it.meta_description)+'</li>'; });
      html += '</ul>';
    }
    if(d.remaining){ html += '<p>'+esc(d.remaining)+' page(s) still need meta &mdash; press the button again for the next batch.</p>'; }
    if(d.message){ html += '<p class="description">'+esc(d.message)+'</p>'; }
    html += '</div>';
    return html;
  }
  document.querySelectorAll('.sgap-seo-site-form button[data-task]').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var form = btn.closest('form');
      var limit = form && form.querySelector('[name=limit]') ? form.querySelector('[name=limit]').value : 8;
      var task = btn.getAttribute('data-task') === 'fix' ? 'fix' : 'scan';
      var box = document.createElement('div');
      box.className = 'sgap-card';
      box.innerHTML = '<h2><span class="dashicons dashicons-update sgap-spin"></span>'+(task==='fix'?'Scanning and writing meta':'Scanning every URL')+'</h2>'
        + '<div class="sgap-progress"><progress></progress><p>Reading titles, meta and content\u2026</p></div>';
      out.prepend(box);
      var slow = setTimeout(function(){ var p = box.querySelector('p'); if(p){ p.textContent = 'Still working \u2014 a full scan reads every published page.'; } }, 12000);
      post(task, limit).then(function(j){
        clearTimeout(slow); box.remove();
        if(!j || !j.success){
          var err = document.createElement('div'); err.className = 'sgap-card sgap-bad';
          err.innerHTML = '<h2>Scan failed</h2><p>'+esc((j && j.data) || 'No answer')+'</p>'; out.prepend(err); return;
        }
        if(j.data && j.data.fix){ out.insertAdjacentHTML('afterbegin', renderFix(j.data.fix)); out.insertAdjacentHTML('afterbegin', render(j.data.scan)); }
        else { out.insertAdjacentHTML('afterbegin', render(j.data)); }
      }).catch(function(err){
        clearTimeout(slow); box.remove();
        var e2 = document.createElement('div'); e2.className = 'sgap-card sgap-bad';
        e2.innerHTML = '<h2>Could not reach the site</h2><p>'+esc(err.message)+'</p>'; out.prepend(e2);
      });
    });
  });
})();
