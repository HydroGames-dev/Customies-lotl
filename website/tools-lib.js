(function(root){
  'use strict';
  const fail = message => { throw new Error(message); };
  function parse(text){if(typeof text!=='string'||text.length>65536)fail('Use at most 64 KiB of JSON.');try{return JSON.parse(text);}catch{fail('The input is not valid JSON.');}}
  function object(v){return v!==null&&typeof v==='object'&&!Array.isArray(v);}
  function scalar(v){return typeof v==='boolean'||typeof v==='string'||(typeof v==='number'&&Number.isSafeInteger(v));}
  function states(text){
    const input=parse(text);if(!object(input))fail('Enter an object mapping state names to arrays.');
    const entries=Object.entries(input);if(entries.length>64)fail('Use no more than 64 state properties.');
    let combinations=1n;const warnings=[],properties=[];
    for(const [name,values] of entries){
      if(!name.includes(':'))fail(`Namespace the state name: ${name}`);
      if(!Array.isArray(values)||!values.length||values.length>4096)fail(`${name}: use 1–4096 explicit values.`);
      if(!values.every(scalar)||!values.every(v=>typeof v===typeof values[0]))fail(`${name}: values must share one bool, safe-integer or string type.`);
      if(new Set(values).size!==values.length)fail(`${name}: duplicate enum values.`);
      if(values.length>16)warnings.push(`${name} has more than 16 values: check target-client enum limits, not just PHP acceptance.`);
      combinations*=BigInt(values.length);properties.push({name,values:values.length,default:values[0]});
    }
    if(combinations>65536n)warnings.push('Large state product: review palette, memory and client limits before adopting this design.');
    return {combinations:combinations.toString(),properties,warnings,note:'Mathematical Cartesian product, not a performance estimate or engine validation.'};
  }
  function vector(text,name){const v=parse(text);if(!Array.isArray(v)||v.length!==3||!v.every(x=>typeof x==='number'&&Number.isFinite(x)))fail(`${name} must be three finite numbers.`);return v;}
  const rounded=v=>Math.round(v*1e6)/1e6;
  function boxes(originText,sizeText,units='pixels',direction='north'){
    const a=vector(originText,'Origin'),b=vector(sizeText,'Size');
    if([...a,...b].some(x=>Math.abs(x)>1000000))fail('Use coordinate magnitudes at most 1,000,000; this is a tool safety bound, not an engine limit.');
    if(b.some(x=>x<=0))fail('Physical box sizes must be positive. Disable collision explicitly for an empty collider.');
    if(!['pixels','blocks'].includes(units))fail('Unknown coordinate units.');
    if(!['north','west','south','east'].includes(direction))fail('Choose a cardinal direction.');
    const origin=units==='pixels'?a:[8-(a[0]+b[0])*16,a[1]*16,a[2]*16-8];
    const size=units==='pixels'?b:b.map(x=>x*16);
    let [x,y,z]=[(8-origin[0]-size[0])/16,origin[1]/16,origin[2]/16+.5],xx=(8-origin[0])/16,yy=y+size[1]/16,zz=z+size[2]/16;
    const voxel={min:[x*16,y*16,z*16].map(rounded),max:[xx*16,yy*16,zz*16].map(rounded)};
    const warnings=[];if([x,y,z].some(v=>v<0)||[xx,yy,zz].some(v=>v>1))warnings.push('This extends outside one occupied cell; check component/host limits and reserve actual cells for structures.');
    for(let i=0;i<['north','west','south','east'].indexOf(direction);i++)[x,z,xx,zz]=[z,1-xx,zz,1-x];
    const bounds=[x,y,z,xx,yy,zz].map(rounded);
    return {client_unrotated:{origin:origin.map(rounded),size:size.map(rounded)},collision_min_max:voxel,server_rotated_bounds:bounds,php:`new AxisAlignedBB(${bounds.join(', ')})`,warnings,note:'Geometry X is mirrored when converting to 26.60 voxel min/max and server bounds. Client vectors remain unrotated; pair them with the visual transformation. Selection/ray behavior still needs testing.'};
  }
  function identifiers(text){
    if(typeof text!=='string'||text.length>65536)fail('Use at most 64 KiB of identifiers.');
    const seen=new Set();return text.split(/\r?\n/).map(x=>x.trim()).filter(Boolean).map(id=>{
      const messages=[];if(!/^[a-z][a-z0-9_]*:[a-z][a-z0-9_]*$/.test(id))messages.push('Does not match the recommended lowercase namespace:path_with_underscores convention.');
      if(id.startsWith('minecraft:'))messages.push('Reserved vanilla namespace: use your own namespace for new content.');
      if(seen.has(id))messages.push('Duplicate identifier.');seen.add(id);
      return {identifier:id,messages,convention_ok:messages.length===0};
    });
  }
  const shape=v=>v===null?'null':Array.isArray(v)?'array':typeof v;
  function inspect(text,kind,api){
    const input=parse(text);if(!object(input)||!Object.keys(input).length)fail('Enter a non-empty object keyed by component identifier.');
    if(!api||!api[kind])fail('Select block or item wrapper data.');
    const known=new Map(Object.values(api[kind]).map(v=>[v.name,v]));
    const result=[];
    for(const [name,value] of Object.entries(input)){
      const entry=known.get(name),messages=[];
      if(!entry){result.push({component:name,known:false,messages:['No wrapper with this name in this branch. This is not a statement about native engine support.']});continue;}
      const fixture=entry.serialized_example;
      if(shape(value)!==shape(fixture))messages.push(`Top-level shape differs from the exported example (${shape(fixture)}).`);
      if(object(value)&&object(fixture)){
        for(const key of Object.keys(fixture))if(!(key in value))messages.push(`Example field absent: ${key}. It may be optional; consult the reference.`);
        for(const [key,v] of Object.entries(value)){
          if(!(key in fixture))messages.push(`Field not observed in the fixture: ${key}. Check the implementation.`);
          else if(shape(v)!==shape(fixture[key]))messages.push(`Field ${key}: example shape is ${shape(fixture[key])}, input is ${shape(v)}.`);
        }
      }
      result.push({component:name,known:true,class:entry.class,messages});
    }
    return {components:result,note:'Comparison to exported wrapper examples only; not a complete schema, NBT type checker or Bedrock validator.'};
  }
  function starter(options){
    const {kind,namespace,path,plugin,stack=1}=options;
    if(!['block','item'].includes(kind))fail('Choose block or item.');
    if(!/^[a-z][a-z0-9_]*$/.test(namespace)||namespace==='minecraft')fail('Use your own lowercase namespace with underscores.');
    if(!/^[a-z][a-z0-9_]*$/.test(path))fail('Use a lowercase content path with underscores.');
    if(!/^[A-Za-z][A-Za-z0-9_]{0,47}$/.test(plugin))fail('Use a plugin name of 1–48 letters, digits or underscores, starting with a letter.');
    if(!Number.isInteger(stack)||stack<1||stack>64)fail('Stack size must be an integer from 1 to 64.');
    const id=namespace+':'+path,texture=namespace+'_'+path,content=kind==='item'?'StarterItem':'StarterBlock';
    const phpNamespace=plugin.toLowerCase();
    const manifest=`name: ${plugin}\nmain: ${phpNamespace}\\Main\nsrc-namespace-prefix: ${phpNamespace}\nversion: 1.0.0\napi: 5.51.0\nload: POSTWORLD\ndepend: [Customies-Axolotl]\n`;
    const main=`<?php\ndeclare(strict_types=1);\nnamespace ${phpNamespace};\n\nfinal class Main extends \\pocketmine\\plugin\\PluginBase {\n    protected function onEnable(): void {\n        $id = \\pocketmine\\${kind}\\${kind==='item'?'ItemTypeIds':'BlockTypeIds'}::newId();\n        \\customiesdevs\\customies\\${kind}\\Customies${kind==='item'?'Item':'Block'}Factory::getInstance()->register${kind==='item'?'Item':'Block'}(\n            static fn() => new ${content}($id), '${id}'\n        );\n    }\n}\n`;
    const item=`<?php\ndeclare(strict_types=1);\nnamespace ${phpNamespace};\n\nuse customiesdevs\\customies\\item\\{ItemComponents, ItemComponentsTrait};\nuse customiesdevs\\customies\\item\\preset\\ItemPresets;\nuse pocketmine\\item\\{Item, ItemIdentifier};\n\nfinal class StarterItem extends Item implements ItemComponents {\n    use ItemComponentsTrait;\n    public function __construct(int $id){\n        parent::__construct(new ItemIdentifier($id), '${plugin} Item');\n        ItemPresets::basic($this, '${texture}', '${plugin} Item', ${stack});\n    }\n    public function getMaxStackSize(): int { return ${stack}; }\n}\n`;
    const block=`<?php\ndeclare(strict_types=1);\nnamespace ${phpNamespace};\n\nuse customiesdevs\\customies\\block\\TraitBlock;\nuse customiesdevs\\customies\\block\\component as B;\nuse customiesdevs\\customies\\block\\properties\\Material;\nuse pocketmine\\block\\{BlockIdentifier, BlockTypeInfo, BlockBreakInfo};\n\nfinal class StarterBlock extends TraitBlock {\n    public function __construct(int $id){\n        $this->addComponent(new B\\GeometryComponent());\n        $this->addComponent(new B\\MaterialInstancesComponent([new Material('*', '${texture}')]));\n        $this->addComponent(new B\\CollisionBoxComponent());\n        $this->addComponent(new B\\SelectionBoxComponent());\n        $this->addComponent(new B\\DestructibleByMiningComponent(1.0));\n        parent::__construct(new BlockIdentifier($id), '${plugin} Block', new BlockTypeInfo(new BlockBreakInfo(1.0)));\n    }\n}\n`;
    const atlas={resource_pack_name:plugin,texture_name:kind==='item'?'atlas.items':'atlas.terrain',texture_data:{[texture]:{textures:`textures/${kind==='item'?'items':'blocks'}/${texture}`}}};
    return {'plugin.yml':manifest,'src/Main.php':main,[`src/${content}.php`]:kind==='item'?item:block,[`resource_pack/textures/${kind==='item'?'item_texture':'terrain_texture'}.json`]:JSON.stringify(atlas,null,2),'README.txt':`Starter outline for ${id}.\nProvide your own resource-pack manifest UUIDs and ${texture}.png at the atlas path.\nThis is not a complete installable pack or a compatibility guarantee. Add gameplay in the appropriate host hooks.\n`};
  }
  const api={parse,states,boxes,identifiers,inspect,starter};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;
  root.CustomiesTools=api;
})(typeof globalThis!=='undefined'?globalThis:this);
