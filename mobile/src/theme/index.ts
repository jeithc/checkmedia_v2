import type { TextStyle, ViewStyle } from 'react-native';

/**
 * CheckMedia / Efectimedios design tokens.
 * All colors, spacing, radius, typography and shadows live here.
 * Screens must reference these — never raw hex.
 */

export const colors = {
  // Brand (Efectimedios red)
  brand: '#c60813',
  brandDark: '#a30c15',

  // App surfaces
  appBg: '#f9fafb',
  surface: '#ffffff',
  surfaceAlt: '#f9fafb',

  // Borders
  border: '#e5e7eb',
  borderSubtle: '#f3f4f6',

  // Text
  text: '#111827',
  textSecondary: '#6b7280',
  textMuted: '#9ca3af',

  // Primary / info (blue)
  primary: '#2563eb',
  primaryBg: '#dbeafe',
  primaryText: '#1e40af',

  // Success (green)
  success: '#16a34a',
  successBg: '#dcfce7',
  successText: '#15803d',

  // Danger (red)
  danger: '#dc2626',
  dangerBg: '#fee2e2',
  dangerText: '#991b1b',

  // Structural (purple)
  structural: '#9333ea',
  structuralBg: '#f3e8ff',
  structuralText: '#6b21a8',

  // Warning (amber)
  warningBg: '#fefce8',
  warningBorder: '#fef3c7',
  warningText: '#854d0e',

  // Misc
  dark: '#111827',
  white: '#ffffff',
  overlay: 'rgba(0,0,0,0.92)',
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
  xxxl: 48,
} as const;

export const radius = {
  sm: 8,
  md: 12,
  lg: 16,
  full: 999,
} as const;

export const typography = {
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.text,
  } as TextStyle,
  h2: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.text,
  } as TextStyle,
  overline: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    color: colors.textSecondary,
  } as TextStyle,
  body: {
    fontSize: 15,
    fontWeight: '400',
    color: colors.text,
  } as TextStyle,
  bodySecondary: {
    fontSize: 14,
    fontWeight: '400',
    color: colors.textSecondary,
  } as TextStyle,
  small: {
    fontSize: 13,
    fontWeight: '400',
    color: colors.textSecondary,
  } as TextStyle,
} as const;

export const shadow = {
  card: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.06,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 2,
  } as ViewStyle,
  elevated: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.12,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 6 },
    elevation: 6,
  } as ViewStyle,
  button: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.15,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 3 },
    elevation: 3,
  } as ViewStyle,
} as const;

export const theme = {
  colors,
  spacing,
  radius,
  typography,
  shadow,
} as const;

export default theme;
