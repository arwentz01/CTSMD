import Constants from 'expo-constants';
import {Platform} from 'react-native';

/**
 * Temporary closed-testing update indicator. CTSMD.me/mobile-release.json is
 * updated only after Google Play has made the new test build available.
 * Native Android versionCode, not an OTA/JS app version, is compared.
 */
export type AvailableAndroidUpdate={version:string;versionCode:number};

const configured=process.env.EXPO_PUBLIC_CTSMD_API_BASE_URL||'https://ctsmd.me';
const BASE=configured.replace(/\/$/,'');
const CHECK_INTERVAL_MS=5*60*1000;
let lastCheck=0;
let cached:AvailableAndroidUpdate|null=null;

export const ANDROID_PLAY_STORE_URL='https://play.google.com/store/apps/details?id=com.ctsmd_connect_v01';

export function newerClosedTestRelease(manifest:unknown,installedCode:number|null|undefined):AvailableAndroidUpdate|null{
 if(typeof installedCode!=='number'||!Number.isSafeInteger(installedCode)||installedCode<=0)return null;
 if(!manifest||typeof manifest!=='object'||!('android' in manifest))return null;
 const release=(manifest as {android?:unknown}).android;
 if(!release||typeof release!=='object')return null;
 const data=release as Record<string,unknown>;
 if(data.track!=='closed_testing'||data.available!==true)return null;
 if(typeof data.version!=='string'||!/^\d+\.\d+\.\d+$/.test(data.version))return null;
 if(typeof data.versionCode!=='number'||!Number.isSafeInteger(data.versionCode)||data.versionCode<=installedCode)return null;
 return {version:data.version,versionCode:Number(data.versionCode)};
}

export async function checkForAndroidUpdate(force=false):Promise<AvailableAndroidUpdate|null>{
 if(Platform.OS!=='android'||Constants.appOwnership==='expo'||Constants.expoGoConfig!=null)return null;
 const installedCode=Constants.platform?.android?.versionCode;
 if(typeof installedCode!=='number'||installedCode<1)return null;
 if(!force&&Date.now()-lastCheck<CHECK_INTERVAL_MS)return cached;
 lastCheck=Date.now();
 const controller=new AbortController();
 const timeout=setTimeout(()=>controller.abort(),7000);
 try{
  const response=await fetch(BASE+'/mobile-release.json',{headers:{Accept:'application/json','Cache-Control':'no-cache'},signal:controller.signal});
  if(!response.ok){cached=null;return null;}
  cached=newerClosedTestRelease(await response.json(),installedCode);
  return cached;
 }catch{
  cached=null;
  return null; // Never interfere with Home or other navigation if offline.
 }finally{clearTimeout(timeout);}
}
