(() => {
  'use strict';
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const theme = $('#theme-toggle');
  function applyTheme(mode){document.documentElement.dataset.theme=mode;theme?.setAttribute('aria-pressed',String(mode==='dark'));if(theme){theme.textContent=mode==='dark'?'☀':'◐';theme.setAttribute('aria-label',mode==='dark'?'Switch to light theme':'Switch to dark theme');}}
  let savedTheme;try{savedTheme=localStorage.getItem('customies-theme');}catch{}
  applyTheme(savedTheme || (matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'));
  theme?.addEventListener('click',()=>{const mode=document.documentElement.dataset.theme==='dark'?'light':'dark';applyTheme(mode);try{localStorage.setItem('customies-theme',mode);}catch{}});
  const menu = $('.menu-toggle');
  menu?.addEventListener('click', () => {
    const open = !$('#navigation').classList.contains('open');
    $('#navigation').classList.toggle('open', open);
    menu.setAttribute('aria-expanded', String(open));
  });
  async function copyText(text, button) {
    const old = button.textContent;
    try {
      await navigator.clipboard.writeText(text);
      button.textContent = 'Copied';
    } catch {
      // Local previews or older browsers may lack the clipboard API.
      const area = document.createElement('textarea');
      area.value = text; area.style.position = 'fixed'; area.style.left = '-10000px';
      document.body.append(area); area.select();
      const ok = document.execCommand('copy'); area.remove();
      button.textContent = ok ? 'Copied' : 'Copy unavailable';
    }
    setTimeout(() => { button.textContent = old; }, 1600);
  }
  $$('[data-copy]').forEach(b => b.addEventListener('click', () => copyText(b.dataset.copy, b)));
  $$('.codehilite').forEach(block => {
    const b = document.createElement('button'); b.className = 'copy-code'; b.textContent = 'Copy code';
    b.type = 'button'; b.addEventListener('click', () => copyText($('pre', block).textContent, b));
    block.append(b);
  });
  $$('dialog').forEach(dialog => {
    $('[data-close]', dialog)?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', e => { if (e.target === dialog) {
      const r = dialog.getBoundingClientRect();
      if(e.clientX<r.left || e.clientX>r.right || e.clientY<r.top || e.clientY>r.bottom) dialog.close();
    }});
  });
  let searchIndex;
  const searchDialog = $('#search-dialog');
  async function openSearch() {
    if (!searchDialog.open) searchDialog.showModal();
    $('#site-search').focus();
    try {
      searchIndex ??= fetch(`assets/search-${document.body.dataset.scope || 'customies'}.json`).then(r => { if (!r.ok) throw Error(); return r.json(); });
      await searchIndex;
    } catch { $('#search-status').textContent = 'Search could not load. Use the navigation or try reloading.'; searchIndex = null; }
  }
  $('.search-trigger')?.addEventListener('click', openSearch);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
  });
  $('#site-search')?.addEventListener('input', async e => {
    const query = e.target.value.toLowerCase().trim();
    const results = $('#search-results'); results.replaceChildren();
    if (!query) { $('#search-status').textContent = 'Type to find guides, components and examples.'; return; }
    const terms = query.split(/\s+/);
    let index;
    try { index = await searchIndex; if (!index) return; } catch { return; }
    const matches = index.map(record => ({ ...record, score: terms.reduce((score, word) => score + (record.title.toLowerCase().includes(word) ? 6 : 0) + (record.text.toLowerCase().includes(word) ? 1 : 0), 0) }))
      .filter(r => terms.every(t => (r.title + ' ' + r.text).toLowerCase().includes(t)))
      .sort((a, b) => b.score - a.score).slice(0, 16);
    $('#search-status').textContent = matches.length ? `${matches.length} results` : 'No matches. Try another word, such as light, armor or house.';
    matches.forEach(record => {
      const link = document.createElement('a'); link.href = record.url;
      const title = document.createElement('strong'); title.textContent = record.title;
      const snippet = document.createElement('span');
      const at = record.text.toLowerCase().indexOf(terms[0]);
      snippet.textContent = (at > 45 ? '… ' : '') + record.text.slice(Math.max(0, at - 45), Math.max(0, at - 45) + 155) + '…';
      link.append(title, snippet); results.append(link);
    });
  });
  function revealHash(){
    let id;try{id=decodeURIComponent(location.hash.slice(1));}catch{return;}
    const target=document.getElementById(id);
    if(target?.matches('.component-example')){target.open=true;target.scrollIntoView({block:'start'});}
  }
  window.addEventListener('hashchange',revealHash);revealHash();
  const filter = $('#component-filter');
  filter?.addEventListener('input', () => {
    const q = filter.value.toLowerCase().trim();
    $$('tbody tr').forEach(row => row.hidden = !row.textContent.toLowerCase().includes(q));
    $$('.component-example').forEach(row => row.hidden = !row.textContent.toLowerCase().includes(q));
  });
  const gallery = $('.asset-grid');
  if (gallery) {
    const empty = document.createElement('p'); empty.className = 'no-results'; empty.textContent = 'No specimens match. Try a different name or category.'; empty.hidden = true; gallery.append(empty);
    const update = () => {
      const q = $('#gallery-query').value.toLowerCase().trim(); const kind = $('#gallery-kind').value;
      let count = 0;
      $$('.asset-card').forEach(card => {
        const category = kind === 'all' || card.dataset.kind === kind || (kind === 'lab' && card.dataset.lab === 'true') || (kind === 'playable' && card.dataset.lab === 'false');
        card.hidden = !(category && card.dataset.search.replaceAll('_', ' ').includes(q.replaceAll('_', ' ')));
        if (!card.hidden) count++;
      });
      $('#gallery-status').textContent = `${count} ${count === 1 ? 'specimen' : 'specimens'}`; empty.hidden = count > 0;
    };
    $('#gallery-query').addEventListener('input', update); $('#gallery-kind').addEventListener('change', update);
  }
  $('#pause-animation')?.addEventListener('click', e => {
    const pause = document.body.classList.toggle('animation-paused');
    e.currentTarget.setAttribute('aria-pressed', String(pause)); e.currentTarget.textContent = pause ? 'Resume animations' : 'Pause animations';
  });

  let modelData;
  function getModels() {
    modelData ??= fetch('assets/models.json').then(r => { if (!r.ok) throw Error('Model data unavailable'); return r.json(); });
    return modelData;
  }
  const colors = {
    cottage_floor: '#b18d60', cottage_roof: '#518980', cottage_timber: '#936844', cottage_window: '#86b9b4', cottage_stone: '#8f919c',
    market_floor: '#ac9167', market_canopy: '#ce91a9', market_counter: '#b78a58', gateway_stone: '#688ca0',
    prism_cottage: '#8cbbac', market_hall: '#c8a29b', grand_gateway: '#6e9fbc'
  };
  function colorFor(texture, powered) {
    const name = texture.replace('showcase_', '');
    if (powered) return '#edc579';
    if (colors[name]) return colors[name];
    let hash = 0; for (const c of name) hash = (hash * 31 + c.charCodeAt(0)) | 0;
    return `hsl(${140 + Math.abs(hash % 48)} 28% ${43 + Math.abs(hash % 16)}%)`;
  }
  async function initViewer(container) {
    const canvas = $('canvas', container); const ctx = canvas.getContext('2d');
    if (!ctx) { $('.viewer-caption', container).textContent = 'Canvas is unavailable. Open the geometry sheet in the gallery.'; return; }
    let model;
    try { model = (await getModels())[container.dataset.model]; if (!model) throw Error(); }
    catch { $('.viewer-caption', container).textContent = 'Preview could not load. Original geometry is linked in the gallery.'; return; }
    let yaw = -.2; let down = false; let last = 0;
    const powered = $('[data-power]', container); powered.disabled = model.mode !== 'powered';
    canvas.tabIndex = 0; canvas.setAttribute('aria-label', `Geometry preview of ${container.dataset.model}; use left and right arrow keys to rotate`);
    const rot = p => [p[0] * Math.cos(yaw) + p[2] * Math.sin(yaw), p[1], -p[0] * Math.sin(yaw) + p[2] * Math.cos(yaw)];
    const project = p => [(p[0] - p[2]) * .866, (p[0] + p[2]) * .45 - p[1]];
    function draw() {
      if (!container.isConnected) return;
      const width = canvas.clientWidth, height = canvas.clientHeight; if (!width || !height) return;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      if (canvas.width !== Math.round(width * dpr) || canvas.height !== Math.round(height * dpr)) { canvas.width = Math.round(width * dpr); canvas.height = Math.round(height * dpr); }
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, width, height);
      const faces = [];
      for (const cube of model.cubes) {
        const [x,y,z] = cube.origin, [w,h,d] = cube.size, xx=x+w, yy=y+h, zz=z+d;
        const all = [
          {p:[[x,yy,z],[xx,yy,z],[xx,yy,zz],[x,yy,zz]],n:[0,1,0],shade:1.1},
          {p:[[xx,y,z],[xx,y,zz],[xx,yy,zz],[xx,yy,z]],n:[1,0,0],shade:.75},
          {p:[[x,y,zz],[xx,y,zz],[xx,yy,zz],[x,yy,zz]],n:[0,0,1],shade:.9},
          {p:[[x,y,z],[x,y,zz],[x,yy,zz],[x,yy,z]],n:[-1,0,0],shade:.75},
          {p:[[x,y,z],[xx,y,z],[xx,yy,z],[x,yy,z]],n:[0,0,-1],shade:.9}
        ];
        for (const face of all) {
          const normal = rot(face.n); if(normal[0]+normal[2]+normal[1] <= .01) continue;
          const points = face.p.map(rot);
          faces.push({points:points.map(project),depth:points.reduce((s,p)=>s+p[0]+p[2]+p[1]*.4,0)/4,color:colorFor(cube.texture,powered.checked && Boolean(cube.active_texture)),shade:face.shade});
        }
      }
      if (!faces.length) return;
      const points=faces.flatMap(f=>f.points), xs=points.map(p=>p[0]),ys=points.map(p=>p[1]);
      const minX=Math.min(...xs),maxX=Math.max(...xs),minY=Math.min(...ys),maxY=Math.max(...ys);
      const scale=Math.min((width-70)/(maxX-minX),(height-75)/(maxY-minY));
      const middleX=(minX+maxX)/2,middleY=(minY+maxY)/2;
      for (const face of faces.sort((a,b)=>a.depth-b.depth)) {
        ctx.beginPath(); face.points.forEach((p,i)=>ctx[i?'lineTo':'moveTo'](width/2+(p[0]-middleX)*scale,height/2+(p[1]-middleY)*scale+5)); ctx.closePath();
        ctx.fillStyle=face.color; ctx.fill();
        ctx.fillStyle=face.shade<1?`rgba(12,40,37,${1-face.shade})`:'rgba(255,255,240,.13)'; ctx.fill();
        ctx.strokeStyle='rgba(22,57,45,.35)'; ctx.lineWidth=.65; ctx.stroke();
      }
    }
    canvas.addEventListener('pointerdown',e=>{down=true;last=e.clientX;canvas.setPointerCapture(e.pointerId);});
    canvas.addEventListener('pointermove',e=>{if(down){yaw+=(e.clientX-last)*.012;last=e.clientX;draw();}});
    canvas.addEventListener('pointerup',()=>down=false);canvas.addEventListener('pointercancel',()=>down=false);
    canvas.addEventListener('keydown',e=>{if(['ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();yaw+=e.key==='ArrowLeft'?-.2:.2;draw();}});
    $$('[data-rotate]',container).forEach(b=>b.addEventListener('click',()=>{yaw+=Number(b.dataset.rotate)*Math.PI/4;draw();}));
    $('[data-reset]',container).addEventListener('click',()=>{yaw=-.2;powered.checked=false;draw();});
    powered.addEventListener('change',draw);
    const observer = new ResizeObserver(() => { if(container.isConnected)draw();else observer.disconnect(); });observer.observe(canvas);draw();
  }
  $$('.model-viewer').forEach(initViewer);
  $$('[data-view]').forEach(b=>b.addEventListener('click',async()=>{
    const dialog=$('#model-dialog'); $('#model-title').textContent=b.dataset.view.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
    const holder=$('#model-content'); holder.replaceChildren();
    const container=document.createElement('div');container.className='model-viewer';container.dataset.model=b.dataset.view;
    container.innerHTML='<canvas width="760" height="560" role="img"></canvas><div class="viewer-toolbar"><button data-rotate="-1" aria-label="Rotate model left">↶</button><button data-rotate="1" aria-label="Rotate model right">↷</button><button data-reset>Reset</button><label><input type="checkbox" data-power> Powered</label></div><p class="viewer-caption">Drag or use arrow keys to rotate · Authored geometry, not the game renderer</p>';
    holder.append(container);dialog.showModal();initViewer(container);
  }));
})();
