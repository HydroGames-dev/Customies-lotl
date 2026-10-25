(() => {
  'use strict';
  const api=globalThis.CustomiesTools;
  let wrapperData;
  document.querySelectorAll('[data-tool]').forEach(form=>{
    const button=form.querySelector('[type=submit]'),status=form.querySelector('.tool-status'),result=form.querySelector('.tool-result'),pre=result.querySelector('pre'),files=result.querySelector('.tool-files');
    if(!api){status.textContent='Tool could not load. Reload or use the documentation.';return;}
    button.disabled=false;status.textContent='Ready. Inputs stay in this browser.';
    form.addEventListener('submit',async event=>{
      event.preventDefault();button.disabled=true;status.textContent='Checking…';files.replaceChildren();
      const values=Object.fromEntries(new FormData(form));
      try{
        let output;
        switch(form.dataset.tool){
          case 'states':output=api.states(values.states);break;
          case 'boxes':output=api.boxes(values.origin,values.size,values.units,values.direction);break;
          case 'identifiers':output=api.identifiers(values.identifiers);break;
          case 'components':
            wrapperData??=fetch('assets/wrapper-api.json').then(r=>{if(!r.ok)throw Error('Wrapper data unavailable. Reload and try again.');return r.json();});
            output=api.inspect(values.components,values.kind,await wrapperData);break;
          case 'starter':
            output=api.starter({...values,stack:Number(values.stack)});
            for(const [name,content] of Object.entries(output)){
              const section=document.createElement('section'),title=document.createElement('h3'),code=document.createElement('pre'),download=document.createElement('button');
              title.textContent=name;code.textContent=content;code.tabIndex=0;download.type='button';download.textContent='Download '+name;
              download.addEventListener('click',()=>{
                const url=URL.createObjectURL(new Blob([content],{type:'text/plain;charset=utf-8'})),a=document.createElement('a');
                a.href=url;a.download=name.replaceAll('/','_');a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
              });section.append(title,code,download);files.append(section);
            }
            break;
          default:throw Error('Unknown tool.');
        }
        pre.textContent=form.dataset.tool==='starter'?'Save the files at the project paths below. Add your own assets, manifest UUIDs and gameplay.':JSON.stringify(output,null,2);
        result.hidden=false;status.textContent='Result ready. Review the tool’s limits before using it.';status.classList.remove('tool-error');
      }catch(error){pre.textContent='';result.hidden=true;status.textContent=error.message || 'Could not process the input.';status.classList.add('tool-error');}
      finally{button.disabled=false;}
    });
  });
})();
