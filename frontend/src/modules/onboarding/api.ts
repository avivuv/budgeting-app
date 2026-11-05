import { api } from '@/core/api';
import type { Envelope, Me } from '@/core/api';

export type CreateFamilyInput = {
    name: string;
    period_start_day: number;
};

/** POST /families (docs/api-contracts/onboarding.md): answers with the new Me. */
export async function createFamily(
    input: CreateFamilyInput,
): Promise<Envelope<Me> & { message: string }> {
    return api.post('/families', input);
}
