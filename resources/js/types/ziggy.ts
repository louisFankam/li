import { Config, RouteParams } from 'ziggy-js';

type RouteParamsValue = RouteParams<string> | number | Record<string, unknown>;

declare global {
    function route(): Config;
    function route(name: string, params?: RouteParamsValue, absolute?: boolean): string;
}

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}
