/**
 * NetSendo MCP Server - API Client
 *
 * HTTP client for communicating with NetSendo REST API v1
 */
import axios from 'axios';
export class NetSendoApiError extends Error {
    statusCode;
    errors;
    constructor(message, statusCode, errors) {
        super(message);
        this.statusCode = statusCode;
        this.errors = errors;
        this.name = 'NetSendoApiError';
    }
}
export class NetSendoApiClient {
    client;
    debug;
    constructor(config) {
        this.debug = config.debug;
        this.client = axios.create({
            baseURL: `${config.apiUrl}/api/v1`,
            headers: {
                'Authorization': `Bearer ${config.apiKey}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            timeout: 30000,
        });
        // Request interceptor for debugging
        if (this.debug) {
            this.client.interceptors.request.use((config) => {
                console.error(`[NetSendo API] ${config.method?.toUpperCase()} ${config.url}`);
                return config;
            });
        }
        // Response interceptor for error handling
        this.client.interceptors.response.use((response) => response, (error) => {
            if (error.response) {
                const { status, data } = error.response;
                throw new NetSendoApiError(data.message || `API error: ${status}`, status, data.errors);
            }
            throw new NetSendoApiError(error.message || 'Network error', 0);
        });
    }
    // ============================================================================
    // Subscribers
    // ============================================================================
    async listSubscribers(params) {
        const response = await this.client.get('/subscribers', { params });
        return response.data;
    }
    async getSubscriber(id) {
        const response = await this.client.get(`/subscribers/${id}`);
        return response.data.data;
    }
    async getSubscriberByEmail(email) {
        const response = await this.client.get(`/subscribers/by-email/${encodeURIComponent(email)}`);
        return response.data.data;
    }
    async createSubscriber(data) {
        const response = await this.client.post('/subscribers', data);
        return response.data.data;
    }
    async updateSubscriber(id, data) {
        const response = await this.client.put(`/subscribers/${id}`, data);
        return response.data.data;
    }
    async deleteSubscriber(id) {
        await this.client.delete(`/subscribers/${id}`);
    }
    async syncSubscriberTags(id, tagIds) {
        const response = await this.client.post(`/subscribers/${id}/tags`, { tags: tagIds });
        return response.data.data;
    }
    // ============================================================================
    // Contact Lists
    // ============================================================================
    async listContactLists(params) {
        const response = await this.client.get('/lists', { params });
        return response.data;
    }
    async getContactList(id) {
        const response = await this.client.get(`/lists/${id}`);
        return response.data.data;
    }
    async getListSubscribers(listId, params) {
        const response = await this.client.get(`/lists/${listId}/subscribers`, { params });
        return response.data;
    }
    // ============================================================================
    // Tags
    // ============================================================================
    async listTags() {
        const response = await this.client.get('/tags');
        return response.data.data;
    }
    async getTag(id) {
        const response = await this.client.get(`/tags/${id}`);
        return response.data.data;
    }
    // ============================================================================
    // Custom Fields
    // ============================================================================
    async listCustomFields() {
        const response = await this.client.get('/custom-fields');
        return response.data.data;
    }
    // ============================================================================
    // Email Operations
    // ============================================================================
    async sendEmail(data) {
        const response = await this.client.post('/email/send', data);
        return response.data;
    }
    async getEmailStatus(id) {
        const response = await this.client.get(`/email/status/${id}`);
        return response.data.data;
    }
    async listMailboxes() {
        const response = await this.client.get('/email/mailboxes');
        return response.data.data;
    }
    // ============================================================================
    // SMS Operations
    // ============================================================================
    async sendSms(data) {
        const response = await this.client.post('/sms/send', data);
        return response.data;
    }
    async getSmsStatus(id) {
        const response = await this.client.get(`/sms/status/${id}`);
        return response.data.data;
    }
    async listSmsProviders() {
        const response = await this.client.get('/sms/providers');
        return response.data.data;
    }
    // ============================================================================
    // Account / Stats (internal API)
    // ============================================================================
    async getAccountInfo() {
        const response = await this.client.get('/account');
        return response.data;
    }
    /**
     * Test connection to the API
     */
    async testConnection() {
        try {
            const info = await this.getAccountInfo();
            return {
                success: true,
                message: `Connected to NetSendo ${info.version}`,
                version: info.version,
            };
        }
        catch (error) {
            if (error instanceof NetSendoApiError) {
                return {
                    success: false,
                    message: `API Error: ${error.message} (${error.statusCode})`,
                };
            }
            return {
                success: false,
                message: `Connection failed: ${error.message}`,
            };
        }
    }
}
//# sourceMappingURL=api-client.js.map