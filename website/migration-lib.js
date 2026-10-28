(function(root){
  'use strict';
  const A=root.CustomiesAuthoring||(typeof require==='function'?require('./authoring-lib.js'):null);
  const parser=root.acorn||(typeof require==='function'?require('acorn'):null);
  const Z=root.fflate||(typeof require==='function'?require('fflate'):null);
  const LIMITS=Object.freeze({archive:32*1024*1024,total:64*1024*1024,file:8*1024*1024,entries:1500,depth:2,script:131072});
  const fail=m=>{throw Error(m);},own=(o,k)=>Object.prototype.hasOwnProperty.call(o,k),isObject=v=>v!==null&&typeof v==='object'&&!Array.isArray(v);
  const decode=bytes=>new TextDecoder('utf-8',{fatal:true}).decode(bytes),encode=s=>new TextEncoder().encode(s),pretty=d=>JSON.stringify(d,null,2)+'\n';
  function safePath(path){
    if(typeof path!=='string'||path.length>240||/[\x00-\x1f:]/.test(path))fail('Invalid archive path.');
    path=path.replaceAll('\\','/');if(path.startsWith('/')||path.split('/').some(p=>p==='..'||p==='.'))fail('Unsafe archive path: '+path);
    if(path.split('/').some(p=>p&&(/[. ]$/.test(p)||/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i.test(p))))fail('Unsafe Windows archive path: '+path);
    if(!path||path.split('/').some((p,i,a)=>!p&&i<a.length-1))fail('Empty archive path segment.');return path;
  }
  function jsonc(source){
    if(source.length>LIMITS.script)fail('JSON definition exceeds 128 KiB.');
    let out='',quoted=false,escape=false;
    for(let i=0;i<source.length;i++){
      const c=source[i];if(quoted){out+=c;if(escape)escape=false;else if(c==='\\')escape=true;else if(c==='"')quoted=false;continue;}
      if(c==='"'){quoted=true;out+=c;continue;}
      if(c==='/'&&source[i+1]==='/'){while(i<source.length&&source[i]!=='\n')i++;out+='\n';continue;}
      if(c==='/'&&source[i+1]==='*'){i+=2;while(i<source.length&&!(source[i]==='*'&&source[i+1]==='/')){out+=source[i]==='\n'?'\n':' ';i++;}if(i>=source.length)fail('Unclosed JSON comment.');i++;continue;}
      out+=c;
    }
    let clean='';quoted=false;escape=false;
    for(let i=0;i<out.length;i++){const c=out[i];if(quoted){clean+=c;if(escape)escape=false;else if(c==='\\')escape=true;else if(c==='"')quoted=false;continue;}if(c==='"')quoted=true;
      if(c===','&&/^[\s]*[}\]]/.test(out.slice(i+1)))continue;clean+=c;
    }
    return A.parse(clean.replace(/^\uFEFF/,''));
  }
  const crcTable=Array.from({length:256},(_,n)=>{for(let i=0;i<8;i++)n=(n>>>1)^((n&1)?0xedb88320:0);return n>>>0;});
  function crc32(bytes){let n=0xffffffff;for(const b of bytes)n=(n>>>8)^crcTable[(n^b)&255];return (n^0xffffffff)>>>0;}
  function unzip(bytes,budget){
    if(!(bytes instanceof Uint8Array)||bytes.length>LIMITS.archive||bytes.length<22)fail('Use an unencrypted ZIP-based pack of at most 32 MiB.');
    const view=new DataView(bytes.buffer,bytes.byteOffset,bytes.byteLength),u16=i=>view.getUint16(i,true),u32=i=>view.getUint32(i,true);let end=-1;
    for(let i=bytes.length-22;i>=Math.max(0,bytes.length-65557);i--)if(u32(i)===0x06054b50&&i+22+u16(i+20)===bytes.length){end=i;break;}
    if(end<0||u16(end+4)||u16(end+6)||u16(end+8)!==u16(end+10))fail('Split or invalid ZIP archive.');
    const count=u16(end+10),centralSize=u32(end+12),central=u32(end+16);if(count===65535||central===0xffffffff||central+centralSize!==end)fail('ZIP64 and malformed central directories are not supported.');
    if(budget.entries+count>LIMITS.entries)fail('Archive contains too many files (limit 1,500).');budget.entries+=count;
    const files=new Map(),seen=new Set(),ranges=[];let at=central,declared=0;
    for(let i=0;i<count;i++){
      if(at+46>end||u32(at)!==0x02014b50)fail('Invalid ZIP directory entry.');
      const flags=u16(at+8),method=u16(at+10),expectedCrc=u32(at+16),compressed=u32(at+20),size=u32(at+24),nameSize=u16(at+28),extraSize=u16(at+30),commentSize=u16(at+32),external=u32(at+38),local=u32(at+42);
      if(flags&1)fail('Encrypted packs cannot be converted.');if(![0,8].includes(method))fail('Unsupported ZIP compression method.');
      if(size>LIMITS.file||compressed>LIMITS.archive||size===0xffffffff||local===0xffffffff)fail('Archive entry exceeds the 8 MiB file limit or uses ZIP64.');
      if(at+46+nameSize+extraSize+commentSize>end)fail('Truncated ZIP directory.');
      const name=safePath(decode(bytes.subarray(at+46,at+46+nameSize))),key=name.toLowerCase();if(seen.has(key))fail('Duplicate archive path: '+name);seen.add(key);
      if(((external>>>16)&0xf000)===0xa000)fail('Symbolic links are not supported.');
      declared+=size;if(declared+budget.bytes>LIMITS.total)fail('Expanded archive exceeds 64 MiB.');
      if(local+30>central||u32(local)!==0x04034b50||u16(local+8)!==method||u16(local+6)!==flags)fail('Invalid ZIP local header.');
      const localName=u16(local+26),localExtra=u16(local+28),start=local+30+localName+localExtra,stop=start+compressed;
      if(stop>central||decode(bytes.subarray(local+30,local+30+localName))!==name.replaceAll('\\','/'))fail('ZIP entry bounds/name mismatch.');
      if(ranges.some(([a,b])=>local<b&&stop>a))fail('Overlapping ZIP entries.');ranges.push([local,stop]);
      if(!name.endsWith('/')){
        let chunks=[],actual=0;
        const accept=data=>{actual+=data.length;if(actual>size||actual>LIMITS.file||actual+budget.bytes>LIMITS.total)fail('Expanded file exceeds its declared size or the extraction limit.');chunks.push(data.slice());};
        const input=bytes.subarray(start,stop);
        if(method===0)accept(input);else{const stream=new Z.Inflate(data=>accept(data));for(let p=0;p<input.length;p+=4096)stream.push(input.subarray(p,p+4096),p+4096>=input.length);}
        if(actual!==size)fail('Truncated expanded file: '+name);
        const data=new Uint8Array(actual);let p=0;for(const chunk of chunks){data.set(chunk,p);p+=chunk.length;}
        if(crc32(data)!==expectedCrc)fail('ZIP checksum mismatch: '+name);budget.bytes+=actual;files.set(name,data);
      }
      at+=46+nameSize+extraSize+commentSize;
    }
    if(at!==end)fail('Unexpected ZIP directory data.');return files;
  }
  function collect(name,bytes){
    safePath(name);const result=new Map(),budget={bytes:0,entries:0};
    function visit(path,data,depth){
      if(/\.(?:mcpack|mcaddon|zip)$/i.test(path)){
        if(depth>LIMITS.depth)fail('Nested archive depth exceeds two.');
        for(const [inner,value] of unzip(data,budget))visit(path.replace(/\.[^.]+$/,'')+'/'+inner,value,depth+1);
      }else{
        if(depth===0){budget.entries++;budget.bytes+=data.length;if(data.length>LIMITS.file)fail('File exceeds 8 MiB.');}
        if(result.has(path))fail('Duplicate expanded path: '+path);result.set(path,data);
      }
    }
    visit(name,bytes,0);return result;
  }
  const eventTypes={itemUse:['pocketmine\\event\\player\\PlayerItemUseEvent','item'],playerBreakBlock:['pocketmine\\event\\block\\BlockBreakEvent','block'],itemConsume:['pocketmine\\event\\player\\PlayerItemConsumeEvent','item'],playerInteract:['pocketmine\\event\\player\\PlayerInteractEvent','block']};
  const effects=new Set(['speed','slowness','haste','mining_fatigue','strength','instant_health','instant_damage','jump_boost','nausea','regeneration','resistance','fire_resistance','water_breathing','invisibility','blindness','night_vision','hunger','weakness','poison','wither','health_boost','absorption','saturation','levitation','fatal_poison','conduit_power','darkness']);
  const prop=n=>n&&!n.computed&&n.type==='MemberExpression'&&n.property.type==='Identifier'?n.property.name:null;
  const chain=n=>n?.type==='Identifier'?[n.name]:prop(n)?[...chain(n.object),prop(n)]:[];
  function script(source,path,bindings={}){
    if(source.length>LIMITS.script)fail(path+': script exceeds 128 KiB.');
    const report=[],handlers=[],references=new Set(),imports=[];let ast;
    const note=(node,message)=>report.push({file:path,line:node?.loc?.start.line??1,status:'manual',message});
    try{ast=parser.parse(source,{ecmaVersion:'latest',sourceType:'module',locations:true});}catch(e){note(null,'JavaScript syntax: '+e.message);return {handlers,report,references:[],imports};}
    const top=new Map([['world',{type:'world'}],['system',{type:'system'}]]),registries=new Map([['itemComponentRegistry','item'],['blockComponentRegistry','block']]);
    const literal=n=>n?.type==='Literal'&&['string','number','boolean'].includes(typeof n.value);
    const v=(type,php)=>({type,php});
    function resolve(n,env,event){
      if(literal(n)){if(typeof n.value==='number'&&(!Number.isFinite(n.value)||Math.abs(n.value)>1e6))fail('Numeric literal is out of range.');return {type:typeof n.value,php:A.value(n.value),literal:n.value};}
      if(n?.type==='Identifier'&&env.has(n.name))return env.get(n.name);
      if(prop(n)){
        const owner=resolve(n.object,env,event),key=prop(n);
        if(owner.type==='event'){
          if(['source','player'].includes(key))return v('player','$event->getPlayer()');
          if(['itemStack','itemStackBeforeBreak'].includes(key))return v('item','$event->getItem()');
          if(key==='block'&&event!=='itemUse'&&event!=='itemConsume')return v('block','$event->getBlock()');
        }
        if(owner.type==='player'){
          if(key==='name')return v('string',owner.php+'->getName()');
          if(key==='location')return v('position',owner.php+'->getPosition()');
          if(key==='onScreenDisplay')return v('display',owner.php);
        }
        if(['item','block'].includes(owner.type)&&key==='typeId')return {type:owner.type+'Id',object:owner.php};
        if(owner.type==='item'&&key==='amount')return v('number',owner.php+'->getCount()');
        if(owner.type==='position'&&['x','y','z'].includes(key))return v('number',owner.php+'->'+key);
      }
      if(n?.type==='UnaryExpression'&&['!','-'].includes(n.operator)){const x=resolve(n.argument,env,event);if(!['number','boolean'].includes(x.type))fail('Unary operator needs a scalar.');return v(n.operator==='!'?'boolean':'number','('+n.operator+x.php+')');}
      if(n?.type==='BinaryExpression'||n?.type==='LogicalExpression'){
        const l=resolve(n.left,env,event),r=resolve(n.right,env,event),op=n.operator;
        if(['itemId','blockId'].includes(l.type)&&typeof r.literal==='string'&&['===','==','!==','!='].includes(op)){
          if(r.literal.length>256||!/^[a-z0-9_.-]+:[a-z0-9_./-]+$/.test(r.literal))fail('Identifier comparisons require a lowercase namespaced Bedrock identifier.');
          references.add(r.literal);return v('boolean',(['!==','!='].includes(op)?'!':'')+'$this->matches'+(l.type==='itemId'?'Item':'Block')+'('+l.object+', '+A.php(r.literal)+')');
        }
        if(!['string','number','boolean'].includes(l.type)||!['string','number','boolean'].includes(r.type)||!['===','!==','==','!=','<','>','<=','>=','&&','||','+','-','*'].includes(op))fail('Unsupported comparison or arithmetic.');
        if(['<','>','<=','>=','+','-','*'].includes(op)&&(l.type!=='number'||r.type!=='number'))fail('Non-numeric arithmetic/order comparisons need manual PHP typing.');
        if(['&&','||'].includes(op)&&(l.type!=='boolean'||r.type!=='boolean'))fail('JavaScript operand-valued logical expressions need manual PHP typing.');
        if(['==','!='].includes(op)&&(l.type!==r.type||l.type==='string'))fail('Loose JavaScript equality needs a manual PHP comparison.');
        if(op==='+'&&(l.type==='string'||r.type==='string'))fail('JavaScript string concatenation needs manual PHP typing.');
        return v(['+','-','*'].includes(op)?'number':'boolean','('+l.php+' '+op+' '+r.php+')');
      }
      fail('Unsupported expression '+(n?.type??'missing')+'.');
    }
    function constant(n,env){if(literal(n))return n.value;if(n?.type==='Identifier'&&env?.has(n.name)&&own(env.get(n.name),'literal'))return env.get(n.name).literal;fail('This argument must be a literal or literal constant.');}
    function numberArg(n,min=0,max=1e6,env){const x=constant(n,env);if(typeof x!=='number'||!Number.isSafeInteger(x)||x<min||x>max)fail('Expected an integer argument from '+min+' to '+max+'.');return x;}
    function objectArgs(node,methods=false){if(!node)return {};if(node.type!=='ObjectExpression')fail('Options must be a literal object.');const result=Object.create(null);for(const p of node.properties){if(p.type!=='Property'||p.computed||p.kind!=='init'||(p.method&&!methods))fail('Unsupported option syntax.');const key=p.key.name??p.key.value;if(own(result,key))fail('Duplicate option '+key);result[key]=p.value;}return result;}
    function call(n,env,event){
      if(n.type!=='CallExpression'||n.optional||!prop(n.callee))fail('Only supported direct API calls are translated.');
      const owner=resolve(n.callee.object,env,event),method=prop(n.callee),args=n.arguments,num=(node,min,max)=>numberArg(node,min,max,env);
      if(owner.type==='display'&&method==='setActionBar'&&args.length===1){const x=resolve(args[0],env,event);if(x.type!=='string')fail('Action bar needs text.');return owner.php+'->sendActionBarMessage('+x.php+');';}
      if(owner.type!=='player')fail('API call '+method+' needs manual migration.');
      if(method==='sendMessage'&&args.length===1){const x=resolve(args[0],env,event);if(x.type!=='string')fail('Message needs text.');return owner.php+'->sendMessage('+x.php+');';}
      if(method==='addEffect'&&args.length>=2&&args.length<=3){
        const name=String(constant(args[0],env)).replace(/^minecraft:/,'');if(!effects.has(name))fail('Effect '+name+' has no verified PocketMine mapping.');const duration=num(args[1],1,72000),opts=objectArgs(args[2]);
        for(const key of Object.keys(opts))if(!['amplifier','showParticles'].includes(key))fail('Effect option '+key+' needs manual migration.');
        const amplifier=opts.amplifier?num(opts.amplifier,0,255):0,particles=opts.showParticles?constant(opts.showParticles,env):true;if(typeof particles!=='boolean')fail('showParticles must be boolean.');
        return owner.php+'->getEffects()->add(new \\pocketmine\\entity\\effect\\EffectInstance(\\pocketmine\\entity\\effect\\VanillaEffects::'+name.toUpperCase()+'(), '+duration+', '+amplifier+', '+A.value(particles)+'));';
      }
      if(['addLevels','addExperience'].includes(method)&&args.length===1)return owner.php+'->getXpManager()->'+(method==='addLevels'?'addXpLevels':'addXp')+'('+num(args[0],0,100000)+');';
      if(method==='extinguishFire'&&!args.length)return owner.php+'->extinguish();';
      if(method==='setOnFire'&&args.length===1)return owner.php+'->setOnFire('+num(args[0],0,3600)+');';
      if(method==='clearVelocity'&&!args.length)return owner.php+'->setMotion(\\pocketmine\\math\\Vector3::zero());';
      fail('API call '+method+' needs manual migration.');
    }
    function bind(pattern,val,env,event){
      if(pattern.type==='Identifier'){env.set(pattern.name,val);return;}
      if(pattern.type==='ObjectPattern')for(const p of pattern.properties){if(p.type!=='Property'||p.computed||p.value.type!=='Identifier')fail('Unsupported destructuring.');const key=p.key.name??p.key.value;const temp=new Map(env);temp.set('__event',val);env.set(p.value.name,resolve({type:'MemberExpression',computed:false,object:{type:'Identifier',name:'__event'},property:{type:'Identifier',name:key}},temp,event));}
      else fail('Unsupported callback parameter.');
    }
    function statement(n,env,event,indent='        '){
      if(n.type==='BlockStatement'){const scoped=new Map(env);return n.body.map(x=>statement(x,scoped,event,indent)).join('\n');}
      if(n.type==='VariableDeclaration'){
        if(n.kind!=='const')fail('Mutable variables need a manual rewrite.');for(const d of n.declarations){if(!d.init)fail('Missing initializer.');bind(d.id,resolve(d.init,env,event),env,event);}return '';
      }
      if(n.type==='ExpressionStatement')return indent+call(n.expression,env,event);
      if(n.type==='ReturnStatement'&&!n.argument)return indent+'return;';
      if(n.type==='IfStatement'){const test=resolve(n.test,env,event);if(!['boolean','number'].includes(test.type))fail('Non-boolean/non-numeric truthiness needs manual PHP typing.');return indent+'if('+test.php+'){\n'+statement(n.consequent,new Map(env),event,indent+'    ')+'\n'+indent+'}'+(n.alternate?' else {\n'+statement(n.alternate,new Map(env),event,indent+'    ')+'\n'+indent+'}':'');}
      if(n.type==='EmptyStatement')return '';
      fail('Statement '+n.type+' needs manual migration.');
    }
    function compile(fn,event,filter,node){
      if(!['ArrowFunctionExpression','FunctionExpression'].includes(fn?.type)||fn.async||fn.generator||fn.params.length>1){note(node,'Only synchronous callbacks with zero/one event parameter are translated.');return;}
      const previousReferences=new Set(references);try{
        const env=new Map(top);if(fn.params[0])bind(fn.params[0],{type:'event'},env,event);
        const body=fn.body.type==='BlockStatement'?statement(fn.body,env,event):'        '+call(fn.body,env,event);
        handlers.push({event,body,filter,file:path,line:node.loc.start.line});
        report.push({file:path,line:node.loc.start.line,status:'translated',message:event+' → '+eventTypes[event][0]+'. Verify timing and cancellation in-game.'});
      }catch(e){for(const id of references)if(!previousReferences.has(id))references.delete(id);note(node,e.message+' This callback is left entirely for manual work; no partial actions were emitted.');}
    }
    function register(node){
      if(node.type!=='CallExpression'||prop(node.callee)!=='registerCustomComponent'||node.arguments.length!==2)return false;
      try{
        const names=chain(node.callee.object),kind=registries.get(names[names.length-1]);if(!kind)fail('Unknown custom component registry alias.');
        const id=constant(node.arguments[0],top),properties=objectArgs(node.arguments[1],true),ids=bindings[id]??[];
        if(!ids.length){note(node,'Custom component '+id+' has no imported block/item binding. Add its JSON definition.');return true;}
        for(const [event,fn] of Object.entries(properties)){
          const mapping=kind==='item'?{onUse:'itemUse',onConsume:'itemConsume'}:{onPlayerInteract:'playerInteract'};
          if(!own(mapping,event)){note(fn,'Custom event '+event+' needs a PHP hook/scheduler implementation.');continue;}
          const filter={kind,ids};ids.forEach(id=>references.add(id));compile(fn,mapping[event],filter,node);
        }
      }catch(e){note(node,e.message);}return true;
    }
    for(const node of ast.body){
      if(node.type==='ImportDeclaration'){
        imports.push(node.source.value);
        if(node.source.value==='@minecraft/server')for(const s of node.specifiers){if(s.type==='ImportSpecifier'&&['world','system'].includes(s.imported.name))top.set(s.local.name,{type:s.imported.name});}
        else if(!node.source.value.startsWith('.'))note(node,'External module '+node.source.value+' needs a PHP replacement.');
        continue;
      }
      if(node.type==='VariableDeclaration'&&node.kind==='const'){
        let ok=true;for(const d of node.declarations){if(d.id.type==='Identifier'&&literal(d.init)){try{top.set(d.id.name,resolve(d.init,top,'itemUse'));}catch{ok=false;}}else ok=false;}if(!ok)note(node,'Top-level configuration/helper expressions need a manual rewrite.');continue;
      }
      const callNode=node.type==='ExpressionStatement'?node.expression:null;
      if(callNode?.type==='CallExpression'&&prop(callNode.callee)==='subscribe'){
        const names=chain(callNode.callee),origin=top.get(names[0]);
        if(origin?.type==='world'&&names[1]==='afterEvents'&&['itemUse','playerBreakBlock'].includes(names[2])&&callNode.arguments.length===1){compile(callNode.arguments[0],names[2],null,node);continue;}
        if(origin?.type==='system'&&names[1]==='beforeEvents'&&names[2]==='startup'&&callNode.arguments.length===1){
          const fn=callNode.arguments[0];if(fn?.body?.type!=='BlockStatement'){note(node,'Startup callback needs an explicit body.');continue;}
          if(fn.async){note(node,'Async startup needs manual initialization.');continue;}
          if(fn.params[0]?.type==='ObjectPattern')for(const p of fn.params[0].properties)if(p.type==='Property'&&p.value.type==='Identifier'&&registries.has(p.key.name))registries.set(p.value.name,registries.get(p.key.name));
          // Registries are recognized by the original property/name; user code is never executed.
          for(const s of fn.body.body)if(s.type!=='ExpressionStatement'||!register(s.expression))note(s,'Startup statement needs manual initialization.');continue;
        }
      }
      if(callNode&&register(callNode))continue;
      if(node.type!=='EmptyStatement')note(node,'Top-level '+node.type+' is preserved in originals and needs manual migration.');
    }
    return {handlers,report,references:[...references],imports};
  }
  function migrate(entries,options={}){
    const name=A.className(options.pluginName??'ConvertedAddon'),ns='migrated'+name.toLowerCase(),files={},report={target:'Customies 1.9.0 / Axolotl 5.51.0 / Bedrock 26.60',definitions:[],scripts:[],assets:[],review:[],translated_callbacks:0,manual_callbacks:0},definitions=[],bindings=Object.create(null),documents=new Map();let outputSize=0;
    const put=(path,data)=>{if(own(files,path))fail('Output collision: '+path);outputSize+=typeof data==='string'?encode(data).length:data.byteLength;if(outputSize>128*1024*1024)fail('Generated project exceeds 128 MiB; split the add-on before converting.');files[path]=data;};
    for(const [path,bytes] of entries){safePath(path);put('originals/'+path,bytes);if(/\.json$/i.test(path)){try{documents.set(path,jsonc(decode(bytes)));}catch(e){report.review.push({file:path,message:e.message});}}}
    let index=0;
    for(const [path,data] of documents){
      if(!isObject(data))continue;
      const kind=own(data,'minecraft:block')?'block':own(data,'minecraft:item')?'item':null;if(!kind)continue;
      const body=data['minecraft:'+kind],id=body?.description?.identifier;
      if(definitions.some(d=>d.id===id)){report.review.push({file:path,message:'Duplicate content identifier '+id+'; definition skipped.'});continue;}
      try{
        if(index>=256)fail('At most 256 definitions per migration.');
        const custom=body.components?.['minecraft:custom_components'];if(custom!==undefined&&(!Array.isArray(custom)||!custom.every(id=>typeof id==='string'&&id.includes(':'))))fail('custom_components must be an array of namespaced component names.');
        const className='Definition'+(++index),converted=A.convert(data,{className});
        const code=converted.files['src/'+className+'.php'].replace('namespace content'+className.toLowerCase()+';','namespace '+ns+';');put('plugin/src/'+className+'.php',code);
        definitions.push({kind,id,className});report.definitions.push({file:path,identifier:id,kind,className,states:converted.report.state_combinations,review:converted.report.review});
        for(const key of [...Object.keys(body.components??{}).filter(k=>!k.startsWith('minecraft:')), ...(body.components?.['minecraft:custom_components']??[])]){if(!bindings[key])bindings[key]=[];bindings[key].push(id);}
      }catch(e){report.review.push({file:path,message:'Definition needs manual work: '+e.message});}
    }
    const scriptFiles=new Map([...entries].filter(([p])=>/\.js$/i.test(p))),roots=[],resources=[];
    for(const [path,manifest] of documents){
      if(!/(^|\/)manifest\.json$/i.test(path)||!Array.isArray(manifest.modules))continue;const prefix=path.slice(0,-'manifest.json'.length);
      for(const module of manifest.modules){if(module.type==='script'&&typeof module.entry==='string')roots.push(prefix+safePath(module.entry.replace(/^\.\//,'')));if(module.type==='resources')resources.push({prefix,manifest});}
      for(const key of ['dependencies','capabilities'])if(manifest[key])report.review.push({file:path,message:key+' are preserved in originals. Resolve Script API dependencies and experimental capabilities on the PHP host.'});
    }
    const reachable=new Set();
    function follow(path){
      if(reachable.has(path))return;const bytes=scriptFiles.get(path);if(!bytes){report.review.push({file:path,message:'Missing script entry/import.'});return;}reachable.add(path);
      if(bytes.length>LIMITS.script){report.review.push({file:path,message:'Script exceeds 128 KiB; preserved for manual migration.'});return;}
      try{const ast=parser.parse(decode(bytes),{ecmaVersion:'latest',sourceType:'module'});for(const node of ast.body)if(node.type==='ImportDeclaration'&&node.source.value.startsWith('.')){
        const parts=path.split('/').slice(0,-1);for(const segment of node.source.value.split('/')){if(segment==='..'){if(!parts.length)fail('Import escapes the project.');parts.pop();}else if(segment!=='.')parts.push(segment);}
        let target=parts.join('/');if(!/\.js$/i.test(target))target+='.js';follow(target);
      }}catch(e){report.review.push({file:path,message:'Script graph: '+e.message});}
    }
    (roots.length?roots:[...scriptFiles.keys()]).forEach(follow);
    const handlers=[],refs=new Set();
    for(const path of reachable){try{const translated=script(decode(scriptFiles.get(path)),path,bindings);handlers.push(...translated.handlers);translated.references.forEach(id=>refs.add(id));report.scripts.push(...translated.report);}catch(e){report.review.push({file:path,message:e.message});}}
    for(const path of scriptFiles.keys())if(!reachable.has(path))report.review.push({file:path,message:'Not reachable from a manifest script entry; preserved without translation.'});
    resources.forEach(({prefix},i)=>{for(const [path,data] of entries)if(path.startsWith(prefix)){const relative=path.slice(prefix.length);if(/\.(?:js|mcpack|mcaddon|zip)$/i.test(relative))continue;const target='resource_packs/pack_'+(i+1)+'/'+relative;put(target,data);report.assets.push({source:path,target});}});
    const registration=definitions.sort((a,b)=>(a.kind==='block'?-1:1)-(b.kind==='block'?-1:1)).map((d,i)=>{
      const type=d.kind==='block'?'Block':'Item',id='$id'+i;return '        '+id+' = \\pocketmine\\'+d.kind+'\\'+type+'TypeIds::newId();\n        \\customiesdevs\\customies\\'+d.kind+'\\Customies'+type+'Factory::getInstance()->register'+type+'(static fn() => new '+d.className+'('+id+'), '+A.php(d.id)+');';
    }).join('\n');
    put('plugin/plugin.yml',`name: ${name}\nmain: ${ns}\\Main\nsrc-namespace-prefix: ${ns}\nversion: 1.0.0\napi: 5.51.0\nload: POSTWORLD\ndepend: [Customies-Axolotl]\n`);
    put('plugin/src/Main.php',`<?php\ndeclare(strict_types=1);\nnamespace ${ns};\nfinal class Main extends \\pocketmine\\plugin\\PluginBase {\n    protected function onEnable(): void {\n${registration}\n        $this->getServer()->getPluginManager()->registerEvents(new MigrationHooks($this), $this);\n    }\n}\n`);
    const listenerMethods=handlers.map((h,i)=>{
      let guard='';if(h.filter){const fn=h.filter.kind==='item'?'matchesItem':'matchesBlock',source=h.filter.kind==='item'?'$event->getItem()':'$event->getBlock()';guard='        if(!('+h.filter.ids.map(id=>'$this->'+fn+'('+source+', '+A.php(id)+')').join(' || ')+')){ return; }\n';}
      if(h.event==='playerInteract')guard+='        if($event->getAction() !== \\pocketmine\\event\\player\\PlayerInteractEvent::RIGHT_CLICK_BLOCK){ return; }\n';
      return '    // Source: '+h.file.replace(/[\r\n]/g,'')+':'+h.line+'; verify event timing/cancellation.\n    public function handle'+i+'(\\'+eventTypes[h.event][0]+' $event): void {\n'+guard+h.body+'\n    }';
    }).join('\n');
    put('plugin/src/MigrationHooks.php',`<?php\ndeclare(strict_types=1);\nnamespace ${ns};\nfinal class MigrationHooks implements \\pocketmine\\event\\Listener {
    private array $itemTypes = [];
    private array $blockTypes = [];
    private array $itemNames = [];
    private array $blockNames = [];
    public function __construct(\\pocketmine\\plugin\\PluginBase $plugin){
        foreach(${A.value([...refs])} as $id){
            $item = \\pocketmine\\item\\StringToItemParser::getInstance()->parse($id);
            if($item === null){ $plugin->getLogger()->warning('Migration identifier unresolved: '.$id); continue; }
            $this->itemTypes[$id] = $this->itemName($item);
            if($item instanceof \\pocketmine\\item\\ItemBlock){ $this->blockTypes[$id] = $this->blockName($item->getBlock()); }
        }
    }
    private function itemName(\\pocketmine\\item\\Item $item): string {
        return $this->itemNames[$item->getStateId()] ??= \\pocketmine\\world\\format\\io\\GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
    }
    private function blockName(\\pocketmine\\block\\Block $block): string {
        return $this->blockNames[$block->getStateId()] ??= \\pocketmine\\world\\format\\io\\GlobalBlockStateHandlers::getSerializer()->serialize($block->getStateId())->getName();
    }
    private function matchesItem(\\pocketmine\\item\\Item $item, string $id): bool { return isset($this->itemTypes[$id]) && $this->itemName($item) === $this->itemTypes[$id]; }
    private function matchesBlock(\\pocketmine\\block\\Block $block, string $id): bool { return isset($this->blockTypes[$id]) && $this->blockName($block) === $this->blockTypes[$id]; }
${listenerMethods}
}
`);
    report.translated_callbacks=handlers.length;report.manual_callbacks=report.scripts.filter(s=>s.status==='manual').length;
    report.review.push({file:'project',message:'Source migration, not a compiled PHAR. PocketMine events differ from Script API before/after events; test cancellation, timing, inventories, persistence and multiplayer on a test server.'});
    if(!resources.length)report.review.push({file:'resources',message:'No resource-pack manifest found. Supply referenced textures/geometries/attachables and a matching resource pack.'});
    if(!definitions.length&&!handlers.length)report.review.push({file:'project',message:'No supported content or behavior was translated; output is a manual migration scaffold and preserved assets only.'});
    put('migration-report.json',pretty(report));
    put('README.md',`# ${name} migration\n\nTarget: ${report.target}.\n\n1. Read migration-report.json. Review every generated class and manual entry. Originals are preserved.\n2. Build plugin/ into a PHAR with DevTools or your normal pipeline. Install Customies first.\n3. Package each resource_packs/pack_N folder as a .mcpack and configure the server resource stack. These are assets, not behavior packs.\n4. Run PHP/host tests; join with Bedrock and test hooks, timing/cancellation, collision, drops, save/reload and multiplayer.\n\nUploaded JS was parsed, never executed. Entity AI, arbitrary JS, commands, UI, timers, modules, recipes and unsupported components need manual PHP implementations. Original files are reference material and are never automatically loaded by the generated plugin.\n`);
    return {files,report};
  }
  const api={LIMITS,safePath,jsonc,crc32,unzip,collect,script,migrate};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;root.CustomiesMigration=api;
})(typeof globalThis!=='undefined'?globalThis:this);
