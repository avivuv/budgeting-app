/*
 * Error bodies of the REST API (docs/api-contracts/README.md §1):
 * { message, errors?: { field: [..] }, rule?: "BR-xxx", code?: "no_family" }.
 */
export type ApiErrorBody = {
    message?: string;
    errors?: Record<string, string[]>;
    rule?: string;
    code?: string;
};

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: ApiErrorBody = {},
    ) {
        super(body.message ?? `Permintaan gagal (${status}).`);
        this.name = 'ApiError';
    }

    get rule(): string | undefined {
        return this.body.rule;
    }

    get code(): string | undefined {
        return this.body.code;
    }
}

/** 422: validation or a broken business rule, with messages per field. */
export class ApiValidationError extends ApiError {
    constructor(body: ApiErrorBody) {
        super(422, body);
        this.name = 'ApiValidationError';
    }

    /** First message of every field, e.g. { amount: "Nominal wajib diisi." }. */
    get fieldErrors(): Record<string, string> {
        const errors: Record<string, string> = {};

        for (const [field, messages] of Object.entries(
            this.body.errors ?? {},
        )) {
            if (messages[0] !== undefined) {
                errors[field] = messages[0];
            }
        }

        if (Object.keys(errors).length === 0 && this.body.message) {
            errors.general = this.body.message;
        }

        return errors;
    }
}

export function toApiError(status: number, body: unknown): ApiError {
    const data = (
        typeof body === 'object' && body !== null ? body : {}
    ) as ApiErrorBody;

    return status === 422
        ? new ApiValidationError(data)
        : new ApiError(status, data);
}
