(function(){
 'use strict';
 var form=document.getElementById('rl-video-upload');
 if(!form||!window.XMLHttpRequest||!window.FormData)return;
 var panel=document.getElementById('rl-upload-status');
 var phase=document.getElementById('rl-upload-phase');
 var percent=document.getElementById('rl-upload-percent');
 var progress=document.getElementById('rl-upload-progress');
 var bytes=document.getElementById('rl-upload-bytes');
 var speed=document.getElementById('rl-upload-speed');
 var message=document.getElementById('rl-upload-message');
 var submit=document.getElementById('rl-upload-submit');
 var cancel=document.getElementById('rl-upload-cancel');
 var fileInput=form.querySelector('input[type=file][name=video]');
 var xhr=null,startedAt=0,finished=false;
 function human(n){if(n<1048576)return Math.round(n/1024)+' KB';return (n/1048576).toFixed(1)+' MB'}
 function show(head,details,state){
  panel.hidden=false;panel.dataset.state=state||'sending';
  phase.textContent=head;message.textContent=details;
 }
 function enable(){
  form.querySelectorAll('input,select,textarea').forEach(function(el){el.disabled=false});
  submit.disabled=false;submit.textContent='Upload & publish';cancel.hidden=true;
 }
 function reset(){
  xhr=null;enable();finished=false;
 }
 function fail(details){
  show('Upload not completed',details,'error');percent.textContent='Not saved';
  progress.removeAttribute('value');speed.textContent='';
  reset();
 }
 function complete(details){
  finished=true;
  show('Video saved successfully',details||'Your video is ready in the library.','success');
  percent.textContent='Done';progress.value=100;speed.textContent='';
  cancel.hidden=true;
  // Reload to display the newly published item and updated disk totals.
  window.setTimeout(function(){window.location.assign(form.action||window.location.href)},1100);
 }
 cancel.addEventListener('click',function(){
  if(xhr&&!finished){xhr.abort();show('Upload cancelled','No new video was published. You can try again.','error');percent.textContent='Cancelled';progress.value=0;reset()}
 });
 form.addEventListener('submit',function(event){
  if(xhr)return;
  if(!form.reportValidity())return;
  event.preventDefault();
  var file=fileInput.files&&fileInput.files[0];
  if(!file){fail('Choose a video file first.');return}
  var maxNode=document.querySelector('.rl-hosting-limits strong');
  // Server remains authoritative; this is only a friendly preflight.
  var appLimit=form.getAttribute('data-max-bytes');
  if(appLimit&&file.size>Number(appLimit)){fail('This file is larger than the current server allowance ('+human(Number(appLimit))+'). Compress the video or choose a smaller file.');return}
  try{xhr=new XMLHttpRequest();xhr.open('POST',form.action||window.location.href,true)}
  catch(e){fail('Your browser could not begin the upload. Please retry.');return}
  finished=false;startedAt=Date.now();progress.value=0;percent.textContent='0%';bytes.textContent='0 MB / '+human(file.size);speed.textContent='';
  show('Uploading '+file.name,'Please keep this page open until CTSMD confirms the upload.','sending');
  submit.disabled=true;submit.textContent='Uploading…';
  // Keep enabled fields for FormData; lock only after the payload is copied.
  var payload=new FormData(form);
  form.querySelectorAll('input,select,textarea').forEach(function(el){el.disabled=true});
  cancel.hidden=false;
  xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
  xhr.setRequestHeader('Accept','application/json');
  xhr.upload.addEventListener('progress',function(e){
   if(!e.lengthComputable){percent.textContent='Uploading';bytes.textContent=human(e.loaded)+' transferred';return}
   var pct=Math.min(100,Math.round(100*e.loaded/e.total));
   progress.value=pct;percent.textContent=pct+'%';
   bytes.textContent=human(e.loaded)+' / '+human(e.total);
   var elapsed=Math.max(0.1,(Date.now()-startedAt)/1000);
   var rate=e.loaded/elapsed;
   var remaining=rate>0?(e.total-e.loaded)/rate:0;
   speed.textContent=human(rate)+'/s'+(remaining>0?' · about '+Math.ceil(remaining)+'s remaining':'');
   if(pct>=100)show('Transfer complete — saving video','The file reached Bluehost. CTSMD is verifying and recording it; please keep this page open.','processing');
  });
  xhr.upload.addEventListener('load',function(){progress.value=100;percent.textContent='100%';show('Transfer complete — saving video','CTSMD is finishing the upload and checking the file. Please keep this page open.','processing')});
  xhr.addEventListener('load',function(){
   var result=null;
   try{result=JSON.parse(xhr.responseText)}catch(e){}
   if(xhr.status>=200&&xhr.status<300&&result&&result.ok===true){complete(result.message)}
   else{fail(result&&result.message?result.message:'The server did not confirm the upload (HTTP '+xhr.status+'). The video may not have been saved; check the library before retrying.')}
  });
  xhr.addEventListener('error',function(){fail('Connection lost during upload. Check your connection and the library before trying again.')});
  xhr.addEventListener('timeout',function(){fail('Upload timed out. Check the library before trying again.')});
  xhr.addEventListener('abort',function(){if(xhr)fail('Upload was interrupted. Check the library before retrying.')});
  xhr.send(payload);
 });
})();