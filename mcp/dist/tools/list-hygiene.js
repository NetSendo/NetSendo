/**
 * NetSendo MCP Server - List Hygiene Tools
 *
 * Diagnose what is hurting a list's deliverability, then fix it. Every
 * mutating tool here defaults to a dry run, and the irreversible actions
 * additionally require confirm=true.
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
const CATEGORIES = [
    'missing_contact',
    'invalid_syntax',
    'typo_domain',
    'disposable_domain',
    'role_address',
    'duplicate',
    'hard_bounced',
    'soft_bounce_risk',
    'unsubscribed',
    'unconfirmed',
    'suppressed',
    'globally_inactive',
    'never_engaged',
    'dormant',
];
const CATEGORY_GUIDE = `Categories:
- invalid_syntax     — not a valid address; will always bounce
- typo_domain        — misspelled provider (gmial.com); always bounces, a correction is suggested
- disposable_domain  — throwaway mailbox; expires then hard-bounces
- role_address       — shared inbox (info@, biuro@); raises complaint rates
- missing_contact    — no email on an email list (or no phone on an SMS list)
- duplicate          — same real mailbox present more than once (use dedupe_list to merge)
- hard_bounced       — membership already marked bounced
- soft_bounce_risk   — repeated soft bounces, above the list's threshold
- suppressed         — on the account suppression list yet still active here
- unsubscribed       — opted out (expected on a healthy list; not a defect)
- unconfirmed        — double opt-in never completed
- globally_inactive  — contact deactivated account-wide but still counted here
- never_engaged      — no open and no click since signup, older than the threshold
- dormant            — engaged once, silent since the threshold`;
const thresholds = {
    unconfirmed_after_days: z.number().min(1).max(365).optional().describe('Age before an unconfirmed double opt-in counts as a problem (default: 14)'),
    never_engaged_after_days: z.number().min(1).max(1095).optional().describe('Membership age before "no engagement at all" counts (default: 90)'),
    dormant_after_days: z.number().min(1).max(1095).optional().describe('Silence before a previously engaged contact counts as dormant (default: 180)'),
    soft_bounce_threshold: z.number().min(1).max(20).optional().describe('Soft bounces before a contact is at risk (default: the list setting, otherwise 3)'),
};
export function registerListHygieneTools(server, api) {
    server.tool('analyze_list_health', `Full hygiene report for a list. Read-only — this never changes anything.

Start here before any clean-up. Returns per-category counts with samples, a 0–100 health score, the engagement split (engaged / never engaged / dormant) and ordered recommendations naming the suggested action for each problem.

${CATEGORY_GUIDE}`, {
        list_id: z.number().describe('Contact list ID'),
        sample_size: z.number().min(0).max(50).optional().describe('Example contacts per category (default: 5)'),
        max_scan: z.number().min(1).max(50000).optional().describe('Cap on members examined (default and max: 50000)'),
        ...thresholds,
    }, async ({ list_id, ...params }) => {
        try {
            return ok(await api.analyzeListHygiene(list_id, compact(params)));
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('clean_list', `Apply an action to every member matching the chosen problem categories.

SAFETY: dry_run defaults to true. Run it that way first, show the user what would be affected, and only then re-run with dry_run=false. "delete" and "suppress" are irreversible and additionally require confirm=true.

Actions:
- unsubscribe — mark the membership unsubscribed and cancel their queued messages (safest; keeps the record and the audit trail)
- remove      — detach from this list only; the contact and their other lists stay
- tag         — only label them (requires "tag"); ideal for building a re-engagement segment instead of deleting
- delete      — soft-delete the contact account-wide  [IRREVERSIBLE, needs confirm=true]
- suppress    — add to the do-not-mail suppression list and unsubscribe everywhere  [IRREVERSIBLE, needs confirm=true]

Rule of thumb: bounces and invalid addresses → remove or unsubscribe; never_engaged / dormant → tag first and try a win-back campaign before deleting anything.

${CATEGORY_GUIDE}`, {
        list_id: z.number().describe('Contact list ID'),
        categories: z.array(z.enum(CATEGORIES)).min(1).describe('Problem categories to act on'),
        action: z.enum(['unsubscribe', 'remove', 'delete', 'tag', 'suppress']).describe('What to do with the matched members'),
        tag: z.string().optional().describe('Tag name — required when action="tag"; created if it does not exist'),
        dry_run: z.boolean().optional().describe('Report without writing (default: true)'),
        confirm: z.boolean().optional().describe('Required for action="delete" or "suppress" when dry_run=false. Only set this after the user has explicitly approved.'),
        limit: z.number().min(1).max(50000).optional().describe('Maximum members to process in this call (default: 1000)'),
        reason: z.string().optional().describe('Reason recorded on the change, e.g. "quarterly hygiene"'),
        ...thresholds,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.cleanList(list_id, compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('dedupe_list', `Merge members whose addresses point at the same real mailbox — case differences, and Gmail dot/+tag aliases (j.an+news@gmail.com = jan@gmail.com).

The surviving record keeps every list membership, tag, custom field value and the higher engagement counters; the duplicates are soft-deleted.

SAFETY: dry_run defaults to true and shows the exact merge groups. Writing requires dry_run=false AND confirm=true.`, {
        list_id: z.number().describe('Contact list ID'),
        dry_run: z.boolean().optional().describe('Report the merge groups without writing (default: true)'),
        confirm: z.boolean().optional().describe('Required when dry_run=false — merging deletes the duplicate records'),
        limit: z.number().min(1).max(5000).optional().describe('Maximum duplicate groups to process (default: 500)'),
        keep: z.enum(['oldest', 'most_engaged']).optional().describe('Which record survives (default: oldest)'),
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.dedupeList(list_id, compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('verify_list_emails', `Check whether a list's addresses can actually receive mail: syntax, disposable and role detection, typo domains with suggested corrections, and an MX/DNS lookup per domain.

Read-only. DNS results are cached for a day and looked up per domain rather than per address, so cost scales with the number of domains.

Returns deliverable / risky / undeliverable counts, the overall deliverable rate, domains lacking MX records, and the individual problem addresses.`, {
        list_id: z.number().describe('Contact list ID'),
        limit: z.number().min(1).max(10000).optional().describe('Addresses to check (default: 1000)'),
        status: z.enum(['active', 'inactive', 'unsubscribed', 'bounced', 'all']).optional().describe('Membership status to check (default: active)'),
        check_mx: z.boolean().optional().describe('Perform DNS MX lookups (default: true). Set false for a fast syntax-only pass.'),
        max_domains: z.number().min(1).max(1000).optional().describe('How many of the most common domains to resolve (default: 200)'),
    }, async ({ list_id, ...payload }) => {
        try {
            return ok(await api.verifyListEmails(list_id, compact(payload)));
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_hygiene_options', 'Return the exact category and action names clean_list accepts, and which actions are irreversible. Use this if a clean_list call was rejected for an unknown category.', {}, async () => {
        try {
            return ok(await api.getHygieneOptions());
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=list-hygiene.js.map