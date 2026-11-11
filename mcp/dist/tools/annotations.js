/**
 * NetSendo MCP Server - Tool annotations
 *
 * Hints for MCP hosts about what a tool does, derived from the tool name so
 * every tool is covered without a per-tool table. Hosts use them to decide
 * which calls need a confirmation from the user:
 *   - read-only tools only read data,
 *   - destructive tools delete or irreversibly change data.
 * They are hints, not access control: the API enforces permissions.
 */
const READ_ONLY_PREFIXES = ['get_', 'list_', 'preview_', 'analyze_'];
const READ_ONLY_NAMES = new Set(['test_connection', 'export_list']);
const DESTRUCTIVE_PREFIXES = ['delete_', 'remove_', 'clean_', 'dedupe_', 'reset_', 'regenerate_', 'end_'];
/** Tools that send something to people outside the account. */
const OPEN_WORLD_PREFIXES = ['send_', 'schedule_', 'resend_', 'test_webhook'];
export function toolAnnotations(name) {
    const readOnly = READ_ONLY_NAMES.has(name) || READ_ONLY_PREFIXES.some((prefix) => name.startsWith(prefix));
    return {
        title: name
            .split('_')
            .map((word, index) => (index === 0 ? word.charAt(0).toUpperCase() + word.slice(1) : word))
            .join(' '),
        readOnlyHint: readOnly,
        destructiveHint: !readOnly && DESTRUCTIVE_PREFIXES.some((prefix) => name.startsWith(prefix)),
        openWorldHint: !readOnly && OPEN_WORLD_PREFIXES.some((prefix) => name.startsWith(prefix)),
    };
}
//# sourceMappingURL=annotations.js.map