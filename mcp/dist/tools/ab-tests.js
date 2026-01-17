/**
 * NetSendo MCP Server - A/B Test Tools
 *
 * Tools for managing A/B tests on campaigns
 */
import { z } from 'zod';
export function registerAbTestTools(server, api) {
    // List A/B Tests
    server.tool('list_ab_tests', 'List all A/B tests with optional filtering by status or campaign.', {
        status: z.string().optional().describe('Filter by status (draft, running, completed)'),
        message_id: z.number().optional().describe('Filter by campaign/message ID'),
        page: z.number().optional().describe('Page number'),
        per_page: z.number().optional().describe('Items per page'),
    }, async ({ status, message_id, page, per_page }) => {
        try {
            const tests = await api.listAbTests({ status, message_id, page, per_page });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            ab_tests: tests.data.map(t => ({
                                id: t.id,
                                name: t.name,
                                message_id: t.message_id,
                                status: t.status,
                                test_type: t.test_type,
                                winning_metric: t.winning_metric,
                                variants_count: t.variants?.length ?? 0,
                                winner: t.winner_variant?.variant_letter ?? null,
                                started_at: t.test_started_at,
                                ended_at: t.test_ended_at,
                            })),
                            pagination: tests.meta,
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
    // Create A/B Test
    server.tool('create_ab_test', 'Create a new A/B test for a campaign. Add variants after creation.', {
        message_id: z.number().describe('Campaign ID to test'),
        name: z.string().describe('Test name'),
        test_type: z.enum(['subject', 'content', 'sender', 'send_time', 'full']).describe('What to test'),
        winning_metric: z.enum(['open_rate', 'click_rate', 'conversion_rate']).describe('Metric to determine winner'),
        sample_percentage: z.number().min(10).max(50).describe('Percentage of recipients for testing (10-50%)'),
        test_duration_hours: z.number().min(1).max(168).describe('Test duration in hours (1-168)'),
        auto_select_winner: z.boolean().optional().describe('Automatically select winner after duration'),
    }, async ({ message_id, name, test_type, winning_metric, sample_percentage, test_duration_hours, auto_select_winner }) => {
        try {
            const test = await api.createAbTest({
                message_id,
                name,
                test_type,
                winning_metric,
                sample_percentage,
                test_duration_hours,
                auto_select_winner,
            });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'A/B test created. Add variants using add_ab_variant.',
                            test: {
                                id: test.id,
                                name: test.name,
                                status: test.status,
                                test_type: test.test_type,
                            },
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
    // Add Variant
    server.tool('add_ab_variant', 'Add a variant to an A/B test. Each variant represents one version to test.', {
        test_id: z.number().describe('A/B test ID'),
        variant_letter: z.string().describe('Variant identifier (A, B, C, etc.)'),
        subject: z.string().optional().describe('Subject line for this variant'),
        content: z.string().optional().describe('Content for this variant'),
        mailbox_id: z.number().optional().describe('Mailbox/sender for this variant'),
        is_control: z.boolean().optional().describe('Is this the control variant?'),
        weight: z.number().optional().describe('Traffic weight (1-100)'),
    }, async ({ test_id, variant_letter, subject, content, mailbox_id, is_control, weight }) => {
        try {
            const variant = await api.addAbTestVariant(test_id, {
                variant_letter: variant_letter.toUpperCase(),
                subject,
                content,
                mailbox_id,
                is_control,
                weight,
            });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: `Variant ${variant.variant_letter} added`,
                            variant: {
                                id: variant.id,
                                letter: variant.variant_letter,
                                is_control: variant.is_control,
                            },
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
    // Start A/B Test
    server.tool('start_ab_test', 'Start running an A/B test. Requires at least 2 variants.', {
        test_id: z.number().describe('A/B test ID'),
    }, async ({ test_id }) => {
        try {
            const result = await api.startAbTest(test_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'A/B test started',
                            test_id: result.test.id,
                            status: result.test.status,
                            ends_at: result.ends_at,
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
    // End A/B Test
    server.tool('end_ab_test', 'End a running A/B test and optionally select a winner manually.', {
        test_id: z.number().describe('A/B test ID'),
        winner_variant_id: z.number().optional().describe('Manually select winner variant ID (optional)'),
    }, async ({ test_id, winner_variant_id }) => {
        try {
            const result = await api.endAbTest(test_id, winner_variant_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'A/B test ended',
                            test_id: result.test.id,
                            winner: result.winner,
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
    // Get A/B Test Results
    server.tool('get_ab_test_results', 'Get detailed results and statistics for an A/B test.', {
        test_id: z.number().describe('A/B test ID'),
    }, async ({ test_id }) => {
        try {
            const results = await api.getAbTestResults(test_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify(results, null, 2),
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
    // Delete A/B Test
    server.tool('delete_ab_test', 'Delete an A/B test. Cannot delete running tests.', {
        test_id: z.number().describe('A/B test ID to delete'),
    }, async ({ test_id }) => {
        try {
            await api.deleteAbTest(test_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'A/B test deleted',
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
//# sourceMappingURL=ab-tests.js.map