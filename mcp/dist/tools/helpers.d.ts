/**
 * NetSendo MCP Server - Tool Helpers
 *
 * Shared response envelope so every tool reports success and failure the same
 * way, and API validation errors reach the model as actionable text instead of
 * a bare "Request failed with status code 422".
 */
type ToolResult = {
    content: Array<{
        type: 'text';
        text: string;
    }>;
    isError?: boolean;
};
export declare function ok(data: unknown): ToolResult;
/**
 * Turn an error into a message the model can act on: field-level validation
 * details are flattened, and the HTTP status is kept so a 409 (confirmation
 * required) is distinguishable from a genuine failure.
 */
export declare function fail(error: unknown): ToolResult;
/**
 * Drop undefined keys so optional MCP arguments never travel to the API as
 * explicit nulls, which Laravel's validators would reject.
 */
export declare function compact<T extends Record<string, unknown>>(input: T): Partial<T>;
export {};
//# sourceMappingURL=helpers.d.ts.map