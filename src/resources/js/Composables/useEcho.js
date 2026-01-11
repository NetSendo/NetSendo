import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ref, onMounted, onUnmounted } from 'vue';

// Make Pusher available globally (required by Echo)
window.Pusher = Pusher;

let echoInstance = null;

/**
 * Initialize Echo with Reverb configuration
 */
export function initEcho(config) {
    if (echoInstance) {
        return echoInstance;
    }

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });

    return echoInstance;
}

/**
 * Get the Echo instance
 */
export function getEcho() {
    return echoInstance;
}

/**
 * Vue composable for using Echo in components
 */
export function useEcho(config) {
    const isConnected = ref(false);
    const connectionError = ref(null);

    onMounted(() => {
        if (!config || !config.key) {
            console.warn('[useEcho] No config provided, skipping Echo initialization');
            return;
        }

        try {
            const echo = initEcho(config);

            // Listen for connection events
            echo.connector.pusher.connection.bind('connected', () => {
                isConnected.value = true;
                connectionError.value = null;
                console.log('[useEcho] Connected to WebSocket');
            });

            echo.connector.pusher.connection.bind('disconnected', () => {
                isConnected.value = false;
                console.log('[useEcho] Disconnected from WebSocket');
            });

            echo.connector.pusher.connection.bind('error', (error) => {
                connectionError.value = error;
                console.error('[useEcho] Connection error:', error);
            });

        } catch (error) {
            connectionError.value = error;
            console.error('[useEcho] Failed to initialize Echo:', error);
        }
    });

    onUnmounted(() => {
        // Don't disconnect on unmount - keep the connection alive
        // for other components that might use it
    });

    return {
        isConnected,
        connectionError,
        getEcho,
    };
}

/**
 * Subscribe to a private channel
 */
export function usePrivateChannel(channelName, eventName, callback) {
    const channel = ref(null);

    onMounted(() => {
        const echo = getEcho();
        if (!echo) {
            console.warn('[usePrivateChannel] Echo not initialized');
            return;
        }

        channel.value = echo.private(channelName);
        channel.value.listen(`.${eventName}`, callback);

        console.log(`[usePrivateChannel] Subscribed to ${channelName}, listening for ${eventName}`);
    });

    onUnmounted(() => {
        if (channel.value) {
            const echo = getEcho();
            if (echo) {
                echo.leave(channelName);
                console.log(`[usePrivateChannel] Left channel ${channelName}`);
            }
        }
    });

    return { channel };
}

export default { initEcho, getEcho, useEcho, usePrivateChannel };
