import { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ActivityIndicator,
  Image,
  Pressable,
  Modal,
  Linking,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import * as auditsApi from '../../../src/api/audits';
import { colors, spacing, radius, typography } from '../../../src/theme';
import { Screen } from '../../../src/ui/Screen';
import { AppHeader } from '../../../src/ui/AppHeader';
import { HeaderMenu } from '../../../src/ui/HeaderMenu';
import { Card } from '../../../src/ui/Card';
import { Badge } from '../../../src/ui/Badge';

export default function AuditDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token } = useAuth();
  const [zoomUri, setZoomUri] = useState<string | null>(null);

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
    <Screen header={<AppHeader onBack={() => router.back()} title={`Auditoría #${id}`} right={<HeaderMenu />} />}>
      <Text style={styles.title}>Auditoría #{data.id}</Text>
      <Text style={styles.meta}>
        Semana {data.week}/{data.year} · {data.audit_type === 'structural' ? 'Estructural' : 'General'}
      </Text>

      <Card title="Cumplimiento">
        {data.values.map((v, i) => (
          <View
            key={v.criterion_id}
            style={[styles.row, i > 0 && styles.rowDivider]}
          >
            <View style={styles.rowHead}>
              <Text style={styles.cName}>{v.name ?? `Criterio ${v.criterion_id}`}</Text>
              <Badge
                tone={v.value === 'bad' ? 'bad' : 'good'}
                label={v.value === 'bad' ? 'Malo' : 'Bueno'}
                icon={v.value === 'bad' ? 'close-circle' : 'checkmark-circle'}
              />
            </View>
            {v.value === 'bad' && !!v.comment && <Text style={styles.comment}>{v.comment}</Text>}
          </View>
        ))}
      </Card>

      {!!data.observation && (
        <Card title="Observación">
          <Text style={styles.obsText}>{data.observation}</Text>
        </Card>
      )}

      <Card title={`Fotos (${data.photos.length})`}>
        <View style={styles.thumbs}>
          {data.photos.map((p) =>
            p.file_type === 'pdf' ? (
              <Pressable key={p.id} onPress={() => Linking.openURL(p.url)} accessibilityLabel="Abrir PDF" style={styles.pdfThumb}>
                <Text style={styles.pdfText}>PDF</Text>
              </Pressable>
            ) : (
              <Pressable key={p.id} onPress={() => setZoomUri(p.url)} accessibilityLabel="Ampliar foto">
                <Image source={{ uri: p.url }} style={styles.thumb} />
              </Pressable>
            ),
          )}
        </View>
      </Card>

      <Modal visible={!!zoomUri} transparent animationType="fade" onRequestClose={() => setZoomUri(null)}>
        <Pressable style={styles.backdrop} onPress={() => setZoomUri(null)}>
          {!!zoomUri && (
            <Image source={{ uri: zoomUri }} style={styles.full} resizeMode="contain" />
          )}
          <Text style={styles.closeHint}>Toca para cerrar</Text>
        </Pressable>
      </Modal>
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.appBg },
  error: { ...typography.body, color: colors.danger },
  title: { ...typography.title },
  meta: { ...typography.overline, marginTop: spacing.xs, marginBottom: spacing.lg },
  row: { paddingVertical: spacing.md },
  rowDivider: { borderTopWidth: 1, borderTopColor: colors.borderSubtle },
  rowHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
  },
  cName: { ...typography.body, fontWeight: '500', flexShrink: 1 },
  comment: { ...typography.bodySecondary, marginTop: spacing.sm },
  obsText: { ...typography.body },
  thumbs: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  thumb: {
    width: 100,
    height: 100,
    borderRadius: radius.md,
    backgroundColor: colors.borderSubtle,
  },
  pdfThumb: {
    width: 100,
    height: 100,
    borderRadius: radius.md,
    backgroundColor: colors.borderSubtle,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pdfText: { ...typography.small, color: colors.textSecondary, fontWeight: "700" },
  backdrop: { flex: 1, backgroundColor: colors.overlay, alignItems: 'center', justifyContent: 'center' },
  full: { width: '100%', height: '85%' },
  closeHint: {
    ...typography.small,
    color: colors.textMuted,
    position: 'absolute',
    bottom: spacing.xxl,
  },
});
