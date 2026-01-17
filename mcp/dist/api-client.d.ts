/**
 * NetSendo MCP Server - API Client
 *
 * HTTP client for communicating with NetSendo REST API v1
 */
import type { Config } from './config.js';
import type { Subscriber, SubscriberCreateInput, SubscriberUpdateInput, ContactList, Tag, EmailSendInput, EmailStatus, Mailbox, SmsSendInput, SmsStatus, SmsProvider, CustomField, PaginatedResponse } from './types.js';
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