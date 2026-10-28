(function(){
  'use strict';
  const A=window.CustomiesAuthoring;if(!A)return;
  const pretty=v=>JSON.stringify(v,null,2)+'\n';
  const samples={
    slab:{collision:{origin:[-8,0,-8],size:[16,8,16]},selection:{origin:[-8,0,-8],size:[16,8,16]}},
    stair:{collision:[{min:[0,0,0],max:[16,8,16]},{min:[0,8,0],max:[8,16,8]}],selection:true},
    pillar:{collision:{min:[4,0,4],max:[12,24,12]},selection:{min:[4,0,4],max:[12,24,12]}},
    ghost:{collision:false,selection:{origin:[-6,0,-6],size:[12,12,12]}}
  };
  function scene(canvas){
    const ctx=canvas.getContext('2d');let yaw=-.68,pitch=.55,zoom=1,layers=[];
    function project(p){const [x,y,z]=[p[0]-.5,p[1]-.5,p[2]-.5],u=x*Math.cos(yaw)+z*Math.sin(yaw),v=z*Math.cos(yaw)-x*Math.sin(yaw),dpr=Math.min(window.devicePixelRatio||1,2),scale=Math.min(190,canvas.width/dpr*.38)*dpr*zoom;return [canvas.width/2+u*scale,canvas.height*.55+(-y*Math.cos(pitch)+v*Math.sin(pitch))*scale,y*Math.sin(pitch)+v*Math.cos(pitch)];}
    function draw(){
      const dpr=Math.min(window.devicePixelRatio||1,2),width=Math.max(180,canvas.clientWidth),height=360;canvas.width=width*dpr;canvas.height=height*dpr;
      // Draw in CSS pixels so high-DPI displays retain the same camera framing.
      const realWidth=canvas.width,realHeight=canvas.height;
      const oldProject=project;
      ctx.setTransform(1,0,0,1,0,0);ctx.clearRect(0,0,realWidth,realHeight);
      ctx.save();ctx.scale(dpr,dpr);
      function p(v){const q=oldProject(v);return [q[0]/dpr,q[1]/dpr,q[2]];}
      function line(points,color,width=1){ctx.beginPath();points.forEach((v,i)=>{const q=p(v);i?ctx.lineTo(q[0],q[1]):ctx.moveTo(q[0],q[1]);});ctx.strokeStyle=color;ctx.lineWidth=width;ctx.stroke();}
      const faces=[];
      for(let i=0;i<=4;i++){line([[i/4,0,0],[i/4,0,1]],'#718a8240');line([[0,0,i/4],[1,0,i/4]],'#718a8240');}
      for(const layer of layers)for(const b of layer.boxes){
        const [x,y,z,X,Y,Z]=b,v=[[x,y,z],[X,y,z],[X,y,Z],[x,y,Z],[x,Y,z],[X,Y,z],[X,Y,Z],[x,Y,Z]];
        for(const indices of [[0,1,2,3],[4,7,6,5],[0,4,5,1],[1,5,6,2],[2,6,7,3],[3,7,4,0]]){const points=indices.map(i=>v[i]);faces.push({points,depth:points.reduce((n,q)=>n+p(q)[2],0)/4,color:layer.color});}
      }
      faces.sort((a,b)=>b.depth-a.depth);
      for(const f of faces){ctx.beginPath();f.points.forEach((q,i)=>{const s=p(q);i?ctx.lineTo(s[0],s[1]):ctx.moveTo(s[0],s[1]);});ctx.closePath();ctx.fillStyle=f.color+'20';ctx.fill();ctx.strokeStyle=f.color;ctx.lineWidth=1.5;ctx.stroke();}
      const axes=[[[1.25,0,0],'X · E','#ca715b'],[[0,1.5,0],'Y · Up','#3e9a7d'],[[0,0,1.25],'Z · S','#648dcb'],[[.5,0,-.35],'N','#718a82']];ctx.font='600 12px system-ui';
      for(const [end,label,color] of axes){line([[0,0,0],end],color,1.3);const q=p(end);ctx.fillStyle=color;ctx.fillText(label,q[0]+5,q[1]-5);}
      ctx.restore();
    }
    let pointer=null;canvas.addEventListener('pointerdown',e=>{pointer=[e.clientX,e.clientY];canvas.setPointerCapture(e.pointerId);});
    canvas.addEventListener('pointermove',e=>{if(!pointer)return;yaw+=(e.clientX-pointer[0])*.01;pitch=Math.max(.12,Math.min(1.3,pitch+(e.clientY-pointer[1])*.008));pointer=[e.clientX,e.clientY];draw();});
    canvas.addEventListener('pointerup',()=>pointer=null);canvas.addEventListener('pointercancel',()=>pointer=null);
    canvas.addEventListener('dblclick',()=>{yaw=-.68;pitch=.55;zoom=1;draw();});
    canvas.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','+','=','-'].includes(e.key))return;e.preventDefault();if(e.key==='ArrowLeft')yaw-=.15;if(e.key==='ArrowRight')yaw+=.15;if(e.key==='ArrowUp')pitch=Math.min(1.3,pitch+.1);if(e.key==='ArrowDown')pitch=Math.max(.12,pitch-.1);if(['+','='].includes(e.key))zoom=Math.min(1.6,zoom+.1);if(e.key==='-')zoom=Math.max(.5,zoom-.1);draw();});
    new ResizeObserver(draw).observe(canvas);
    return data=>{layers=data;draw();};
  }
  function download(data,name,type){const url=URL.createObjectURL(new Blob([data],{type})),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
  document.querySelectorAll('[data-authoring]').forEach(async form=>{
    const kind=form.dataset.authoring,status=form.querySelector('.authoring-status'),result=form.querySelector('.authoring-result'),fileSelect=form.querySelector('[data-files]'),code=form.querySelector('[data-code]'),report=form.querySelector('[data-report]'),submit=form.querySelector('[type=submit]');
    const fields=form.elements,source=fields.namedItem('source'),draw=form.querySelector('canvas')?scene(form.querySelector('canvas')):null,initial=source?.value;let files={},fixtures;
    function show(output,message){files=output.files;fileSelect.replaceChildren();for(const path of Object.keys(files)){const o=document.createElement('option');o.value=path;o.textContent=path;fileSelect.append(o);}code.textContent=files[fileSelect.value];report.replaceChildren();
      if(output.report){const summary=document.createElement('p');summary.textContent=(output.report.state_combinations??1)+' state combination(s) · '+(output.report.converted_components??0)+' base components adapted';report.append(summary);for(const note of output.report.review??[]){const p=document.createElement('p');p.textContent=note;report.append(p);}}
      result.hidden=false;status.textContent=message;status.classList.remove('has-error');
    }
    function run(){try{
      const options=Object.fromEntries(new FormData(form));
      if(kind==='generator'){const out=A.generate(options);show(out,'Source generated. Review the files and required assets before installing.');}
      if(kind==='converter'){const out=A.convert(source.value,options);show(out,'Converted '+out.report.kind+' '+out.report.identifier+'. Review '+out.report.review.length+' integration note(s).');}
      if(kind==='shapes'){const out=A.visualize(source.value,options.direction);draw([{boxes:out.collision,color:'#269d99'},{boxes:out.selection,color:'#d99a35'}]);show({files:{'collision.php':'// Local server collision bounds; add in recalculateCollisionBoxes()\nreturn ['+out.php.join(', ')+'];\n','shape.json':pretty(out)}},out.collision.length+' collision box(es), '+out.selection.length+' selection box(es). Bounds use local block units.');}
      if(kind==='rotation'){
        const f=fixtures.find(f=>f.shape===options.shape&&f.half===options.half&&f.direction===options.direction);if(!f)throw Error('No tested fixture for this selection.');
        const occupied=boxes=>[.25,.75].flatMap(x=>[.25,.75].flatMap(y=>[.25,.75].map(z=>boxes.some(([a,b,c,d,e,f])=>x>a&&x<d&&y>b&&y<e&&z>c&&z<f))));
        const ok=JSON.stringify(occupied(f.client))===JSON.stringify(occupied(f.server))&&JSON.stringify(occupied(f.server))===JSON.stringify(occupied(f.vanilla));
        const colors={client:'#269d99',server:'#d99a35',vanilla:'#7973d4'};draw(['client','server','vanilla'].filter(k=>options.layer==='all'||options.layer===k).map(k=>({boxes:f[k],color:colors[k]})));
        show({files:{'tested-shape.json':pretty(f),'collision.php':'return ['+f.server.map(b=>'new AxisAlignedBB('+b.join(', ')+')').join(', ')+'];\n'}},(ok?'MATCH':'MISMATCH')+' · '+options.shape+' / '+options.half+' / '+options.direction+' · client mesh, PHP and vanilla occupancy.');
      }
    }catch(e){result.hidden=true;files={};draw?.([]);status.textContent=e.message;status.classList.add('has-error');}}
    fileSelect.addEventListener('change',()=>code.textContent=files[fileSelect.value]??'');
    form.addEventListener('submit',e=>{e.preventDefault();run();});
    form.querySelector('[data-authoring-copy]').addEventListener('click',async()=>{try{await navigator.clipboard.writeText(files[fileSelect.value]);status.textContent='Copied '+fileSelect.value;}catch{status.textContent='Select the code and copy it using your device’s copy action.';}});
    form.querySelector('[data-download]').addEventListener('click',()=>download(A.zip(files),'customies-'+kind+'.zip','application/zip'));
    function updatePresets(){const item=fields.kind.value==='item',preset=fields.preset;preset.replaceChildren();for(const [key,label] of (item?[['basic','Basic item'],['food','Food · restores hunger'],['durable','Durable · attack damage'],['armor','Armor · helmet']]:[['cube','Solid cube'],['cardinal','Four-direction block'],['slab','Merging slab']])){const o=document.createElement('option');o.value=key;o.textContent=label;preset.append(o);}form.querySelector('[data-stack]').hidden=!item;fields.stack.value=64;}
    if(kind==='generator'){fields.kind.addEventListener('change',()=>{updatePresets();run();});fields.preset.addEventListener('change',()=>{if(['durable','armor'].includes(fields.preset.value))fields.stack.value=1;run();});}
    if(kind==='shapes')fields.example.addEventListener('change',()=>{source.value=pretty(samples[fields.example.value]);run();});
    if(kind==='converter')fields.example.addEventListener('change',()=>{
      if(fields.example.value==='block')source.value=initial;else{const armor=fields.example.value==='armor';source.value=pretty({format_version:'1.26.60','minecraft:item':{description:{identifier:armor?'mycontent:helmet':'mycontent:berry'},components:armor?{'minecraft:icon':'mycontent_helmet','minecraft:max_stack_size':1,'minecraft:durability':{max_durability:256},'minecraft:wearable':{slot:'slot.armor.head',protection:2}}:{'minecraft:icon':'mycontent_berry','minecraft:food':{nutrition:4,saturation_modifier:'normal'},'minecraft:use_animation':'eat'}}});}run();
    });
    form.querySelector('[data-reset]').addEventListener('click',()=>{form.reset();if(source)source.value=initial;if(kind==='generator')updatePresets();run();});
    if(kind==='rotation')try{const response=await fetch('assets/shape-fixtures.json');if(!response.ok)throw Error('Fixture download failed.');fixtures=await response.json();}catch{status.textContent='Unable to load the tested stair fixtures. Reload this page to retry.';return;}
    form.querySelectorAll('select').forEach(select=>{if(select===fileSelect||select.name==='example'||(kind==='generator'&&['kind','preset'].includes(select.name)))return;select.addEventListener('change',run);});
    submit.disabled=false;run();
  });
})();
