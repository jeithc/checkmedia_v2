import { View, Text, StyleSheet, ActivityIndicator } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import * as spacesApi from '../../../src/api/spaces';
import { resolveAuditOptions } from '../../../src/audit/auditType';
import { colors, spacing, typography } from '../../../src/theme';
import { Screen } from '../../../src/ui/Screen';
import { Card } from '../../../src/ui/Card';
import { Button } from '../../../src/ui/Button';
import { Badge } from '../../../src/ui/Badge';
import { AppHeader } from '../../../src/ui/AppHeader';
import { HeaderMenu } from '../../../src/ui/HeaderMenu';

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

  const locationLine1 = [data.city, data.location_name].filter(Boolean).join(' - ');
  const locationLine2 = [data.address, data.zone].filter(Boolean).join(' - ');
  const hasLocation = Boolean(locationLine1 || locationLine2);
  const hasProvider = Boolean(data.provider);

  return (
    <Screen header={<AppHeader onBack={() => router.back()} title="Espacio" right={<HeaderMenu />} />}>
      <Card padded={false}>
        <View style={styles.spaceHeader}>
          <View style={styles.spaceHeaderText}>
            <Text style={styles.code}>{data.external_code}</Text>
            {data.type && <Text style={styles.overline}>{data.type}</Text>}
          </View>
          <Badge label={`#${data.id}`} tone="neutral" />
        </View>
      </Card>

      {(hasLocation || hasProvider) && (
        <Card>
          {hasLocation && (
            <View style={styles.infoBlock}>
              <Text style={styles.infoLabel}>UBICACIÓN</Text>
              {Boolean(locationLine1) && <Text style={styles.infoValue}>{locationLine1}</Text>}
              {Boolean(locationLine2) && (
                <Text style={styles.infoValueSecondary}>{locationLine2}</Text>
              )}
            </View>
          )}
          {hasProvider && (
            <View style={hasLocation ? styles.infoBlockSpaced : undefined}>
              <Text style={styles.infoLabel}>PROVEEDOR</Text>
              <Text style={styles.infoValue}>{data.provider}</Text>
            </View>
          )}
        </Card>
      )}

      {data.booking ? (
        <Card accent="success">
          <View style={styles.alertRow}>
            <Ionicons
              name="checkmark-circle"
              size={22}
              color={colors.success}
              style={styles.alertIcon}
            />
            <View style={styles.alertBody}>
              <Text style={styles.bookingClient}>{data.booking.client_name ?? '—'}</Text>
              <Text style={styles.bookingMeta}>Contrato: {data.booking.contract_code ?? '—'}</Text>
              <Text style={styles.bookingMeta}>Producto: {data.booking.product_name ?? '—'}</Text>
            </View>
          </View>
        </Card>
      ) : (
        <Card accent="warning">
          <View style={styles.alertRow}>
            <Ionicons
              name="warning-outline"
              size={22}
              color={colors.warningText}
              style={styles.alertIcon}
            />
            <Text style={styles.warnText}>Sin pauta comercial asignada para esta semana.</Text>
          </View>
        </Card>
      )}

      {data.duplicate ? (
        <View>
          <Card accent="danger">
            <View style={styles.alertRow}>
              <Ionicons
                name="warning-outline"
                size={22}
                color={colors.danger}
                style={styles.alertIcon}
              />
              <View style={styles.alertBody}>
                <Text style={styles.dupTitle}>Ya se reportó el elemento esta semana.</Text>
                <Text style={styles.dupText}>
                  Este espacio ya tiene una auditoría registrada para la semana actual.
                </Text>
              </View>
            </View>
          </Card>
          <Button
            title="Ver auditoría"
            variant="success"
            icon="eye-outline"
            fullWidth
            onPress={() => router.push(`/(app)/audit/${data.existing_audit_id}`)}
          />
          <View style={styles.gap} />
          <Button
            title="Complementar"
            variant="primary"
            icon="add-circle-outline"
            fullWidth
            onPress={() =>
              router.push(
                `/(app)/audit/new?spaceId=${data.id}&code=${encodeURIComponent(data.external_code)}&mode=complement&auditId=${data.existing_audit_id}`,
              )
            }
          />
          <View style={styles.gap} />
          <Button title="Cancelar" variant="secondary" fullWidth onPress={() => router.back()} />
        </View>
      ) : (
        <Button
          title="Auditar"
          variant="brand"
          icon="clipboard-outline"
          fullWidth
          onPress={() => router.push(`/(app)/audit/new?spaceId=${data.id}&code=${encodeURIComponent(data.external_code)}`)}
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.appBg },
  error: { color: colors.danger },
  spaceHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: colors.surfaceAlt,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.lg,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderSubtle,
  },
  spaceHeaderText: { flexShrink: 1, paddingRight: spacing.md },
  infoBlock: {},
  infoBlockSpaced: { marginTop: spacing.lg },
  infoLabel: { ...typography.overline, color: colors.textSecondary, marginBottom: spacing.xs },
  infoValue: { ...typography.body, color: colors.text },
  infoValueSecondary: { ...typography.bodySecondary, color: colors.textSecondary, marginTop: spacing.xs },
  code: { ...typography.title },
  overline: { ...typography.overline, marginTop: spacing.xs },
  alertRow: { flexDirection: 'row', alignItems: 'flex-start' },
  alertIcon: { marginRight: spacing.md, marginTop: 1 },
  alertBody: { flex: 1 },
  bookingClient: { ...typography.body, fontWeight: '700', color: colors.successText },
  bookingMeta: { ...typography.bodySecondary, marginTop: spacing.xs },
  warnText: { ...typography.body, color: colors.warningText, flex: 1 },
  dupTitle: { ...typography.h2, color: colors.dangerText, marginBottom: spacing.xs },
  dupText: { ...typography.bodySecondary, color: colors.dangerText },
  gap: { height: spacing.md },
});
