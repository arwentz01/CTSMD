const {test}=require('node:test');
const assert=require('node:assert/strict');
const ts=require('typescript'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
function render(os,visible=false){
 const handlers={},removed=[];
 const React={createElement:(type,props,...children)=>({type,props:props||{},children}),useState:()=>[visible,()=>{}],useEffect:fn=>{const cleanup=fn();if(cleanup)cleanup()}};
 const native={Keyboard:{addListener:(name,fn)=>{handlers[name]=fn;return {remove:()=>removed.push(name)}}},KeyboardAvoidingView:'KeyboardAvoidingView',Platform:{OS:os},StyleSheet:{create:x=>x}};
 const safe={SafeAreaView:'SafeAreaView',useSafeAreaInsets:()=>({top:24})};const exports={};
 const js=ts.transpileModule(fs.readFileSync(path.join(__dirname,'../src/components/ConversationViewport.tsx'),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.React,esModuleInterop:true}}).outputText;
 vm.runInNewContext(js,{exports,require:id=>id==='react'?React:id==='react-native'?native:id==='react-native-safe-area-context'?safe:id==='../theme/colors'?{colors:{background:'ivory'}}:null});
 return {tree:exports.ConversationViewport({children:'composer'}),handlers,removed};
}
for(const [os,behavior,show,hide] of [['android','height','keyboardDidShow','keyboardDidHide'],['ios','padding','keyboardWillShow','keyboardWillHide']])test(`${os} conversation viewport applies keyboard behavior and cleans up listeners`,()=>{
 const {tree,handlers,removed}=render(os);const avoid=tree.children[0];assert.equal(avoid.props.behavior,behavior);assert.equal(avoid.props.keyboardVerticalOffset,24);assert.equal(avoid.children[0],'composer');assert.ok(handlers[show]&&handlers[hide]);assert.deepEqual(removed,[show,hide]);
 const visible=render(os,true).tree;assert.equal(visible.props.edges.includes('bottom'),false);
});
