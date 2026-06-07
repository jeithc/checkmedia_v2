import { TextInput, Text, View, StyleSheet, TextInputProps } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, spacing, radius, typography } from '../theme';

type Tone = 'default' | 'danger';
type IconName = keyof typeof Ionicons.glyphMap;

export function Field({
  label,
  icon,
  tone = 'default',
  style,
  multiline,
  ...props
}: {
  label: string;
  icon?: IconName;
  tone?: Tone;
} & TextInputProps) {
  const isDanger = tone === 'danger';

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <View
        style={[
          styles.field,
          isDanger ? styles.fieldDanger : styles.fieldDefault,
          multiline && styles.fieldMultiline,
        ]}
      >
        {icon ? (
          <Ionicons
            name={icon}
            size={18}
            color={isDanger ? colors.danger : colors.textMuted}
            style={styles.icon}
          />
        ) : null}
        <TextInput
          style={[styles.input, multiline && styles.inputMultiline, style]}
          placeholderTextColor={colors.textMuted}
          autoCapitalize="none"
          multiline={multiline}
          {...props}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: spacing.lg,
  },
  label: {
    ...typography.overline,
    marginBottom: spacing.xs,
  },
  field: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    minHeight: 48,
  },
  fieldDefault: {
    borderColor: colors.border,
  },
  fieldDanger: {
    borderColor: colors.danger,
    backgroundColor: colors.dangerBg,
  },
  fieldMultiline: {
    alignItems: 'flex-start',
    paddingTop: spacing.md,
  },
  icon: {
    marginRight: spacing.sm,
  },
  input: {
    flex: 1,
    paddingVertical: 13,
    fontSize: 15,
    color: colors.text,
  },
  inputMultiline: {
    minHeight: 88,
    textAlignVertical: 'top',
  },
});
