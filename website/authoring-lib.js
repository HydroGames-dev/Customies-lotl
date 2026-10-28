(function(root){
  'use strict';
  const error=m=>{throw Error(m);};
  const obj=v=>v!==null && typeof v==='object' && !Array.isArray(v);
  const own=(o,k)=>Object.prototype.hasOwnProperty.call(o,k);
  const php=s=>"'"+String(s).replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";
  const value=v=>v===null?'null':typeof v==='boolean'?(v?'true':'false'):typeof v==='number'?String(v):typeof v==='string'?php(v):Array.isArray(v)?'['+v.map(value).join(', ')+']':'['+Object.entries(v).map(([k,x])=>php(k)+' => '+value(x)).join(', ')+']';
  function parse(text){
    if(typeof text!=='string'||text.length>131072)error('Use at most 128 KiB of JSON.');
    let d;try{d=JSON.parse(text);}catch{error('Enter valid JSON, without comments or trailing commas.');}
    let count=0;function visit(v,depth){if(++count>20000||depth>32)error('JSON is too complex (maximum depth 32 / 20,000 values).');if(typeof v==='number'&&(!Number.isFinite(v)||(Number.isInteger(v)&&!Number.isSafeInteger(v))))error('JSON numbers must be finite and integer values must be safely representable.');if(v&&typeof v==='object')for(const x of Object.values(v))visit(x,depth+1);}visit(d,0);return d;
  }
  function number(v,path,min=-1e6,max=1e6,integer=false){if(typeof v!=='number'||!Number.isFinite(v)||v<min||v>max||(integer&&!Number.isSafeInteger(v)))error(`${path}: expected ${integer?'an integer':'a finite number'} from ${min} to ${max}.`);return v;}
  function text(v,path){if(typeof v!=='string'||v.length>1024)error(`${path}: expected a string of at most 1024 characters.`);return v;}
  function bool(v,path){if(typeof v!=='boolean')error(`${path}: expected true or false.`);return v;}
  function vector(v,path){if(!Array.isArray(v)||v.length!==3)error(`${path}: use three numbers.`);return v.map(x=>number(x,path));}
  const vec=v=>'new Vector3('+v.join(', ')+')';
  function identifier(id){if(typeof id!=='string'||!/^\w[\w.-]*:[a-z0-9_./-]+$/.test(id)||id!==id.toLowerCase()||id.startsWith('minecraft:'))error('Use your own lowercase namespace:name identifier.');return id;}
  function className(s){if(!/^[A-Za-z][A-Za-z0-9_]{0,47}$/.test(s)||/^(class|trait|interface|enum|match|fn|function|new|extends|implements|abstract|final|readonly|public|private|protected|static|self|parent|true|false|null|int|float|string|bool|array|object|mixed|never|void|iterable|resource|yield|echo|return|if|else|while|switch|case|try|catch|throw|use|namespace|const|unset|isset|empty|eval|exit|die|print|include|require|clone|list|global|var|callable|and|or|xor|break|continue|do|for|foreach|default|declare|instanceof|insteadof|as|finally|goto)$/i.test(s))error('Choose a PHP class name using 1–48 letters/digits/underscores, starting with a letter, without PHP keywords.');return s;}
  const aabb=b=>'new AxisAlignedBB('+b.join(', ')+')';
  function bounds(box,path='box'){
    if(!obj(box))error(path+': expected a box object.');
    let min,max;
    if(own(box,'min')||own(box,'max')){if(own(box,'origin')||own(box,'size'))error(path+': choose min/max or origin/size.');min=vector(box.min,path+'.min');max=vector(box.max,path+'.max');}
    else{const o=vector(box.origin??[-8,0,-8],path+'.origin'),s=vector(box.size??[16,16,16],path+'.size');if(s.some(v=>v<=0))error(path+': sizes must be positive.');min=[8-o[0]-s[0],o[1],o[2]+8];max=[8-o[0],o[1]+s[1],o[2]+s[2]+8];}
    if(min.some((x,i)=>x<0||max[i]>[16,24,16][i]||x>=max[i]))error(path+': bounds must increase inside X/Z 0–16 and Y 0–24.');
    return {min,max,local:[...min.map(x=>x/16),...max.map(x=>x/16)]};
  }
  function boxes(v,path='collision'){
    if(v===false)return []; if(v===true||v===undefined)return [bounds({})];
    const list=Array.isArray(v)?v:[v];if(list.length>16)error(path+': at most 16 boxes.');return list.map((b,i)=>bounds(b,path+'['+i+']'));
  }
  function rotate(b,direction='north'){
    let [x,y,z,xx,yy,zz]=b;const turns=['north','west','south','east'].indexOf(direction);if(turns<0)error('Choose a cardinal direction.');
    for(let i=0;i<turns;i++)[x,z,xx,zz]=[z,1-xx,zz,1-x];return [x,y,z,xx,yy,zz];
  }
  function visualize(input,direction='north'){
    const d=typeof input==='string'?parse(input):input;if(!obj(d))error('Use an object with collision and selection fields.');
    const collision=boxes(d.collision??d['minecraft:collision_box']??true),selection=boxes(d.selection??d['minecraft:selection_box']??true);
    if(selection.length>1)error('A selection box is a single box or a boolean.');
    return {collision:collision.map(b=>rotate(b.local,direction)),selection:selection.map(b=>rotate(b.local,direction)),voxel:collision.map(({min,max})=>({min,max})),php:collision.map(b=>aabb(rotate(b.local,direction)))};
  }
  function stateDefinitions(input){
    if(!obj(input))error('description.states must be an object.');const result={};let product=1;
    for(const [name,list] of Object.entries(input)){
      if(!name.includes(':')||!Array.isArray(list)||!list.length||list.length>16)error(name+': states need a namespaced name and 1–16 explicit values.');
      text(name,'state name');for(const v of list)if(typeof v==='string')text(v,'state value');
      if(!list.every(v=>['string','boolean'].includes(typeof v)||(typeof v==='number'&&Number.isSafeInteger(v)))||!list.every(v=>typeof v===typeof list[0])||new Set(list).size!==list.length)error(name+': use unique values of one bool/int/string type.');
      product*=list.length;if(product>65536)error('State product exceeds the authoring tool limit of 65,536.');result[name]=list;
    }return result;
  }
  // Parse a deliberately small Molang subset; never execute user input as JavaScript or PHP.
  function condition(source,states){
    if(typeof source!=='string'||source.length>4096)return null;
    const tokens=[];let pos=0;
    while(pos<source.length){
      const rest=source.slice(pos),space=/^\s+/.exec(rest);if(space){pos+=space[0].length;continue;}
      const q=/^(?:q|query)\.block_state\(\s*'([a-z0-9_:./-]+)'\s*\)/i.exec(rest);
      if(q){if(!own(states,q[1]))return null;tokens.push({type:'atom',php:'$this->getState('+php(q[1])+')?->getCurrentValue()'});pos+=q[0].length;continue;}
      const s=/^'([^'\\]*)'/.exec(rest),n=/^-?(?:\d+(?:\.\d+)?|\.\d+)/.exec(rest),b=/^(true|false)\b/.exec(rest),op=/^(==|!=|<=|>=|&&|\|\||[!()<>])/.exec(rest);
      if(s){tokens.push({type:'atom',php:php(s[1])});pos+=s[0].length;}
      else if(n){if(!Number.isFinite(Number(n[0])))return null;tokens.push({type:'atom',php:String(Number(n[0]))});pos+=n[0].length;}
      else if(b){tokens.push({type:'atom',php:b[1]});pos+=b[0].length;}
      else if(op){tokens.push({type:op[0],php:op[0]});pos+=op[0].length;}
      else return null;
    }
    let i=0;function primary(){if(tokens[i]?.type==='!'){i++;return '(!'+primary()+')';}if(tokens[i]?.type==='('){i++;const x=or();if(tokens[i++]?.type!==')')throw Error();return '('+x+')';}if(tokens[i]?.type!=='atom')throw Error();return tokens[i++].php;}
    function compare(){let x=primary();if(['==','!=','<','>','<=','>='].includes(tokens[i]?.type)){const op=tokens[i++].php;x='('+x+' '+op+' '+primary()+')';}return x;}
    function and(){let x=compare();while(tokens[i]?.type==='&&'){i++;x='('+x+' && '+compare()+')';}return x;}
    function or(){let x=and();while(tokens[i]?.type==='||'){i++;x='('+x+' || '+and()+')';}return x;}
    try{const result=or();return i===tokens.length?result:null;}catch{return null;}
  }
  function adapter(kind,name,v,notes,path){
    const n=(x,lo=-1e6,hi=1e6,int=false)=>number(x,path,lo,hi,int),t=x=>text(x,path),p=x=>php(t(x));
    function fields(keys){if(!obj(v))error(path+': expected an object.');for(const key of Object.keys(v))if(!keys.includes(key))notes.push(path+'.'+key+': not converted; review manually.');return v;}
    const simple=kind==='block'?{
      'minecraft:friction':['FrictionComponent',0,.9,false], 'minecraft:light_emission':['LightEmissionComponent',0,15,true], 'minecraft:light_dampening':['LightDampeningComponent',0,15,true]
    }:{'minecraft:max_stack_size':['MaxStackSizeComponent',1,64,true],'minecraft:damage':['DamageComponent',0,32767,true],'minecraft:fuel':['FuelComponent',.05,1e6,false],'minecraft:swing_duration':['SwingDurationComponent',0,1000,false],'minecraft:storage_weight_limit':['StorageWeightLimitComponent',0,64,true]};
    if(own(simple,name)){const [cls,lo,hi,int]=simple[name];if(obj(v))fields(['value','duration']);return 'new '+(kind==='block'?'B':'I')+'\\'+cls+'('+n(obj(v)?v.value??v.duration:v,lo,hi,int)+')';}
    if(name==='minecraft:display_name'){if(obj(v))fields(['value']);return 'new '+(kind==='block'?'B':'I')+'\\DisplayNameComponent('+p(obj(v)?v.value:v)+')';}
    if(kind==='block'){
      switch(name){
        case 'minecraft:collision_box':{const list=boxes(v,path);return list.length?'(new B\\CollisionBoxComponent())->addBoxes(['+list.map(b=>'Box::fromMinMax('+vec(b.min)+', '+vec(b.max)+')').join(', ')+'])':'new B\\CollisionBoxComponent(false)';}
        case 'minecraft:selection_box':{const list=boxes(v,path);if(list.length>1)error(path+': use a single selection box.');if(!list.length)return 'new B\\SelectionBoxComponent(false)';const {min,max}=list[0];return 'new B\\SelectionBoxComponent(true, '+vec([8-max[0],min[1],min[2]-8])+', '+vec(max.map((x,i)=>x-min[i]))+')';}
        case 'minecraft:geometry':{if(typeof v==='string')return 'new B\\GeometryComponent('+p(v)+')';fields(['identifier','bone_visibility','culling','culling_layer','culling_shape','uv_lock']);return 'new B\\GeometryComponent('+p(v.identifier)+', '+value(v.bone_visibility??{})+', '+p(v.culling??'')+', '+p(v.culling_layer??'minecraft:culling_layer.undefined')+', '+p(v.culling_shape??'minecraft:empty')+', '+value(v.uv_lock??false)+')';}
        case 'minecraft:material_instances':{
          if(!obj(v))error(path+': expected a material map.');const material=[];
          for(const [face,m] of Object.entries(v)){if(!obj(m))error(path+'.'+face+': expected a material object.');for(const k of Object.keys(m))if(!['texture','render_method','tint_method','ambient_occlusion','face_dimming','isotropic'].includes(k))notes.push(path+'.'+face+'.'+k+': not converted.');
            const render=m.render_method??'opaque',tint=m.tint_method??'none';if(!['opaque','blend','alpha_test','double_sided','alpha_test_single_sided','alpha_test_to_opaque','alpha_test_single_sided_to_opaque','blend_to_opaque'].includes(render))error(path+': unsupported render_method '+render);
            if(!['none','default_foliage','birch_foliage','evergreen_foliage','dry_foliage','grass','water'].includes(tint))error(path+': unsupported tint_method '+tint);
            material.push('new Material('+php(face)+', '+p(m.texture)+', RenderMethod::from('+php(render)+'), TintMethod::from('+php(tint)+'), '+n(m.ambient_occlusion??1,0,1)+', '+value(m.face_dimming??true)+', '+value(m.isotropic??false)+')');
          }return 'new B\\MaterialInstancesComponent(['+material.join(', ')+'])';
        }
        case 'minecraft:transformation':{fields(['rotation','rotation_pivot','scale','scale_pivot','translation']);const r=vector(v.rotation??[0,0,0],path);if(r.some(x=>x%90!==0))error(path+': this wrapper requires quarter-turn rotations.');return 'new B\\TransformationComponent('+[r,vector(v.rotation_pivot??[0,0,0],path),vector(v.scale??[1,1,1],path),vector(v.scale_pivot??[0,0,0],path),vector(v.translation??[0,0,0],path)].map(vec).join(', ')+')';}
        case 'minecraft:destructible_by_mining':if(typeof v==='boolean'){if(!v){notes.push(path+': unbreakable client metadata is not representable by this wrapper; add server policy manually.');return null;}return 'new B\\DestructibleByMiningComponent()';}fields(['seconds_to_destroy']);return 'new B\\DestructibleByMiningComponent('+n(v.seconds_to_destroy??0,0)+')';
        case 'minecraft:destructible_by_explosion':if(typeof v==='boolean'){if(!v){notes.push(path+': explosion immunity needs custom server handling.');return null;}return 'new B\\DestructibleByExplosionComponent()';}fields(['explosion_resistance']);return 'new B\\DestructibleByExplosionComponent('+n(v.explosion_resistance??0,0)+')';
        case 'minecraft:map_color':if(obj(v))fields(['color']);return 'new B\\MapColorComponent('+value(obj(v)?v.color:v)+')';
        case 'minecraft:flammable':if(typeof v==='boolean')return v?'new B\\FlammableComponent()':null;fields(['catch_chance_modifier','destroy_chance_modifier','lava_flammable']);return 'new B\\FlammableComponent('+n(v.catch_chance_modifier??5,0,1e6,true)+', '+n(v.destroy_chance_modifier??20,0,1e6,true)+', '+value(v.lava_flammable??false)+')';
        case 'minecraft:movable':fields(['movement_type']);return 'new B\\MovableComponent('+p(v.movement_type??'immovable')+')';
        case 'minecraft:block_entity':fields(['dynamic_properties','container','entity_storage']);if(v.container){for(const k of Object.keys(v.container))if(!['slot_count','title'].includes(k))notes.push(path+'.container.'+k+': not converted.');}notes.push(path+': implement PHP Tile/inventory/entity storage; metadata is not that gameplay implementation.');return 'new B\\BlockEntityComponent('+value(v.dynamic_properties??false)+', '+value(v.container?.slot_count??null)+', '+value(v.entity_storage??false)+', '+value(v.container?.title??null)+')';
        case 'minecraft:crafting_table':fields(['crafting_tags','table_name']);notes.push(path+': add an authoritative PHP crafting inventory/use hook.');return 'new B\\CraftingTableComponent('+value(v.crafting_tags??[])+', '+p(v.table_name??'Crafting')+')';
        case 'minecraft:replaceable':return 'new B\\ReplaceableComponent()';
        case 'minecraft:flower_pottable':return 'new B\\FlowerPottableComponent()';
        case 'minecraft:entity_fall_on':fields(['minimum_fall_distance']);return 'new B\\EntityFallOnComponent('+n(v.minimum_fall_distance??0,0)+')';
      }
    }else{
      const toggles={'minecraft:allow_off_hand':'AllowOffHandComponent','minecraft:can_destroy_in_creative':'CanDestroyInCreativeComponent','minecraft:glint':'GlintComponent','minecraft:hand_equipped':'HandEquippedComponent','minecraft:liquid_clipped':'LiquidClippedComponent','minecraft:should_despawn':'ShouldDespawnComponent','minecraft:stacked_by_data':'StackedByDataComponent'};
      if(own(toggles,name)){if(obj(v))fields(['value']);return 'new I\\'+toggles[name]+'('+value(bool(obj(v)?v.value:v,path))+')';}
      switch(name){
        case 'minecraft:icon':{if(typeof v==='string')return 'new I\\IconComponent('+p(v)+')';fields(['texture','textures']);const d=v.textures??{default:v.texture};return 'new I\\IconComponent('+['default','dyed','trim','bundle_open_back','bundle_open_front'].map(k=>p(d[k]??'')).join(', ')+')';}
        case 'minecraft:food':fields(['can_always_eat','nutrition','saturation_modifier','using_converts_to']);return 'new I\\FoodComponent('+value(bool(v.can_always_eat??false,path))+', '+n(v.nutrition??0,0,100,true)+', '+saturation(v.saturation_modifier??.6)+', '+p(v.using_converts_to??'')+')';
        case 'minecraft:durability':fields(['max_durability','damage_chance']);return 'new I\\DurabilityComponent('+n(v.max_durability,1,1e6,true)+', '+n(v.damage_chance?.min??100,0,100,true)+', '+n(v.damage_chance?.max??100,0,100,true)+')';
        case 'minecraft:wearable':fields(['slot','protection','hide_player_location']);return 'new I\\WearableComponent('+p(v.slot)+', '+n(v.protection??0,0,100,true)+', '+value(v.hide_player_location??false)+')';
        case 'minecraft:enchantable':fields(['slot','value']);return 'new I\\EnchantableComponent('+p(v.slot??'none')+', '+n(v.value??0,0,1e6,true)+')';
        case 'minecraft:cooldown':fields(['category','duration','type']);return 'new I\\CooldownComponent('+p(v.category)+', '+n(v.duration,0)+', '+p(v.type??'use')+')';
        case 'minecraft:digger':fields(['use_efficiency','destroy_speeds']);notes.push(path+': implement authoritative PHP mining/tool behavior.');return 'new I\\DiggerComponent('+value(v.use_efficiency??false)+', '+value(v.destroy_speeds??[])+')';
        case 'minecraft:use_animation':if(obj(v))fields(['value']);return 'new I\\UseAnimationComponent('+p(obj(v)?v.value:v)+')';
        case 'minecraft:use_modifiers':fields(['movement_modifier','use_duration','emit_vibrations','start_sound','start_using']);return 'new I\\UseModifiersComponent('+n(v.movement_modifier??1,0,1)+', '+n(v.use_duration??0,0)+', '+value(v.emit_vibrations??false)+', '+value(v.start_sound??null)+', '+p(v.start_using??'always')+')';
        case 'minecraft:swing_sounds':fields(['attack_critical_hit','attack_hit','attack_miss']);return 'new I\\SwingSoundsComponent('+p(v.attack_critical_hit??'attack.critical')+', '+p(v.attack_hit??'attack.strong')+', '+p(v.attack_miss??'attack.nodamage')+')';
        case 'minecraft:durability_sensor':{fields(['durability_thresholds']);if(!Array.isArray(v.durability_thresholds)||!v.durability_thresholds.length)error(path+': at least one durability threshold is required.');
          const thresholds=v.durability_thresholds.map(row=>{if(!obj(row))error(path+': threshold must be an object.');for(const k of Object.keys(row))if(!['durability','particle_type','sound_event'].includes(k))notes.push(path+'.durability_thresholds.'+k+': not converted.');
            const bits=["'durability' => "+n(row.durability,0,1e6,true)];if(row.particle_type!==undefined){notes.push(path+': particle_type needs a ParticleType enum adapter; add it manually.');}if(row.sound_event!==undefined)bits.push("'sound_event' => "+p(row.sound_event));else error(path+': this adapter requires sound_event; particle-only thresholds need manual code.');return '['+bits.join(', ')+']';});
          return 'new I\\DurabilitySensorComponent(['+thresholds.join(', ')+'])';}
        case 'minecraft:rarity':if(obj(v))fields(['value']);return 'new I\\RarityComponent('+p(obj(v)?v.value:v)+')';
        case 'minecraft:fire_resistant':return 'new I\\FireResistantComponent()';
      }
    }
    notes.push(path+': no safe adapter; implement manually.');return null;
  }
  function saturation(v){const names={poor:.1,low:.3,normal:.6,good:.8,max:1,supernatural:1.2};return typeof v==='string'&&own(names,v)?names[v]:number(v,'saturation_modifier',0,100);}
  const json=v=>JSON.stringify(v,null,2)+'\n';
  function convert(source,options={}){
    const input=typeof source==='string'?parse(source):parse(JSON.stringify(source));
    if(!obj(input))error('Paste a complete minecraft:block or minecraft:item JSON definition.');
    const kinds=['block','item'].filter(k=>own(input,'minecraft:'+k));if(kinds.length!==1)error('Provide exactly one minecraft:block or minecraft:item definition.');
    const kind=kinds[0],data=input['minecraft:'+kind];if(!obj(data)||!obj(data.description)||!obj(data.components))error('description and components must be objects.');
    const id=identifier(data.description.identifier),cls=className(options.className??'GeneratedContent'),ns='content'+cls.toLowerCase();
    if(['vector3','axisalignedbb','raytraceresult','box','material','rendermethod','tintmethod','b','i','blockidentifier','blocktypeinfo','blockbreakinfo','itemidentifier','traitblock','slabblock','contentplugin'].includes(cls.toLowerCase()))error('Choose a class name that does not conflict with a generated import.');
    const notes=[],components=[],setup=[],methods=[],permutations=[],states=stateDefinitions(data.description.states??{}),c=data.components;
    if(kind==='block'){
      for(const [map,path] of [[c,'components'],...(data.permutations??[]).map((p,i)=>[p.components,'permutations['+i+'].components'])]){
        if(obj(map)&&obj(map['minecraft:collision_box'])){map['minecraft:collision_box']=[map['minecraft:collision_box']];notes.push(path+'.minecraft:collision_box: normalized legacy single box to an array for Minecraft 1.26.60.');}
      }
    }
    for(const k of Object.keys(input))if(!['format_version','minecraft:'+kind].includes(k))notes.push(k+': not converted.');
    for(const k of Object.keys(data))if(!['description','components','permutations'].includes(k))notes.push('minecraft:'+kind+'.'+k+': not converted.');
    for(const k of Object.keys(data.description))if(!['identifier','states','traits','menu_category'].includes(k))notes.push('description.'+k+': not converted.');
    if(data.description.menu_category)notes.push('description.menu_category: use the Customies creative category API after registration.');
    let stateProduct=Object.values(states).reduce((n,a)=>n*a.length,1);
    for(const [key,list] of Object.entries(states))setup.push('$this->addState(new \\customiesdevs\\customies\\block\\states\\BlockState('+php(key)+', '+value(list)+'));');
    if(kind==='item'&&setup.length)error('Block states belong in a block definition.');
    if(kind==='block')for(const [key,v] of Object.entries(data.description.traits??{})){
      if(!obj(v))error('description.traits.'+key+': expected an object.');
      if(!['minecraft:placement_direction','minecraft:placement_position'].includes(key)){notes.push('description.traits.'+key+': add this trait and its server connection/topology rules manually.');continue;}
      const allowed=key==='minecraft:placement_direction'?['enabled_states','y_rotation_offset','blocks_to_corner_with']:['enabled_states'];
      for(const k of Object.keys(v))if(!allowed.includes(k))notes.push('description.traits.'+key+'.'+k+': not converted.');
      const enabled=v.enabled_states;
      if(!Array.isArray(enabled)||!enabled.length)error('Traits require a nonempty enabled_states array.');
      let phpTrait=null,definitions={};
      if(key==='minecraft:placement_direction'){
        definitions={'minecraft:cardinal_direction':['south','west','north','east'],'minecraft:facing_direction':['down','up','south','north','west','east'],'minecraft:sixteen_way_rotation':Array.from({length:16},(_,i)=>i),'minecraft:corner_and_cardinal_direction':null};
        phpTrait='new \\customiesdevs\\customies\\block\\traits\\PlacementDirectionTrait('+value(enabled)+', '+number(v.y_rotation_offset??0,key,0,270,true)+', '+value(v.blocks_to_corner_with??[])+')';
      }else if(key==='minecraft:placement_position'){
        definitions={'minecraft:vertical_half':['bottom','top'],'minecraft:block_face':['down','up','south','north','west','east']};
        phpTrait='new \\customiesdevs\\customies\\block\\traits\\PlacementPositionTrait('+value(enabled)+')';
      }
      if(!phpTrait){notes.push('description.traits.'+key+': no automatic trait adapter; add this trait and server topology manually.');continue;}
      if(new Set(enabled).size!==enabled.length)error('Duplicate enabled trait states.');
      for(const name of enabled){
        if(!own(definitions,name))error(key+': unsupported enabled state '+name);
        const defs=definitions[name]===null?{'minecraft:cardinal_direction':definitions['minecraft:cardinal_direction'],'minecraft:corner':['none','inner_left','inner_right','outer_left','outer_right']}:{[name]:definitions[name]};
        for(const [k,list] of Object.entries(defs)){if(own(states,k))error('State '+k+' is already declared or supplied by another trait.');states[k]=list;stateProduct*=list.length;}
      }
      setup.push('$this->addTrait('+phpTrait+');');
    }
    if(stateProduct>65536)error('Combined states and traits exceed 65,536 authoring variants.');
    const mapped=(map,path)=>{if(!obj(map))error(path+': expected a component map.');const out=[];for(const [k,v] of Object.entries(map)){try{const expression=adapter(kind,k,v,notes,path+'.'+k);if(expression)out.push(expression);}catch(e){error(path+'.'+k+': '+e.message);}}return out;};
    components.push(...mapped(c,'components'));
    if(data.permutations!==undefined){
      if(kind!=='block'||!Array.isArray(data.permutations)||data.permutations.length>256)error('Use at most 256 block permutations.');
      for(const [i,p] of data.permutations.entries()){
        if(!obj(p)||typeof p.condition!=='string')error('Permutation needs condition and components.');
        for(const k of Object.keys(p))if(!['condition','components'].includes(k))notes.push('permutations['+i+'].'+k+': not converted.');
        const list=mapped(p.components,'permutations['+i+'].components'),compiled=condition(p.condition,states);
        if(list.length)setup.push('$this->addPermutation(new \\customiesdevs\\customies\\block\\permutations\\BlockPermutation('+php(p.condition)+', '+list[0]+', ['+list.slice(1).join(', ')+']));');
        if(compiled)permutations.push({test:compiled,c:p.components});else notes.push('permutations['+i+']: condition kept for the client; server evaluation requires manual PHP (only block_state comparisons, booleans and parentheses are translated).');
      }
    }
    const scalar=(v,fallback)=>obj(v)?v.value??v.duration??fallback:v??fallback;
    const display=scalar(c['minecraft:display_name'],id);
    text(display,'display_name');let base='\\pocketmine\\item\\Item',ctor='new ItemIdentifier($id), '+php(display);
    if(kind==='block'){
      base='\\customiesdevs\\customies\\block\\TraitBlock';
      ctor='new BlockIdentifier($id), '+php(display)+', new BlockTypeInfo(new BlockBreakInfo(1.0))';
      notes.push('Server break hardness is initialized to 1.0; tune BlockBreakInfo, tool requirements, drops and explosion policy for your game.');
      function physical(name,defaultValue,render){
        let out='        $result = '+render(c[name]??defaultValue)+';\n';
        for(const p of permutations)if(own(p.c,name))out+='        if('+p.test+'){ $result = '+render(p.c[name])+'; }\n';
        return out+'        return $result;';
      }
      const localBoxes=v=>'['+boxes(v).map(b=>aabb(b.local)).join(', ')+']';
      const shapePhysical=name=>physical(name,true,localBoxes).replace('return $result;','return $this->rotatePhysicalBoxes($result);');
      methods.push('    protected function recalculateCollisionBoxes(): array {\n'+shapePhysical('minecraft:collision_box')+'\n    }');
      methods.push('    public function isSolid(): bool { return $this->isFullCube(); }');
      methods.push('    private function selectionBoxes(): array {\n'+shapePhysical('minecraft:selection_box')+'\n    }');
      function turns(v){if(!obj(v))return 0;const r=v.rotation??[0,0,0];if(r[0]!==0||r[2]!==0||JSON.stringify(v.scale??[1,1,1])!=='[1,1,1]'||['translation','rotation_pivot','scale_pivot'].some(k=>JSON.stringify(v[k]??[0,0,0])!=='[0,0,0]')){notes.push('transformation: non-Y rotation, scale, translation or custom pivots require matching PHP boxes manually.');return 0;}return ((r[1]/90)%4+4)%4;}
      let turnCode='        $turns = '+turns(c['minecraft:transformation'])+';\n';
      for(const p of permutations)if(own(p.c,'minecraft:transformation'))turnCode+='        if('+p.test+'){ $turns = '+turns(p.c['minecraft:transformation'])+'; }\n';
      methods.push(`    private function rotatePhysicalBoxes(array $boxes): array {
${turnCode}        $out = [];
        foreach($boxes as $b){
            [$x,$z,$X,$Z] = [$b->minX,$b->minZ,$b->maxX,$b->maxZ];
            for($i=0;$i<$turns;++$i){ [$x,$z,$X,$Z] = [$z,1-$X,$Z,1-$x]; }
            $out[] = new AxisAlignedBB($x,$b->minY,$z,$X,$b->maxY,$Z);
        }
        return $out;
    }`);
      methods.push(`    public function calculateIntercept(Vector3 $pos1, Vector3 $pos2): ?RayTraceResult {
        $nearest = null; $distance = INF;
        foreach($this->selectionBoxes() as $box){
            $hit = $box->offsetCopy($this->getPosition()->x, $this->getPosition()->y, $this->getPosition()->z)->calculateIntercept($pos1, $pos2);
            if($hit !== null && ($d = $hit->hitVector->distanceSquared($pos1)) < $distance){ $nearest = $hit; $distance = $d; }
        }
        return $nearest;
    }`);
      for(const [name,method,type,def] of [['minecraft:light_emission','getLightLevel','int',0],['minecraft:light_dampening','getLightFilter','int',15],['minecraft:friction','getFrictionFactor','float',.6]])methods.push('    public function '+method+'(): '+type+' {\n'+physical(name,def,x=>String(number(x,name,0,type==='int'?15:.9,type==='int')))+'\n    }');
      if(c['minecraft:replaceable'])methods.push('    public function canBeReplaced(): bool { return true; }');
    }else{
      const food=c['minecraft:food'],durability=c['minecraft:durability'],wearable=c['minecraft:wearable'];
      if(c['minecraft:durability_sensor']&&!durability)error('durability_sensor requires minecraft:durability.');
      if(food&&(durability||wearable))error('Combining Food and Durable/Armor requires a custom PHP base class; split the item or implement that class manually.');
      if(wearable){
        const slots={'slot.armor.head':'HEAD','slot.armor.chest':'CHEST','slot.armor.legs':'LEGS','slot.armor.feet':'FEET'};
        if(!own(slots,wearable.slot))error('Automatic armor generation supports head, chest, legs and feet slots.');
        base='\\pocketmine\\item\\Armor';ctor+=', new \\pocketmine\\item\\ArmorTypeInfo('+number(wearable.protection??0,'protection',0,100,true)+', '+number(durability?.max_durability??256,'max_durability',1,1e6,true)+', \\pocketmine\\inventory\\ArmorInventory::SLOT_'+slots[wearable.slot]+')';
        notes.push('Armor needs matching resource-pack attachables, material, textures and animation; the generator does not synthesize those assets.');
      }else if(durability){base='\\pocketmine\\item\\Durable';methods.push('    public function getMaxDurability(): int { return '+number(durability.max_durability,'max_durability',1,1e6,true)+'; }');}
      else if(food){base='\\pocketmine\\item\\Food';methods.push('    public function getFoodRestore(): int { return '+number(food.nutrition??0,'nutrition',0,100,true)+'; }','    public function getSaturationRestore(): float { return '+(Number(food.nutrition??0)*saturation(food.saturation_modifier??.6)*2)+'; }','    public function requiresHunger(): bool { return '+value(!(food.can_always_eat??false))+'; }');
        if(food.using_converts_to){methods.push('    public function getResidue(): \\pocketmine\\item\\Item { return \\pocketmine\\item\\StringToItemParser::getInstance()->parse('+php(food.using_converts_to)+') ?? \\pocketmine\\item\\VanillaItems::AIR(); }');notes.push('Ensure using_converts_to resolves through StringToItemParser before players consume this item.');}
      }
      const stack=number(scalar(c['minecraft:max_stack_size'],food?64:(durability||wearable?1:64)),'max_stack_size',1,64,true);
      if((durability||wearable)&&stack!==1)error('Durable items and armor must have stack size 1.');
      methods.push('    public function getMaxStackSize(): int { return '+stack+'; }');
      if(c['minecraft:fuel']!==undefined)methods.push('    public function getFuelTime(): int { return '+Math.round(number(scalar(c['minecraft:fuel'],0),'fuel',.05,1e6)*20)+'; }');
      if(c['minecraft:damage']!==undefined)methods.push('    public function getAttackPoints(): int { return '+number(scalar(c['minecraft:damage'],0),'damage',0,32767,true)+'; }');
      for(const k of Object.keys(c))if(['minecraft:cooldown','minecraft:durability_sensor','minecraft:use_modifiers','minecraft:fire_resistant','minecraft:storage_weight_limit'].includes(k))notes.push('components.'+k+': client metadata converted; implement the matching authoritative PHP action/policy.');
    }
    const imports=`use customiesdevs\\customies\\block\\component as B;
use customiesdevs\\customies\\item\\component as I;
use customiesdevs\\customies\\block\\properties\\{Box, Material, RenderMethod, TintMethod};
use pocketmine\\math\\{Vector3, AxisAlignedBB, RayTraceResult};
use pocketmine\\block\\{BlockIdentifier, BlockTypeInfo, BlockBreakInfo};
use pocketmine\\item\\ItemIdentifier;`;
    const itemTraits=kind==='item'?' implements \\customiesdevs\\customies\\item\\ItemComponents':'',traits=kind==='item'?'    use \\customiesdevs\\customies\\item\\ItemComponentsTrait;\n':'';
    let code=`<?php\ndeclare(strict_types=1);\nnamespace ${ns};\n\n${imports}\n\nfinal class ${cls} extends ${base}${itemTraits} {\n${traits}    public function __construct(int $id){\n${setup.map(x=>'        '+x).join('\n')}\n        parent::__construct(${ctor});\n${components.map(x=>'        $this->addComponent('+x+');').join('\n')}\n    }\n${methods.join('\n')}\n}\n`;
    const title=kind==='block'?'Block':'Item',factory='\\customiesdevs\\customies\\'+kind+'\\Customies'+title+'Factory';
    const files={'plugin.yml':`name: ${cls}Plugin\nmain: ${ns}\\ContentPlugin\nsrc-namespace-prefix: ${ns}\nversion: 1.0.0\napi: 5.51.0\nload: POSTWORLD\ndepend: [Customies-Axolotl]\n`,['src/'+cls+'.php']:code,'src/ContentPlugin.php':`<?php\ndeclare(strict_types=1);\nnamespace ${ns};\nfinal class ContentPlugin extends \\pocketmine\\plugin\\PluginBase {\n    protected function onEnable(): void {\n        $id = \\pocketmine\\${kind}\\${title}TypeIds::newId();\n        ${factory}::getInstance()->register${title}(static fn() => new ${cls}($id), ${php(id)});\n    }\n}\n`,'source.json':json(input)};
    const report={kind,identifier:id,class:ns+'\\'+cls,state_combinations:stateProduct,converted_components:components.length,review:notes};
    files['conversion-report.json']=json(report);files['README.txt']='Target: Customies 1.9.0, Axolotl API 5.51.0, Minecraft 26.60.\nReview conversion-report.json. Component metadata is not a replacement for server gameplay.\nAdd required PNGs/geometries/attachables to a resource pack, run PHP host tests, then test a Bedrock client.\n';
    return {files,report};
  }
  function generate(options){
    const kind=options.kind??'block',preset=options.preset??'cube',id=identifier(options.identifier??'mycontent:prism'),texture=options.texture??id.replaceAll(':','_').replaceAll('/','_'),name=options.name??'Prism';
    if(!['block','item'].includes(kind))error('Choose item or block.');if(!/^[a-z0-9_]+$/.test(texture))error('Texture key must use lowercase letters, digits and underscores.');
    const c={'minecraft:display_name':text(name,'name')},description={identifier:id};
    if(kind==='item'){
      if(!['basic','food','durable','armor'].includes(preset))error('Choose an item preset.');
      c['minecraft:icon']=texture;c['minecraft:max_stack_size']=number(Number(options.stack??(['armor','durable'].includes(preset)?1:64)),'stack',1,64,true);
      if(preset==='food'){c['minecraft:food']={nutrition:number(Number(options.nutrition??6),'nutrition',0,100,true),saturation_modifier:number(Number(options.saturation??.6),'saturation',0,100)};c['minecraft:use_animation']='eat';c['minecraft:use_modifiers']={use_duration:1.6,movement_modifier:.35};}
      if(['durable','armor'].includes(preset))c['minecraft:durability']={max_durability:number(Number(options.durability??256),'durability',1,1e6,true)};
      if(preset==='durable')c['minecraft:damage']=number(Number(options.damage??5),'damage',0,32767,true);
      if(preset==='armor')c['minecraft:wearable']={slot:options.slot??'slot.armor.head',protection:number(Number(options.protection??2),'protection',0,100,true)};
    }else{
      if(!['cube','cardinal','slab'].includes(preset))error('Choose a block preset.');
      c['minecraft:geometry']='minecraft:geometry.full_block';c['minecraft:material_instances']={'*':{texture,render_method:'opaque'}};c['minecraft:collision_box']=true;c['minecraft:selection_box']=true;
      c['minecraft:destructible_by_mining']={seconds_to_destroy:1.5*number(Number(options.hardness??1),'hardness',0,100)};
      c['minecraft:light_emission']=number(Number(options.light??0),'light',0,15,true);
      if(preset==='cardinal')description.traits={'minecraft:placement_direction':{enabled_states:['minecraft:cardinal_direction']}};
    }
    const source={format_version:'1.26.60',['minecraft:'+kind]:{description,components:c}};
    if(options.states){if(kind!=='block'||preset==='slab')error('Additional states are available for cube/cardinal blocks; use the converter for a custom slab subclass.');description.states=stateDefinitions(parse(options.states));}
    if(options.components){const extra=parse(options.components);if(!obj(extra))error('Extra components must be a JSON object.');if(preset==='slab')error('Use the converter for custom slab component overrides.');Object.assign(c,extra);}
    if(kind==='block'&&preset==='cardinal')source['minecraft:block'].permutations=['north','west','south','east'].map((d,i)=>({condition:"q.block_state('minecraft:cardinal_direction') == '"+d+"'",components:{'minecraft:transformation':{rotation:[0,i*90,0]}}}));
    const result=convert(source,{className:options.className??'PrismContent'}),files=result.files;
    if(kind==='block'&&preset==='slab'){
      const cls=options.className??'PrismContent',ns='content'+cls.toLowerCase(),geo='geometry.'+texture;
      files['src/'+cls+'.php']=`<?php\ndeclare(strict_types=1);\nnamespace ${ns};\nuse customiesdevs\\customies\\block\\{SlabBlock, preset\\BlockPresets};\nuse customiesdevs\\customies\\block\\component as B;\nuse customiesdevs\\customies\\block\\properties\\Material;\nuse pocketmine\\block\\{BlockIdentifier, BlockTypeInfo, BlockBreakInfo};\nfinal class ${cls} extends SlabBlock {\n    public function __construct(int $id){\n        BlockPresets::slab($this, ${php(geo+'.bottom')}, ${php(geo+'.top')});\n        $this->addComponent(new B\\MaterialInstancesComponent([new Material('*', ${php(texture)})]));\n        $this->addComponent(new B\\DestructibleByMiningComponent(1.5));\n        parent::__construct(new BlockIdentifier($id), ${php(name)}, new BlockTypeInfo(new BlockBreakInfo(1.0)));\n    }\n}\n`;
      for(const half of ['bottom','top'])files['resource_pack/models/blocks/'+texture+'_'+half+'.geo.json']=json({format_version:'1.12.0','minecraft:geometry':[{description:{identifier:geo+'.'+half,texture_width:16,texture_height:16,visible_bounds_width:2,visible_bounds_height:2,visible_bounds_offset:[0,.5,0]},bones:[{name:'root',pivot:[0,0,0],cubes:[{origin:[-8,half==='top'?8:0,-8],size:[16,8,16],uv:[0,0]}]}]}]});
      delete files['source.json'];result.report.state_combinations=4;result.report.review.push('Slab preset uses SlabBlock for merging, saved-state migration, physical half boxes and two-item double drops.');
    }
    const uuid=()=>{if(root.crypto?.randomUUID)return root.crypto.randomUUID();return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=Math.floor(Math.random()*16);return (c==='x'?r:(r&3)|8).toString(16);});};
    files['resource_pack/manifest.json']=json({format_version:2,header:{name:name+' resources',description:'Resources for '+id,uuid:uuid(),version:[1,0,0],min_engine_version:[1,26,60]},modules:[{type:'resources',uuid:uuid(),version:[1,0,0]}]});
    files['resource_pack/textures/'+(kind==='item'?'item_texture':'terrain_texture')+'.json']=json({resource_pack_name:name,texture_name:kind==='item'?'atlas.items':'atlas.terrain',texture_data:{[texture]:{textures:'textures/'+(kind==='item'?'items':'blocks')+'/'+texture}}});
    result.report.review.push('Required asset: resource_pack/textures/'+(kind==='item'?'items':'blocks')+'/'+texture+'.png (supply your own artwork).');
    const classPath='src/'+(options.className??'PrismContent')+'.php';
    if(kind==='block'){const hardness=number(Number(options.hardness??1),'hardness',0,100);files[classPath]=files[classPath].replace('new BlockBreakInfo(1.0)','new BlockBreakInfo('+hardness+')');result.report.review=result.report.review.map(note=>note.startsWith('Server break hardness is initialized')?'Server hardness is '+hardness+'. Review tool requirements, drops, mining component overrides and explosion policy.':note);
      if(preset==='slab'){const light=number(Number(options.light??0),'light',0,15,true);files[classPath]=files[classPath].replace('new B\\DestructibleByMiningComponent(1.5)','new B\\DestructibleByMiningComponent('+(hardness*1.5)+')');files[classPath]=files[classPath].replace('BlockPresets::slab($this,',' $this->addComponent(new B\\LightEmissionComponent('+light+'));\n        BlockPresets::slab($this,');files[classPath]=files[classPath].replace(/\n}\n$/,()=> '\n    public function getLightLevel(): int { return '+light+'; }\n}\n');}
    }
    if(options.lore){const lore=String(options.lore).split(/\r?\n/).map(s=>text(s,'lore'));if(lore.length>16)error('Use at most 16 lore lines.');
      // Insert through a method override for block items, or directly after item component setup.
      if(kind==='block')files[classPath]=files[classPath].replace(/\n}\n$/,()=> '\n    public function asItem(): \\pocketmine\\item\\Item { return parent::asItem()->setLore('+value(lore)+'); }\n}\n');
      else files[classPath]=files[classPath].replace(/parent::__construct\([^\n]+\);/,match=>match+'\n        $this->setLore('+value(lore)+');');
    }
    files['conversion-report.json']=json(result.report);return result;
  }
  // Small uncompressed ZIP writer: UTF-8 names/content, no network or third-party runtime.
  function zip(files){
    const enc=new TextEncoder(),chunks=[],central=[];let offset=0;
    const header=(length)=>{const a=new Uint8Array(length);return [a,new DataView(a.buffer)];};
    const crc=data=>{let n=0xffffffff;for(const byte of data){n^=byte;for(let k=0;k<8;k++)n=(n>>>1)^((n&1)?0xedb88320:0);}return (n^0xffffffff)>>>0;};
    for(const [path,text] of Object.entries(files)){
      if(path.startsWith('/')||path.split('/').some(p=>p==='..'))error('Unsafe archive path.');
      const name=enc.encode(path),data=text instanceof Uint8Array?text:enc.encode(text),sum=crc(data),[a,v]=header(30+name.length);v.setUint32(0,0x04034b50,true);v.setUint16(4,20,true);v.setUint16(6,0x800,true);v.setUint32(14,sum,true);v.setUint32(18,data.length,true);v.setUint32(22,data.length,true);v.setUint16(26,name.length,true);a.set(name,30);chunks.push(a,data);
      const [b,w]=header(46+name.length);w.setUint32(0,0x02014b50,true);w.setUint16(4,20,true);w.setUint16(6,20,true);w.setUint16(8,0x800,true);w.setUint32(16,sum,true);w.setUint32(20,data.length,true);w.setUint32(24,data.length,true);w.setUint16(28,name.length,true);w.setUint32(42,offset,true);b.set(name,46);central.push(b);offset+=a.length+data.length;
    }
    const start=offset;chunks.push(...central);for(const b of central)offset+=b.length;
    const [end,v]=header(22);v.setUint32(0,0x06054b50,true);v.setUint16(8,central.length,true);v.setUint16(10,central.length,true);v.setUint32(12,offset-start,true);v.setUint32(16,start,true);chunks.push(end);
    const out=new Uint8Array(offset+22);let at=0;for(const b of chunks){out.set(b,at);at+=b.length;}return out;
  }
  const api={parse,visualize,bounds,rotate,condition,adapter,identifier,className,value,php,stateDefinitions,convert,generate,zip};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;root.CustomiesAuthoring=api;
})(typeof globalThis!=='undefined'?globalThis:this);
