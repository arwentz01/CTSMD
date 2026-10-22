(()=>{'use strict';
const pages=Array.from(document.querySelectorAll('[data-page]'));
const spread=document.querySelector('[data-spread]');
const prev=document.querySelector('[data-prev]');
const next=document.querySelector('[data-next]');
const count=document.querySelector('[data-page-count]');
const contents=document.querySelector('#program-contents');
const contentsToggle=document.querySelector('[data-toc-toggle]');
if(!pages.length||!spread||!prev||!next||!count)return;

let current=0;
const facing=()=>window.matchMedia('(min-width: 1250px)').matches;
const normalized=index=>{
  index=Math.max(0,Math.min(index,pages.length-1));
  if(facing()&&index>1&&index%2===0)return index-1;
  return index;
};
const visibleIndexes=index=>facing()&&index>0?[index,index+1].filter(i=>i<pages.length):[index];
const indexFromHash=()=>{const match=location.hash.match(/^#page-(\d+)$/);return match?Number(match[1])-1:0;};

function show(index,updateUrl=true){
  current=normalized(index);
  const visible=visibleIndexes(current);
  pages.forEach((page,i)=>{page.hidden=!visible.includes(i);page.setAttribute('aria-hidden',visible.includes(i)?'false':'true');});
  spread.dataset.facing=String(visible.length===2);
  prev.disabled=current===0;
  next.disabled=visible[visible.length-1]>=pages.length-1;
  const first=visible[0]+1,last=visible[visible.length-1]+1;
  count.textContent=first===last?`Page ${first} of ${pages.length}`:`Pages ${first}–${last} of ${pages.length}`;
  if(updateUrl)history.replaceState(null,'',`#page-${first}`);
  window.scrollTo({top:0,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
}

prev.addEventListener('click',()=>show(facing()&&current>1?current-2:current-1));
next.addEventListener('click',()=>show(facing()&&current>0?current+2:current+1));
document.addEventListener('keydown',event=>{
  if(event.altKey||event.ctrlKey||event.metaKey||event.target.closest('button,a,input,textarea,select,[contenteditable]'))return;
  if(event.key==='ArrowLeft'){event.preventDefault();prev.click();}
  if(event.key==='ArrowRight'){event.preventDefault();next.click();}
});
document.querySelectorAll('a[href^="#page-"]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();const page=Number(link.hash.slice(6))-1;show(page);if(contents){contents.hidden=true;contentsToggle?.setAttribute('aria-expanded','false');}}));
contentsToggle?.addEventListener('click',()=>{const open=contents?.hidden??true;if(contents)contents.hidden=!open;contentsToggle.setAttribute('aria-expanded',String(open));});
document.querySelectorAll('[data-share]').forEach(button=>button.addEventListener('click',()=>navigator.share?navigator.share({title:document.title,url:location.href}):navigator.clipboard.writeText(location.href)));
window.addEventListener('hashchange',()=>show(indexFromHash(),false));
window.addEventListener('resize',()=>show(current,false));
window.addEventListener('beforeprint',()=>pages.forEach(page=>page.hidden=false));
window.addEventListener('afterprint',()=>show(current,false));
show(indexFromHash(),false);
})();
