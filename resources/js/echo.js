import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import { reverbConnectionOptions } from './reverb-connection.js';

window.Pusher = Pusher;

const connectionOptions = reverbConnectionOptions(window.location, document, import.meta.env.DEV ? import.meta.env : {});

if (connectionOptions.key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        ...connectionOptions,
        enabledTransports: ['ws', 'wss'],
    });
}
