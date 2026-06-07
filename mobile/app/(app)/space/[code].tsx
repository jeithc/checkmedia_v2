import { View, Text, StyleSheet, ActivityIndicator, ScrollView } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import * as spacesApi from '../../../src/api/spaces';
import { resolveAuditOptions } from '../../../src/audit/auditType';
import { Button } from '../../../src/ui/Button';

export default function SpaceScreen() {
  const { code } = useLocalSearchParams<{ code: string }>();
  const { token, permissions } = useAuth();
  const auditType = permissions ? resolveAuditOptions(permissions).defaultType : 'general';

  const { data, isLoading, error } = useQuery({
    queryKey: ['space', code, auditType],
    queryFn: () => spacesApi.searchSpace(String(code), auditType, token ?? ''),
  });

  if (isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
      </View>
    );
  }
  if (error || !data) {
    return (
      <View style={styles.center}>
        <Text style={styles.error}>No se pudo cargar el espacio.</Text>
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.code}>{data.external_code}</Text>
      {data.type && <Text style={styles.meta}>Tipo: {data.type}</Text>}
      {data.booking && (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Pauta actual</Text>
          <Text>Cliente: {data.booking.client_name ?? '—'}</Text>
          <Text>Contrato: {data.booking.contract_code ?? '—'}</Text>
          <Text>Producto: {data.booking.product_name ?? '—'}</Text>
        </View>
      )}
      {data.duplicate && (
        <Text style={styles.warn}>Este espacio ya tiene una auditoría esta semana.</Text>
      )}
      <Button
        title="Auditar"
        onPress={() => router.push(`/(app)/audit/new?spaceId=${data.id}&code=${encodeURIComponent(data.external_code)}`)}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  code: { fontSize: 24, fontWeight: '700' },
  meta: { color: '#475569', marginTop: 4 },
  card: { backgroundColor: '#f1f5f9', padding: 16, borderRadius: 8, marginVertical: 16 },
  cardTitle: { fontWeight: '700', marginBottom: 8 },
  warn: { color: '#b45309', marginBottom: 16 },
  error: { color: '#dc2626' },
});
