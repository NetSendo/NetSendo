/**
 * NetSendo MCP Server - Messaging Tools
 *
 * Tools for sending emails and SMS
 */
import { z } from 'zod';
export function registerMessagingTools(server, api) {
    // List Mailboxes
    server.tool('list_mailboxes', `Get all available mailboxes for sending emails.

MAILBOX SELECTION WORKFLOW:
1. Call list_mailboxes to see available mailboxes
2. Look for mailbox with is_default: true as the global default
3. When using contact lists, check list's default_mailbox (via list_contact_lists or get_contact_list)
4. Use list's default_mailbox if set, otherwise use global default

PRIORITY ORDER:
1. List's default_mailbox (if contact_list_ids provided)
2. Global default mailbox (is_default: true)
3. Any verified, active mailbox

TIPS:
- Mailbox with is_default: true is the user's preferred sender
- Each contact list can have its own default_mailbox
- Always use a verified mailbox (is_verified: true)
- Use mailbox from_email and from_name for display purposes`, {}, async () => {
        try {
            const mailboxes = await api.listMailboxes();
            // Find the default mailbox
            const defaultMailbox = mailboxes.find(m => m.is_default);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            mailboxes: mailboxes.map(m => ({
                                id: m.id,
                                name: m.name,
                                email: m.email,
                                is_default: m.is_default,
                                is_verified: m.is_verified,
                            })),
                            total: mailboxes.length,
                            default_mailbox_id: defaultMailbox?.id ?? null,
                            tip: defaultMailbox
                                ? `Use mailbox_id: ${defaultMailbox.id} (${defaultMailbox.name}) as the global default`
                                : 'No default mailbox set. Choose any verified mailbox.',
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Send Email
    server.tool('send_email', `Send a single email to a subscriber. Requires a verified mailbox.

PERSONALIZATION (use list_placeholders for full list):
- [[first_name]], [[last_name]], [[email]]
- [[unsubscribe_link]] - REQUIRED for compliance
- {{male|female}} - Gender-based text

EXAMPLE:
subject: "Cześć [[first_name]]!"
content: "<p>Dziękujemy za kontakt.</p><a href='[[unsubscribe_link]]'>Wypisz</a>"

NOTE: For bulk sends, use create_campaign instead.`, {
        subscriber_id: z.number().optional().describe('Subscriber ID (provide either this or email)'),
        email: z.string().email().optional().describe('Email address (provide either this or subscriber_id)'),
        mailbox_id: z.number().describe('Mailbox ID to send from (use list_mailboxes to get IDs)'),
        subject: z.string().min(1).describe('Email subject line'),
        content: z.string().min(1).describe('Email content (HTML or plain text)'),
        content_type: z.enum(['html', 'text']).optional().describe('Content type (default: html)'),
    }, async ({ subscriber_id, email, mailbox_id, subject, content, content_type }) => {
        try {
            if (!subscriber_id && !email) {
                return {
                    content: [{ type: 'text', text: 'Error: Either subscriber_id or email is required' }],
                    isError: true,
                };
            }
            const result = await api.sendEmail({
                subscriber_id,
                email,
                mailbox_id,
                subject,
                content,
                content_type: content_type ?? 'html',
            });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'Email queued for sending',
                            email_id: result.id,
                            status: result.status,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Get Email Status
    server.tool('get_email_status', 'Check the delivery status of a sent email.', {
        email_id: z.string().describe('Email ID (returned from send_email)'),
    }, async ({ email_id }) => {
        try {
            const status = await api.getEmailStatus(email_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            id: status.id,
                            status: status.status,
                            sent_at: status.sent_at,
                            delivered_at: status.delivered_at,
                            opened_at: status.opened_at,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // List SMS Providers
    server.tool('list_sms_providers', 'Get all available SMS providers. Use the provider ID when sending SMS.', {}, async () => {
        try {
            const providers = await api.listSmsProviders();
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            providers: providers.map(p => ({
                                id: p.id,
                                name: p.name,
                                provider: p.provider,
                                is_active: p.is_active,
                            })),
                            total: providers.length,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Send SMS
    server.tool('send_sms', `Send a single SMS to a subscriber or phone number.

PERSONALIZATION:
- [[first_name]], [[phone]], etc.
- Max 160 characters per SMS segment

EXAMPLE:
content: "Cześć [[first_name]]! Twoje zamówienie jest gotowe."

NOTE: For bulk SMS, use create_campaign with channel='sms'.`, {
        subscriber_id: z.number().optional().describe('Subscriber ID (provide either this or phone)'),
        phone: z.string().optional().describe('Phone number (provide either this or subscriber_id)'),
        provider_id: z.number().optional().describe('SMS Provider ID (optional, uses default if not specified)'),
        content: z.string().min(1).max(160).describe('SMS message content (max 160 characters)'),
    }, async ({ subscriber_id, phone, provider_id, content }) => {
        try {
            if (!subscriber_id && !phone) {
                return {
                    content: [{ type: 'text', text: 'Error: Either subscriber_id or phone is required' }],
                    isError: true,
                };
            }
            const result = await api.sendSms({
                subscriber_id,
                phone,
                provider_id,
                content,
            });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'SMS queued for sending',
                            sms_id: result.id,
                            status: result.status,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Get SMS Status
    server.tool('get_sms_status', 'Check the delivery status of a sent SMS.', {
        sms_id: z.string().describe('SMS ID (returned from send_sms)'),
    }, async ({ sms_id }) => {
        try {
            const status = await api.getSmsStatus(sms_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            id: status.id,
                            status: status.status,
                            sent_at: status.sent_at,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
}
//# sourceMappingURL=messaging.js.map