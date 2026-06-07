import { useEffect, useMemo, useState } from 'react';
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, Pressable, Image } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import * as criteriaApi from '../../../src/api/criteria';
import * as auditsApi from '../../../src/api/audits';
import { resolveAuditOptions } from '../../../src/audit/auditType';
import { validateAudit } from '../../../src/audit/validation';
import { submitBuiltAudit } from '../../../src/audit/useAuditSubmit';
import { capturePhoto } from '../../../src/photos/capture';
import type { UploadPhoto } from '../../../src/photos/resize';
import type { CriterionValue } from '../../../src/api/types';
import { ApiError } from '../../../src/api/errors';
import { Field } from '../../../src/ui/Field';
import { Button } from '../../../src/ui/Button';

export default function AuditFormScreen() {
  const { spaceId, mode, auditId } = useLocalSearchParams<{
    spaceId: string;
    code: string;
    mode?: string;
    auditId?: string;
  }>();
  const isComplement = mode === 'complement' && !!auditId;
  const { token, permissions } = useAuth();
  const options = useMemo(
    () => (permissions ? resolveAuditOptions(permissions) : null),
    [permissions],
  );
  const auditType = options?.defaultType ?? 'general';

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
  const [progress, setProgress] = useState(0);
  const [done, setDone] = useState(false);
  const [seeded, setSeeded] = useState(false);

  // Criteria reported "bad" in the existing audit cannot be downgraded to "good".
  const lockedBad = useMemo(
    () =>
      new Set(
        (existing?.values ?? []).filter((v) => v.value === 'bad').map((v) => v.criterion_id),
      ),
    [existing],
  );

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
    setProgress(0);
    try {
      await submitBuiltAudit(
        {
          spaceId: Number(spaceId),
          auditType,
          purpose: 'audit_only',
          observation,
          values: fullValues,
          photos,
          capturedAt: capturedAt ?? new Date().toISOString(),
          mode: isComplement ? 'complement' : 'new',
        },
        token ?? '',
        (f) => setProgress(f),
      );
      setDone(true);
      setTimeout(() => router.push('/(app)/home'), 1200);
    } catch (e) {
      if (e instanceof ApiError && e.isConflict) {
        setErrors(['Ya existe una auditoría para este espacio esta semana.']);
      } else if (e instanceof ApiError && e.isValidation) {
        setErrors([e.message]);
      } else {
        setErrors([e instanceof Error ? e.message : 'No se pudo guardar.']);
      }
    } finally {
      setBusy(false);
    }
  };

  if (isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>{isComplement ? 'Complementar auditoría' : 'Auditoría'}</Text>

      {(criteria ?? []).map((c) => (
        <View key={c.id} style={styles.criterion}>
          <Text style={styles.cName}>{c.name}</Text>
          <View style={styles.row}>
            <Pressable
              onPress={() => setValue(c.id, 'good')}
              disabled={lockedBad.has(c.id)}
              style={[
                styles.pill,
                valueFor(c.id) === 'good' && styles.pillGood,
                lockedBad.has(c.id) && styles.pillDisabled,
              ]}
            >
              <Text>Bueno</Text>
            </Pressable>
            <Pressable
              onPress={() => setValue(c.id, 'bad')}
              style={[styles.pill, valueFor(c.id) === 'bad' && styles.pillBad]}
            >
              <Text>Malo</Text>
            </Pressable>
          </View>
          {valueFor(c.id) === 'bad' && (
            <Field
              label="Comentario"
              value={values[c.id]?.comment ?? ''}
              onChangeText={(t) => setComment(c.id, t)}
            />
          )}
        </View>
      ))}

      <Field label="Observación" value={observation} onChangeText={setObservation} multiline />

      <Button title="Tomar foto" onPress={takePhoto} />
      <Text style={styles.photoCount}>{photos.length} foto(s) agregada(s)</Text>

      {photos.length > 0 && (
        <View style={styles.thumbs}>
          {photos.map((photo, i) => (
            <View key={photo.uri} style={styles.thumbWrap}>
              <Image source={{ uri: photo.uri }} style={styles.thumb} />
              <Pressable
                onPress={() => removePhoto(i)}
                style={styles.thumbRemove}
                hitSlop={8}
                accessibilityLabel={`Quitar foto ${i + 1}`}
              >
                <Text style={styles.thumbRemoveText}>×</Text>
              </Pressable>
            </View>
          ))}
        </View>
      )}

      {errors.map((e) => (
        <Text key={e} style={styles.error}>
          {e}
        </Text>
      ))}
      {done && <Text style={styles.ok}>Auditoría guardada.</Text>}
      {busy && progress > 0 && progress < 1 && (
        <Text style={styles.photoCount}>Subiendo… {Math.round(progress * 100)}%</Text>
      )}

      <Button title="Guardar auditoría" onPress={save} loading={busy} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  title: { fontSize: 20, fontWeight: '700', marginBottom: 16 },
  criterion: { marginBottom: 16 },
  cName: { fontWeight: '600', marginBottom: 6 },
  row: { flexDirection: 'row', gap: 8 },
  pill: { borderWidth: 1, borderColor: '#cbd5e1', borderRadius: 999, paddingVertical: 6, paddingHorizontal: 16 },
  pillGood: { backgroundColor: '#bbf7d0', borderColor: '#16a34a' },
  pillDisabled: { opacity: 0.4 },
  pillBad: { backgroundColor: '#fecaca', borderColor: '#dc2626' },
  photoCount: { marginVertical: 8, color: '#475569' },
  thumbs: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 8 },
  thumbWrap: { position: 'relative' },
  thumb: { width: 84, height: 84, borderRadius: 8, backgroundColor: '#e2e8f0' },
  thumbRemove: {
    position: 'absolute',
    top: -6,
    right: -6,
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#dc2626',
    alignItems: 'center',
    justifyContent: 'center',
  },
  thumbRemoveText: { color: '#fff', fontSize: 15, fontWeight: '700', lineHeight: 17 },
  error: { color: '#dc2626', marginVertical: 4 },
  ok: { color: '#16a34a', marginVertical: 8, fontWeight: '600' },
});
