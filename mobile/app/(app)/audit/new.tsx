import { useEffect, useMemo, useState } from 'react';
import { View, Text, StyleSheet, ActivityIndicator, Pressable, Image } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import * as criteriaApi from '../../../src/api/criteria';
import * as auditsApi from '../../../src/api/audits';
import { resolveAuditOptions } from '../../../src/audit/auditType';
import { validateAudit } from '../../../src/audit/validation';
import { buildNewSubmission } from '../../../src/audit/useAuditSubmit';
import { capturePhoto } from '../../../src/photos/capture';
import type { UploadPhoto } from '../../../src/photos/resize';
import type { CriterionValue, AuditType, AuditPurpose } from '../../../src/api/types';
import { getDb } from '../../../src/db';
import * as repo from '../../../src/offline/queueRepo';
import { persistPhoto } from '../../../src/offline/photoStore';
import { useSync } from '../../../src/offline/SyncProvider';
import { colors, spacing, radius, typography } from '../../../src/theme';
import { Screen } from '../../../src/ui/Screen';
import { AppHeader } from '../../../src/ui/AppHeader';
import { SelectCard } from '../../../src/ui/SelectCard';
import { Card } from '../../../src/ui/Card';
import { Button } from '../../../src/ui/Button';
import { Field } from '../../../src/ui/Field';
import { Pill } from '../../../src/ui/Pill';
import { Badge } from '../../../src/ui/Badge';

export default function AuditFormScreen() {
  const { spaceId, code, mode, auditId } = useLocalSearchParams<{
    spaceId: string;
    code: string;
    mode?: string;
    auditId?: string;
  }>();
  const isComplement = mode === 'complement' && !!auditId;
  const { token, permissions, signOut } = useAuth();
  const { sync } = useSync();
  const options = useMemo(
    () => (permissions ? resolveAuditOptions(permissions) : null),
    [permissions],
  );
  const [auditType, setAuditType] = useState<AuditType>('general');
  const [purpose, setPurpose] = useState<AuditPurpose>('audit_only');

  const { data: criteria, isLoading } = useQuery({
    queryKey: ['criteria', auditType],
    queryFn: () => criteriaApi.listCriteria(auditType, token ?? ''),
  });

  // When complementing, load the existing audit to preload values/observation
  // and lock criteria already reported as "bad" (cannot be downgraded to good).
  const { data: existing } = useQuery({
    queryKey: ['audit', auditId],
    queryFn: () => auditsApi.getAudit(Number(auditId), token ?? ''),
    enabled: isComplement,
  });

  const [values, setValues] = useState<Record<number, { value: CriterionValue; comment: string }>>({});
  const [observation, setObservation] = useState('');
  const [photos, setPhotos] = useState<UploadPhoto[]>([]);
  const [capturedAt, setCapturedAt] = useState<string | null>(null);
  const [errors, setErrors] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [seeded, setSeeded] = useState(false);
  const [typeSeeded, setTypeSeeded] = useState(false);

  // Criteria reported "bad" in the existing audit cannot be downgraded to "good".
  const lockedBad = useMemo(
    () =>
      new Set(
        (existing?.values ?? []).filter((v) => v.value === 'bad').map((v) => v.criterion_id),
      ),
    [existing],
  );

  // Seed the audit type once. For new audits, seed from the resolved default.
  // When complementing, the type is fixed by the existing audit; prefer its
  // type when available, otherwise keep the default.
  useEffect(() => {
    if (typeSeeded) return;
    if (isComplement) {
      if (existing) {
        setAuditType(existing.audit_type);
        setTypeSeeded(true);
      }
      return;
    }
    if (options) {
      setAuditType(options.defaultType);
      setTypeSeeded(true);
    }
  }, [typeSeeded, isComplement, existing, options]);

  // Preload existing values/observation once, when complementing.
  useEffect(() => {
    if (!isComplement || seeded || !existing) return;
    const preset: Record<number, { value: CriterionValue; comment: string }> = {};
    existing.values.forEach((v) => {
      preset[v.criterion_id] = { value: v.value, comment: v.comment ?? '' };
    });
    setValues(preset);
    setObservation(existing.observation ?? '');
    setSeeded(true);
  }, [isComplement, seeded, existing]);

  const valueFor = (id: number) => values[id]?.value ?? 'good';
  const setValue = (id: number, value: CriterionValue) => {
    // Block downgrading a locked "bad" criterion back to "good".
    if (value === 'good' && lockedBad.has(id)) return;
    setValues((p) => ({ ...p, [id]: { value, comment: p[id]?.comment ?? '' } }));
  };
  const setComment = (id: number, comment: string) =>
    setValues((p) => ({ ...p, [id]: { value: p[id]?.value ?? 'good', comment } }));

  // Changing the audit type swaps the criteria set, so reset answers to avoid
  // orphaned criterion ids from the previous type. Never invoked in complement
  // mode (the selectors are hidden and the type is fixed).
  const selectType = (next: AuditType) => {
    if (next === auditType) return;
    setAuditType(next);
    setValues({});
  };

  const takePhoto = async () => {
    const photo = await capturePhoto();
    if (photo) {
      setPhotos((p) => [...p, photo]);
      // Capture time of the first photo, stamped on-device (not at server
      // receipt) so the server watermarks and computes the week from it. This
      // is the post-shutter callback time (shutter + ~resize), an accepted
      // approximation of the true EXIF shutter time.
      if (!capturedAt) setCapturedAt(new Date().toISOString());
    }
  };

  const removePhoto = (index: number) => {
    setPhotos((p) => {
      const next = p.filter((_, i) => i !== index);
      if (next.length === 0) setCapturedAt(null);
      return next;
    });
  };

  const save = async () => {
    const fullValues: typeof values = {};
    (criteria ?? []).forEach((c) => {
      fullValues[c.id] = values[c.id] ?? { value: 'good', comment: '' };
    });

    let errs = validateAudit({ photos, values: fullValues });
    // When complementing an audit that already has photos, a new photo is optional.
    const hasExistingPhotos = isComplement && (existing?.photos.length ?? 0) > 0;
    if (hasExistingPhotos) {
      errs = errs.filter((e) => !e.toLowerCase().includes('foto'));
    }
    setErrors(errs);
    if (errs.length > 0) return;

    setBusy(true);
    try {
      const db = await getDb();
      // 1. Build the submission first so it owns the clientUuid; use the same
      //    uuid for the persisted photo filenames and the enqueued record.
      const submission = buildNewSubmission(
        {
          spaceId: Number(spaceId),
          externalCode: String(code),
          auditType,
          purpose,
          observation,
          values: fullValues,
          photos,
          capturedAt: capturedAt ?? new Date().toISOString(),
          mode: isComplement ? 'complement' : 'new',
        },
        [],
      );
      // 2. Persist photos to durable storage keyed by the submission uuid.
      const persisted: string[] = [];
      for (let i = 0; i < photos.length; i++) {
        persisted.push(await persistPhoto(photos[i].uri, submission.clientUuid, i));
      }
      submission.photos = persisted.map((uri) => ({
        localUri: uri,
        capturedAt: submission.capturedAt,
      }));
      // 3. Enqueue and return to home immediately; uploading happens in the queue.
      await repo.enqueue(db, submission, Date.now());
      setDone(true);
      // 4. Fire-and-forget sync; do not block UX.
      sync();
      setTimeout(() => router.push('/(app)/home'), 600);
    } catch (e) {
      setErrors([e instanceof Error ? e.message : 'No se pudo guardar.']);
    } finally {
      setBusy(false);
    }
  };

  if (isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <Screen
      header={
        <AppHeader
          title={isComplement ? 'Complementar auditoría' : 'Auditoría'}
          onBack={() => router.back()}
          onSignOut={signOut}
        />
      }
    >
      <View style={styles.body}>
        {!isComplement && options?.canChooseType && (
          <Card title="Tipo de Auditoría">
            {options.types.includes('general') && (
              <SelectCard
                icon="clipboard-outline"
                title="General"
                subtitle="Auditoría estándar"
                tone="primary"
                selected={auditType === 'general'}
                onPress={() => selectType('general')}
              />
            )}
            {options.types.includes('structural') && (
              <SelectCard
                icon="business-outline"
                title="Estructural"
                subtitle="Inspección estructural"
                tone="structural"
                selected={auditType === 'structural'}
                onPress={() => selectType('structural')}
              />
            )}
          </Card>
        )}

        {!isComplement && (options?.purposes.length ?? 0) > 1 && (
          <Card title="Propósito de la Visita">
            <SelectCard
              icon="clipboard-outline"
              title="Solo Auditoría"
              subtitle="Inspección sin mantenimiento"
              tone="primary"
              selected={purpose === 'audit_only'}
              onPress={() => setPurpose('audit_only')}
            />
            <SelectCard
              icon="shield-checkmark-outline"
              title="Mant. Preventivo"
              subtitle="Cuenta para el timer preventivo"
              tone="success"
              selected={purpose === 'preventive_maintenance'}
              onPress={() => setPurpose('preventive_maintenance')}
            />
          </Card>
        )}

        {(criteria ?? []).map((c) => {
          const isBad = valueFor(c.id) === 'bad';
          const locked = lockedBad.has(c.id);
          return (
            <Card key={c.id}>
              <View style={styles.criterionHead}>
                <Text style={styles.cName}>{c.name}</Text>
                {locked && <Badge label="Bloqueado" tone="bad" icon="lock-closed-outline" />}
              </View>
              <View style={styles.row}>
                <View style={styles.pillSlot}>
                  <Pill
                    label="Bueno"
                    tone="good"
                    selected={valueFor(c.id) === 'good'}
                    disabled={locked}
                    onPress={() => setValue(c.id, 'good')}
                  />
                </View>
                <View style={styles.pillSlot}>
                  <Pill
                    label="Malo"
                    tone="bad"
                    selected={isBad}
                    onPress={() => setValue(c.id, 'bad')}
                  />
                </View>
              </View>
              {isBad && (
                <View style={styles.criterionField}>
                  <Field
                    label="Detalle de la irregularidad *"
                    icon="warning-outline"
                    tone="danger"
                    value={values[c.id]?.comment ?? ''}
                    onChangeText={(t) => setComment(c.id, t)}
                  />
                </View>
              )}
            </Card>
          );
        })}

        <Card
          title="Evidencias"
          accent={photos.length > 0 ? 'success' : undefined}
        >
          <View style={styles.evidenceHead}>
            <Badge label={`${photos.length} foto(s) agregada(s)`} tone="neutral" icon="image-outline" />
          </View>

          <View style={styles.thumbs}>
            <Pressable
              onPress={takePhoto}
              style={({ pressed }) => [styles.addTile, pressed && styles.pressed]}
              accessibilityRole="button"
              accessibilityLabel="Tomar foto"
            >
              <Ionicons name="camera-outline" size={26} color={colors.textSecondary} />
              <Text style={styles.addLabel}>Tomar foto</Text>
            </Pressable>

            {photos.map((photo, i) => (
              <View key={photo.uri} style={styles.thumbWrap}>
                <Image source={{ uri: photo.uri }} style={styles.thumb} />
                <Pressable
                  onPress={() => removePhoto(i)}
                  style={styles.thumbRemove}
                  hitSlop={8}
                  accessibilityLabel={`Quitar foto ${i + 1}`}
                >
                  <Ionicons name="close" size={14} color={colors.white} />
                </Pressable>
              </View>
            ))}
          </View>
        </Card>

        <Card title="Observación General">
          <Text style={styles.helper}>
            Opcional. Los detalles por ítem ya se capturan arriba.
          </Text>
          <Field
            label="Observación"
            icon="create-outline"
            value={observation}
            onChangeText={setObservation}
            multiline
          />
        </Card>

        {errors.map((e) => (
          <View key={e} style={styles.errorChip}>
            <Ionicons name="alert-circle" size={16} color={colors.dangerText} style={styles.chipIcon} />
            <Text style={styles.errorChipText}>{e}</Text>
          </View>
        ))}

        {done && (
          <View style={styles.okChip}>
            <Ionicons name="checkmark-circle" size={16} color={colors.successText} style={styles.chipIcon} />
            <Text style={styles.okChipText}>Auditoría guardada.</Text>
          </View>
        )}

        <View style={styles.submit}>
          <Button
            title="Guardar auditoría"
            variant="success"
            icon="checkmark-circle"
            fullWidth
            onPress={save}
            loading={busy}
          />
        </View>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.appBg,
  },
  body: {
    marginTop: spacing.lg,
  },
  criterionHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: spacing.md,
  },
  cName: {
    ...typography.body,
    fontWeight: '500',
    flexShrink: 1,
    marginRight: spacing.sm,
  },
  row: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  pillSlot: {
    flex: 1,
  },
  criterionField: {
    marginTop: spacing.lg,
  },
  evidenceHead: {
    marginBottom: spacing.md,
  },
  thumbs: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
  },
  addTile: {
    width: 84,
    height: 84,
    borderRadius: radius.md,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: colors.border,
    backgroundColor: colors.surfaceAlt,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addLabel: {
    ...typography.small,
    fontSize: 12,
    marginTop: spacing.xs,
  },
  pressed: {
    opacity: 0.7,
  },
  thumbWrap: {
    position: 'relative',
  },
  thumb: {
    width: 84,
    height: 84,
    borderRadius: radius.md,
    backgroundColor: colors.borderSubtle,
  },
  thumbRemove: {
    position: 'absolute',
    top: -6,
    right: -6,
    width: 22,
    height: 22,
    borderRadius: radius.full,
    backgroundColor: colors.danger,
    alignItems: 'center',
    justifyContent: 'center',
  },
  helper: {
    ...typography.small,
    marginBottom: spacing.md,
  },
  chipIcon: {
    marginRight: spacing.sm,
  },
  errorChip: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.dangerBg,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.sm,
  },
  errorChipText: {
    ...typography.bodySecondary,
    color: colors.dangerText,
    flexShrink: 1,
  },
  okChip: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.successBg,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.sm,
  },
  okChipText: {
    ...typography.bodySecondary,
    color: colors.successText,
    fontWeight: '600',
    flexShrink: 1,
  },
  submit: {
    marginTop: spacing.sm,
  },
});
