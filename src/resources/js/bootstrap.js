import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// A GOLD feature refused by the server (402, App\Licensing\FeatureRequiresGold) opens the
// upgrade dialog; the caller still gets the rejected promise for its own error handling.
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const data = error.response?.data;
        if (error.response?.status === 402 && data?.code === 'gold_required') {
            window.dispatchEvent(
                new CustomEvent('netsendo:gold-required', { detail: { feature: data.feature } }),
            );
        }
        return Promise.reject(error);
    },
);
