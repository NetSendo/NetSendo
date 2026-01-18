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
  // Message (Campaign) types
  Message,
  MessageCreateInput,
  MessageUpdateInput,
  MessageStats,
  // A/B Test types
  AbTest,
  AbTestCreateInput,
  AbTestVariant,
  AbTestVariantInput,
  AbTestVariantResult,
  // Funnel types
  Funnel,
  FunnelCreateInput,
  FunnelStep,
  FunnelStepInput,
  FunnelStats,
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
  // Messages (Campaigns)
  // ============================================================================

  async listMessages(params?: {
    page?: number;
    per_page?: number;
    channel?: 'email' | 'sms';
    type?: 'broadcast' | 'autoresponder';
    status?: string;
    search?: string;
  }): Promise<PaginatedResponse<Message>> {
    const response = await this.client.get('/messages', { params });
    return response.data;
  }

  async getMessage(id: number): Promise<Message> {
    const response = await this.client.get(`/messages/${id}`);
    return response.data.data;
  }

  async createMessage(data: MessageCreateInput): Promise<Message> {
    const response = await this.client.post('/messages', data);
    return response.data.data;
  }

  async updateMessage(id: number, data: MessageUpdateInput): Promise<Message> {
    const response = await this.client.put(`/messages/${id}`, data);
    return response.data.data;
  }

  async deleteMessage(id: number): Promise<void> {
    await this.client.delete(`/messages/${id}`);
  }

  async setMessageLists(id: number, contactListIds: number[]): Promise<{ message: Message; planned_recipients: number }> {
    const response = await this.client.post(`/messages/${id}/lists`, { contact_list_ids: contactListIds });
    return response.data;
  }

  async setMessageExclusions(id: number, excludedListIds: number[]): Promise<{ message: Message; planned_recipients: number }> {
    const response = await this.client.post(`/messages/${id}/exclusions`, { excluded_list_ids: excludedListIds });
    return response.data;
  }

  async scheduleMessage(id: number, scheduledAt: string): Promise<Message> {
    const response = await this.client.post(`/messages/${id}/schedule`, { scheduled_at: scheduledAt });
    return response.data.data;
  }

  async sendMessage(id: number): Promise<{ message: Message; recipients_added?: number }> {
    const response = await this.client.post(`/messages/${id}/send`);
    return response.data;
  }

  async getMessageStats(id: number): Promise<MessageStats> {
    const response = await this.client.get(`/messages/${id}/stats`);
    return response.data.data;
  }

  // ============================================================================
  // A/B Tests
  // ============================================================================

  async listAbTests(params?: {
    page?: number;
    per_page?: number;
    status?: string;
    message_id?: number;
  }): Promise<PaginatedResponse<AbTest>> {
    const response = await this.client.get('/ab-tests', { params });
    return response.data;
  }

  async getAbTest(id: number): Promise<AbTest> {
    const response = await this.client.get(`/ab-tests/${id}`);
    return response.data.data;
  }

  async createAbTest(data: AbTestCreateInput): Promise<AbTest> {
    const response = await this.client.post('/ab-tests', data);
    return response.data.data;
  }

  async addAbTestVariant(testId: number, data: AbTestVariantInput): Promise<AbTestVariant> {
    const response = await this.client.post(`/ab-tests/${testId}/variants`, data);
    return response.data.data;
  }

  async startAbTest(id: number): Promise<{ test: AbTest; ends_at: string }> {
    const response = await this.client.post(`/ab-tests/${id}/start`);
    return response.data;
  }

  async endAbTest(id: number, winnerVariantId?: number): Promise<{ test: AbTest; winner: { variant_letter: string; id: number } | null }> {
    const response = await this.client.post(`/ab-tests/${id}/end`, { winner_variant_id: winnerVariantId });
    return response.data;
  }

  async getAbTestResults(id: number): Promise<{
    test_id: number;
    name: string;
    status: string;
    test_type: string;
    winning_metric: string;
    test_started_at: string | null;
    test_ended_at: string | null;
    winner: { variant_letter: string; id: number } | null;
    results: Record<string, AbTestVariantResult>;
  }> {
    const response = await this.client.get(`/ab-tests/${id}/results`);
    return response.data.data;
  }

  async deleteAbTest(id: number): Promise<void> {
    await this.client.delete(`/ab-tests/${id}`);
  }

  // ============================================================================
  // Funnels (Automation)
  // ============================================================================

  async listFunnels(params?: {
    page?: number;
    per_page?: number;
    status?: string;
    trigger_type?: string;
    search?: string;
  }): Promise<PaginatedResponse<Funnel>> {
    const response = await this.client.get('/funnels', { params });
    return response.data;
  }

  async getFunnel(id: number): Promise<Funnel & { stats: FunnelStats }> {
    const response = await this.client.get(`/funnels/${id}`);
    return response.data.data;
  }

  async createFunnel(data: FunnelCreateInput): Promise<Funnel> {
    const response = await this.client.post('/funnels', data);
    return response.data.data;
  }

  async updateFunnel(id: number, data: Partial<FunnelCreateInput>): Promise<Funnel> {
    const response = await this.client.put(`/funnels/${id}`, data);
    return response.data.data;
  }

  async addFunnelStep(funnelId: number, data: FunnelStepInput): Promise<FunnelStep> {
    const response = await this.client.post(`/funnels/${funnelId}/steps`, data);
    return response.data.data;
  }

  async activateFunnel(id: number): Promise<Funnel> {
    const response = await this.client.post(`/funnels/${id}/activate`);
    return response.data.data;
  }

  async pauseFunnel(id: number): Promise<Funnel> {
    const response = await this.client.post(`/funnels/${id}/pause`);
    return response.data.data;
  }

  async getFunnelStats(id: number): Promise<{
    id: number;
    name: string;
    status: string;
    stats: FunnelStats;
    trigger: { type: string; list?: string; form?: string; tag?: string };
  }> {
    const response = await this.client.get(`/funnels/${id}/stats`);
    return response.data.data;
  }

  async deleteFunnel(id: number): Promise<void> {
    await this.client.delete(`/funnels/${id}`);
  }

  // ============================================================================
  // Custom Fields & Placeholders
  // ============================================================================

  async listPlaceholders(): Promise<{
    system: Array<{ name: string; placeholder: string; label: string; type: string }>;
    custom: Array<{ name: string; placeholder: string; label: string; type: string; field_type: string }>;
  }> {
    const response = await this.client.get('/custom-fields/placeholders');
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

