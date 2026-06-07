import { useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { router } from 'expo-router';
import { useAuth } from '../../src/auth/AuthContext';
import * as spacesApi from '../../src/api/spaces';
import { resolveAuditOptions } from '../../src/audit/auditType';
import { Field } from '../../src/ui/Field';
import { Button } from '../../src/ui/Button';

export default function HomeScreen() {
  const { token, permissions, signOut } = useAuth();
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const auditType = permissions ? resolveAuditOptions(permissions).defaultType : 'general';

  const search = async () => {
    setError(null);
    setBusy(true);
    try {
      await spacesApi.searchSpace(code.trim(), auditType, token ?? '');
      router.push(`/(app)/space/${encodeURIComponent(code.trim())}`);
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Error al buscar.';
      setError(msg.includes('no encontrado') ? 'Espacio no encontrado.' : msg);
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Buscar espacio</Text>
      <Field
        label="Código del espacio"
        testID="code"
        value={code}
        onChangeText={setCode}
        autoCapitalize="characters"
      />
      {error && <Text style={styles.error}>{error}</Text>}
      <Button title="Buscar" onPress={search} loading={busy} disabled={code.trim() === ''} />
      <View style={{ height: 24 }} />
      <Button title="Cerrar sesión" onPress={signOut} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24 },
  title: { fontSize: 20, fontWeight: '700', marginVertical: 16 },
  error: { color: '#dc2626', marginBottom: 12 },
});
