import { View, Text, Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, spacing, radius, typography } from '../theme';

type Tone = 'primary' | 'structural' | 'success';

const TONES: Record<Tone, { solid: string; bg: string }> = {
  primary: { solid: colors.primary, bg: colors.primaryBg },
  structural: { solid: colors.structural, bg: colors.structuralBg },
  success: { solid: colors.success, bg: colors.successBg },
};

/**
 * SelectCard — a tappable option row mirroring the web radio cards
 * (e.g. "Tipo de Auditoría" / "Propósito de la Visita").
 *
 * Left: a tinted rounded icon box (tone *Bg fill, tone solid icon).
 * Middle: title + optional subtitle.
 * Selected: 2px tone-solid border + tone-tinted background.
 * Unselected: white surface + neutral border.
 */
export function SelectCard({
  icon,
  title,
  subtitle,
  selected,
  onPress,
  tone = 'primary',
  testID,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  subtitle?: string;
  selected: boolean;
  onPress: () => void;
  tone?: Tone;
  testID?: string;
}) {
  const t = TONES[tone];

  return (
    <Pressable
      onPress={onPress}
      testID={testID}
      accessibilityRole="button"
      accessibilityState={{ selected }}
      style={({ pressed }) => [
        styles.row,
        selected
          ? { borderColor: t.solid, backgroundColor: t.bg, borderWidth: 2 }
          : { borderColor: colors.border, backgroundColor: colors.surface, borderWidth: 1 },
        pressed && styles.pressed,
      ]}
    >
      <View style={[styles.iconBox, { backgroundColor: t.bg }]}>
        <Ionicons name={icon} size={20} color={t.solid} />
      </View>

      <View style={styles.body}>
        <Text style={styles.title} numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? (
          <Text style={styles.subtitle} numberOfLines={2}>
            {subtitle}
          </Text>
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    minHeight: 56,
    padding: spacing.md,
    borderRadius: radius.md,
    marginBottom: spacing.sm,
  },
  pressed: {
    opacity: 0.85,
  },
  iconBox: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: {
    flex: 1,
  },
  title: {
    ...typography.body,
    fontWeight: '600',
    color: colors.text,
  },
  subtitle: {
    ...typography.small,
    color: colors.textSecondary,
  },
});
