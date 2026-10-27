(function(){'use strict';
document.querySelectorAll('[data-copy-video-link]').forEach(function(button){
 button.addEventListener('click',async function(){
  var id=Number(button.dataset.videoId);
  if(!Number.isSafeInteger(id)||id<1)return;
  var title=(button.dataset.videoTitle||'Watch video').replace(/[\[\]\\]/g,'').replace(/\s+/g,' ').trim().slice(0,150)||'Watch video';
  var link=new URL('/videos/view?id='+id,window.location.origin).href;
  var snippet='['+title+']('+link+')';
  try{
   if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(snippet)}
   else{
    var field=document.createElement('textarea');field.value=snippet;field.style.position='fixed';field.style.left='-9999px';document.body.appendChild(field);field.select();if(!document.execCommand('copy'))throw Error('Copy unavailable');field.remove();
   }
   var old=button.textContent;button.textContent='Copied — paste into Community';window.setTimeout(function(){button.textContent=old},3000);
  }catch(e){window.prompt('Copy this Community link:',snippet)}
 });
});
})();