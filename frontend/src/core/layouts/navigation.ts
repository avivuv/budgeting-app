import type { Component, InjectionKey } from 'vue';
import type { RouteLocationRaw } from 'vue-router';

/*
 * Menu of the app shell. Defined outside core (src/navigation.ts) because
 * it points at module routes; core only renders it.
 *
 * During the migration (docs/18) a destination not yet moved to the SPA is
 * an `href` to its old page; it becomes a `to` when its module arrives.
 */
export type NavItem = {
    title: string;
    icon: Component;
    /** SPA route. */
    to?: RouteLocationRaw;
    /** Old (Inertia) page, full page load. */
    href?: string;
    /** Hidden unless the active family grants it (BR-B09). */
    requires?: 'write' | 'manage';
};

export type Navigation = {
    /** Sidebar menu. */
    main: NavItem[];
    /** Phone bottom bar: two items, the add button, one item, "Lainnya". */
    bottom: { left: NavItem[]; right: NavItem[] };
    /** "+ Transaksi". */
    create: NavItem;
    notifications: NavItem;
    settings: NavItem;
    createFamily: NavItem;
};

export const NAVIGATION: InjectionKey<Navigation> = Symbol('navigation');
