import React,{createContext,useCallback,useContext,useEffect,useMemo,useState} from 'react';
import {router} from 'expo-router';
import {ApiError,login,me} from '../api/client';
import {clearToken,readToken,writeToken} from './session';
import type {CtsmdUser} from '../types/ctsmd';
type Auth={user:CtsmdUser|null;busy:boolean;signIn:(email:string,password:string)=>Promise<void>;signOut:()=>Promise<void>;refresh:()=>Promise<void>};
const Context=createContext<Auth|null>(null);
export function AuthProvider({children}:{children:React.ReactNode}){
 const [user,setUser]=useState<CtsmdUser|null>(null),[busy,setBusy]=useState(true);
 const refresh=useCallback(async()=>{try{const token=await readToken();if(!token){setUser(null);return}const data=await me();setUser(data.user)}catch(e){setUser(null);if(e instanceof ApiError&&e.status===401)await clearToken()}finally{setBusy(false)}},[]);
 useEffect(()=>{void refresh()},[refresh]);
 const signIn=useCallback(async(email:string,password:string)=>{const result=await login(email,password);await writeToken(result.token);try{const verified=await me();setUser(verified.user)}catch(e){await clearToken();throw e}},[]);
 const signOut=useCallback(async()=>{const token=await readToken();setUser(null);await clearToken();router.replace('/login');if(token){try{await apiLogout(token)}catch{}}},[]);
 const value=useMemo(()=>({user,busy,refresh,signIn,signOut}),[user,busy,refresh,signIn,signOut]);return <Context.Provider value={value}>{children}</Context.Provider>;
}
async function apiLogout(token:string){const base=process.env.EXPO_PUBLIC_CTSMD_API_BASE_URL||'https://ctsmd.me';await fetch(base.replace(/\/$/,'')+'/api/mobile/logout',{method:'POST',headers:{Authorization:'Bearer '+token,'Content-Type':'application/json'},body:'{}'})}
export function useAuth(){const value=useContext(Context);if(!value)throw new Error('AuthProvider missing');return value}
