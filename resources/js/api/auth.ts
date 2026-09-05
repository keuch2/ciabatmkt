import { api } from './client';
import type { User, Wrapped } from './types';

export function login(email: string, password: string): Promise<User> {
    return api<Wrapped<User>>('POST', '/api/auth/login', { email, password }).then((r) => r.data);
}

export function logout(): Promise<void> {
    return api<void>('POST', '/api/auth/logout');
}

export function me(): Promise<User> {
    return api<Wrapped<User>>('GET', '/api/auth/me').then((r) => r.data);
}

export function forgotPassword(email: string): Promise<{ message: string }> {
    return api('POST', '/api/auth/forgot-password', { email });
}

export interface ResetPasswordPayload {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export function resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
    return api('POST', '/api/auth/reset-password', payload);
}
