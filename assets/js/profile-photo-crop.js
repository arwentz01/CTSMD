(()=>{'use strict';
const form=document.querySelector('[data-avatar-crop-form]');if(!form)return;
const input=form.querySelector('[data-avatar-source]');
const controls=form.querySelector('[data-avatar-crop-controls]');
const canvas=form.querySelector('[data-avatar-preview]');
const zoom=form.querySelector('[data-avatar-zoom]');
const x=form.querySelector('[data-avatar-x]');
const y=form.querySelector('[data-avatar-y]');
const status=form.querySelector('[data-avatar-crop-status]');
const context=canvas?.getContext('2d');
if(!input||!controls||!canvas||!context)return;
let image=null,objectUrl=null,processing=false,start=null,skipCrop=false;
function render(target=canvas){
 if(!image)return;
 const ctx=target.getContext('2d');if(!ctx)return;
 const size=target.width;
 const scale=Math.max(size/image.naturalWidth,size/image.naturalHeight)*Number(zoom.value);
 const width=image.naturalWidth*scale,height=image.naturalHeight*scale;
 const left=(size-width)/2+(width-size)*Number(x.value)/200;
 const top=(size-height)/2+(height-size)*Number(y.value)/200;
 ctx.fillStyle='#fff';ctx.fillRect(0,0,size,size);ctx.drawImage(image,left,top,width,height);
}
function reset(){
 image=null;controls.hidden=true;if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;
}
input.addEventListener('change',()=>{
 reset();const file=input.files?.[0];if(!file)return;
 if(file.size>6*1024*1024||!['image/jpeg','image/png','image/webp'].includes(file.type)){
  input.value='';status.textContent='Select a JPEG, PNG or WebP file smaller than 6 MB.';return;
 }
 status.textContent='Preparing preview…';objectUrl=URL.createObjectURL(file);
 const selected=objectUrl,element=new Image();
 element.onload=()=>{
  if(selected!==objectUrl)return;image=element;zoom.value='1';x.value='0';y.value='0';
  controls.hidden=false;status.textContent='Move or zoom your photo, then save.';render();
 };
 element.onerror=()=>{if(selected!==objectUrl)return;input.value='';reset();status.textContent='Unable to open this image.';};
 element.src=objectUrl;
});
[zoom,x,y].forEach(control=>control.addEventListener('input',()=>render()));
canvas.addEventListener('pointerdown',event=>{if(!image)return;canvas.setPointerCapture(event.pointerId);start={px:event.clientX,py:event.clientY,x:Number(x.value),y:Number(y.value)};});
canvas.addEventListener('pointermove',event=>{
 if(!start||!image)return;
 const bounds=canvas.getBoundingClientRect();
 const factor=Math.max(0.05,Number(zoom.value)-0.75);
 x.value=String(Math.max(-100,Math.min(100,start.x+(event.clientX-start.px)/bounds.width*170/factor)));
 y.value=String(Math.max(-100,Math.min(100,start.y+(event.clientY-start.py)/bounds.height*170/factor)));
 render();
});
canvas.addEventListener('pointerup',()=>{start=null;});
canvas.addEventListener('pointercancel',()=>{start=null;});
form.addEventListener('submit',async event=>{
 if(skipCrop||processing||!image)return;
 event.preventDefault();processing=true;status.textContent='Saving your adjusted crop…';
 const output=document.createElement('canvas');output.width=512;output.height=512;render(output);
 try{
  const blob=await new Promise(resolve=>output.toBlob(resolve,'image/jpeg',0.88));
  if(!blob)throw new Error('Could not prepare image.');
  const original=input.files?.[0];if(!original)throw new Error('Choose a photo.');
  const transfer=new DataTransfer();transfer.items.add(new File([blob],'profile-photo.jpg',{type:'image/jpeg'}));
  input.files=transfer.files;
  skipCrop=true;form.requestSubmit(event.submitter||undefined);
 }catch(error){processing=false;status.textContent='Cropping was unavailable. CTSMD will center-crop the original image.';skipCrop=true;form.requestSubmit(event.submitter||undefined);}
});
})();
