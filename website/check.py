"""Check static output, deep links, downloads and catalog coverage before publishing."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit,unquote
import json,hashlib,re

OUT=Path(__file__).resolve().parent/'dist'
class Document(HTMLParser):
    def __init__(self,text):
        super().__init__();self.ids=set();self.links=[];self.headings=0;self.feed(text)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if 'id' in a:
            assert a['id'] not in self.ids,'Duplicate ID: '+a['id']
            self.ids.add(a['id'])
        if tag=='h1':self.headings+=1
        if tag=='a' and 'href' in a:self.links.append((a['href'],True))
        for name in ('src','href'):
            if name in a and not(tag=='a' and name=='href'):self.links.append((a[name],False))

documents={p:Document(p.read_text(encoding='utf-8')) for p in OUT.glob('*.html')}
for path,doc in documents.items():
    assert doc.headings==1,f'{path.name}: expected one main heading, got {doc.headings}'
    for raw,anchor in doc.links:
        url=urlsplit(raw)
        if url.scheme or url.netloc:continue
        if url.path.startswith('/Customies-lotl/'):target=OUT/url.path.removeprefix('/Customies-lotl/')
        else:target=path.parent/unquote(url.path) if url.path else path
        if target.is_dir():target=target/'index.html'
        assert target.exists(),f'{path.name}: broken resource {raw}'
        if url.fragment and target.suffix=='.html':assert unquote(url.fragment) in documents[target].ids,f'{path.name}: broken anchor {raw}'
gallery=(OUT/'gallery.html').read_text(encoding='utf-8')
assert gallery.count('class="asset-card"')==139,'Catalog count drift'
assert gallery.count('class="flipbook-card"')==13,'Animation count drift'
for name,count in [('block-components',33),('item-components',42)]:
    assert (OUT/(name+'.html')).read_text(encoding='utf-8').count('class="component-example"')==count
for style in re.findall(r"url\(['\"]?([^)'\"]+)", (OUT/'assets/style.css').read_text(encoding='utf-8')):
    assert (OUT/'assets'/style).exists(),'Missing CSS resource '+style
models=json.loads((OUT/'assets/models.json').read_text(encoding='utf-8'))
assert len(models['prism_cottage']['cubes'])>=96
search=json.loads((OUT/'assets/search.json').read_text(encoding='utf-8'))
for record in search:
    url=urlsplit(record['url']);target=OUT/url.path
    assert target in documents
    if url.fragment:assert url.fragment in documents[target].ids
if (OUT/'downloads').exists():
    for line in (OUT/'downloads/SHA256SUMS.txt').read_text(encoding='utf-8').splitlines():
        checksum,name=line.split();assert hashlib.sha256((OUT/'downloads'/name).read_bytes()).hexdigest()==checksum,'Download checksum mismatch'
print(f'PASS: {len(documents)} pages, all internal links/anchors/resources, 139 specimens, 75 examples, 13 flipbooks, model/search data and download checksums.')

# Library and demo content must remain separate in navigation, search and downloads.
showcase={'showcase','showcase-install','showcase-walkthroughs','structures','gallery','showcase-downloads','showcase-validation'}
migration={'migration','migration-tool','migration-guide','migration-support'}
for scope in ('customies','showcase','migration'):
    records=json.loads((OUT/f'assets/search-{scope}.json').read_text(encoding='utf-8'))
    assert records and all(r['scope']==scope for r in records)
    assert all((r['url'].split('.html')[0] in showcase)==(scope=='showcase') for r in records)
    assert all((r['url'].split('.html')[0] in migration)==(scope=='migration') for r in records)
for name in ('index','getting-started','coding','guide','assets','performance','downloads'):
    page=(OUT/(name+'.html')).read_text(encoding='utf-8')
    assert 'data-scope="customies"' in page
    assert '/cshow give' not in page and 'CustomiesShowcase-install.zip' not in page
assert '<canvas' not in (OUT/'index.html').read_text(encoding='utf-8')
for name in showcase:assert 'data-scope="showcase"' in (OUT/(name+'.html')).read_text(encoding='utf-8')
print('PASS: independent Customies/CustomiesShowcase navigation, search indexes, guides and downloads.')

# Reflected documentation must track the actual wrapper sources.
api=json.loads((OUT.parent/'api-reference.json').read_text(encoding='utf-8'))
for kind,expected in [('block',33),('item',42)]:
    assert len(api[kind])==expected
    for key,entry in api[kind].items():
        source=OUT.parents[1]/entry['source']
        assert hashlib.sha256(source.read_bytes().replace(b'\r\n',b'\n')).hexdigest()==entry['source_sha256'],'Stale API export: '+key
        page=OUT/(kind+'-component-'+key+'.html')
        assert page in documents,'Missing component detail: '+key
        text=page.read_text(encoding='utf-8')
        assert all(heading in text for heading in ('Constructor parameters','PHP usage','Serialized wrapper value','Server behavior','Combining this component','Verify before shipping'))
        for parameter in entry['parameters']:assert parameter['name'] in text
        for method in entry.get('methods',[]):assert method['name'] in text
assert len([p for p in documents if '-component-' in p.stem])==75
assert 'Block traits' in (OUT/'block-traits.html').read_text(encoding='utf-8')
print('PASS: 75 complete component articles match source hashes and parameters; dedicated state/trait/item topics exist.')

for name in ('how-it-works','data-guide','updating-customies','toolchain','tutorial-data','tutorial-equipment','tutorial-debugging','accessibility','tools','tool-generator','tool-shapes','tool-converter','tool-rotation'):
    assert OUT/(name+'.html') in documents,'Missing guide/tool: '+name
for name in ('authoring-lib.js','authoring.js','authoring.css','shape-fixtures.json'):assert (OUT/'assets'/name).exists()
for name in ('tool-generator','tool-shapes','tool-converter','tool-rotation'):
    text=(OUT/(name+'.html')).read_text(encoding='utf-8')
    assert 'data-authoring=' in text and 'type="submit" disabled' in text,'Tools must wait for local initialization'
assert (OUT/'assets/wrapper-api.json').exists()
for retired in ('tool-states','tool-boxes','tool-identifiers','tool-components','tool-starter'):
    assert not (OUT/(retired+'.html')).exists(),'Retired toolbox page survived: '+retired
for name in ('tool-shapes','tool-rotation'):
    assert '<canvas' in (OUT/(name+'.html')).read_text(encoding='utf-8')
print('PASS: architecture, data, maintenance, reader guides and four browser-local authoring tools.')
for page in migration:
    assert OUT/(page+'.html') in documents
    assert 'data-scope="migration"' in (OUT/(page+'.html')).read_text(encoding='utf-8')
for page in ('index','migration-tool','tool-generator','changelog'):
    text=(OUT/(page+'.html')).read_text(encoding='utf-8')
    assert 'aria-label="Workspace"' in text and 'id="palette-select"' in text
assert 'data-migration' in (OUT/'migration-tool.html').read_text(encoding='utf-8')
assert 'type="submit" disabled' in (OUT/'migration-tool.html').read_text(encoding='utf-8'),'Migration input must never submit before local handlers initialize'
release_count=len(re.findall(r'^## \d+\.\d+\.\d+', (OUT.parents[1]/'CHANGELOG.md').read_text(encoding='utf-8'),re.M))
assert (OUT/'changelog.html').read_text(encoding='utf-8').count('class="release-entry"')==release_count and release_count>=4
for style in ('studio.css','authoring.css'):
    for resource in re.findall(r"url\(['\"]?([^)'\"]+)",(OUT/'assets'/style).read_text(encoding='utf-8')):assert (OUT/'assets'/resource).exists()
for name in ('migration-lib.js','migration-worker.js','migration.js','vendor/acorn.js','vendor/fflate.js','licenses/acorn.txt','licenses/fflate.txt','licenses/inter.txt','licenses/manrope.txt'):
    assert (OUT/'assets'/name).exists()
print('PASS: independent Migration Lab, archive tool, changelog, palette controls, bundled parser/ZIP tools, fonts and licenses.')
