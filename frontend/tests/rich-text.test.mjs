import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';
globalThis.window = new JSDOM('<!doctype html>').window;
const { sanitizeRichText } = await import('../src/utils/sanitizeRichText.mjs');
test('admin and website use identical policy', () => {
 assert.equal(readFileSync(new URL('../src/utils/sanitizeRichText.mjs', import.meta.url),'utf8'),readFileSync(new URL('../../admin-panel/lib/sanitizeRichText.mjs', import.meta.url),'utf8'));
});
test('ordinary formatting and safe links survive', () => {
 const html='<p class="ql-align-center"><strong>Care</strong> <em>first</em></p><ol><li data-list="ordered">One</li></ol><a href="https://example.invalid/help">Help</a>';
 assert.equal(sanitizeRichText(html),html);
});
test('executable content, URL tricks, embeds and clobbering are removed', () => {
 const payloads=[
 '<script>alert(1)</script><img src=x onerror=alert(1)><p onclick=alert(1)>Hello</p>',
 '<svg><g onload=alert(1)></g></svg><math><mtext><img src=x onerror=alert(1)></mtext></math>',
 '<a href="jav&#x61;script:alert(1)">Link</a><a href="data:text/html,evil">Data</a>',
 '<iframe srcdoc="<script>alert(1)</script>"></iframe><object data="https://example.invalid"></object>',
 '<form id=attributes><input name=action></form><p id=__proto__ style="position:fixed">Hello</p>',
 '<math><mtext><table><mglyph><style><!--</style><img title="--><img src=x onerror=alert(1)>">',
 '<a href="java\nscript:alert(1)" target="_blank">Link</a><template><img src=x onerror=alert(1)></template>'
 ];
 for(const payload of payloads){
  const clean=sanitizeRichText(payload), doc=new JSDOM(clean).window.document;
  assert.equal(doc.querySelector('script,img,svg,math,iframe,object,style,form,input,template'),null,clean);
  for(const el of doc.body.querySelectorAll('*')){
   for(const a of el.attributes) assert.ok(!/^on/i.test(a.name)&&!['style','id','name','target'].includes(a.name),clean);
   if(el.hasAttribute('href')) assert.ok(!/^(javascript|data|vbscript):/i.test(el.getAttribute('href').replace(/\s/g,'')),clean);
  }
  assert.equal(sanitizeRichText(clean),clean);
 }
});
test('non-string input fails closed',()=>{for(const x of [null,undefined,{},123])assert.equal(sanitizeRichText(x),'');});
