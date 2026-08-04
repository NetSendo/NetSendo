/**
 * NetSendo MCP Server - Type Definitions
 * 
 * TypeScript interfaces matching NetSendo API responses
 */

// ============================================================================
// Subscriber Types
// ============================================================================

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

// ============================================================================
// Contact List Types
// ============================================================================

export interface ContactList {
  id: number;
  name: string;
  description: string | null;
  subscribers_count: number;
  double_opt_in: boolean;
  default_mailbox?: {
    id: number;
    name: string;
    from_email: string;
    from_name: string;
  } | null;
  created_at: string;
  updated_at: string;
}

// ============================================================================
// Tag Types
// ============================================================================

export interface Tag {
  id: number;
  name: string;
  color: string | null;
  subscribers_count?: number;
  created_at: string;
}

// ============================================================================
// Message (Campaign) Types
// ============================================================================

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
  scheduled_at?: string;
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

// ============================================================================
// A/B Test Types
// ============================================================================

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

// ============================================================================
// Funnel (Automation) Types
// ============================================================================

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

// Legacy Campaign types (kept for compatibility)
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

// ============================================================================
// CRM Types
// ============================================================================

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

// ============================================================================
// Email Types
// ============================================================================

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

// ============================================================================
// SMS Types
// ============================================================================

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

// ============================================================================
// Dashboard / Stats Types
// ============================================================================

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

// ============================================================================
// Custom Fields Types
// ============================================================================

export interface CustomField {
  id: number;
  name: string;
  slug: string;
  type: 'text' | 'number' | 'date' | 'select' | 'checkbox';
  options: string[] | null;
  required: boolean;
  placeholder: string;
}

// ============================================================================
// Automation Types
// ============================================================================

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

// ============================================================================
// API Response Types
// ============================================================================

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

// ============================================================================
// List Management Types (import / export / hygiene / activity)
// ============================================================================

export type ImportFormat = 'csv' | 'tsv' | 'json' | 'emails';

export interface ListImportPayload {
  format?: ImportFormat;
  /** Raw text for csv/tsv/emails */
  data?: string;
  /** Array of objects for format=json */
  records?: Array<Record<string, unknown>>;
  delimiter?: string;
  has_header?: boolean;
  /** Column index (or header/key name) → field name, 'custom:<name>' or 'ignore' */
  column_mapping?: Record<string, string>;

  dry_run?: boolean;
  update_existing?: boolean;
  skip_invalid?: boolean;
  skip_role?: boolean;
  skip_disposable?: boolean;
  skip_suppressed?: boolean;
  fix_typos?: boolean;
  trigger_automations?: boolean;
  detect_gender?: boolean;
  status?: 'active' | 'inactive' | 'unsubscribed';
  source?: string;
  tags?: Array<string | number>;
  sample_size?: number;
}

export interface ImportPlanSummary {
  total: number;
  create: number;
  update: number;
  reactivate: number;
  already_active: number;
  skipped: number;
  invalid: number;
  duplicate_in_payload: number;
  issues: Record<string, number>;
}

export interface ListImportPreview {
  list: { id: number; name: string; type: string };
  detected: {
    columns: string[];
    header: string[] | null;
    delimiter: string | null;
    mapping: Record<string, string>;
  };
  summary: ImportPlanSummary;
  warnings: string[];
  sample: Array<{
    row: number;
    email: string | null;
    phone: string | null;
    first_name: string | null;
    last_name: string | null;
    action: string;
    reason: string | null;
    issues: string[];
    suggestion: string | null;
  }>;
  problem_rows: Array<{
    row: number;
    email: string | null;
    action: string;
    reason: string | null;
    issues: string[];
    suggestion: string | null;
  }>;
}

export interface ListImportResult {
  created: number;
  updated: number;
  reactivated: number;
  already_active: number;
  skipped: number;
  invalid: number;
  failed: number;
  errors: Array<{ row: number; email: string | null; error: string }>;
  subscriber_ids: number[];
  plan_summary: ImportPlanSummary;
  warnings: string[];
}

export interface ListExportOptions {
  format?: 'json' | 'csv' | 'ndjson';
  fields?: string[];
  status?: 'active' | 'inactive' | 'unsubscribed' | 'bounced' | 'all';
  tag_ids?: number[];
  subscribed_after?: string;
  subscribed_before?: string;
  engaged?: boolean;
  limit?: number;
  cursor?: number;
  delimiter?: string;
}

export interface ListExportResult {
  list: { id: number; name: string; type: string };
  format: string;
  fields: string[];
  filters: Record<string, unknown>;
  count: number;
  has_more: boolean;
  next_cursor: number | null;
  /** Present when format=json */
  records?: Array<Record<string, unknown>>;
  /** Present when format=csv or ndjson */
  data?: string;
}

export type HygieneCategory =
  | 'missing_contact'
  | 'invalid_syntax'
  | 'typo_domain'
  | 'disposable_domain'
  | 'role_address'
  | 'duplicate'
  | 'hard_bounced'
  | 'soft_bounce_risk'
  | 'unsubscribed'
  | 'unconfirmed'
  | 'suppressed'
  | 'globally_inactive'
  | 'never_engaged'
  | 'dormant';

export type HygieneAction = 'unsubscribe' | 'remove' | 'delete' | 'tag' | 'suppress';

export interface ListHygieneReport {
  list: { id: number; name: string; type: string; double_opt_in: boolean };
  scanned: number;
  truncated: boolean;
  totals: {
    members: number;
    active: number;
    unsubscribed: number;
    bounced: number;
    other_status: number;
    confirmed: number;
  };
  engagement: {
    engaged: number;
    never_engaged: number;
    dormant: number;
    engagement_rate: number;
  };
  issues: Record<HygieneCategory, {
    count: number;
    sample: Array<{ subscriber_id: number; email: string | null; status: string; detail: string | null }>;
  }>;
  health_score: number;
  thresholds: Record<string, number>;
  recommendations: Array<{
    category: HygieneCategory;
    count: number;
    share_percent: number;
    suggested_action: HygieneAction;
    severity: 'critical' | 'high' | 'medium' | 'low';
    description: string;
  }>;
}

export interface ListCleanResult {
  list: { id: number; name: string };
  action: HygieneAction;
  categories: HygieneCategory[];
  dry_run: boolean;
  matched: number;
  limited_to: number;
  affected: number;
  failed: number;
  by_category: Record<string, number>;
  sample: Array<{ subscriber_id: number; email: string | null; categories: string[] }>;
  errors: Array<{ subscriber_id: number; error: string }>;
}

export interface ListDedupeResult {
  list: { id: number; name: string };
  dry_run: boolean;
  duplicate_groups: number;
  duplicate_records: number;
  merged: number;
  failed: number;
  keep_strategy: string;
  groups: Array<{
    canonical_email: string;
    keep: { id: number; email: string };
    merge: Array<{ id: number; email: string }>;
  }>;
  errors: Array<{ subscriber_id: number; error: string }>;
}

export interface ListVerifyResult {
  list: { id: number; name: string };
  checked: number;
  status_filter: string;
  verdicts: { deliverable: number; risky: number; undeliverable: number; unknown: number };
  deliverable_rate: number;
  domains: Array<{ domain: string; addresses: number; has_mx: boolean }>;
  domains_without_mx: Array<{ domain: string; addresses: number; has_mx: boolean }>;
  problems: Array<{
    subscriber_id: number;
    email: string | null;
    verdict: string;
    issues: string[];
    suggestion: string | null;
  }>;
}

export interface MemberSelection {
  subscriber_ids?: number[];
  emails?: string[];
  filter?: {
    status?: 'active' | 'inactive' | 'unsubscribed' | 'bounced' | 'all';
    tag_ids?: number[];
    engaged?: boolean;
    subscribed_before?: string;
    subscribed_after?: string;
    never_opened?: boolean;
    limit?: number;
  };
}

export interface ListActivityFeed {
  list: { id: number; name: string };
  window_days: number;
  since: string;
  event_counts: Record<string, number>;
  events: Array<{
    type: string;
    occurred_at: string;
    subscriber_id: number;
    email: string | null;
    name: string | null;
    detail: Record<string, unknown> | null;
  }>;
  note: string | null;
}

export interface ListEngagement {
  list: { id: number; name: string; type: string };
  window_days: number;
  since: string;
  audience: {
    members: number;
    active: number;
    unsubscribed: number;
    bounced: number;
    by_status: Record<string, number>;
  };
  growth: {
    added: number;
    lost: number;
    net: number;
    churn_rate: number;
    daily: Array<{ date: string; added: number; lost: number }>;
  };
  delivery: {
    sent: number;
    total_opens: number;
    unique_openers: number;
    total_clicks: number;
    unique_clickers: number;
    open_rate: number;
    click_rate: number;
    click_to_open_rate: number;
  };
  top_messages: Array<{ message_id: number; subject: string; opens: number; unique_openers: number }>;
  top_links: Array<{ url: string; clicks: number; unique_clickers: number }>;
  most_engaged: Array<{
    subscriber_id: number;
    email: string | null;
    name: string | null;
    opens: number;
    clicks: number;
    last_opened_at: string | null;
    last_clicked_at: string | null;
  }>;
  inactive: { never_engaged: number; no_activity_90_days: number };
}

export interface SubscriberActivity {
  subscriber: {
    id: number;
    email: string | null;
    phone: string | null;
    name: string | null;
    is_active_global: boolean;
    source: string | null;
    language: string | null;
    opens_count: number;
    clicks_count: number;
    last_opened_at: string | null;
    last_clicked_at: string | null;
    created_at: string | null;
  };
  tags: Array<{ id: number; name: string }>;
  lists: Array<{
    id: number;
    name: string;
    status: string;
    source: string | null;
    subscribed_at: string | null;
    confirmed_at: string | null;
    unsubscribed_at: string | null;
  }>;
  pending_messages: number;
  window_days: number;
  events: Array<{ type: string; occurred_at: string; detail: Record<string, unknown> }>;
}

export interface ListStats {
  id: number;
  name: string;
  type: string;
  description: string | null;
  group: string | null;
  members: {
    total: number;
    active: number;
    unsubscribed: number;
    bounced: number;
    by_status: Record<string, number>;
  };
  engagement: {
    openers: number;
    clickers: number;
    confirmed: number;
    open_share_percent: number;
    click_share_percent: number;
  };
  last_30_days: { added: number; lost: number; net: number };
  configuration: Record<string, unknown>;
  created_at: string | null;
}

export interface ContactListCreateInput {
  name: string;
  type?: 'email' | 'sms';
  description?: string;
  contact_list_group_id?: number;
  default_mailbox_id?: number;
  default_sms_provider_id?: number;
  is_public?: boolean;
  timezone?: string;
  double_opt_in?: boolean;
  resubscription_behavior?: 'reset_date' | 'keep_original_date';
  max_subscribers?: number;
}

export interface ContactListUpdateInput extends Partial<ContactListCreateInput> {
  signups_blocked?: boolean;
  webhook_url?: string;
  webhook_events?: string[];
}

export interface SuppressionEntry {
  id: number;
  email: string;
  reason: string;
  suppressed_at: string;
}

export interface NotificationInput {
  type?: 'info' | 'success' | 'warning' | 'error';
  title: string;
  message?: string;
  action_url?: string;
  list_id?: number;
  data?: Record<string, unknown>;
}
