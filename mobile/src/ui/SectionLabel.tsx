import { ReactNode } from 'react';
import { Text, StyleSheet } from 'react-native';
import { spacing, typography } from '../theme';

/**
 * SectionLabel — standalone OVERLINE section heading.
 */
export function SectionLabel({ children }: { children: ReactNode }) {
  return <Text style={styles.label}>{children}</Text>;
}

const styles = StyleSheet.create({
  label: {
    ...typography.overline,
    marginBottom: spacing.sm,
  },
});
