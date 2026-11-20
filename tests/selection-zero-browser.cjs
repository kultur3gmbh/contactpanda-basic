// Real wp_localize_script -> browser -> real authenticated AJAX handler. No customer data.
const cp=require('child_process'),fs=require('fs'),os=require('os'),path=require('path'),assert=require('assert'),http=require('http');
const fixture=process.env.CONTACTPANDA_TEST_WP_ROOT;
if(!fixture)throw Error('CONTACTPANDA_TEST_WP_ROOT must identify a disposable fixture');
const php=process.env.PHP_BIN||'/opt/homebrew/bin/php',bridge=path.join(__dirname,'selection-request-fixture.php');
const config=JSON.parse(cp.execFileSync(php,[bridge,'config'],{encoding:'utf8'}));assert.strictEqual(config.current,'0','Use actual WordPress-localized string zero');
const source=fs.readFileSync(path.join(__dirname,'../contactpanda/assets/contactpanda-selections.js'),'utf8');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'cp-zero-'));
const chrome=cp.spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',['--headless','--disable-gpu','--disable-extensions','--no-first-run','--remote-debugging-pipe','--user-data-dir='+profile,'about:blank'],{stdio:['ignore','ignore','ignore','pipe','pipe']});
let buf='',seq=0,pending=new Map(),requests=[],savedId=0;
chrome.stdio[4].on('data',b=>{buf+=b;let k;while((k=buf.indexOf('\0'))>=0){let r=JSON.parse(buf.slice(0,k));buf=buf.slice(k+1);if(pending.has(r.id)){pending.get(r.id)(r);pending.delete(r.id);}}});
function req(method,params={},sessionId){return new Promise(resolve=>{pending.set(++seq,resolve);chrome.stdio[3].write(JSON.stringify({id:seq,method,params,sessionId})+'\0');});}
const server=http.createServer((request,response)=>{
 if(request.url==='/ajax'){let body='';request.on('data',d=>body+=d);request.on('end',()=>{requests.push(new URLSearchParams(body).get('id'));let worker=cp.spawn(php,[bridge,'ajax']);let output='';worker.stdout.on('data',b=>output+=b);worker.on('close',()=>{response.setHeader('Content-Type','application/json');response.end(output);});worker.stdin.end(body);});return;}
 response.setHeader('Content-Type','text/html');response.end('<!doctype html><html><body><div class="contactpanda-bulk-toolbar"><select id="contactpanda-bulk-action"><option value="">Actions</option></select></div></body></html>');
});
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));let {result:{targetId}}=await req('Target.createTarget',{url:'about:blank'});let {result:{sessionId:s}}=await req('Target.attachToTarget',{targetId,flatten:true});
 async function run(expression){let r=await req('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},s);if(r.result.exceptionDetails)throw Error(JSON.stringify(r.result.exceptionDetails));return r.result.result.value;}
 await req('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/'},s);await run('new Promise(r=>setTimeout(r,150))');
 await run('window.CP_SELECTIONS='+JSON.stringify(config)+';window.__alerts=[];window.alert=m=>__alerts.push(m);');
 await run(source);await run('new Promise(r=>setTimeout(r,1500))');
 const alerts=await run('__alerts');
 if(process.env.EXPECT_SELECTION_BUG==='1'){
  assert.deepEqual(requests,['0']);assert(alerts.some(m=>m.includes('Unknown selection action')));console.log('REPRODUCED: WordPress string "0" triggers load of nonexistent selection and real AJAX error');
 }else{
  assert.deepEqual(requests,[]);assert.deepEqual(alerts,[]);
  for(const suffix of ['?s=Fixture','?s=Another']){
   await req('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/'+suffix},s);await run('new Promise(r=>setTimeout(r,100))');
   await run('window.CP_SELECTIONS='+JSON.stringify(config)+';window.__alerts=[];window.alert=m=>__alerts.push(m);');await run(source);await run('new Promise(r=>setTimeout(r,250))');
   assert.deepEqual(requests,[]);assert.deepEqual(await run('__alerts'),[]);
  }
  console.log('PASS real WordPress string "0": no spurious load or AJAX error on opening, reloading and searching');
  const saved=JSON.parse(cp.execFileSync(php,[bridge,'config-saved'],{encoding:'utf8'}));savedId=Number(saved.current);assert(savedId>0);
  await req('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?selection='+savedId},s);await run('new Promise(r=>setTimeout(r,100))');
  await run('window.CP_SELECTIONS='+JSON.stringify(saved)+';window.__alerts=[];window.alert=m=>__alerts.push(m);');await run(source);await run('new Promise(r=>setTimeout(r,1200))');
  assert.deepEqual(requests,[String(savedId)]);assert.deepEqual(await run('ContactPandaSelections.ids()'),['730001','730002']);assert.deepEqual(await run('__alerts'),[]);
  console.log('PASS positive WordPress string ID still loads its real saved selection');
 }
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{if(savedId)cp.execFileSync(php,[bridge,'cleanup',String(savedId)]);chrome.kill();server.close();});
setTimeout(()=>{chrome.kill();server.close();process.exitCode=1;},25000).unref();
