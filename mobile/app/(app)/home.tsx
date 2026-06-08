import { useState } from 'react';
import { View, Text, Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useAuth } from '../../src/auth/AuthContext';
import * as spacesApi from '../../src/api/spaces';
import { resolveAuditOptions } from '../../src/audit/auditType';
import { useSync } from '../../src/offline/SyncProvider';
import { Screen } from '../../src/ui/Screen';
import { AppHeader } from '../../src/ui/AppHeader';
import { Card } from '../../src/ui/Card';
import { Field } from '../../src/ui/Field';
import { Button } from '../../src/ui/Button';
import { Badge } from '../../src/ui/Badge';
import { colors, spacing, typography } from '../../src/theme';

export default function HomeScreen() {
  const { token, permissions, signOut } = useAuth();
  const { pendingCount } = useSync();
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
    <Screen header={<AppHeader brand onSignOut={signOut} />}>
      {pendingCount > 0 && (
        <Pressable
          onPress={() => router.push('/(app)/queue')}
          style={styles.pendingBadge}
          accessibilityRole="button"
        >
          <Badge label={`${pendingCount} pendientes`} tone="warning" icon="cloud-upload-outline" />
        </Pressable>
      )}

      <Text style={styles.title}>Buscar espacio</Text>

      <Card title="Buscar espacio">
        <Field
          label="Código del espacio"
          icon="search"
          testID="code"
          value={code}
          onChangeText={setCode}
          autoCapitalize="characters"
        />
        {error && <Text style={styles.error}>{error}</Text>}
        <Button
          title="Buscar"
          variant="dark"
          icon="search"
          fullWidth
          onPress={search}
          loading={busy}
          disabled={code.trim() === ''}
        />
      </Card>

      <View style={styles.empty}>
        <Ionicons name="search" size={44} color={colors.textMuted} />
        <Text style={styles.emptyHeading}>Busque un espacio</Text>
        <Text style={styles.emptyHelper}>
          Ingrese el código del elemento publicitario para comenzar.
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pendingBadge: {
    alignSelf: 'flex-start',
    marginBottom: spacing.lg,
  },
  title: {
    ...typography.title,
    marginBottom: spacing.lg,
  },
  error: {
    ...typography.small,
    color: colors.danger,
    marginBottom: spacing.md,
  },
  empty: {
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.xxl,
    gap: spacing.sm,
  },
  emptyHeading: {
    ...typography.h2,
    marginTop: spacing.sm,
  },
  emptyHelper: {
    ...typography.bodySecondary,
    textAlign: 'center',
  },
});
