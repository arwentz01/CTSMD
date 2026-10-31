'use strict';
/* Layout math shared by the editor and customer view; no inventory or pricing state. */
((scope) => {
 const check=(x,y)=>{if(!Number.isFinite(x)||!Number.isFinite(y)||x<0||y<0||x>5000||y>5000)throw new Error('This arrangement extends beyond the layout. Move the starting position inward or reduce the angle/spacing.');};
 const angle=value=>{if(!Number.isFinite(value)||value<-180||value>180)throw new Error('Choose an angle between −180° and 180°.');return value*Math.PI/180;};
 const dimensions=item=>item.kind==='seat'?{width:40,height:40}:{width:Number(item.width??(item.kind==='stage'?280:item.kind==='interpreter'?160:100)),height:Number(item.height??(item.kind==='stage'?60:40))};
 function row(count,x,y,spacing,degrees){if(!Number.isInteger(count)||count<1||count>50||!Number.isFinite(spacing)||spacing<24||spacing>200)throw new Error('Generate 1–50 seats with spacing between 24 and 200.');const radians=angle(degrees);return Array.from({length:count},(_,i)=>{const px=x+i*spacing*Math.cos(radians),py=y+i*spacing*Math.sin(radians);check(px,py);return{x:Math.round(px*100)/100,y:Math.round(py*100)/100};});}
 function rotate(items,degrees){if(!items.length)throw new Error('Choose a row containing seats to rotate.');const radians=angle(degrees),cx=items.reduce((n,s)=>n+s.x,0)/items.length,cy=items.reduce((n,s)=>n+s.y,0)/items.length;return items.map(s=>{const x=cx+(s.x-cx)*Math.cos(radians)-(s.y-cy)*Math.sin(radians),y=cy+(s.x-cx)*Math.sin(radians)+(s.y-cy)*Math.cos(radians);check(x,y);return{x:Math.round(x*100)/100,y:Math.round(y*100)/100};});}
 function bounds(items){if(!items.length)return[0,0,800,500];const left=Math.min(...items.map(s=>s.x))-100,top=Math.min(...items.map(s=>s.y))-70,right=Math.max(...items.map(s=>s.x+dimensions(s).width))+40,bottom=Math.max(...items.map(s=>s.y+dimensions(s).height))+40;return[left,top,Math.max(400,right-left),Math.max(260,bottom-top)];}
 const api={row,rotate,bounds,dimensions};if(typeof module==='object'&&module.exports)module.exports=api;else scope.CommerceGeometry=api;
})(typeof window==='undefined'?{}:window);
