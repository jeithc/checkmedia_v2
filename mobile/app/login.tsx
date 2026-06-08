import { useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  TextInput,
  Image,
  StyleSheet,
  Pressable,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useAuth } from '../src/auth/AuthContext';
import { colors, spacing, radius, typography } from '../src/theme';

export default function LoginScreen() {
  const { signIn, signOut, unlock, status } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const autoPrompted = useRef(false);

  useEffect(() => {
    if (status === 'authenticated') router.replace('/(app)/home');
  }, [status]);

  // When the session is locked, auto-prompt biometric unlock once on mount.
  useEffect(() => {
    if (status === 'locked' && !autoPrompted.current) {
      autoPrompted.current = true;
      unlock();
    }
  }, [status, unlock]);

  const submit = async () => {
    setError(null);
    setBusy(true);
    try {
      await signIn(username.trim(), password);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo iniciar sesión.');
    } finally {
      setBusy(false);
    }
  };

  const isLocked = status === 'locked';

  return (
    <SafeAreaView style={styles.screen}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <View style={styles.form}>
          <Image
            source={require('../assets/logo.png')}
            style={styles.logo}
            resizeMode="contain"
          />

          {isLocked ? (
            <View style={styles.lockBox}>
              <Pressable
                onPress={unlock}
                style={({ pressed }) => [styles.bioBtn, pressed && styles.btnPressed]}
                accessibilityRole="button"
              >
                <Ionicons name="finger-print" size={28} color={colors.white} />
                <Text style={styles.btnText}>Desbloquear</Text>
              </Pressable>
              <Pressable onPress={signOut} hitSlop={8} style={styles.linkBtn}>
                <Text style={styles.link}>Usar otra cuenta</Text>
              </Pressable>
            </View>
          ) : (
            <>
              <View style={styles.input}>
                <Ionicons name="person-outline" size={20} color={colors.textMuted} />
                <TextInput
                  testID="username"
                  style={styles.inputText}
                  placeholder="Ingrese su Usuario"
                  placeholderTextColor="rgba(17,24,39,0.4)"
                  value={username}
                  onChangeText={setUsername}
                  autoCapitalize="none"
                  autoCorrect={false}
                />
              </View>
              <View style={styles.input}>
                <Ionicons name="lock-closed-outline" size={20} color={colors.textMuted} />
                <TextInput
                  testID="password"
                  style={styles.inputText}
                  placeholder="Ingrese su Clave"
                  placeholderTextColor="rgba(17,24,39,0.4)"
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry
                />
              </View>

              {error && <Text style={styles.error}>{error}</Text>}

              <Pressable
                onPress={submit}
                disabled={busy}
                style={({ pressed }) => [styles.btn, pressed && styles.btnPressed]}
                accessibilityRole="button"
              >
                {busy ? (
                  <ActivityIndicator color={colors.white} />
                ) : (
                  <View style={styles.btnInner}>
                    <Ionicons name="log-in-outline" size={20} color={colors.white} />
                    <Text style={styles.btnText}>Ingresar</Text>
                  </View>
                )}
              </Pressable>
            </>
          )}
        </View>

        <Text style={styles.footer}>
          © 2026. Check Media · EFECTIMEDIOS. Todos los derechos reservados.
        </Text>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: colors.brand,
  },
  flex: {
    flex: 1,
  },
  form: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: spacing.xl,
  },
  logo: {
    width: 240,
    height: 56,
    alignSelf: 'center',
    marginBottom: spacing.xxl,
  },
  input: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.lg,
    height: 54,
    marginBottom: spacing.lg,
  },
  inputText: {
    flex: 1,
    fontSize: 16,
    color: colors.text,
  },
  error: {
    color: colors.white,
    fontWeight: '600',
    marginBottom: spacing.md,
  },
  btn: {
    height: 54,
    borderRadius: radius.sm,
    backgroundColor: 'rgba(255,255,255,0.18)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.35)',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.xs,
  },
  btnPressed: {
    opacity: 0.75,
  },
  btnInner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  btnText: {
    color: colors.white,
    fontWeight: '700',
    fontSize: 16,
    letterSpacing: 0.5,
  },
  lockBox: {
    alignItems: 'center',
    gap: spacing.lg,
  },
  bioBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    height: 54,
    paddingHorizontal: spacing.xxl,
    borderRadius: radius.sm,
    backgroundColor: 'rgba(255,255,255,0.18)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.35)',
  },
  linkBtn: {
    paddingVertical: spacing.sm,
  },
  link: {
    color: 'rgba(255,255,255,0.85)',
    fontWeight: '600',
  },
  footer: {
    ...typography.small,
    color: 'rgba(255,255,255,0.6)',
    textAlign: 'center',
    paddingHorizontal: spacing.xl,
    paddingBottom: spacing.lg,
  },
});
