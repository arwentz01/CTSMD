/* CTSMD web practice player: zero CDN dependencies, native browser video playback. */
(function(){
 'use strict';
 document.querySelectorAll('.rl-card-actions').forEach(function(card){
  var video=card.querySelector('video');
  var tools=card.querySelector('.rl-playback-tools');
  if(!video||!tools)return;
  var start=null,end=null;
  var rewind=tools.querySelector('[data-video-rewind]');
  var speed=tools.querySelector('[data-video-speed]');
  var markStart=tools.querySelector('[data-loop-start]');
  var markEnd=tools.querySelector('[data-loop-end]');
  var clear=tools.querySelector('[data-loop-clear]');
  function update(){clear.hidden=start===null&&end===null;markStart.textContent=start===null?'Loop start':'A: '+start.toFixed(1)+'s';markEnd.textContent=end===null?'Loop end':'B: '+end.toFixed(1)+'s'}
  rewind.addEventListener('click',function(){video.currentTime=Math.max(0,video.currentTime-10)});
  speed.addEventListener('change',function(){video.playbackRate=Number(speed.value)||1});
  markStart.addEventListener('click',function(){start=video.currentTime;if(end!==null&&end<=start)end=null;update()});
  markEnd.addEventListener('click',function(){if(start===null)return;var next=video.currentTime;if(next<=start)return;end=next;update()});
  clear.addEventListener('click',function(){start=null;end=null;update()});
  video.addEventListener('timeupdate',function(){if(start!==null&&end!==null&&video.currentTime>=end){video.currentTime=start;if(video.paused)void video.play().catch(function(){})}});
  video.addEventListener('ended',function(){if(start!==null&&end!==null){video.currentTime=start;void video.play().catch(function(){})}});
  update();
 });
})();