import Constants from 'expo-constants';
import {Platform} from 'react-native';

/**
 * Expo Go has no Android remote-push implementation (SDK 53+).
 * Check both ownership and execution environment because the latter may
 * identify development clients as storeClient on some Expo SDK versions.
 */
export function isExpoGo():boolean {
 return Constants.appOwnership==='expo'||Constants.expoGoConfig!=null;
}
export function supportsNativePush():boolean {
 return (Platform.OS==='ios'||Platform.OS==='android')&&!isExpoGo();
}
