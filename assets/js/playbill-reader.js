/* CTSMD digital-program reader. All publication content comes from server-filtered HTML. */
(()=>{'use strict';
const root=document.body,main=document.querySelector('.pb2-public-main'),controls=document.querySelector('.pb2-reader-controls');
if(!main||!controls)return;
const original=Array.from(main.querySelectorAll(':scope > [data-reader-page]'));
if(!original.length)return;
const sizeFor=section=>section.querySelector('.pb2-cast')?4:section.querySelector('.pb2-team')?16:section.querySelector('.pb2-sponsors')?4:0;
for(const section of original){
 const count=sizeFor(section);if(!count)continue;
 const container=section.querySelector('.pb2-cast,.pb2-team,.pb2-sponsors');
 const items=Array.from(container.children);if(items.length<=count)continue;
 let insertAfter=section;
 for(let offset=count;offset<items.length;offset+=count){
  const extra=section.cloneNode(false);extra.removeAttribute('id');extra.classList.add('pb2-continuation');
  const label=section.querySelector(':scope > small');const heading=section.querySelector(':scope > h2');
  if(label)extra.append(label.cloneNode(true));if(heading){const h=heading.cloneNode(true);h.textContent+=' · continued';extra.append(h)}
  const wrap=container.cloneNode(false);items.slice(offset,offset+count).forEach(item=>wrap.append(item));extra.append(wrap);
  insertAfter.parentNode.insertBefore(extra,insertAfter.nextSibling);insertAfter=extra;
 }
}
const pages=Array.from(main.querySelectorAll(':scope > [data-reader-page]'));
pages.forEach((page,index)=>{page.dataset.pageNumber=String(index+1);page.setAttribute('aria-label','Program page '+(index+1)+' of '+pages.length)});
let index=0;
const prev=controls.querySelector('[data-prev]'),next=controls.querySelector('[data-next]'),label=controls.querySelector('[data-page-count]');
function select(n,changeHash){
 index=Math.max(0,Math.min(n,pages.length-1));
 pages.forEach((page,i)=>{page.classList.toggle('is-current',i===index);page.hidden=i!==index;});
 if(prev)prev.disabled=index===0;if(next)next.disabled=index===pages.length-1;
 if(label)label.textContent='Page '+(index+1)+' of '+pages.length;
 if(changeHash){const target=pages[index].id;history.replaceState(null,'',target?'#'+target:'#page-'+(index+1));}
 window.scrollTo(0,0);
}
function indexFromHash(){
 if(/^#page-\d+$/.test(location.hash)){return Number(location.hash.slice(6))-1}
 const target=location.hash.slice(1);return pages.findIndex(p=>p.id===target);
}
if(pages.length>1){
 controls.hidden=false;root.classList.add('pb2-reader-active');
 select(Math.max(0,indexFromHash()),false);
 prev?.addEventListener('click',()=>select(index-1,true));next?.addEventListener('click',()=>select(index+1,true));
 document.querySelectorAll('.pb2-toc a[href^="#"],.pb2-top a[href^="#"]').forEach(a=>a.addEventListener('click',event=>{
  const target=a.getAttribute('href')?.slice(1),pageIndex=pages.findIndex(p=>p.id===target);
  if(pageIndex>=0){event.preventDefault();select(pageIndex,true)}
 }));
 window.addEventListener('hashchange',()=>{const i=indexFromHash();if(i>=0)select(i,false)});
 document.addEventListener('keydown',event=>{
  if(event.altKey||event.ctrlKey||event.metaKey||event.target?.closest?.('button,input,textarea,select,[contenteditable]'))return;
  if(event.key==='ArrowRight'){event.preventDefault();select(index+1,true)}
  if(event.key==='ArrowLeft'){event.preventDefault();select(index-1,true)}
 });
}
const restore=()=>{if(pages.length>1)select(index,false)};
window.addEventListener('beforeprint',()=>{pages.forEach(p=>{p.hidden=false;p.removeAttribute('aria-hidden')});});
window.addEventListener('afterprint',restore);
})();
