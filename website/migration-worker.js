'use strict';
importScripts('vendor/acorn.js','vendor/fflate.js','authoring-lib.js','migration-lib.js');
self.onmessage=event=>{
  try{
    self.postMessage({stage:'Reading pack files and checking archive bounds…'});
    const {name,bytes,pluginName}=event.data,entries=self.CustomiesMigration.collect(name,new Uint8Array(bytes));
    self.postMessage({stage:'Translating definitions and supported script callbacks…'});
    const output=self.CustomiesMigration.migrate(entries,{pluginName});
    const transfers=[...new Set(Object.values(output.files).filter(v=>v instanceof Uint8Array).map(v=>v.buffer))];
    self.postMessage({output},transfers);
  }catch(error){self.postMessage({error:error.message});}
};
