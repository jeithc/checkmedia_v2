import { View, Text, StyleSheet, ActivityIndicator, ScrollView, Image } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import * as auditsApi from '../../../src/api/audits';

export default function AuditDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token } = useAuth();

  const { data, isLoading, error } = useQuery({
    queryKey: ['audit', id],
    queryFn: () => auditsApi.getAudit(Number(id), token ?? ''),
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
        <Text style={styles.error}>No se pudo cargar la auditoría.</Text>
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>Auditoría #{data.id}</Text>
      <Text style={styles.meta}>
        Semana {data.week}/{data.year} · {data.audit_type === 'structural' ? 'Estructural' : 'General'}
      </Text>

      {data.values.map((v) => (
        <View key={v.criterion_id} style={styles.row}>
          <Text style={styles.cName}>{v.name ?? `Criterio ${v.criterion_id}`}</Text>
          <Text style={[styles.badge, v.value === 'bad' ? styles.badgeBad : styles.badgeGood]}>
            {v.value === 'bad' ? 'Malo' : 'Bueno'}
          </Text>
          {v.value === 'bad' && !!v.comment && <Text style={styles.comment}>{v.comment}</Text>}
        </View>
      ))}

      {!!data.observation && (
        <View style={styles.obsWrap}>
          <Text style={styles.cName}>Observación</Text>
          <Text>{data.observation}</Text>
        </View>
      )}

      <Text style={styles.cName}>Fotos ({data.photos.length})</Text>
      <View style={styles.thumbs}>
        {data.photos.map((p) => (
          <Image key={p.id} source={{ uri: p.url }} style={styles.thumb} />
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  error: { color: '#dc2626' },
  title: { fontSize: 22, fontWeight: '700' },
  meta: { color: '#475569', marginTop: 4, marginBottom: 16 },
  row: { marginBottom: 12 },
  cName: { fontWeight: '600', marginBottom: 4 },
  badge: { alignSelf: 'flex-start', paddingVertical: 2, paddingHorizontal: 10, borderRadius: 999, overflow: 'hidden', fontSize: 13 },
  badgeGood: { backgroundColor: '#bbf7d0', color: '#166534' },
  badgeBad: { backgroundColor: '#fecaca', color: '#991b1b' },
  comment: { color: '#475569', marginTop: 2 },
  obsWrap: { marginVertical: 12 },
  thumbs: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 8 },
  thumb: { width: 100, height: 100, borderRadius: 8, backgroundColor: '#e2e8f0' },
});
