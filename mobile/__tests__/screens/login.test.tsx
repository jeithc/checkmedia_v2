import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import LoginScreen from '../../app/login';
import * as AuthCtx from '../../src/auth/AuthContext';

jest.mock('expo-router', () => ({ router: { replace: jest.fn() } }));

describe('LoginScreen', () => {
  afterEach(() => jest.restoreAllMocks());

  it('calls signIn with entered credentials', async () => {
    const signIn = jest.fn().mockResolvedValue(undefined);
    jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
      status: 'unauthenticated', token: null, user: null, permissions: null,
      signIn, signOut: jest.fn(),
    });

    const { getByTestId, getByText } = await render(<LoginScreen />);
    await fireEvent.changeText(getByTestId('username'), 'auditor');
    await fireEvent.changeText(getByTestId('password'), 'secret123');
    await fireEvent.press(getByText('Ingresar'));

    await waitFor(() => expect(signIn).toHaveBeenCalledWith('auditor', 'secret123'));
  });

  it('shows an error message on failed login', async () => {
    const signIn = jest.fn().mockRejectedValue(new Error('Credenciales inválidas'));
    jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
      status: 'unauthenticated', token: null, user: null, permissions: null,
      signIn, signOut: jest.fn(),
    });

    const { getByTestId, getByText, findByText } = await render(<LoginScreen />);
    await fireEvent.changeText(getByTestId('username'), 'x');
    await fireEvent.changeText(getByTestId('password'), 'y');
    await fireEvent.press(getByText('Ingresar'));

    expect(await findByText(/Credenciales inválidas/)).toBeTruthy();
  });
});
