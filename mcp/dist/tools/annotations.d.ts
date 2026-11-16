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
export interface ToolAnnotations {
    title: string;
    readOnlyHint: boolean;
    destructiveHint: boolean;
    openWorldHint: boolean;
}
export declare function toolAnnotations(name: string): ToolAnnotations;
//# sourceMappingURL=annotations.d.ts.map