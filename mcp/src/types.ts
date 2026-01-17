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
// Campaign Types
// ============================================================================

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
