/**
 * NetSendo MCP Server - Tool Helpers
 *
 * Shared response envelope so every tool reports success and failure the same
 * way, and API validation errors reach the model as actionable text instead of
 * a bare "Request failed with status code 422".
 */
import { NetSendoApiError } from '../api-client.js';
export function ok(data) {
    return {
        content: [{ type: 'text', text: JSON.stringify(data, null, 2) }],
    };
}
/**
 * Turn an error into a message the model can act on: field-level validation
 * details are flattened, and the HTTP status is kept so a 409 (confirmation
 * required) is distinguishable from a genuine failure.
 */
export function fail(error) {
    if (error instanceof NetSendoApiError) {
        const details = error.errors
            ? Object.entries(error.errors)
                .map(([field, messages]) => `  - ${field}: ${messages.join(', ')}`)
                .join('\n')
            : null;
        const hint = error.statusCode === 409
            ? '\nThis operation needs explicit confirmation. Re-run with confirm=true once the user has approved it.'
            : error.statusCode === 403
                ? '\nThe API key is missing a permission. Ask the user to enable it in NetSendo → API keys.'
                : '';
        return {
            content: [
                {
                    type: 'text',
                    text: `Error ${error.statusCode}: ${error.message}${details ? `\n${details}` : ''}${hint}`,
                },
            ],
            isError: true,
        };
    }
    return {
        content: [{ type: 'text', text: `Error: ${error.message}` }],
        isError: true,
    };
}
/**
 * Drop undefined keys so optional MCP arguments never travel to the API as
 * explicit nulls, which Laravel's validators would reject.
 */
export function compact(input) {
    return Object.fromEntries(Object.entries(input).filter(([, value]) => value !== undefined));
}
//# sourceMappingURL=helpers.js.map