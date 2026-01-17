/**
 * NetSendo MCP Server - Configuration
 *
 * Loads configuration from CLI arguments or environment variables
 */
import { z } from 'zod';
const ConfigSchema = z.object({
    apiUrl: z.string().url().describe('NetSendo API base URL'),
    apiKey: z.string().min(1).describe('NetSendo API key'),
    debug: z.boolean().default(false).describe('Enable debug logging'),
});
/**
 * Load configuration from CLI arguments (priority) or environment variables
 */
export function loadConfig(cliArgs) {
    // CLI arguments take priority over environment variables
    const apiUrl = cliArgs?.url || process.env.NETSENDO_API_URL || process.env.NETSENDO_URL;
    const apiKey = cliArgs?.apiKey || process.env.NETSENDO_API_KEY || process.env.NETSENDO_KEY;
    const debug = cliArgs?.debug || process.env.NETSENDO_DEBUG === 'true' || process.env.DEBUG === 'true';
    if (!apiUrl) {
        throw new Error('NetSendo API URL is required.\n' +
            'Use: --url <url> or set NETSENDO_API_URL environment variable\n' +
            'Example: npx @netsendo/mcp-client --url https://app.netsendo.com --api-key <key>');
    }
    if (!apiKey) {
        throw new Error('NetSendo API key is required.\n' +
            'Use: --api-key <key> or set NETSENDO_API_KEY environment variable\n' +
            'Generate an API key in NetSendo: Settings → API Keys');
    }
    const config = ConfigSchema.parse({
        apiUrl: apiUrl.replace(/\/$/, ''), // Remove trailing slash
        apiKey,
        debug,
    });
    return config;
}
/**
 * Validate configuration without throwing
 */
export function validateConfig(cliArgs) {
    const errors = [];
    const apiUrl = cliArgs?.url || process.env.NETSENDO_API_URL || process.env.NETSENDO_URL;
    const apiKey = cliArgs?.apiKey || process.env.NETSENDO_API_KEY || process.env.NETSENDO_KEY;
    if (!apiUrl) {
        errors.push('Missing NetSendo API URL (use --url or NETSENDO_API_URL)');
    }
    if (!apiKey) {
        errors.push('Missing NetSendo API key (use --api-key or NETSENDO_API_KEY)');
    }
    return {
        valid: errors.length === 0,
        errors,
    };
}
//# sourceMappingURL=config.js.map