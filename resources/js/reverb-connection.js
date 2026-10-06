export function reverbConnectionOptions(location, document, environment) {
    const isDevelopment = environment.DEV === true;
    const configuredHost = isDevelopment ? environment.VITE_REVERB_HOST : null;
    const configuredPort = isDevelopment ? Number(environment.VITE_REVERB_PORT) : NaN;
    const secure = isDevelopment && environment.VITE_REVERB_SCHEME
        ? environment.VITE_REVERB_SCHEME === 'https'
        : location.protocol === 'https:';
    const port = Number.isInteger(configuredPort) && configuredPort > 0
        ? configuredPort
        : Number(location.port || (secure ? 443 : 80));

    return {
        key: document.querySelector('meta[name="synkk-reverb-key"]')?.content
            || (isDevelopment ? environment.VITE_REVERB_APP_KEY : undefined),
        wsHost: !configuredHost || configuredHost === 'localhost' ? location.hostname : configuredHost,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
    };
}
