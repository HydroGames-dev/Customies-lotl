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
models=json.loads((OUT/'assets/models.json').read_text())
assert len(models['prism_cottage']['cubes'])>=96
search=json.loads((OUT/'assets/search.json').read_text())
for record in search:
    url=urlsplit(record['url']);target=OUT/url.path
    assert target in documents
    if url.fragment:assert url.fragment in documents[target].ids
if (OUT/'downloads').exists():
    for line in (OUT/'downloads/SHA256SUMS.txt').read_text().splitlines():
        checksum,name=line.split();assert hashlib.sha256((OUT/'downloads'/name).read_bytes()).hexdigest()==checksum,'Download checksum mismatch'
print(f'PASS: {len(documents)} pages, all internal links/anchors/resources, 139 specimens, 75 examples, 13 flipbooks, model/search data and download checksums.')

# Library and demo content must remain separate in navigation, search and downloads.
showcase={'showcase','showcase-install','showcase-walkthroughs','structures','gallery','showcase-downloads','showcase-validation'}
for scope in ('customies','showcase'):
    records=json.loads((OUT/f'assets/search-{scope}.json').read_text(encoding='utf-8'))
    assert records and all(r['scope']==scope for r in records)
    assert all((r['url'].split('.html')[0] in showcase)==(scope=='showcase') for r in records)
for name in ('index','getting-started','coding','guide','assets','performance','downloads'):
    page=(OUT/(name+'.html')).read_text(encoding='utf-8')
    assert 'data-scope="customies"' in page
    assert '/cshow give' not in page and 'CustomiesShowcase-install.zip' not in page
assert '<canvas' not in (OUT/'index.html').read_text(encoding='utf-8')
for name in showcase:assert 'data-scope="showcase"' in (OUT/(name+'.html')).read_text(encoding='utf-8')
print('PASS: independent Customies/CustomiesShowcase navigation, search indexes, guides and downloads.')
