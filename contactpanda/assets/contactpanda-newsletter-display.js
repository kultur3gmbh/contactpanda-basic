(function(){'use strict';document.querySelectorAll('.contactpanda-newsletter-display').forEach(root=>{
 const dialog=root.querySelector('dialog'),bar=root.querySelector('aside'),key='contactpanda-newsletter-dismissed',hours=Number(root.dataset.dismissHours),mode=root.dataset.mode;let timer,previous;
 const dismissed=()=>{try{return hours===0?sessionStorage.getItem(key)==='1':Number(localStorage.getItem(key)||0)>Date.now();}catch(e){return false;}};
 const remember=()=>{try{if(hours===0)sessionStorage.setItem(key,'1');else localStorage.setItem(key,String(Date.now()+hours*3600000));}catch(e){/* Closing still works if browser storage is blocked. */}clearTimeout(timer);};
 const open=()=>{previous=document.activeElement;if(dialog.showModal&&!dialog.open)dialog.showModal();};
 const close=()=>{remember();dialog.close();if(bar)bar.hidden=true;previous?.focus();};
 root.querySelector('[data-close]').addEventListener('click',close);dialog.addEventListener('cancel',e=>{e.preventDefault();close();});dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)close();}});
 root.querySelector('[data-open]')?.addEventListener('click',open);root.querySelector('[data-dismiss-bar]')?.addEventListener('click',()=>{remember();bar.hidden=true;});
 if(!dismissed()){if(bar)bar.hidden=false;if(mode==='popup'||mode==='both'){let first=Date.now();try{first=Number(sessionStorage.getItem('contactpanda-newsletter-first-visit'))||first;sessionStorage.setItem('contactpanda-newsletter-first-visit',String(first));}catch(e){}timer=setTimeout(()=>{if(!dismissed())open();},Math.max(0,Math.max(1,Number(root.dataset.delay))*1000-(Date.now()-first)));}}
});})();
