/**
 * NetSendo MCP Server - API Client
 *
 * HTTP client for communicating with NetSendo REST API v1
 */
import type { Config } from './config.js';
import type { Subscriber, SubscriberCreateInput, SubscriberUpdateInput, ContactList, Tag, EmailSendInput, EmailStatus, Mailbox, SmsSendInput, SmsStatus, SmsProvider, CustomField, PaginatedResponse, Message, MessageCreateInput, MessageUpdateInput, MessageStats, AbTest, AbTestCreateInput, AbTestVariant, AbTestVariantInput, AbTestVariantResult, Funnel, FunnelCreateInput, FunnelStep, FunnelStepInput, FunnelStats, ContactListCreateInput, ContactListUpdateInput, ListStats, ListImportPayload, ListImportPreview, ListImportResult, ListExportOptions, ListExportResult, ListHygieneReport, ListCleanResult, ListDedupeResult, ListVerifyResult, HygieneCategory, HygieneAction, MemberSelection, ListActivityFeed, ListEngagement, SubscriberActivity, SuppressionEntry, NotificationInput } from './types.js';
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
        status?: string;
    }): Promise<PaginatedResponse<Subscriber>>;
    createContactList(data: ContactListCreateInput): Promise<ContactList>;
    updateContactList(id: number, data: ContactListUpdateInput): Promise<ContactList>;
    deleteContactList(id: number, confirm?: boolean): Promise<{
        message: string;
        list_id: number;
    }>;
    getListStats(id: number): Promise<ListStats>;
    previewListImport(listId: number, payload: ListImportPayload): Promise<ListImportPreview>;
    importToList(listId: number, payload: ListImportPayload): Promise<{
        data: ListImportResult | ListImportPreview;
        message?: string;
    }>;
    exportList(listId: number, options?: ListExportOptions): Promise<ListExportResult>;
    getExportFields(listId: number): Promise<{
        standard: string[];
        custom_fields: Array<{
            id: number;
            name: string;
            label: string;
            type: string;
        }>;
    }>;
    /** Queue the classic CSV export; the account owner receives a download link. */
    queueListExport(listId: number): Promise<{
        message: string;
        list_id: number;
    }>;
    analyzeListHygiene(listId: number, params?: {
        unconfirmed_after_days?: number;
        never_engaged_after_days?: number;
        dormant_after_days?: number;
        soft_bounce_threshold?: number;
        sample_size?: number;
        max_scan?: number;
    }): Promise<ListHygieneReport>;
    cleanList(listId: number, payload: {
        categories: HygieneCategory[];
        action: HygieneAction;
        tag?: string;
        dry_run?: boolean;
        confirm?: boolean;
        limit?: number;
        reason?: string;
        unconfirmed_after_days?: number;
        never_engaged_after_days?: number;
        dormant_after_days?: number;
        soft_bounce_threshold?: number;
    }): Promise<{
        data: ListCleanResult;
        message: string;
    }>;
    dedupeList(listId: number, payload?: {
        dry_run?: boolean;
        confirm?: boolean;
        limit?: number;
        keep?: 'oldest' | 'most_engaged';
    }): Promise<{
        data: ListDedupeResult;
        message: string;
    }>;
    verifyListEmails(listId: number, payload?: {
        limit?: number;
        status?: string;
        check_mx?: boolean;
        max_domains?: number;
    }): Promise<ListVerifyResult>;
    getHygieneOptions(): Promise<{
        categories: string[];
        actions: string[];
        destructive_actions: string[];
        max_scan: number;
    }>;
    addListMembers(listId: number, payload: MemberSelection & {
        source_list_id?: number;
        source?: string;
        trigger_automations?: boolean;
    }): Promise<{
        data: Record<string, number>;
        message: string;
    }>;
    removeListMembers(listId: number, payload: MemberSelection & {
        confirm?: boolean;
    }): Promise<{
        data: Record<string, number>;
        message: string;
    }>;
    setListMemberStatus(listId: number, payload: MemberSelection & {
        status: 'active' | 'inactive' | 'unsubscribed' | 'bounced';
        reason?: string;
        trigger_automations?: boolean;
    }): Promise<{
        data: Record<string, unknown>;
        message: string;
    }>;
    transferListMembers(listId: number, mode: 'move' | 'copy', payload: MemberSelection & {
        target_list_id: number;
        source?: string;
        trigger_automations?: boolean;
    }): Promise<{
        data: Record<string, unknown>;
        message: string;
    }>;
    tagListMembers(listId: number, payload: MemberSelection & {
        add?: string[];
        remove?: string[];
    }): Promise<{
        data: Record<string, unknown>;
        message: string;
    }>;
    getListActivity(listId: number, params?: {
        days?: number;
        limit?: number;
        types?: string[];
    }): Promise<ListActivityFeed>;
    getListEngagement(listId: number, params?: {
        days?: number;
    }): Promise<ListEngagement>;
    getSubscriberActivity(subscriberId: number, params?: {
        days?: number;
        limit?: number;
    }): Promise<SubscriberActivity>;
    listSuppressions(params?: {
        search?: string;
        reason?: string;
        per_page?: number;
    }): Promise<{
        data: SuppressionEntry[];
        meta: {
            total: number;
            current_page: number;
            last_page: number;
        };
    }>;
    addSuppressions(payload: {
        emails: string[];
        reason?: string;
        unsubscribe_existing?: boolean;
    }): Promise<{
        data: {
            suppressed: number;
            memberships_unsubscribed: number;
            reason: string;
        };
        message: string;
    }>;
    removeSuppressions(emails: string[]): Promise<{
        data: {
            removed: number;
        };
        message: string;
    }>;
    listNotifications(params?: {
        unread_only?: boolean;
        limit?: number;
    }): Promise<{
        data: Array<Record<string, unknown>>;
        meta: {
            unread: number;
        };
    }>;
    createNotification(payload: NotificationInput): Promise<{
        data: Record<string, unknown>;
        message: string;
    }>;
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