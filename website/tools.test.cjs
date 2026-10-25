'use strict';
const assert=require('node:assert/strict');
const tool=require('./tools-lib.js');

assert.equal(tool.states('{"x:direction":["n","s","e","w"],"x:power":[false,true]}').combinations,'8');
assert.equal(tool.states('{}').combinations,'1');
assert.throws(()=>tool.states('{"x:a":[1,true]}'),/one bool/);
assert.throws(()=>tool.states('{"x:a":[1,1]}'),/duplicate/);
assert.throws(()=>tool.states('{"x:a":[]}'),/explicit/);
assert.throws(()=>tool.states('{"plain":[1]}'),/Namespace/);
assert.throws(()=>tool.states('{"x:a":[null]}'),/one bool/);
assert.throws(()=>tool.parse('x'),/valid JSON/);
assert.throws(()=>tool.parse(' '.repeat(65537)),/64 KiB/);
assert.equal(tool.states(JSON.stringify({'x:a':Array.from({length:32},(_,i)=>i)})).warnings.length,1);
assert.deepEqual(tool.boxes('[-8,0,-8]','[16,8,16]').server_rotated_bounds,[0,0,0,1,.5,1]);
assert.deepEqual(tool.boxes('[0,0,0]','[1,0.5,1]','blocks').client_unrotated,{origin:[-8,0,-8],size:[16,8,16]});
assert.deepEqual(tool.boxes('[-8,0,-8]','[8,16,16]','pixels','west').server_rotated_bounds,[0,0,.5,1,1,1]);
assert.deepEqual(tool.boxes('[-8,0,-8]','[8,16,16]','pixels','south').server_rotated_bounds,[.5,0,0,1,1,1]);
assert.equal(tool.boxes('[-16,0,-8]','[16,16,16]').warnings.length,1);
assert.throws(()=>tool.boxes('[0,0]','[1,1,1]'),/three/);
assert.throws(()=>tool.boxes('[0,0,0]','[1,0,1]'),/positive/);
assert.throws(()=>tool.boxes('[0,0,0]','[1,1,1]','pixels','up'),/cardinal/);
const ids=tool.identifiers('mine:lamp\nmine:lamp\nminecraft:thing\nBad:id');
assert.equal(ids[0].convention_ok,true);assert.match(ids[1].messages[0],/Duplicate/);assert.match(ids[2].messages[0],/Reserved/);assert.equal(ids[3].convention_ok,false);
const api={item:{cooldown:{name:'minecraft:cooldown',class:'Cooldown',serialized_example:{category:'x',duration:2,type:'use'}}}};
assert.equal(tool.inspect('{"minecraft:cooldown":{"category":"a","duration":2,"type":"use"}}','item',api).components[0].messages.length,0);
assert.equal(tool.inspect('{"x:unknown":{}}','item',api).components[0].known,false);
assert.match(tool.inspect('{"minecraft:cooldown":{"duration":"2"}}','item',api).components[0].messages.join(' '),/shape/);
for(const kind of ['item','block']){
  const files=tool.starter({kind,namespace:'mycontent',path:'lamp',plugin:'MyContent',stack:16});
  assert.match(files['plugin.yml'],/depend: \[Customies-Axolotl\]/);
  assert.match(files['src/Main.php'],/newId\(\)/);
  assert.match(files[`src/Starter${kind==='item'?'Item':'Block'}.php`],/namespace mycontent;/);
  assert.ok(!files['src/Main.php'].includes('\\\\pocketmine'),'PHP namespace must have single separators');
}
assert.throws(()=>tool.starter({kind:'item',namespace:'minecraft',path:'x',plugin:'X',stack:1}),/namespace/);
assert.throws(()=>tool.starter({kind:'item',namespace:'x',path:'x',plugin:'<script>',stack:1}),/plugin name/);
assert.throws(()=>tool.starter({kind:'item',namespace:'x',path:'x',plugin:'X',stack:65}),/Stack/);
console.log('PASS: calculator, conversion/rotation, identifier audit, wrapper inspection and both starter kinds, including invalid inputs.');

if(process.argv[2]==='--fixtures'){
  const fs=require('node:fs'),path=require('node:path');const output=process.argv[3];
  if(!output)throw Error('Pass a fixture output path.');
  fs.mkdirSync(path.dirname(output),{recursive:true});
  fs.writeFileSync(output,JSON.stringify(['item','block'].map(kind=>({kind,files:tool.starter({kind,namespace:'documentation',path:kind,plugin:kind==='item'?'DocsItem':'DocsBlock',stack:16})})),null,2));
}
