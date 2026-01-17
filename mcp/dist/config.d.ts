/**
 * NetSendo MCP Server - Configuration
 *
 * Loads configuration from CLI arguments or environment variables
 */
import { z } from 'zod';
declare const ConfigSchema: z.ZodObject<{
    apiUrl: z.ZodString;
    apiKey: z.ZodString;
    debug: z.ZodDefault<z.ZodBoolean>;
}, "strip", z.ZodTypeAny, {
    apiUrl: string;
    apiKey: string;
    debug: boolean;
}, {
    apiUrl: string;
    apiKey: string;
    debug?: boolean | undefined;
}>;
export type Config = z.infer<typeof ConfigSchema>;
export interface CliArgs {
    url?: string;
    apiKey?: string;
    debug?: boolean;
}
/**
 * Load configuration from CLI arguments (priority) or environment variables
 */
export declare function loadConfig(cliArgs?: CliArgs): Config;
/**
 * Validate configuration without throwing
 */
export declare function validateConfig(cliArgs?: CliArgs): {
    valid: boolean;
    errors: string[];
};
export {};
//# sourceMappingURL=config.d.ts.map