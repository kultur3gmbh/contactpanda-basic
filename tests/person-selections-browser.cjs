const cp=require('child_process'),fs=require('fs'),os=require('os'),path=require('path'),assert=require('assert'),http=require('http');
const root=path.resolve(__dirname,'../contactpanda'),profile=fs.mkdtempSync(path.join(os.tmpdir(),'cp-selection-browser-'));
const chrome=process.env.CHROME_BIN||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const browser=cp.spawn(chrome,['--headless','--disable-gpu','--disable-extensions','--no-first-run','--remote-debugging-pipe','--user-data-dir='+profile,'about:blank'],{stdio:['ignore','ignore','ignore','pipe','pipe']});
let buf='',i=0,pending=new Map();browser.stdio[4].on('data',b=>{buf+=b;let k;while((k=buf.indexOf('\0'))>=0){let x=JSON.parse(buf.slice(0,k));buf=buf.slice(k+1);if(pending.has(x.id)){pending.get(x.id)(x);pending.delete(x.id)}}});
function req(method,params={},sessionId){return new Promise(r=>{pending.set(++i,r);browser.stdio[3].write(JSON.stringify({id:i,method,params,sessionId})+'\0')})}
const server=http.createServer((request,response)=>{response.end('<html lang="de"><body>Fixture</body></html>')});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const {result:{targetId}}=await req('Target.createTarget',{url:'about:blank'}),{result:{sessionId:s}}=await req('Target.attachToTarget',{targetId,flatten:true});
async function run(expression){const r=await req('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},s);if(r.result.exceptionDetails)throw Error(JSON.stringify(r.result.exceptionDetails));return r.result.result.value;}
await req('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?s=Fixture'},s);await run('new Promise(r=>setTimeout(r,200))');
await run(`document.body.innerHTML='<form class="contactpanda-search"><div class="contactpanda-people-search-row"><input name="s" value="Fixture"><button type="button" class="cp-selection-add" data-list-token="fixturetoken">?+</button><span class="cp-selection-result" role="status"></span></div></form><div class="contactpanda-bulk-toolbar"><select id="contactpanda-bulk-action"><option value="selection_save">Save</option><option value="merge">Merge</option><option value="selection_remove">Remove</option></select><button type="button" class="contactpanda-open-bulk">Run</button><span class="contactpanda-bulk-selection"></span></div><input type="checkbox" class="contactpanda-select-all"><input type="checkbox" class="contactpanda-person-select" value="2"><input type="checkbox" class="contactpanda-person-select" value="3"><form id="contactpanda-campaign-form"><input name="subject" value="Keep draft"><fieldset class="contactpanda-audience-source"><label hidden><input type="radio" name="audience_source" value="manual"><span class="cp-manual-selection-label"></span></label><input name="manual_contact_ids" type="hidden"><div class="contactpanda-customer-search-results"></div></fieldset></form>';
window.__errors=[];addEventListener('error',e=>__errors.push(e.message));window.CONTACTPANDA_CONFIG={};window.CP_SELECTIONS={ajax:'/ajax',nonce:'fixture',user:1,source:'contactpanda',current:'0',rows:[{id:7,name:'Fixture saved',count:2}],i18n:{name:'Name',save:'Speichern',cancel:'Abbrechen',load:'Auswahl laden',error:'Fehler',added:'%d hinzugefügt',capacity:'Maximal 10000'}};sessionStorage.setItem('cp-selection:1:contactpanda',JSON.stringify({ids:['1'],query:'?s=Fixture'}));window.__requests=[];window.fetch=(url,opts)=>{const operation=opts.body.get('cp_selection_operation');__requests.push({url,operation,token:opts.body.get('list_token')});const data=operation==='search_results'?{ids:[2,3,4],total:3}:{id:7,name:'Fixture saved',ids:[2,3]};return Promise.resolve({json:()=>Promise.resolve({success:true,data})});};`);
await run(fs.readFileSync(root+'/assets/contactpanda-admin.js','utf8'));
await run(fs.readFileSync(root+'/assets/contactpanda-selections.js','utf8'));
assert.deepEqual(await run('ContactPandaSelections.ids()'),['1']);
await run(`document.querySelector('.cp-selection-add').click();new Promise(r=>setTimeout(r,30))`);
assert.equal(await run('__requests[0].operation'),'search_results');
assert.equal(await run('__requests[0].token'),'fixturetoken');
assert.equal(await run('document.querySelector(".cp-selection-result").textContent'),'3 hinzugefügt');
assert.deepEqual(await run('ContactPandaSelections.ids()'),['1','2','3','4']);
assert.equal(await run('document.querySelector(".contactpanda-select-all").checked'),true);
await run(`document.querySelector('#contactpanda-bulk-action').value='selection_remove';document.querySelector('.contactpanda-open-bulk').click();new Promise(r=>setTimeout(r,30))`);
assert.deepEqual(await run('ContactPandaSelections.ids()'),['1','4']);
assert.equal(await run('document.querySelectorAll(".contactpanda-person-select:checked").length'),0);
await run(`document.querySelector('.contactpanda-select-all').checked=true;document.querySelector('.contactpanda-select-all').dispatchEvent(new Event('change',{bubbles:true}));new Promise(r=>setTimeout(r,30))`);
assert.deepEqual(await run('ContactPandaSelections.ids()'),['1','4','2','3']);
await run(`var chooser=document.querySelector('.cp-saved-person-selection select');chooser.value='7';chooser.dispatchEvent(new Event('change'));new Promise(r=>setTimeout(r,30))`);
assert.equal(await run('document.querySelector("[name=manual_contact_ids]").value'),'2,3');
assert.equal(await run('__requests[1].operation'),'load');
assert.equal(await run('document.querySelector("[name=subject]").value'),'Keep draft');
assert.equal(await run('document.querySelector("[name=audience_source]").checked'),true);
assert.equal(await run('document.querySelectorAll(".contactpanda-people-search-row select").length'),1);
assert.deepEqual(await run('__errors'),[]);
await run(`document.body.insertAdjacentHTML('beforeend','<div class="contactpanda-output-modal"><h2 id="contactpanda-output-title"></h2><div class="contactpanda-output-columns"></div><div class="contactpanda-modal-message"></div><form action="/admin-post" class="contactpanda-output-form"><input name="action"><input name="selected_ids"><input type="radio" name="scope" value="selected"><input type="radio" name="scope" value="all" checked><button type="submit" class="contactpanda-submit-output">Print</button></form></div><button type="button" class="contactpanda-open-print">Print</button>');
window.__prints=0;window.open=()=>{throw Error('Unexpected new tab')};window.fetch=()=>Promise.resolve({ok:true,text:()=>Promise.resolve('<!doctype html><html><body><table><tr><td>Fixture tags</td></tr></table></body></html>')});
var observer=new MutationObserver(()=>{var f=document.getElementById('contactpanda-print-frame');if(f&&!f.__observed){f.__observed=true;f.addEventListener('load',()=>{f.contentWindow.print=()=>{__prints++}})}});observer.observe(document.body,{childList:true});
document.querySelector('.contactpanda-open-print').click();document.querySelector('.contactpanda-output-form').requestSubmit();new Promise(r=>setTimeout(r,250));`);
assert.equal(await run('__prints'),1);
assert.equal(await run('document.querySelector(".contactpanda-output-form").target'),'_self');
assert.equal(await run('document.getElementById("contactpanda-print-frame").contentDocument.querySelector("td").textContent'),'Fixture tags');
await run(`const inlineField=(kind,field,value,options='') =>
	'<div class="contactpanda-editable contactpanda-list-editable" data-entity="'+kind+'" data-contact="31" data-company="41" data-field="'+field+'">'+
	'<div class="contactpanda-field-row"><button class="contactpanda-edit contactpanda-field-value" type="button" data-value="'+value+'">'+(value||'–––')+'</button></div>'+
	options+'<div class="contactpanda-field-message" aria-live="polite"></div></div>';
const salutationOptions='<template class="contactpanda-inline-options"><select><option value="">Select</option><option value="ms">Ms</option></select></template>';
document.body.insertAdjacentHTML('beforeend','<table id="inline-fixture" class="contactpanda-table"><tbody>'+
	'<tr><td>'+inlineField('contact','first_name','')+'</td><td hidden>'+inlineField('contact','last_name','Hidden')+'</td></tr>'+
	'<tr><td>'+inlineField('company','city','')+'</td></tr>'+
	'<tr data-salutation-entry="en:fixture"><td>'+inlineField('dictionary','salutation','',salutationOptions)+'</td><td data-salutation-source>Import</td><td><a data-salutation-delete href="/delete">Delete</a></td></tr>'+
	'</tbody></table><button id="after-inline-fixture">Next settings area</button>');
window.inlineRequests=[];
window.fetch=(url,opts)=>{
	const body=new URLSearchParams(opts.body);
	const field=body.get('field'), value=body.get('value'), action=body.get('action');
	inlineRequests.push({field, value, action, entry:body.get('entry_id')});
	const response=value==='rejected' ? {success:false,data:{message:'Value rejected'}} : {
		success:true,data:{value,display:value==='ms'?'Ms':value,entry_id:'en:fixture',delete_url:'/delete',source:'Manual'}
	};
	return Promise.resolve({ok:true,json:()=>Promise.resolve(response)});
};
window.inlineKey=(key)=>{document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key,bubbles:true,cancelable:true}));};
document.querySelector('#inline-fixture button').click();
document.activeElement.value='Fixture';
inlineKey('Tab');
new Promise(r=>setTimeout(r,40));`);
assert.equal(await run('inlineRequests[0].action'),'contactpanda_update_contact_field');
assert.equal(await run('document.activeElement.closest("[data-field]").dataset.field'),'city');
assert.equal(await run('document.querySelector("#inline-fixture tr .contactpanda-field-value").textContent'),'Fixture');
await run(`document.activeElement.value='rejected';inlineKey('Tab');new Promise(r=>setTimeout(r,40));`);
assert.equal(await run('document.activeElement.closest("[data-field]").dataset.field'),'city');
assert.equal(await run('document.activeElement.closest("[data-field]").querySelector(".contactpanda-field-message").textContent'),'Value rejected');
await run(`document.activeElement.value='Vienna';inlineKey('Tab');new Promise(r=>setTimeout(r,40));`);
assert.equal(await run('document.activeElement.tagName'),'SELECT');
assert.equal(await run('inlineRequests[2].action'),'contactpanda_update_company_field');
await run(`document.activeElement.value='ms';inlineKey('Escape');`);
assert.equal(await run('document.activeElement.textContent'),'–––');
assert.equal(await run('inlineRequests.length'),3);
await run(`document.activeElement.click();document.activeElement.value='ms';inlineKey('Tab');new Promise(r=>setTimeout(r,40));`);
assert.equal(await run('document.activeElement.matches("[data-salutation-delete]")'),true);
assert.equal(await run('inlineRequests[3].action'),'contactpanda_update_salutation_entry');
assert.equal(await run('inlineRequests[3].entry'),'en:fixture');
assert.equal(await run('document.querySelector("[data-salutation-entry] .contactpanda-field-value").textContent'),'Ms');
assert.deepEqual(await run('__errors'),[]);

await req('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?s=NoMatch&cp_add_results=1'},s);
await run('new Promise(r=>setTimeout(r,150))');
await run(`document.body.innerHTML='<form class="contactpanda-search"><input name="s" value="NoMatch"><button class="cp-selection-add" data-list-token="emptyresults" type="button">Add</button><span class="cp-selection-result"></span></form><div class="contactpanda-bulk-toolbar"><select id="contactpanda-bulk-action"><option value="salutations">Salutations</option></select><button class="contactpanda-open-bulk">Run</button></div><details class="cp-selection-retained" open hidden><summary>Outside <span data-cp-retained-count></span></summary><ul></ul><button data-cp-retained-more>More</button></details><dialog class="contactpanda-salutation-preview"><p class="contactpanda-salutation-preview-message"></p><div class="contactpanda-salutation-preview-rows" hidden><table><tbody></tbody></table></div><button class="contactpanda-apply-salutations" disabled>Apply</button></dialog>';
window.CONTACTPANDA_CONFIG={nonce:'fixture',ajaxUrl:'/ajax'};
window.CP_SELECTIONS={ajax:'/ajax',nonce:'fixture',user:1,source:'contactpanda',current:'0',rows:[],i18n:{added:'%d added'}};
sessionStorage.setItem('cp-selection:1:contactpanda',JSON.stringify({ids:['1'],names:{1:'Fixture Person'},query:'s=Previous',pending:true}));
window.requests=[];
window.fetch=(url,{body})=>{
 const params=new URLSearchParams(body);
 const operation=params.get('cp_selection_operation')||params.get('operation');
 requests.push([operation,params.get('ids'),params.get('preview')]);
 const data=operation==='search_results'?{ids:[],total:0}:{preview:'reviewtoken',rows:[{name:'Fixture Person',label:'Ms'}]};
 return Promise.resolve({ok:true,json:()=>Promise.resolve({success:true,data})});
};`);
await run(fs.readFileSync(root+'/assets/contactpanda-admin.js','utf8'));
await run(fs.readFileSync(root+'/assets/contactpanda-selections.js','utf8'));
await run('new Promise(r=>setTimeout(r,40))');
assert.deepEqual(await run('ContactPandaSelections.ids()'),['1']);
assert.equal(await run('document.querySelector(".cp-selection-retained li").textContent'),'Fixture Person');
assert.equal(await run('document.querySelector(".cp-selection-retained").hidden'),false);
assert.equal(await run('requests[0][0]'),'search_results');
await run(`document.querySelector('.contactpanda-open-bulk').click();new Promise(r=>setTimeout(r,40))`);
assert.deepEqual(await run('requests[1]'),['preview','["1"]',null]);
assert.equal(await run('document.querySelector(".contactpanda-salutation-preview tbody").textContent'),'Fixture PersonMs');
assert.equal(await run('document.querySelector(".contactpanda-apply-salutations").disabled'),false);
assert.equal(await run('requests.length'),2);
console.log('PASS selection remains visible with zero search results and salutation preview requires confirmation');
console.log('PASS keyboard inline editing across rows, hidden fields, server errors, Escape and next focus');
console.log('PASS print content prepared in iframe without opening an empty tab');
console.log('PASS additive selection across searches, visible header selection, removal without deletion, saved-list chooser and newsletter draft retention');
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>{browser.kill();server.close()});
setTimeout(()=>{browser.kill();server.close();process.exitCode=1},45000).unref();
