/**
 * NetSendo MCP Server - API Client
 *
 * HTTP client for communicating with NetSendo REST API v1
 */
import type { Config } from './config.js';
import type { Subscriber, SubscriberCreateInput, SubscriberUpdateInput, ContactList, Tag, EmailSendInput, EmailStatus, Mailbox, SmsSendInput, SmsStatus, SmsProvider, CustomField, PaginatedResponse, Message, MessageCreateInput, MessageUpdateInput, MessageStats, AbTest, AbTestCreateInput, AbTestVariant, AbTestVariantInput, AbTestVariantResult, Funnel, FunnelCreateInput, FunnelStep, FunnelStepInput, FunnelStats } from './types.js';
export declare class NetSendoApiError extends Error {
    statusCode: number;
    errors?: Record<string, string[]> | undefined;
    constructor(message: string, statusCode: number, errors?: Record<string, string[]> | undefined);
}
export declare class NetSendoApiClient {
    private client;
    private debug;
    constructor(config: Config);
    listSubscribers(params?: {
        page?: number;
        per_page?: number;
        search?: string;
        list_id?: number;
        status?: string;
    }): Promise<PaginatedResponse<Subscriber>>;
    getSubscriber(id: number): Promise<Subscriber>;
    getSubscriberByEmail(email: string): Promise<Subscriber>;
    createSubscriber(data: SubscriberCreateInput): Promise<Subscriber>;
    updateSubscriber(id: number, data: SubscriberUpdateInput): Promise<Subscriber>;
    deleteSubscriber(id: number): Promise<void>;
    syncSubscriberTags(id: number, tagIds: number[]): Promise<Subscriber>;
    listContactLists(params?: {
        page?: number;
        per_page?: number;
    }): Promise<PaginatedResponse<ContactList>>;
    getContactList(id: number): Promise<ContactList>;
    getListSubscribers(listId: number, params?: {
        page?: number;
        per_page?: number;
    }): Promise<PaginatedResponse<Subscriber>>;
    listTags(): Promise<Tag[]>;
    getTag(id: number): Promise<Tag>;
    listCustomFields(): Promise<CustomField[]>;
    sendEmail(data: EmailSendInput): Promise<{
        id: string;
        status: string;
    }>;
    getEmailStatus(id: string): Promise<EmailStatus>;
    listMailboxes(): Promise<Mailbox[]>;
    sendSms(data: SmsSendInput): Promise<{
        id: string;
        status: string;
    }>;
    getSmsStatus(id: string): Promise<SmsStatus>;
    listSmsProviders(): Promise<SmsProvider[]>;
    listMessages(params?: {
        page?: number;
        per_page?: number;
        channel?: 'email' | 'sms';
        type?: 'broadcast' | 'autoresponder';
        status?: string;
        search?: string;
    }): Promise<PaginatedResponse<Message>>;
    getMessage(id: number): Promise<Message>;
    createMessage(data: MessageCreateInput): Promise<Message>;
    updateMessage(id: number, data: MessageUpdateInput): Promise<Message>;
    deleteMessage(id: number): Promise<void>;
    setMessageLists(id: number, contactListIds: number[]): Promise<{
        message: Message;
        planned_recipients: number;
    }>;
    setMessageExclusions(id: number, excludedListIds: number[]): Promise<{
        message: Message;
        planned_recipients: number;
    }>;
    scheduleMessage(id: number, scheduledAt: string, timezone?: string): Promise<Message>;
    sendMessage(id: number): Promise<{
        message: Message;
        recipients_added?: number;
    }>;
    getMessageStats(id: number): Promise<MessageStats>;
    listAbTests(params?: {
        page?: number;
        per_page?: number;
        status?: string;
        message_id?: number;
    }): Promise<PaginatedResponse<AbTest>>;
    getAbTest(id: number): Promise<AbTest>;
    createAbTest(data: AbTestCreateInput): Promise<AbTest>;
    addAbTestVariant(testId: number, data: AbTestVariantInput): Promise<AbTestVariant>;
    startAbTest(id: number): Promise<{
        test: AbTest;
        ends_at: string;
    }>;
    endAbTest(id: number, winnerVariantId?: number): Promise<{
        test: AbTest;
        winner: {
            variant_letter: string;
            id: number;
        } | null;
    }>;
    getAbTestResults(id: number): Promise<{
        test_id: number;
        name: string;
        status: string;
        test_type: string;
        winning_metric: string;
        test_started_at: string | null;
        test_ended_at: string | null;
        winner: {
            variant_letter: string;
            id: number;
        } | null;
        results: Record<string, AbTestVariantResult>;
    }>;
    deleteAbTest(id: number): Promise<void>;
    listFunnels(params?: {
        page?: number;
        per_page?: number;
        status?: string;
        trigger_type?: string;
        search?: string;
    }): Promise<PaginatedResponse<Funnel>>;
    getFunnel(id: number): Promise<Funnel & {
        stats: FunnelStats;
    }>;
    createFunnel(data: FunnelCreateInput): Promise<Funnel>;
    updateFunnel(id: number, data: Partial<FunnelCreateInput>): Promise<Funnel>;
    addFunnelStep(funnelId: number, data: FunnelStepInput): Promise<FunnelStep>;
    activateFunnel(id: number): Promise<Funnel>;
    pauseFunnel(id: number): Promise<Funnel>;
    getFunnelStats(id: number): Promise<{
        id: number;
        name: string;
        status: string;
        stats: FunnelStats;
        trigger: {
            type: string;
            list?: string;
            form?: string;
            tag?: string;
        };
    }>;
    deleteFunnel(id: number): Promise<void>;
    listPlaceholders(): Promise<{
        system: Array<{
            name: string;
            placeholder: string;
            label: string;
            type: string;
        }>;
        custom: Array<{
            name: string;
            placeholder: string;
            label: string;
            type: string;
            field_type: string;
        }>;
    }>;
    getAccountInfo(): Promise<{
        name: string;
        email: string;
        version: string;
    }>;
    /**
     * Test connection to the API
     */
    testConnection(): Promise<{
        success: boolean;
        message: string;
        version?: string;
    }>;
}
//# sourceMappingURL=api-client.d.ts.map