import 'fake-indexeddb/auto';

Object.defineProperty(globalThis, 'route', {
    configurable: true,
    value: (name: string) => `/__test__/${name}`,
});
