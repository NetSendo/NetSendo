/**
 * NetSendo MCP Server - Type Definitions
 *
 * TypeScript interfaces matching NetSendo API responses
 */
export interface Subscriber {
    id: number;
    email: string;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    status: 'subscribed' | 'unsubscribed' | 'bounced' | 'complained';
    source: string | null;
    ip_address: string | null;
    created_at: string;
    updated_at: string;
    lists?: ContactList[];
    tags?: Tag[];
    custom_fields?: Record<string, string | number | boolean | null>;
}
export interface SubscriberCreateInput {
    email: string;
    first_name?: string;
    last_name?: string;
    phone?: string;
    lists?: number[];
    tags?: number[] | string[];
    custom_fields?: Record<string, string | number | boolean>;
    source?: string;
}
export interface SubscriberUpdateInput {
    email?: string;
    first_name?: string;
    last_name?: string;
    phone?: string;
    status?: 'subscribed' | 'unsubscribed';
    custom_fields?: Record<string, string | number | boolean>;
}
export interface ContactList {
    id: number;
    name: string;
    description: string | null;
    subscribers_count: number;
    double_opt_in: boolean;
    created_at: string;
    updated_at: string;
}
export interface Tag {
    id: number;
    name: string;
    color: string | null;
    subscribers_count?: number;
    created_at: string;
}
export interface Message {
    id: number;
    user_id: number;
    channel: 'email' | 'sms';
    type: 'broadcast' | 'autoresponder';
    subject: string;
    preheader: string | null;
    content: string;
    status: 'draft' | 'scheduled' | 'sending' | 'sent' | 'active';
    mailbox_id: number | null;
    template_id: number | null;
    day: number | null;
    time_of_day: string | null;
    timezone: string | null;
    scheduled_at: string | null;
    is_active: boolean;
    sent_count: number;
    planned_recipients_count: number;
    created_at: string;
    updated_at: string;
    mailbox?: Mailbox;
    contact_lists?: ContactList[];
    excluded_lists?: ContactList[];
}
export interface MessageCreateInput {
    subject: string;
    channel: 'email' | 'sms';
    type: 'broadcast' | 'autoresponder';
    content?: string;
    preheader?: string;
    mailbox_id?: number;
    template_id?: number;
    day?: number;
    time_of_day?: string;
    timezone?: string;
    contact_list_ids?: number[];
    excluded_list_ids?: number[];
}
export interface MessageUpdateInput {
    subject?: string;
    content?: string;
    preheader?: string;
    mailbox_id?: number;
    template_id?: number;
    day?: number;
    time_of_day?: string;
    timezone?: string;
    is_active?: boolean;
}
export interface MessageStats {
    id: number;
    subject: string;
    status: string;
    type: string;
    sent_count: number;
    planned_recipients_count: number;
    queue_stats: {
        planned: number;
        queued: number;
        sent: number;
        failed: number;
        skipped: number;
        total: number;
    };
    schedule_stats?: {
        sent: number;
        today: number;
        tomorrow: number;
        day_after_tomorrow: number;
        days_3_7: number;
        over_7_days: number;
        missed: number;
        total_scheduled: number;
    };
}
export interface AbTest {
    id: number;
    message_id: number;
    user_id: number;
    name: string;
    status: 'draft' | 'running' | 'paused' | 'completed' | 'cancelled';
    test_type: 'subject' | 'content' | 'sender' | 'send_time' | 'full';
    winning_metric: 'open_rate' | 'click_rate' | 'conversion_rate';
    sample_percentage: number;
    test_duration_hours: number;
    auto_select_winner: boolean;
    confidence_threshold: number;
    winner_variant_id: number | null;
    test_started_at: string | null;
    test_ended_at: string | null;
    final_results: Record<string, AbTestVariantResult> | null;
    created_at: string;
    updated_at: string;
    message?: Message;
    variants?: AbTestVariant[];
    winner_variant?: AbTestVariant;
}
export interface AbTestVariant {
    id: number;
    ab_test_id: number;
    variant_letter: string;
    subject: string | null;
    content: string | null;
    mailbox_id: number | null;
    is_control: boolean;
    weight: number;
}
export interface AbTestVariantResult {
    variant_id: number;
    variant_letter: string;
    sent: number;
    opens: number;
    unique_opens: number;
    clicks: number;
    unique_clicks: number;
    open_rate: number;
    click_rate: number;
    click_to_open_rate: number;
}
export interface AbTestCreateInput {
    message_id: number;
    name: string;
    test_type: 'subject' | 'content' | 'sender' | 'send_time' | 'full';
    winning_metric: 'open_rate' | 'click_rate' | 'conversion_rate';
    sample_percentage: number;
    test_duration_hours: number;
    auto_select_winner?: boolean;
    confidence_threshold?: number;
}
export interface AbTestVariantInput {
    variant_letter: string;
    subject?: string;
    content?: string;
    mailbox_id?: number;
    is_control?: boolean;
    weight?: number;
}
export interface Funnel {
    id: number;
    user_id: number;
    name: string;
    slug: string;
    status: 'draft' | 'active' | 'paused';
    trigger_type: 'list_signup' | 'tag_added' | 'form_submit' | 'manual';
    trigger_list_id: number | null;
    trigger_form_id: number | null;
    trigger_tag: string | null;
    subscribers_count: number;
    completed_count: number;
    settings: Record<string, unknown>;
    created_at: string;
    updated_at: string;
    steps?: FunnelStep[];
    trigger_list?: ContactList;
}
export interface FunnelStep {
    id: number;
    funnel_id: number;
    type: 'start' | 'email' | 'sms' | 'delay' | 'condition' | 'action' | 'end';
    name: string;
    order: number;
    config: Record<string, unknown>;
    message_id: number | null;
    delay_value: number | null;
    delay_unit: 'minutes' | 'hours' | 'days' | null;
    condition_type: string | null;
    condition_config: Record<string, unknown> | null;
    next_step_id: number | null;
}
export interface FunnelCreateInput {
    name: string;
    trigger_type: 'list_signup' | 'tag_added' | 'form_submit' | 'manual';
    trigger_list_id?: number;
    trigger_form_id?: number;
    trigger_tag?: string;
    settings?: Record<string, unknown>;
}
export interface FunnelStepInput {
    type: 'email' | 'sms' | 'delay' | 'condition' | 'action' | 'end';
    name: string;
    after_step_id?: number;
    config?: Record<string, unknown>;
    message_id?: number;
    delay_value?: number;
    delay_unit?: 'minutes' | 'hours' | 'days';
    condition_type?: string;
    condition_config?: Record<string, unknown>;
}
export interface FunnelStats {
    total_subscribers: number;
    active_subscribers: number;
    completed: number;
    completion_rate: number;
    steps_count: number;
}
export interface Campaign {
    id: number;
    name: string;
    subject: string;
    status: 'draft' | 'scheduled' | 'sending' | 'sent' | 'paused';
    type: 'regular' | 'ab_test' | 'automation';
    sent_count: number;
    open_count: number;
    click_count: number;
    bounce_count: number;
    unsubscribe_count: number;
    open_rate: number;
    click_rate: number;
    scheduled_at: string | null;
    sent_at: string | null;
    created_at: string;
    updated_at: string;
}
export interface CampaignStats {
    id: number;
    name: string;
    sent: number;
    delivered: number;
    opened: number;
    clicked: number;
    bounced: number;
    unsubscribed: number;
    complained: number;
    open_rate: number;
    click_rate: number;
    bounce_rate: number;
    unsubscribe_rate: number;
}
export interface CrmContact {
    id: number;
    first_name: string | null;
    last_name: string | null;
    email: string;
    phone: string | null;
    company_id: number | null;
    company?: CrmCompany;
    subscriber_id: number | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}
export interface CrmCompany {
    id: number;
    name: string;
    website: string | null;
    industry: string | null;
    size: string | null;
    created_at: string;
}
export interface CrmTask {
    id: number;
    title: string;
    description: string | null;
    contact_id: number | null;
    deal_id: number | null;
    user_id: number;
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
    priority: 'low' | 'medium' | 'high';
    due_date: string | null;
    completed_at: string | null;
    created_at: string;
    updated_at: string;
}
export interface CrmTaskCreateInput {
    title: string;
    description?: string;
    contact_id?: number;
    deal_id?: number;
    priority?: 'low' | 'medium' | 'high';
    due_date?: string;
}
export interface CrmDeal {
    id: number;
    title: string;
    value: number;
    currency: string;
    stage_id: number;
    contact_id: number | null;
    company_id: number | null;
    probability: number;
    expected_close_date: string | null;
    status: 'open' | 'won' | 'lost';
    created_at: string;
    updated_at: string;
}
export interface EmailSendInput {
    subscriber_id?: number;
    email?: string;
    mailbox_id: number;
    subject: string;
    content: string;
    content_type?: 'html' | 'text';
}
export interface EmailStatus {
    id: string;
    status: 'queued' | 'sent' | 'delivered' | 'opened' | 'clicked' | 'bounced' | 'failed';
    sent_at: string | null;
    delivered_at: string | null;
    opened_at: string | null;
}
export interface Mailbox {
    id: number;
    name: string;
    email: string;
    is_default: boolean;
    is_verified: boolean;
}
export interface SmsSendInput {
    subscriber_id?: number;
    phone?: string;
    provider_id?: number;
    content: string;
}
export interface SmsStatus {
    id: string;
    status: 'queued' | 'sent' | 'delivered' | 'failed';
    sent_at: string | null;
}
export interface SmsProvider {
    id: number;
    name: string;
    provider: string;
    is_active: boolean;
}
export interface DashboardStats {
    subscribers: {
        total: number;
        subscribed: number;
        unsubscribed: number;
        new_today: number;
        new_this_week: number;
        new_this_month: number;
    };
    campaigns: {
        total: number;
        sent: number;
        scheduled: number;
        draft: number;
    };
    emails: {
        sent_today: number;
        sent_this_week: number;
        sent_this_month: number;
        avg_open_rate: number;
        avg_click_rate: number;
    };
}
export interface CustomField {
    id: number;
    name: string;
    slug: string;
    type: 'text' | 'number' | 'date' | 'select' | 'checkbox';
    options: string[] | null;
    required: boolean;
    placeholder: string;
}
export interface Automation {
    id: number;
    name: string;
    description: string | null;
    trigger_type: string;
    is_active: boolean;
    runs_count: number;
    last_run_at: string | null;
    created_at: string;
    updated_at: string;
}
export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}
export interface ApiErrorResponse {
    message: string;
    errors?: Record<string, string[]>;
}
//# sourceMappingURL=types.d.ts.map