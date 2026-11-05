import { reactive, ref } from 'vue';
import type { Ref, UnwrapRef } from 'vue';
import { ApiValidationError } from './errors';

/*
 * Form state for an API call (docs/15 §9): values, `processing`, and the
 * per-field messages of a 422 (including broken business rules, which carry
 * their field). Validation itself lives in the backend.
 */
export type ApiForm<T extends object> = {
    values: UnwrapRef<T>;
    errors: Ref<Record<string, string>>;
    processing: Ref<boolean>;
    /** Runs the call; a 422 fills `errors` and resolves to undefined. */
    submit: <R>(
        call: (values: UnwrapRef<T>) => Promise<R>,
    ) => Promise<R | undefined>;
    clearErrors: () => void;
    reset: (...fields: (keyof T)[]) => void;
};

export function useApiForm<T extends object>(initial: T): ApiForm<T> {
    const defaults = structuredClone(initial);
    const values = reactive(structuredClone(initial)) as UnwrapRef<T>;
    const errors = ref<Record<string, string>>({});
    const processing = ref(false);

    async function submit<R>(
        call: (values: UnwrapRef<T>) => Promise<R>,
    ): Promise<R | undefined> {
        if (processing.value) {
            return undefined;
        }

        processing.value = true;
        errors.value = {};

        try {
            return await call(values);
        } catch (error) {
            if (error instanceof ApiValidationError) {
                errors.value = error.fieldErrors;

                return undefined;
            }

            throw error;
        } finally {
            processing.value = false;
        }
    }

    function reset(...fields: (keyof T)[]): void {
        const keys =
            fields.length > 0 ? fields : (Object.keys(defaults) as (keyof T)[]);
        for (const key of keys) {
            (values as Record<keyof T, unknown>)[key] = structuredClone(
                defaults[key],
            );
        }
    }

    return {
        values,
        errors,
        processing,
        submit,
        clearErrors: () => {
            errors.value = {};
        },
        reset,
    };
}
