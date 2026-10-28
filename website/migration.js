(()=>{
  'use strict';
  const form=document.querySelector('[data-migration]');if(!form)return;
  const status=form.querySelector('[role=status]'),fields=form.elements,result=form.querySelector('[data-migration-result]'),fileSelect=form.querySelector('[data-migration-files]'),code=form.querySelector('[data-migration-code]'),summary=form.querySelector('[data-migration-summary]'),diagnostics=form.querySelector('[data-migration-review]'),submit=form.querySelector('[type=submit]'),cancel=form.querySelector('[data-migration-cancel]'),image=form.querySelector('[data-migration-image]');
  let worker=null,files={},imageUrl=null,timeout=null,sequence=0;
  const sample=`import { world } from '@minecraft/server';

const WAND = 'minecraft:stick';
world.afterEvents.itemUse.subscribe((event) => {
  if (event.itemStack.typeId !== WAND) return;
  const player = event.source;
  player.addEffect('speed', 200, { amplifier: 1, showParticles: true });
  player.sendMessage('Speed for ten seconds!');
  player.onScreenDisplay.setActionBar('Wand activated');
});
`;
  function end(){clearTimeout(timeout);worker?.terminate();worker=null;submit.disabled=false;cancel.hidden=true;form.removeAttribute('aria-busy');}
  function fileView(){
    if(imageUrl){URL.revokeObjectURL(imageUrl);imageUrl=null;}image.hidden=true;
    const value=files[fileSelect.value],copy=form.querySelector('[data-migration-copy]');
    if(typeof value==='string'){code.textContent=value;copy.disabled=false;return;}
    if(/\.(json|js|md|txt|lang|yml|php|mcfunction)$/i.test(fileSelect.value)){try{code.textContent=new TextDecoder('utf-8',{fatal:true}).decode(value);copy.disabled=false;return;}catch{}}
    copy.disabled=true;code.textContent='Binary asset · '+(value?.byteLength??0).toLocaleString()+' bytes · preserved unchanged in the ZIP.';
    if(/\.png$/i.test(fileSelect.value)){imageUrl=URL.createObjectURL(new Blob([value],{type:'image/png'}));image.src=imageUrl;image.hidden=false;}
  }
  fileSelect.addEventListener('change',fileView);
  form.querySelector('[data-migration-example]').addEventListener('click',()=>{fields.upload.value='';fields.source.value=sample;fields.sourceKind.value='js';status.textContent='Example loaded. Choose Translate project to create PHP.';});
  form.querySelector('[data-migration-clear]').addEventListener('click',()=>{fields.upload.value='';status.textContent='Upload cleared; the editor will be used.';});
  cancel.addEventListener('click',()=>{sequence++;end();status.textContent='Conversion cancelled. Your editor input is retained.';});
  form.querySelector('[data-migration-copy]').addEventListener('click',async()=>{try{await navigator.clipboard.writeText(code.textContent);status.textContent='Copied '+fileSelect.value;}catch{status.textContent='Select the code and use your device’s copy action.';}});
  form.querySelector('[data-migration-download]').addEventListener('click',()=>{
    const bytes=window.CustomiesAuthoring.zip(files),url=URL.createObjectURL(new Blob([bytes],{type:'application/zip'})),a=document.createElement('a');a.href=url;a.download='customies-migration.zip';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
  form.addEventListener('submit',async event=>{
    event.preventDefault();end();result.hidden=true;files={};status.classList.remove('has-error');const current=++sequence;
    try{
      window.CustomiesAuthoring.className(fields.pluginName.value);
      const file=fields.upload.files[0];let bytes,name;
      if(file){if(file.size>32*1024*1024)throw Error('Upload at most 32 MiB.');name=file.name;bytes=await file.arrayBuffer();}
      else{name=fields.sourceKind.value==='json'?'definition.json':'main.js';if(fields.source.value.length>131072)throw Error('Paste at most 128 KiB.');bytes=new TextEncoder().encode(fields.source.value).buffer;}
      if(current!==sequence)return;
      submit.disabled=true;cancel.hidden=false;form.setAttribute('aria-busy','true');status.textContent='Starting local conversion…';
      worker=new Worker('assets/migration-worker.js');
      timeout=setTimeout(()=>{sequence++;end();status.textContent='Conversion exceeded 30 seconds. Split the pack into smaller projects and retry.';status.classList.add('has-error');},30000);
      worker.onerror=()=>{end();status.textContent='The local converter could not start. Reload the page or check browser worker support.';status.classList.add('has-error');};
      worker.onmessage=message=>{
        if(current!==sequence)return;
        if(message.data.stage){status.textContent=message.data.stage;return;}
        end();if(message.data.error){status.textContent=message.data.error;status.classList.add('has-error');return;}
        const output=message.data.output;files=output.files;const report=output.report;fileSelect.replaceChildren();
        const paths=Object.keys(files).sort((a,b)=>a.localeCompare(b));for(const path of paths){const option=document.createElement('option');option.value=path;option.textContent=path;fileSelect.append(option);}fileSelect.value='plugin/src/MigrationHooks.php';fileView();
        summary.replaceChildren();for(const [count,label] of [[report.definitions.length,'definitions'],[report.translated_callbacks,'translated callbacks'],[report.manual_callbacks,'manual script entries'],[report.assets.length,'asset files']]){const cell=document.createElement('div'),number=document.createElement('strong'),text=document.createElement('span');number.textContent=count;text.textContent=label;cell.append(number,text);summary.append(cell);}
        diagnostics.replaceChildren();const rows=[...report.scripts,...report.review,...report.definitions.flatMap(d=>d.review.map(message=>({file:d.file,status:'review',message})))];
        for(const row of rows){const item=document.createElement('li'),badge=document.createElement('span'),text=document.createElement('p');badge.className='migration-badge '+(row.status==='translated'?'is-ready':'needs-review');badge.textContent=row.status==='translated'?'Translated':'Review';text.textContent=row.file+(row.line?':'+row.line:'')+' — '+row.message;item.append(badge,text);diagnostics.append(item);}
        result.hidden=false;status.textContent=report.translated_callbacks||report.definitions.length?'Migration source is ready. Review the report before building the plugin.':'Manual migration scaffold is ready. No supported behavior or definitions were translated.';
      };
      worker.postMessage({name,bytes,pluginName:fields.pluginName.value},[bytes]);
    }catch(error){end();status.textContent=error.message;status.classList.add('has-error');}
  });
  fields.source.value=sample;
  if(typeof Worker!=='function'){status.textContent='This converter requires browser web-worker support. Your input is retained locally; use a current browser.';return;}
  submit.disabled=false;
})();
