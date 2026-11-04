const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const ts=require('typescript');
const path=require('node:path');
function renderer(){
 const exports={},opened=[],navigated=[];
 const React={createElement:(type,props,...children)=>({type,props:props||{},children})};
 const source=ts.transpileModule(fs.readFileSync(path.join(__dirname,'../src/components/CommunityBody.tsx'),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.React,esModuleInterop:true}}).outputText;
 vm.runInNewContext(source,{exports,require:id=>id==='react'?React:id==='react-native'?{Text:'Text',StyleSheet:{create:x=>x},Linking:{openURL:async url=>opened.push(url)}}:id==='expo-router'?{router:{push:route=>navigated.push(route)}}:id==='../theme/colors'?{colors:{text:'black',primary:'red'}}:null,URL});
 return {body:exports.CommunityBody,opened,navigated};
}
test('shared Community text renders safe links and routes CTSMD video through the authorized native screen',()=>{
 const {body,opened,navigated}=renderer();
 const node=body({body:'Watch [Test video](https://ctsmd.me/videos/view?id=1) and https://example.org.\n[Bad](javascript:alert)'});
 const parts=node.children.flat();const video=parts.find(x=>x?.props?.accessibilityRole==='link'&&x.children[0]==='Test video');
 assert.ok(video);assert.equal(parts.some(x=>typeof x==='string'&&x.includes('[Test video]')),false);
 video.props.onPress({stopPropagation(){}});assert.equal(navigated[0].pathname,'/videos/[id]');assert.equal(navigated[0].params.id,'1');
 const external=parts.find(x=>x?.children?.[0]==='https://example.org');assert.ok(external);external.props.onPress({stopPropagation(){}});assert.equal(opened[0],'https://example.org');
 assert.equal(parts.some(x=>x?.props?.accessibilityRole==='link'&&x.children[0]==='Bad'),false);
 assert.ok(parts.some(x=>typeof x==='string'&&x.includes('\n')));
});
