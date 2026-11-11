import { translations } from '@/lang';
import axios from 'axios';
import Pusher from 'pusher-js';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

declare global {
    interface Window {
        axios: typeof axios;
        Pusher: typeof Pusher;
        localizer: {
            translations: typeof translations;
        };
    }
}

window.localizer = {
    translations: translations,
};

window.Pusher = Pusher;

window.axios = axios;

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Configure base URL
axios.defaults.baseURL = window.location.origin;

// Add response interceptor for error handling
axios.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        console.error('Axios error:', error);

        // Handle 419 (CSRF token mismatch)
        if (error.response?.status === 419) {
            console.error('CSRF token mismatch - refreshing page');
            window.location.reload();
        }

        // Handle 401 (Unauthorized)
        if (error.response?.status === 401) {
            console.error('Unauthorized - redirecting to login');
            window.location.href = '/login';
        }

        return Promise.reject(error);
    },
);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your application to easily build robust real-time web applications.
 */
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    // broadcaster: 'reverb',
    // key: import.meta.env.VITE_REVERB_APP_KEY,
    // wsHost: import.meta.env.VITE_REVERB_HOST,
    // wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    // wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    // forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    // enabledTransports: ['ws', 'wss'],
    // pusher
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
});
