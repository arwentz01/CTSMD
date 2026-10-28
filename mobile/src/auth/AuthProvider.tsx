import React,{createContext,useCallback,useContext,useEffect,useMemo,useRef,useState} from 'react';
import {AppState,Alert} from 'react-native';
import {router} from 'expo-router';
import {login,me,logoutSession} from '../api/client';
import {clearToken,readToken,writeToken,onSessionCleared} from './session';
import {storedPushIdentity,forgetPushIdentity} from '../notifications/device';
import type {CtsmdUser} from '../types/ctsmd';
type Auth={user:CtsmdUser|null;busy:boolean;signIn:(email:string,password:string)=>Promise<void>;signOut:()=>Promise<void>;refresh:()=>Promise<void>};
const Context=createContext<Auth|null>(null);
export function AuthProvider({children}:{children:React.ReactNode}){
 const [user,setUser]=useState<CtsmdUser|null>(null),[busy,setBusy]=useState(true);
 const generation=useRef(0);
 const refresh=useCallback(async()=>{const current=++generation.current;try{const token=await readToken();if(!token){if(current===generation.current)setUser(null);return}const data=await me();if(current===generation.current)setUser(data.user)}catch(e){if(current===generation.current)setUser(null)}finally{if(current===generation.current)setBusy(false)}},[]);
 useEffect(()=>{void refresh()},[refresh]);
 useEffect(()=>onSessionCleared(()=>{generation.current++;setUser(null);setBusy(false)}),[]);
 useEffect(()=>{const listener=AppState.addEventListener('change',state=>{if(state==='active')void refresh()});return()=>listener.remove()},[refresh]);
 const signIn=useCallback(async(email:string,password:string)=>{const current=++generation.current;const result=await login(email,password);if(current!==generation.current)throw new Error('Sign-in was interrupted. Please try again.');await writeToken(result.token);try{const verified=await me();if(current===generation.current)setUser(verified.user)}catch(e){await clearToken(result.token);throw e}},[]);
 const signOut=useCallback(async()=>{const token=await readToken();generation.current++;setUser(null);let failed=false;try{if(token)await logoutSession(token,(await storedPushIdentity())||undefined);await forgetPushIdentity()}catch{failed=true}finally{await clearToken(token);router.replace('/login')}if(failed)Alert.alert('Local sign-out complete','Server session and push cleanup could not be confirmed. Reconnect and sign in to retry device cleanup.');},[]);
 const value=useMemo(()=>({user,busy,refresh,signIn,signOut}),[user,busy,refresh,signIn,signOut]);return <Context.Provider value={value}>{children}</Context.Provider>;
}
export function useAuth(){const value=useContext(Context);if(!value)throw new Error('AuthProvider missing');return value}
