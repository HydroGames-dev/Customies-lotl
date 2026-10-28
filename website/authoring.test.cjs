'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),A=require('./authoring-lib.js');
const fixtures=[];
for(const [kind,presets] of [['block',['cube','cardinal','slab']],['item',['basic','food','durable','armor']]])for(const preset of presets){
  const className='Test'+preset[0].toUpperCase()+preset.slice(1),identifier='documentation:'+preset;
  const out=A.generate({kind,preset,className,identifier,name:'Prism '+preset,stack:['durable','armor'].includes(preset)?1:16});
  assert.match(out.files['plugin.yml'],/api: 5.51.0/);assert.match(out.files['src/ContentPlugin.php'],/newId\(\)/);assert.ok(!out.files['src/ContentPlugin.php'].includes('\\\\pocketmine'));
  const manifest=JSON.parse(out.files['resource_pack/manifest.json']);assert.match(manifest.header.uuid,/^[0-9a-f-]{36}$/);assert.notEqual(manifest.header.uuid,manifest.modules[0].uuid);
  fixtures.push({kind,preset,className,identifier,...out});
}
const block={format_version:'1.26.60','minecraft:block':{description:{identifier:'documentation:converted','states':{'documentation:lit':[false,true]},traits:{'minecraft:placement_direction':{enabled_states:['minecraft:cardinal_direction']}}},components:{'minecraft:geometry':{identifier:'minecraft:geometry.full_block'},'minecraft:material_instances':{'*':{texture:'test',render_method:'alpha_test_single_sided',tint_method:'grass'}},'minecraft:collision_box':{min:[0,0,0],max:[8,8,16]},'minecraft:selection_box':{origin:[-8,0,-8],size:[16,8,16]},'minecraft:light_emission':0,'minecraft:light_dampening':2,'minecraft:friction':.4,'minecraft:display_name':{value:"Test 'quote' \\ slash"},'minecraft:custom_components':['untranslated:hook']},permutations:[{condition:"q.block_state('documentation:lit') == true",components:{'minecraft:light_emission':15,'minecraft:collision_box':false,'minecraft:selection_box':false}}]}};
let out=A.convert(block,{className:'ConvertedLamp'});assert.equal(out.report.state_combinations,8);assert.ok(out.report.review.some(s=>s.includes('custom_components')));fixtures.push({kind:'block',preset:'converted',className:'ConvertedLamp',identifier:'documentation:converted',...out});
const complex=structuredClone(block);complex['minecraft:block'].description.identifier='documentation:manual';complex['minecraft:block'].permutations[0].condition='q.time_of_day > 0.5';out=A.convert(complex,{className:'ManualLamp'});assert.ok(out.report.review.some(s=>s.includes('server evaluation')));
assert.ok(A.condition("!q.block_state('x:y') || (query.block_state('x:y') == 'yes' && 2 >= 1)",{'x:y':['yes','no']}));
assert.equal(A.condition("q.block_state('unknown:key') == 1",{}),null);assert.equal(A.condition('phpinfo()',{}),null);assert.equal(A.condition('true; exit',{}),null);
assert.deepEqual(A.visualize('{"collision":false,"selection":false}').collision,[]);
assert.deepEqual(A.bounds({origin:[0,0,-8],size:[8,8,8]}).local,[0,0,0,.5,.5,.5]);
let box=[0,0,0,.5,1,.5];for(let i=0;i<4;i++)box=A.rotate(box,'west');assert.deepEqual(box,[0,0,0,.5,1,.5]);
assert.equal(A.visualize('{"collision":[{"min":[0,0,0],"max":[16,24,16]}]}').collision[0][4],1.5);
for(const bad of ['{}','null','{"minecraft:block":{}}','{"minecraft:item":{},"minecraft:block":{}}'])assert.throws(()=>A.convert(bad));
for(const bad of ['class','Vector3','B','ContentPlugin',"X');exit();//",''] )assert.throws(()=>A.convert(block,{className:bad}));
assert.throws(()=>A.parse('['.repeat(34)+'0'+']'.repeat(34)),/depth/);
assert.throws(()=>A.parse('x'.repeat(131073)),/128/);
assert.throws(()=>A.parse('{"x":1e999}'),/finite/);
assert.throws(()=>A.parse('{"x":9007199254740993}'),/safely/);
assert.throws(()=>A.visualize('{"collision":{"min":[0,0,0],"max":[17,8,8]}}'),/bounds/);
assert.throws(()=>A.visualize('{"selection":[{},{}]}'),/single/);
assert.throws(()=>A.generate({kind:'item',preset:'armor',stack:64}),/stack size/);
assert.throws(()=>A.generate({identifier:'minecraft:stone'}),/namespace/);
assert.throws(()=>A.zip({'../evil.php':'bad'}),/Unsafe/);
const malicious=structuredClone(block);malicious['minecraft:block'].components['constructor']={};out=A.convert(malicious);assert.ok(out.report.review.some(s=>s.includes('constructor')));
const escaped=A.convert({'minecraft:item':{description:{identifier:'documentation:escaped'},components:{'minecraft:display_name':"'; phpinfo(); //",'minecraft:max_stack_size':16}}},{className:'EscapedContent'});fixtures.push({kind:'item',preset:'escaped',className:'EscapedContent',identifier:'documentation:escaped',...escaped});
const nativeSamples={block:{friction:.4,light_emission:3,light_dampening:2,display_name:'Adapters',collision_box:[{min:[0,0,0],max:[16,8,16]}],selection_box:true,geometry:'minecraft:geometry.full_block',material_instances:{'*':{texture:'test'}},transformation:{rotation:[0,90,0]},destructible_by_mining:{seconds_to_destroy:1},destructible_by_explosion:{explosion_resistance:2},map_color:'#334455',flammable:{catch_chance_modifier:5,destroy_chance_modifier:20,lava_flammable:true},movable:{movement_type:'immovable'},block_entity:{dynamic_properties:true,container:{slot_count:9,title:'Container'},entity_storage:true},crafting_table:{crafting_tags:['crafting_table'],table_name:'Table'},replaceable:{},flower_pottable:{},entity_fall_on:{minimum_fall_distance:2}},item:{max_stack_size:16,damage:4,fuel:{duration:10},swing_duration:.4,storage_weight_limit:64,display_name:{value:'Adapters'},allow_off_hand:true,can_destroy_in_creative:true,glint:true,hand_equipped:true,liquid_clipped:false,should_despawn:true,stacked_by_data:false,icon:{textures:{default:'test',dyed:'test_dyed'}},enchantable:{slot:'all',value:12},cooldown:{category:'test',duration:2,type:'use'},digger:{use_efficiency:true,destroy_speeds:[]},use_animation:'eat',use_modifiers:{use_duration:1.6,movement_modifier:.35},swing_sounds:{attack_hit:'attack.strong'},rarity:'rare',fire_resistant:{}}};
for(const [kind,sample] of Object.entries(nativeSamples)){
  const className=kind==='block'?'BlockAdapters':'ItemAdapters',identifier='documentation:'+kind+'_adapters';
  const converted=A.convert({['minecraft:'+kind]:{description:{identifier},components:Object.fromEntries(Object.entries(sample).map(([k,v])=>['minecraft:'+k,v]))}},{className});
  fixtures.push({kind,preset:'adapters',className,identifier,...converted});
}
const archive=A.zip({'hello.txt':'Prism ◆\n','src/Test.php':'<?php\n'});assert.equal(new DataView(archive.buffer).getUint32(0,true),0x04034b50);
const rotation={'format_version':'1.26.60','minecraft:block':{description:{identifier:'documentation:rotated',traits:{'minecraft:placement_direction':{enabled_states:['minecraft:cardinal_direction']}}},components:{'minecraft:geometry':'minecraft:geometry.full_block','minecraft:collision_box':{min:[0,0,0],max:[4,8,12]},'minecraft:selection_box':{min:[0,0,0],max:[4,8,12]}},permutations:['north','west','south','east'].map((d,i)=>({condition:"q.block_state('minecraft:cardinal_direction') == '"+d+"'",components:{'minecraft:transformation':{rotation:[0,i*90,0]}}}))}};
const rotated=A.convert(rotation,{className:'RotatedShape'});assert.ok(Array.isArray(JSON.parse(rotated.files['source.json'])['minecraft:block'].components['minecraft:collision_box']));assert.ok(rotated.report.review.some(s=>s.includes('normalized legacy')));fixtures.push({kind:'block',preset:'rotation',className:'RotatedShape',identifier:'documentation:rotated',...rotated});
for(const options of [{kind:'item',preset:'food',className:'ConfiguredFood',identifier:'documentation:configured_food',stack:16,nutrition:10,saturation:.9,lore:'A $& treat'},{kind:'item',preset:'armor',className:'ConfiguredArmor',identifier:'documentation:configured_armor',stack:1,durability:512,slot:'slot.armor.chest',protection:8,lore:"A 'quoted' chestplate"},{kind:'block',preset:'slab',className:'ConfiguredSlab',identifier:'documentation:configured_slab',hardness:2,light:9,lore:'A luminous slab'}]){fixtures.push({...options,preset:'configured',expectedStack:options.stack??16,configuration:options,...A.generate(options)});}
assert.throws(()=>A.generate({kind:'item',preset:'food',nutrition:101}),/nutrition/);
assert.throws(()=>A.generate({kind:'block',preset:'cube',hardness:-1}),/hardness/);
const shapes=JSON.parse(fs.readFileSync(path.join(__dirname,'shape-fixtures.json'),'utf8'));assert.equal(shapes.length,40);
function occupancy(boxes){return [.25,.75].flatMap(x=>[.25,.75].flatMap(y=>[.25,.75].map(z=>boxes.some(([a,b,c,d,e,f])=>x>a&&x<d&&y>b&&y<e&&z>c&&z<f))));}
for(const f of shapes){assert.deepEqual(occupancy(f.client),occupancy(f.server));assert.deepEqual(occupancy(f.server),occupancy(f.vanilla));}
if(process.argv[2]==='--fixtures'){fs.mkdirSync(path.dirname(process.argv[3]),{recursive:true});fs.writeFileSync(process.argv[3],JSON.stringify(fixtures,null,2));fs.writeFileSync(path.join(path.dirname(process.argv[3]),'authoring-test.zip'),archive);}
console.log('PASS: 7 generator patterns, native conversion, safe PHP strings, state/permutation handling, input limits, ZIP and 40 tested rotation fixtures.');
