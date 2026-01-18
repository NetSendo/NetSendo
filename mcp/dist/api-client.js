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
    // Messages (Campaigns)
    // ============================================================================
    async listMessages(params) {
        const response = await this.client.get('/messages', { params });
        return response.data;
    }
    async getMessage(id) {
        const response = await this.client.get(`/messages/${id}`);
        return response.data.data;
    }
    async createMessage(data) {
        const response = await this.client.post('/messages', data);
        return response.data.data;
    }
    async updateMessage(id, data) {
        const response = await this.client.put(`/messages/${id}`, data);
        return response.data.data;
    }
    async deleteMessage(id) {
        await this.client.delete(`/messages/${id}`);
    }
    async setMessageLists(id, contactListIds) {
        const response = await this.client.post(`/messages/${id}/lists`, { contact_list_ids: contactListIds });
        return response.data;
    }
    async setMessageExclusions(id, excludedListIds) {
        const response = await this.client.post(`/messages/${id}/exclusions`, { excluded_list_ids: excludedListIds });
        return response.data;
    }
    async scheduleMessage(id, scheduledAt) {
        const response = await this.client.post(`/messages/${id}/schedule`, { scheduled_at: scheduledAt });
        return response.data.data;
    }
    async sendMessage(id) {
        const response = await this.client.post(`/messages/${id}/send`);
        return response.data;
    }
    async getMessageStats(id) {
        const response = await this.client.get(`/messages/${id}/stats`);
        return response.data.data;
    }
    // ============================================================================
    // A/B Tests
    // ============================================================================
    async listAbTests(params) {
        const response = await this.client.get('/ab-tests', { params });
        return response.data;
    }
    async getAbTest(id) {
        const response = await this.client.get(`/ab-tests/${id}`);
        return response.data.data;
    }
    async createAbTest(data) {
        const response = await this.client.post('/ab-tests', data);
        return response.data.data;
    }
    async addAbTestVariant(testId, data) {
        const response = await this.client.post(`/ab-tests/${testId}/variants`, data);
        return response.data.data;
    }
    async startAbTest(id) {
        const response = await this.client.post(`/ab-tests/${id}/start`);
        return response.data;
    }
    async endAbTest(id, winnerVariantId) {
        const response = await this.client.post(`/ab-tests/${id}/end`, { winner_variant_id: winnerVariantId });
        return response.data;
    }
    async getAbTestResults(id) {
        const response = await this.client.get(`/ab-tests/${id}/results`);
        return response.data.data;
    }
    async deleteAbTest(id) {
        await this.client.delete(`/ab-tests/${id}`);
    }
    // ============================================================================
    // Funnels (Automation)
    // ============================================================================
    async listFunnels(params) {
        const response = await this.client.get('/funnels', { params });
        return response.data;
    }
    async getFunnel(id) {
        const response = await this.client.get(`/funnels/${id}`);
        return response.data.data;
    }
    async createFunnel(data) {
        const response = await this.client.post('/funnels', data);
        return response.data.data;
    }
    async updateFunnel(id, data) {
        const response = await this.client.put(`/funnels/${id}`, data);
        return response.data.data;
    }
    async addFunnelStep(funnelId, data) {
        const response = await this.client.post(`/funnels/${funnelId}/steps`, data);
        return response.data.data;
    }
    async activateFunnel(id) {
        const response = await this.client.post(`/funnels/${id}/activate`);
        return response.data.data;
    }
    async pauseFunnel(id) {
        const response = await this.client.post(`/funnels/${id}/pause`);
        return response.data.data;
    }
    async getFunnelStats(id) {
        const response = await this.client.get(`/funnels/${id}/stats`);
        return response.data.data;
    }
    async deleteFunnel(id) {
        await this.client.delete(`/funnels/${id}`);
    }
    // ============================================================================
    // Custom Fields & Placeholders
    // ============================================================================
    async listPlaceholders() {
        const response = await this.client.get('/custom-fields/placeholders');
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