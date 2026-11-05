import type { components } from './schema';

/*
 * Shortcuts to the generated OpenAPI types (pnpm run api:types). Modules use
 * these instead of writing their own interfaces (docs/15 §6).
 */
export type Schemas = components['schemas'];

export type Me = Schemas['MeResource'];

export type MeFamily = Me['families'][number];

export type Appearance = Me['appearance'];

/** `{ data: T }` envelope of single resources. */
export type Envelope<T> = { data: T };
