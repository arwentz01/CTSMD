(function(){
 'use strict';
 var root=document.querySelector('[data-getting-started-user]');
 if(!root)return;
 var panel=root.querySelector('.home-getting-started');
 var hide=root.querySelector('[data-start-hide]');
 var restore=root.querySelector('[data-start-restore]');
 if(!panel||!hide||!restore)return;
 var account=root.getAttribute('data-getting-started-user');
 if(!/^\d+$/.test(account||''))return;
 var key='ctsmd:getting-started:hidden-until:'+account;
 var duration=7*24*60*60*1000;
 function read(){
  try {
   var value=Number(window.localStorage.getItem(key)||0);
   return Number.isFinite(value)&&value>Date.now();
  } catch(_){return false;}
 }
 function setHidden(hidden){
  panel.hidden=hidden;
  restore.hidden=!hidden;
 }
 setHidden(read());
 hide.addEventListener('click',function(){
  try{window.localStorage.setItem(key,String(Date.now()+duration));}catch(_){}
  setHidden(true);
 });
 restore.addEventListener('click',function(){
  try{window.localStorage.removeItem(key);}catch(_){}
  setHidden(false);
  panel.querySelector('[data-start-hide]')?.focus();
 });
})();
