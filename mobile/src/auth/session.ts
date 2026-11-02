import * as SecureStore from 'expo-secure-store';
const KEY='ctsmd-mobile-session';
const listeners=new Set<()=>void>();
export function onSessionCleared(listener:()=>void){listeners.add(listener);return()=>{listeners.delete(listener)}}
export async function readToken(){return SecureStore.getItemAsync(KEY)}
export async function writeToken(token:string){await SecureStore.setItemAsync(KEY,token,{keychainAccessible:SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY})}
export async function clearToken(expected?:string|null){
 if(expected!==undefined&&await readToken()!==expected)return;
 await SecureStore.deleteItemAsync(KEY);
 listeners.forEach(listener=>listener());
}
