import React,{useEffect,useState} from 'react';
import {Image,StyleSheet} from 'react-native';
import {readToken} from '../auth/session';

const base=(process.env.EXPO_PUBLIC_CTSMD_API_BASE_URL||'https://ctsmd.me').replace(/\/$/,'');
export function ProductionArtwork({productionId,style,enabled=true}:{productionId:number;style?:object;enabled?:boolean}){
 const [dataUrl,setDataUrl]=useState<string|null>(null);
 useEffect(()=>{
  let active=true;
  setDataUrl(null);
  (async()=>{
   if(!enabled)return;
   const token=await readToken();if(!token)return;
   const response=await fetch(base+'/api/mobile/production-artwork?production_id='+productionId+'&role=banner',{headers:{Authorization:'Bearer '+token,Accept:'image/*'},cache:'no-store'});
   if(!response.ok){console.warn('Production image request failed',productionId,response.status);return;}
   const blob=await response.blob();
   if(!blob.type.startsWith('image/'))return;
   const reader=new FileReader();
   reader.onload=()=>{if(active&&typeof reader.result==='string')setDataUrl(reader.result)};
   reader.readAsDataURL(blob);
  })().catch(e=>console.warn('Production image load failed',productionId,String(e)));
  return()=>{active=false};
 },[productionId,enabled]);
 return <Image source={dataUrl?{uri:dataUrl}:require('../../assets/images/theatre-stage.png')} style={[s.fill,style]} resizeMode="cover"/>;
}
const s=StyleSheet.create({fill:{position:'absolute',top:0,bottom:0,left:0,right:0,width:'100%',height:'100%'}});
