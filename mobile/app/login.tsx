import React, {useState} from 'react';
import {Ionicons} from '@expo/vector-icons';
import {
  ActivityIndicator,
  Alert,
  Image,
  KeyboardAvoidingView,
  Linking,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import {TouchTarget as Pressable} from '../src/components/TouchTarget';
import {useAuth} from '../src/auth/AuthProvider';
import {colors} from '../src/theme/colors';

const WEB_BASE = 'https://ctsmd.me';

async function openAccountPage(path: '/join' | '/forgot-password') {
  try {
    await Linking.openURL(WEB_BASE + path);
  } catch {
    Alert.alert('Unable to open page', 'Please visit ' + WEB_BASE + path + ' in your browser.');
  }
}

export default function Login() {
  const {signIn} = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (busy || !email.trim() || !password) return;
    setBusy(true);
    setError('');
    try {
      await signIn(email.trim(), password);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unable to sign in.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <SafeAreaView style={s.safe} edges={['top', 'bottom', 'left', 'right']}>
      <KeyboardAvoidingView
        style={s.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          style={s.flex}
          contentContainerStyle={s.scrollContent}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode={Platform.OS === 'ios' ? 'interactive' : 'on-drag'}
          automaticallyAdjustKeyboardInsets={Platform.OS === 'ios'}
          showsVerticalScrollIndicator={false}
        >
          <View style={s.content}>
            <View style={s.brand}>
              <Image
                source={require('../assets/images/ctsmd-logo.png')}
                style={s.logo}
                resizeMode="contain"
                accessible={false}
              />
              <Text style={s.eyebrow}>CHILDREN'S THEATRE OF SOUTHERN MARYLAND</Text>
              <Text style={s.title}>CTSMD <Text style={s.brandAccent}>Connect</Text></Text>
              <Text style={s.tagline}>A place for every part of the production.</Text>
            </View>

            <View style={s.card}>
              <Text style={s.cardEyebrow}>WELCOME BACK</Text>
              <Text style={s.heading} accessibilityRole="header">Sign in</Text>
              <Text style={s.help}>Access your productions, schedules and community.</Text>

              <Text style={s.label}>Email address</Text>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="email-address"
                autoComplete="email"
                textContentType="emailAddress"
                accessibilityLabel="Email address"
                value={email}
                onChangeText={setEmail}
                placeholder="you@example.com"
                placeholderTextColor={colors.control}
                selectionColor={colors.primary}
                style={s.input}
                returnKeyType="next"
              />

              <View style={s.passwordLabelRow}>
                <Text style={s.label}>Password</Text>
                <Pressable onPress={() => void openAccountPage('/forgot-password')} accessibilityRole="link">
                  <Text style={s.inlineLink}>Forgot password?</Text>
                </Pressable>
              </View>
              <View style={s.passwordWrap}>
                <TextInput
                  secureTextEntry={!showPassword}
                  autoCapitalize="none"
                  autoCorrect={false}
                  autoComplete="current-password"
                  textContentType="password"
                  accessibilityLabel="Password"
                  value={password}
                  onChangeText={setPassword}
                  placeholder="Enter your password"
                  placeholderTextColor={colors.control}
                  selectionColor={colors.primary}
                  style={s.passwordInput}
                  returnKeyType="go"
                  onSubmitEditing={() => void submit()}
                />
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}
                  accessibilityHint="Toggles whether the password is visible"
                  onPress={() => setShowPassword(value => !value)}
                  style={s.visibilityButton}
                >
                  <Ionicons
                    name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                    size={22}
                    color={colors.muted}
                  />
                </Pressable>
              </View>

              {!!error && <Text accessibilityRole="alert" style={s.error}>{error}</Text>}

              <Pressable
                accessibilityRole="button"
                disabled={busy || !email.trim() || !password}
                onPress={() => void submit()}
                style={s.submit}
              >
                {busy ? <ActivityIndicator color="#fff"/> : <Text style={s.submitText}>Sign in</Text>}
              </Pressable>

              <View style={s.divider} />
              <Text style={s.newHere}>New to CTSMD Connect?</Text>
              <Pressable
                accessibilityRole="link"
                onPress={() => void openAccountPage('/join')}
                style={s.createAccount}
              >
                <Text style={s.createAccountText}>Create parent/guardian account</Text>
                <Ionicons name="arrow-forward" size={17} color={colors.primary}/>
              </Pressable>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  flex: {flex: 1},
  safe: {flex: 1, backgroundColor: colors.background},
  scrollContent: {flexGrow: 1, justifyContent: 'center', paddingHorizontal: 20, paddingTop: 24, paddingBottom: 36},
  content: {width: '100%', maxWidth: 440, alignSelf: 'center', gap: 25},
  brand: {alignItems: 'center', gap: 7},
  logo: {width: 76, height: 76, marginBottom: 6},
  eyebrow: {fontSize: 10, letterSpacing: 1.35, fontWeight: '700', color: colors.primary, textAlign: 'center'},
  title: {fontSize: 34, fontWeight: '800', color: colors.navy, letterSpacing: -0.7},
  brandAccent: {color: colors.primary},
  tagline: {fontSize: 14, lineHeight: 21, color: colors.muted, textAlign: 'center'},
  card: {backgroundColor: colors.card, borderWidth: 1, borderColor: colors.border, borderRadius: 20, padding: 22, gap: 11},
  cardEyebrow: {fontSize: 11, fontWeight: '800', letterSpacing: 1.7, color: colors.primary},
  heading: {fontSize: 27, fontWeight: '800', letterSpacing: -0.4, color: colors.navy},
  help: {fontSize: 14, lineHeight: 21, color: colors.muted, marginBottom: 9},
  label: {fontSize: 14, fontWeight: '700', color: colors.text},
  input: {borderWidth: 1, borderColor: colors.border, borderRadius: 11, backgroundColor: '#fff', color: colors.text, fontSize: 16, minHeight: 52, paddingHorizontal: 14},
  passwordLabelRow: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginTop: 5},
  inlineLink: {fontSize: 13, color: colors.primary, fontWeight: '700'},
  passwordWrap: {flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: colors.border, borderRadius: 11, backgroundColor: '#fff', minHeight: 52},
  passwordInput: {flex: 1, minWidth: 0, color: colors.text, fontSize: 16, paddingLeft: 14, paddingVertical: 12},
  visibilityButton: {width: 52, minHeight: 52, justifyContent: 'center', alignItems: 'center'},
  error: {color: colors.danger, fontSize: 13, lineHeight: 19},
  submit: {backgroundColor: colors.primary, borderRadius: 11, minHeight: 52, alignItems: 'center', justifyContent: 'center', marginTop: 7},
  submitText: {color: '#fff', fontSize: 16, fontWeight: '800'},
  divider: {height: 1, backgroundColor: colors.border, marginTop: 13, marginBottom: 5},
  newHere: {textAlign: 'center', color: colors.muted, fontSize: 13},
  createAccount: {minHeight: 48, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 8},
  createAccountText: {fontSize: 14, fontWeight: '800', color: colors.primary, textAlign: 'center'},
});
