import { describe, expect, it } from 'vite-plus/test';
import { decide } from './guards';
import type { GuardState } from './guards';

const signedOut: GuardState = {
    authenticated: false,
    emailVerified: false,
    hasFamily: false,
};
const ready: GuardState = {
    authenticated: true,
    emailVerified: true,
    hasFamily: true,
};

describe('route guard', () => {
    it('sends signed-out visitors to login and remembers where they were going', () => {
        expect(decide({}, signedOut, '/?period=2026-10')).toEqual({
            name: 'login',
            query: { redirect: '/?period=2026-10' },
        });
        expect(decide({}, signedOut, '/')).toEqual({ name: 'login' });
    });

    it('keeps signed-in users away from login and register', () => {
        expect(decide({ guest: true }, ready, '/login')).toEqual({
            name: 'home',
        });
        expect(decide({ guest: true }, signedOut, '/login')).toBe(true);
    });

    it('asks for email verification before anything else', () => {
        expect(decide({}, { ...ready, emailVerified: false }, '/')).toEqual({
            name: 'verify-email',
        });
        expect(
            decide(
                { verified: false, family: false },
                { ...ready, emailVerified: false },
                '/verify-email',
            ),
        ).toBe(true);
    });

    it('sends users without a family to onboarding', () => {
        expect(decide({}, { ...ready, hasFamily: false }, '/')).toEqual({
            name: 'onboarding',
        });
        expect(
            decide(
                { family: false },
                { ...ready, hasFamily: false },
                '/onboarding',
            ),
        ).toBe(true);
    });

    it('lets public pages through for everyone', () => {
        expect(decide({ public: true }, signedOut, '/reset-password/x')).toBe(
            true,
        );
        expect(decide({ public: true }, ready, '/reset-password/x')).toBe(true);
    });

    it('opens app pages for a verified member of a family', () => {
        expect(decide({}, ready, '/')).toBe(true);
    });
});
