/**
 * Minimal Service Worker ambient types (MIGRATION_PLAN.md Stage 14).
 *
 * TypeScript's built-in "webworker" lib covers generic Worker/
 * DedicatedWorkerGlobalScope APIs but does NOT ship Service-Worker-specific
 * types (ServiceWorkerGlobalScope, FetchEvent, ExtendableEvent, Clients,
 * etc.) — a long-standing, well-known gap, not a local oversight. This file
 * declares only the small subset sw-core.ts actually uses, rather than
 * pulling in a third-party @types package for a handful of interfaces.
 */

interface ExtendableEvent extends Event {
    waitUntil(promise: Promise<unknown>): void;
}

interface FetchEvent extends ExtendableEvent {
    readonly request: Request;
    respondWith(response: Response | Promise<Response>): void;
}

interface ExtendableMessageEvent extends ExtendableEvent {
    readonly data: unknown;
}

interface NotificationEvent extends ExtendableEvent {
    readonly notification: Notification;
}

interface PushEvent extends ExtendableEvent {
    readonly data: unknown;
}

interface Clients {
    claim(): Promise<void>;
}

interface ServiceWorkerGlobalScope extends WorkerGlobalScope {
    readonly clients: Clients;
    readonly caches: CacheStorage;
    skipWaiting(): Promise<void>;

    addEventListener(
        type: 'install' | 'activate',
        listener: (event: ExtendableEvent) => void,
    ): void;
    addEventListener(
        type: 'fetch',
        listener: (event: FetchEvent) => void,
    ): void;
    addEventListener(
        type: 'message',
        listener: (event: ExtendableMessageEvent) => void,
    ): void;
    addEventListener(type: 'push', listener: (event: PushEvent) => void): void;
    addEventListener(
        type: 'notificationclick',
        listener: (event: NotificationEvent) => void,
    ): void;
}
