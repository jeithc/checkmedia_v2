import { useEffect, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { router } from 'expo-router';
import { useAuth } from '../src/auth/AuthContext';
import { biometricsAvailable, unlockWithBiometrics } from '../src/auth/biometrics';
import { loadSession } from '../src/auth/tokenStore';
import { Field } from '../src/ui/Field';
import { Button } from '../src/ui/Button';

export default function LoginScreen() {
  const { signIn, status } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (status === 'authenticated') router.replace('/(app)/home');
  }, [status]);

  // Offer biometric unlock if a session already exists on this device.
  useEffect(() => {
    (async () => {
      const stored = await loadSession();
      if (stored && (await biometricsAvailable())) {
        const ok = await unlockWithBiometrics();
        if (ok) router.replace('/(app)/home');
      }
    })();
  }, []);

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

  return (
    <View style={styles.container}>
      <Text style={styles.title}>CheckMedia Auditor</Text>
      <Field label="Usuario" testID="username" value={username} onChangeText={setUsername} />
      <Field
        label="Contraseña"
        testID="password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      {error && <Text style={styles.error}>{error}</Text>}
      <Button title="Ingresar" onPress={submit} loading={busy} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, justifyContent: 'center' },
  title: { fontSize: 24, fontWeight: '700', marginBottom: 24, textAlign: 'center' },
  error: { color: '#dc2626', marginBottom: 12 },
});
