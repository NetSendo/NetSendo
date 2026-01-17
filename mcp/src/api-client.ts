/**
 * NetSendo MCP Server - API Client
 * 
 * HTTP client for communicating with NetSendo REST API v1
 */

import axios, { AxiosInstance, AxiosError } from 'axios';
import type { Config } from './config.js';
import type {
  Subscriber,
  SubscriberCreateInput,
  SubscriberUpdateInput,
  ContactList,
  Tag,
  Campaign,
  CampaignStats,
  CrmContact,
  CrmTask,
  CrmTaskCreateInput,
  CrmDeal,
  EmailSendInput,
  EmailStatus,
  Mailbox,
  SmsSendInput,
  SmsStatus,
  SmsProvider,
  DashboardStats,
  CustomField,
  Automation,
  PaginatedResponse,
  ApiErrorResponse,
} from './types.js';

export class NetSendoApiError extends Error {
  constructor(
    message: string,
    public statusCode: number,
    public errors?: Record<string, string[]>
  ) {
    super(message);
    this.name = 'NetSendoApiError';
  }
}

export class NetSendoApiClient {
  private client: AxiosInstance;
  private debug: boolean;

  constructor(config: Config) {
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
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError<ApiErrorResponse>) => {
        if (error.response) {
          const { status, data } = error.response;
          throw new NetSendoApiError(
            data.message || `API error: ${status}`,
            status,
            data.errors
          );
        }
        throw new NetSendoApiError(
          error.message || 'Network error',
          0
        );
      }
    );
  }

  // ============================================================================
  // Subscribers
  // ============================================================================

  async listSubscribers(params?: {
    page?: number;
    per_page?: number;
    search?: string;
    list_id?: number;
    status?: string;
  }): Promise<PaginatedResponse<Subscriber>> {
    const response = await this.client.get('/subscribers', { params });
    return response.data;
  }

  async getSubscriber(id: number): Promise<Subscriber> {
    const response = await this.client.get(`/subscribers/${id}`);
    return response.data.data;
  }

  async getSubscriberByEmail(email: string): Promise<Subscriber> {
    const response = await this.client.get(`/subscribers/by-email/${encodeURIComponent(email)}`);
    return response.data.data;
  }

  async createSubscriber(data: SubscriberCreateInput): Promise<Subscriber> {
    const response = await this.client.post('/subscribers', data);
    return response.data.data;
  }

  async updateSubscriber(id: number, data: SubscriberUpdateInput): Promise<Subscriber> {
    const response = await this.client.put(`/subscribers/${id}`, data);
    return response.data.data;
  }

  async deleteSubscriber(id: number): Promise<void> {
    await this.client.delete(`/subscribers/${id}`);
  }

  async syncSubscriberTags(id: number, tagIds: number[]): Promise<Subscriber> {
    const response = await this.client.post(`/subscribers/${id}/tags`, { tags: tagIds });
    return response.data.data;
  }

  // ============================================================================
  // Contact Lists
  // ============================================================================

  async listContactLists(params?: {
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<ContactList>> {
    const response = await this.client.get('/lists', { params });
    return response.data;
  }

  async getContactList(id: number): Promise<ContactList> {
    const response = await this.client.get(`/lists/${id}`);
    return response.data.data;
  }

  async getListSubscribers(listId: number, params?: {
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<Subscriber>> {
    const response = await this.client.get(`/lists/${listId}/subscribers`, { params });
    return response.data;
  }

  // ============================================================================
  // Tags
  // ============================================================================

  async listTags(): Promise<Tag[]> {
    const response = await this.client.get('/tags');
    return response.data.data;
  }

  async getTag(id: number): Promise<Tag> {
    const response = await this.client.get(`/tags/${id}`);
    return response.data.data;
  }

  // ============================================================================
  // Custom Fields
  // ============================================================================

  async listCustomFields(): Promise<CustomField[]> {
    const response = await this.client.get('/custom-fields');
    return response.data.data;
  }

  // ============================================================================
  // Email Operations
  // ============================================================================

  async sendEmail(data: EmailSendInput): Promise<{ id: string; status: string }> {
    const response = await this.client.post('/email/send', data);
    return response.data;
  }

  async getEmailStatus(id: string): Promise<EmailStatus> {
    const response = await this.client.get(`/email/status/${id}`);
    return response.data.data;
  }

  async listMailboxes(): Promise<Mailbox[]> {
    const response = await this.client.get('/email/mailboxes');
    return response.data.data;
  }

  // ============================================================================
  // SMS Operations
  // ============================================================================

  async sendSms(data: SmsSendInput): Promise<{ id: string; status: string }> {
    const response = await this.client.post('/sms/send', data);
    return response.data;
  }

  async getSmsStatus(id: string): Promise<SmsStatus> {
    const response = await this.client.get(`/sms/status/${id}`);
    return response.data.data;
  }

  async listSmsProviders(): Promise<SmsProvider[]> {
    const response = await this.client.get('/sms/providers');
    return response.data.data;
  }

  // ============================================================================
  // Account / Stats (internal API)
  // ============================================================================

  async getAccountInfo(): Promise<{
    name: string;
    email: string;
    version: string;
  }> {
    const response = await this.client.get('/account');
    return response.data;
  }

  /**
   * Test connection to the API
   */
  async testConnection(): Promise<{ success: boolean; message: string; version?: string }> {
    try {
      const info = await this.getAccountInfo();
      return {
        success: true,
        message: `Connected to NetSendo ${info.version}`,
        version: info.version,
      };
    } catch (error) {
      if (error instanceof NetSendoApiError) {
        return {
          success: false,
          message: `API Error: ${error.message} (${error.statusCode})`,
        };
      }
      return {
        success: false,
        message: `Connection failed: ${(error as Error).message}`,
      };
    }
  }
}
