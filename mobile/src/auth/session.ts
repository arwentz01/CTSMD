import * as SecureStore from 'expo-secure-store';
const KEY='ctsmd-mobile-session';
export async function readToken(){return SecureStore.getItemAsync(KEY)}
export async function writeToken(token:string){await SecureStore.setItemAsync(KEY,token,{keychainAccessible:SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY})}
export async function clearToken(){await SecureStore.deleteItemAsync(KEY)}
