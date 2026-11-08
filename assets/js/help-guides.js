(function(){
 'use strict';
 var input=document.getElementById('guide-search');
 var list=document.getElementById('guide-list');
 if(!input||!list)return;
 var guides=Array.from(list.querySelectorAll('.guide-article'));
 var filters=Array.from(document.querySelectorAll('[data-guide-category]'));
 var empty=document.getElementById('guide-no-results');
 var active='all';
 function update(){
  var query=input.value.toLocaleLowerCase().trim();
  var shown=0;
  guides.forEach(function(guide){
   var category=guide.getAttribute('data-guide-category')||'';
   var haystack=guide.getAttribute('data-guide-search')||'';
   var visible=(active==='all'||category===active)&&(!query||haystack.includes(query));
   guide.hidden=!visible;if(visible)shown++;
  });
  if(empty)empty.hidden=shown!==0;
 }
 filters.forEach(function(button){
  button.addEventListener('click',function(){
   active=button.getAttribute('data-guide-category')||'all';
   filters.forEach(function(item){
    var selected=item===button;
    item.classList.toggle('is-selected',selected);
    item.setAttribute('aria-pressed',String(selected));
   });update();
  });
 });
 input.addEventListener('input',update);
 function openHash(){
  if(!location.hash.startsWith('#guide-'))return;
  var guide=document.getElementById(location.hash.slice(1));
  if(guide&&guide.matches('.guide-article')){
   active='all';input.value='';
   filters.forEach(function(button){
    var selected=button.getAttribute('data-guide-category')==='all';
    button.classList.toggle('is-selected',selected);
    button.setAttribute('aria-pressed',String(selected));
   });
   update();guide.open=true;
   guide.scrollIntoView({block:'start',behavior:'auto'});
  }
 }
 window.addEventListener('hashchange',openHash);
 update();openHash();
})();
