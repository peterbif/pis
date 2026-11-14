import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;
const wsHost = import.meta.env.VITE_REVERB_HOST;

if (key && wsHost) {
  const echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    authorizer: channel => ({
      authorize: (socketId, callback) => {
        axios.post('/broadcasting/auth', {
          socket_id: socketId,
          channel_name: channel.name,
        }, { withCredentials: true, withXSRFToken: true })
          .then(({ data }) => callback(false, data))
          .catch(error => callback(true, error));
      },
    }),
  });

  window.Echo = echo;
  const connection = echo.connector.pusher.connection;
  connection.bind('connecting', () => window.dispatchEvent(new Event('pis-reverb-connecting')));
  connection.bind('connected', () => window.dispatchEvent(new Event('pis-reverb-connected')));
  connection.bind('disconnected', () => window.dispatchEvent(new Event('pis-reverb-disconnected')));
  connection.bind('unavailable', () => window.dispatchEvent(new Event('pis-reverb-disconnected')));
  connection.bind('failed', () => window.dispatchEvent(new Event('pis-reverb-disconnected')));
} else {
  window.Echo = null;
  console.info('Chat is using automatic refresh until Reverb client settings are configured.');
}
