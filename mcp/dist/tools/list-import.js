/**
 * NetSendo MCP Server - List Import Tools
 *
 * Import contacts onto a list from whatever shape the data arrives in: pasted
 * CSV, a spreadsheet export, JSON records from another system, or a bare list
 * of addresses.
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
const FORMAT_GUIDE = `Formats:
- "csv"    → put the raw text in "data". Delimiter and header row are auto-detected (comma, semicolon, tab, pipe).
- "tsv"    → same, tab-separated.
- "json"   → put an array of objects in "records", e.g. [{"email":"a@b.pl","first_name":"Anna","custom_fields":{"city":"Kraków"},"tags":["vip"]}]
- "emails" → put addresses in "data", one per line. Accepts "Anna Kowalska <anna@example.com>" and "anna@example.com, Anna, Kowalska".

Column mapping is automatic for common headers in PL/EN/DE/ES (email, e-mail, imię, nazwisko, telefon, first_name, …).
Override it with column_mapping when headers are unusual: {"0":"email","1":"first_name","3":"custom:city","2":"ignore"}
(keys are column indexes for CSV/TSV, or key names for JSON records).`;
const importOptions = {
    update_existing: z.boolean().optional().describe('Fill blanks on contacts that already exist (default: true). Never overwrites data NetSendo already has.'),
    skip_invalid: z.boolean().optional().describe('Skip rows whose address fails syntax validation (default: true).'),
    skip_role: z.boolean().optional().describe('Skip role addresses such as info@, biuro@, sales@ (default: false).'),
    skip_disposable: z.boolean().optional().describe('Skip throwaway mailboxes such as mailinator.com (default: true).'),
    skip_suppressed: z.boolean().optional().describe('Skip addresses on the account suppression list (default: true). Turning this off would re-mail people who asked to be forgotten.'),
    fix_typos: z.boolean().optional().describe('Auto-correct known provider typos (gmial.com → gmail.com) instead of importing them as-is (default: false).'),
    trigger_automations: z.boolean().optional().describe('Fire the signup event so autoresponders, funnels and webhooks start (default: true). Set false for a silent migration.'),
    detect_gender: z.boolean().optional().describe('Guess gender from the first name when missing (default: true).'),
    status: z.enum(['active', 'inactive', 'unsubscribed']).optional().describe('Membership status to assign (default: active).'),
    source: z.string().optional().describe('Source label stored on the membership, e.g. "webinar-2026" (default: api_import).'),
    tags: z.array(z.string()).optional().describe('Tag names to apply to every imported contact. Tags that do not exist are created.'),
};
export function registerListImportTools(server, api) {
    server.tool('preview_list_import', `Dry-run an import: parse the data, show the detected column mapping and report exactly what would happen — without writing anything.

ALWAYS run this before import_subscribers when the data comes from the user unverified. It reveals wrong column mapping, invalid addresses, duplicates and typo domains while they are still cheap to fix.

${FORMAT_GUIDE}

Returns: detected mapping, per-action counts (create/update/reactivate/skip/invalid), a row sample and the problem rows with reasons.`, {
        list_id: z.number().describe('Target contact list ID'),
        format: z.enum(['csv', 'tsv', 'json', 'emails']).optional().describe('Payload format (default: csv)'),
        data: z.string().optional().describe('Raw text for csv/tsv/emails'),
        records: z.array(z.record(z.any())).optional().describe('Array of objects for format=json'),
        delimiter: z.string().optional().describe('Force a CSV delimiter (",", ";", "|", "tab"). Auto-detected when omitted.'),
        has_header: z.boolean().optional().describe('Force header-row handling. Auto-detected when omitted.'),
        column_mapping: z.record(z.string()).optional().describe('Column index/key → field name, "custom:<field>" or "ignore"'),
        sample_size: z.number().min(1).max(50).optional().describe('How many parsed rows to show (default: 10)'),
        ...importOptions,
    }, async ({ list_id, ...payload }) => {
        try {
            return ok(await api.previewListImport(list_id, compact(payload)));
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('import_subscribers', `Import contacts onto a list. Creates new contacts, reactivates unsubscribed ones and fills gaps on existing ones.

${FORMAT_GUIDE}

Behaviour worth knowing:
- Duplicates are folded by real mailbox: JAN@x.pl, jan@x.pl and jan+news@gmail.com/j.an@gmail.com collapse to one contact.
- Suppressed addresses are skipped by default — they asked not to be contacted.
- By default the signup event fires, so welcome autoresponders and funnels start. Pass trigger_automations=false to migrate quietly.
- Up to 5000 rows per call; send the rest in follow-up calls.
- Pass dry_run=true to get the preview_list_import response instead of writing.

Returns: created / updated / reactivated / skipped / invalid / failed counts plus the first errors with row numbers.`, {
        list_id: z.number().describe('Target contact list ID'),
        format: z.enum(['csv', 'tsv', 'json', 'emails']).optional().describe('Payload format (default: csv)'),
        data: z.string().optional().describe('Raw text for csv/tsv/emails'),
        records: z.array(z.record(z.any())).optional().describe('Array of objects for format=json'),
        delimiter: z.string().optional().describe('Force a CSV delimiter (",", ";", "|", "tab")'),
        has_header: z.boolean().optional().describe('Force header-row handling'),
        column_mapping: z.record(z.string()).optional().describe('Column index/key → field name, "custom:<field>" or "ignore"'),
        dry_run: z.boolean().optional().describe('Report what would happen without writing (default: false)'),
        ...importOptions,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.importToList(list_id, compact(payload));
            return ok(result.message ? { ...result.data, message: result.message } : result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=list-import.js.map