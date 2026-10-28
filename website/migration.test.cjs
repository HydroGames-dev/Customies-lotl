'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),Z=require('fflate'),M=require('./migration-lib.js'),A=require('./authoring-lib.js');
const enc=s=>new TextEncoder().encode(s),j=v=>enc(JSON.stringify(v)),fixtures=[];
const wand=`import { world } from '@minecraft/server';
const WAND='migration:wand';
const DURATION=200;
world.afterEvents.itemUse.subscribe((event)=>{
 if(event.itemStack.typeId !== WAND) return;
 const { source: player }=event;
 player.addEffect('speed',DURATION,{amplifier:1,showParticles:true});
 player.sendMessage('Wand activated');
});`;
const item={'format_version':'1.26.60','minecraft:item':{description:{identifier:'migration:wand'},components:{'minecraft:icon':'migration_wand','minecraft:display_name':'Migration Wand','minecraft:max_stack_size':1,'migration:power':{}}}};
const custom=`import {system} from '@minecraft/server';
system.beforeEvents.startup.subscribe(({itemComponentRegistry: items})=>{
 items.registerCustomComponent('migration:power',{
  onUse(event){event.source.addEffect('strength',80,{amplifier:0});},
  onConsume(event){event.source.sendMessage('Consumed');}
 });
});
system.runInterval(()=>{},20);`;
function pngChunk(type,data){const out=new Uint8Array(12+data.length),view=new DataView(out.buffer);view.setUint32(0,data.length);out.set(enc(type),4);out.set(data,8);view.setUint32(8+data.length,M.crc32(out.subarray(4,8+data.length)));return out;}
const header=new Uint8Array(13),hv=new DataView(header.buffer);hv.setUint32(0,16);hv.setUint32(4,16);header[8]=8;header[9]=6;
const scan=new Uint8Array(16*(1+16*4));for(let y=0;y<16;y++)for(let x=0;x<16;x++)scan.set([32,155,132,255],y*65+1+x*4);
const chunks=[new Uint8Array([137,80,78,71,13,10,26,10]),pngChunk('IHDR',header),pngChunk('IDAT',Z.zlibSync(scan)),pngChunk('IEND',new Uint8Array())],png=new Uint8Array(chunks.reduce((n,a)=>n+a.length,0));let po=0;for(const chunk of chunks){png.set(chunk,po);po+=chunk.length;}
const bp={'manifest.json':j({format_version:2,header:{name:'Migration fixture',description:'Script migration integration fixture',uuid:'bb32bc7c-7744-4b53-8bb9-9b4000000001',version:[1,0,0],min_engine_version:[1,26,60]},modules:[{type:'data',uuid:'bb32bc7c-7744-4b53-8bb9-9b4000000002',version:[1,0,0]},{type:'script',language:'javascript',entry:'scripts/main.js',uuid:'bb32bc7c-7744-4b53-8bb9-9b4000000003',version:[1,0,0]}],dependencies:[{module_name:'@minecraft/server',version:'2.0.0'}]}),'items/wand.json':j(item),'scripts/main.js':enc(wand+"\nimport './hooks.js';"),'scripts/hooks.js':enc(custom),'scripts/unused.js':enc("world.afterEvents.itemUse.subscribe(e=>e.source.sendMessage('Unreachable'));")};
const rp={'manifest.json':j({format_version:2,header:{name:'Migration fixture resources',description:'Preserved source assets',uuid:'bb32bc7c-7744-4b53-8bb9-9b4000000004',version:[1,0,0],min_engine_version:[1,26,60]},modules:[{type:'resources',uuid:'bb32bc7c-7744-4b53-8bb9-9b4000000005',version:[1,0,0]}]}),'textures/items/migration_wand.png':png,'textures/blocks/migration_altar.png':png,'textures/item_texture.json':j({resource_pack_name:'migration_fixture',texture_name:'atlas.items',texture_data:{migration_wand:{textures:'textures/items/migration_wand'}}}),'textures/terrain_texture.json':j({resource_pack_name:'migration_fixture',texture_name:'atlas.terrain',texture_data:{migration_altar:{textures:'textures/blocks/migration_altar'}}})};
const addon=Z.zipSync({'Behavior.mcpack':Z.zipSync(bp),'Resources.mcpack':Z.zipSync(rp)});
const entries=M.collect('Fixture.mcaddon',addon),converted=M.migrate(entries,{pluginName:'MigrationTest'});
assert.equal(converted.report.definitions.length,1);assert.equal(converted.report.translated_callbacks,3);assert.ok(converted.report.manual_callbacks>=1);assert.equal(converted.report.assets.length,5);
assert.deepEqual(converted.files['resource_packs/pack_1/textures/items/migration_wand.png'],png);
assert.ok(converted.report.review.some(r=>r.message.includes('Not reachable')));
assert.match(converted.files['plugin/src/MigrationHooks.php'],/EffectInstance/);assert.match(converted.files['plugin/src/MigrationHooks.php'],/matchesItem/);assert.ok(!converted.files['plugin/src/MigrationHooks.php'].includes('runCommand'));
fixtures.push({pluginName:'MigrationTest',...converted});
const variants=M.migrate(new Map([['variants.js',enc(`import {world} from '@minecraft/server';world.afterEvents.itemUse.subscribe(e=>{if(e.itemStack.typeId === 'minecraft:red_wool')e.source.sendMessage('Red');if(e.itemStack.typeId === 'minecraft:potion')e.source.sendMessage('Potion');});`)]]),{pluginName:'VariantMigration'});fixtures.push({pluginName:'VariantMigration',...variants});
const blockDefinition={'format_version':'1.26.60','minecraft:block':{description:{identifier:'migration:altar'},components:{'minecraft:geometry':'minecraft:geometry.full_block','minecraft:material_instances':{'*':{texture:'migration_altar'}},'minecraft:collision_box':[{min:[0,0,0],max:[16,16,16]}],'migration:altar_use':{}}}};
const blockScript=`import {system} from '@minecraft/server';system.beforeEvents.startup.subscribe(({blockComponentRegistry})=>{blockComponentRegistry.registerCustomComponent('migration:altar_use',{onPlayerInteract(event){event.player.addEffect('regeneration',100);}});});`;
const blockProject=M.migrate(new Map([['altar.json',j(blockDefinition)],['altar.js',enc(blockScript)]]),{pluginName:'BlockMigration'});assert.equal(blockProject.report.translated_callbacks,1);fixtures.push({pluginName:'BlockMigration',...blockProject});
const bad=M.script("import {world} from '@minecraft/server';world.afterEvents.itemUse.subscribe(e=>{e.source.sendMessage('Never partial'); e.source.runCommand('say no');});",'bad.js');assert.equal(bad.handlers.length,0);assert.match(bad.report[0].message,/entirely/);
assert.equal(M.script('this is not javascript','syntax.js').handlers.length,0);
assert.equal(M.script("world.afterEvents.itemUse.subscribe(e=>{if(e.itemStack.typeId === 'stick')e.source.sendMessage('wrong alias');});",'alias.js').handlers.length,0);
assert.equal(M.script("world.afterEvents.itemUse.subscribe(e=>{if('01' == '1')e.source.sendMessage('unsafe typing');});",'loose.js').handlers.length,0);
assert.deepEqual(M.jsonc('{"x":"https://test/a//b", /* comment */ "a":[1,2,],}'),{x:'https://test/a//b',a:[1,2]});
assert.throws(()=>M.jsonc('/* unclosed'),/Unclosed/);
for(const name of ['../evil','a/../evil','C:/evil','/evil','a\u0000b','a//b','a/.. /evil','a./file','NUL.txt','a/COM1'])assert.throws(()=>M.safePath(name));
for(const files of [{'../evil.php':enc('bad')},{'a.txt':enc('a'),'A.txt':enc('b')}])assert.throws(()=>M.collect('bad.zip',Z.zipSync(files)),/path|Duplicate/);
const broken=addon.slice();broken[50]^=0xff;assert.throws(()=>M.collect('broken.zip',broken));
const bomb=Z.zipSync({'huge.txt':new Uint8Array(M.LIMITS.file+1)});assert.throws(()=>M.collect('bomb.zip',bomb),/8 MiB/);
const forged=Z.zipSync({'small.txt':new Uint8Array(8192)}),fv=new DataView(forged.buffer),central=fv.getUint32(forged.length-6,true);fv.setUint32(central+24,1,true);assert.throws(()=>M.collect('forged.zip',forged),/declared size/);
let nested=Z.zipSync({'x.txt':enc('x')});for(let n=0;n<3;n++)nested=Z.zipSync({'nested.mcpack':nested});assert.throws(()=>M.collect('nested.mcaddon',nested),/depth/);
const resourceOnly=M.migrate(M.collect('Resources.mcpack',Z.zipSync(rp)));assert.equal(resourceOnly.report.translated_callbacks,0);assert.ok(resourceOnly.report.review.some(r=>r.message.includes('scaffold')));
// Unsupported source code never runs, even when it contains executable-looking globals.
global.migrationExecuted=false;const hostile=M.script("globalThis.migrationExecuted=true; eval('throw 1');",'hostile.js');assert.equal(global.migrationExecuted,false);assert.equal(hostile.handlers.length,0);
const escaped=M.migrate(new Map([['message.js',enc(`import {world} from '@minecraft/server';world.afterEvents.itemUse.subscribe(e=>e.source.sendMessage("'); phpinfo(); //"));`)]]),{pluginName:'EscapedMigration'});fixtures.push({pluginName:'EscapedMigration',...escaped});
assert.match(escaped.files['plugin/src/MigrationHooks.php'],/sendMessage\('\s*\\'/);
const binaryZip=A.zip(converted.files),roundtrip=Z.unzipSync(binaryZip);assert.deepEqual(roundtrip['resource_packs/pack_1/textures/items/migration_wand.png'],png);
if(process.argv[2]==='--fixtures'){fs.mkdirSync(path.dirname(process.argv[3]),{recursive:true});fs.writeFileSync(process.argv[3],JSON.stringify(fixtures.map(f=>({...f,files:Object.fromEntries(Object.entries(f.files).filter(([,v])=>typeof v==='string'))})),null,2));fs.writeFileSync(path.join(path.dirname(process.argv[3]),'migration-test.mcaddon'),addon);fs.writeFileSync(path.join(path.dirname(process.argv[3]),'migration-resources.mcpack'),Z.zipSync(rp));}
console.log('PASS: nested compressed packs, binary assets/ZIP, manifest imports, JSONC, three real hook translations, escaped PHP, no JS execution, unsupported whole callbacks, traversal, duplicates, checksum, expansion and nesting bounds.');
