(function(){
'use strict';
var root=document.querySelector('[data-recipient-picker]');if(!root)return;
var input=root.querySelector('#comm-recipient-search');
var hidden=root.querySelector('[data-recipient-id]');
var results=root.querySelector('[role=listbox]');
var selected=root.querySelector('[data-recipient-selected]');
var hint=root.querySelector('[data-recipient-hint]');
var timer=null,controller=null,items=[],active=-1;
function clear(){items=[];active=-1;results.replaceChildren();input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');}
function setPerson(person){
 hidden.value=String(person.id);
 selected.textContent='To: '+person.name+' · '+person.role+' · '+person.identifier;
 selected.hidden=false;input.value=person.name;hint.textContent='Recipient selected.';clear();
}
function paint(){
 results.replaceChildren();
 items.forEach(function(person,index){
  var button=document.createElement('button');button.type='button';button.id='recipient-option-'+index;button.className='comm-recipient-option';button.setAttribute('role','option');button.setAttribute('aria-selected',String(index===active));
  var title=document.createElement('strong');title.textContent=person.name;
  var detail=document.createElement('small');detail.textContent=person.role+' · '+person.identifier;
  button.append(title,detail);button.addEventListener('click',function(){setPerson(person)});results.appendChild(button);
 });
 input.setAttribute('aria-expanded',String(items.length>0));
 if(active>=0)input.setAttribute('aria-activedescendant','recipient-option-'+active);else input.removeAttribute('aria-activedescendant');
}
input.addEventListener('input',function(){
 hidden.value='';selected.hidden=true;clear();clearTimeout(timer);if(controller)controller.abort();
 var q=input.value.trim();if(q.length<2){hint.textContent='Enter at least two letters to search.';return;}
 hint.textContent='Searching…';timer=setTimeout(async function(){
  controller=new AbortController();
  try{
   var response=await fetch(root.dataset.searchUrl+'?q='+encodeURIComponent(q),{credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal});
   if(!response.ok)throw new Error('Search unavailable');
   var data=await response.json();
   if(input.value.trim()!==q)return;
   items=Array.isArray(data.recipients)?data.recipients.slice(0,25):[];
   hint.textContent=items.length?items.length+' matching members. Select the correct person.':'No eligible members found.';
   paint();
  }catch(error){if(error.name!=='AbortError')hint.textContent='Could not search recipients. Please try again.';}
 },280);
});
input.addEventListener('keydown',function(event){
 if(event.key==='Escape'){clear();return;}
 if(!items.length)return;
 if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();active=(active+(event.key==='ArrowDown'?1:-1)+items.length)%items.length;paint();}
 if(event.key==='Enter'&&active>=0){event.preventDefault();setPerson(items[active]);}
});
root.closest('form')?.addEventListener('submit',function(event){if(!hidden.value){event.preventDefault();hint.textContent='Select a member from the search results before sending.';input.focus();}});
})();
