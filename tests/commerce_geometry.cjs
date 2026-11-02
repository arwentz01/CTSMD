'use strict';
const assert=require('node:assert/strict'),g=require('../assets/js/commerce-geometry.js');
const near=(a,b)=>assert.ok(Math.abs(a-b)<.02,`${a} ≠ ${b}`);
const angled=g.row(4,100,100,50,45);near(angled[1].x-angled[0].x,35.36);near(angled[1].y-angled[0].y,35.36);
const horizontal=g.row(3,100,100,50,0),rotated=g.rotate(horizontal,90);near(rotated[0].x,150);near(rotated[0].y,50);near(rotated[2].x,150);near(rotated[2].y,150);
near(Math.hypot(rotated[1].x-rotated[0].x,rotated[1].y-rotated[0].y),50);assert.deepEqual(horizontal,[{x:100,y:100},{x:150,y:100},{x:200,y:100}]);
assert.throws(()=>g.row(5,10,10,52,-90),/beyond/);assert.throws(()=>g.rotate([{x:0,y:0},{x:100,y:0}],90),/beyond/);assert.throws(()=>g.row(51,100,100,52,0));assert.throws(()=>g.row(3,100,100,10,0));assert.throws(()=>g.row(3,100,100,52,Infinity));
const items=[{kind:'seat',x:100,y:100},{kind:'stage',x:200,y:10,width:900,height:80},{kind:'interpreter',x:1200,y:80,width:160,height:40}],bounds=g.bounds(items);assert.ok(bounds[0]+bounds[2]>=1360);assert.ok(bounds[1]+bounds[3]>=140);assert.equal(g.dimensions({kind:'stage'}).width,280);assert.equal(g.dimensions({kind:'seat'}).width,40);
console.log('PASS angled generation, rigid rotation, bounds rejection, unchanged originals and marker-aware fit');
