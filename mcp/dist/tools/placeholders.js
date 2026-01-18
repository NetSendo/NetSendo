/**
 * NetSendo MCP Server - Placeholder Tools
 *
 * Tools for listing available placeholders for email/SMS personalization
 */
export function registerPlaceholderTools(server, api) {
    // List Placeholders
    server.tool('list_placeholders', `Get all available placeholders for personalizing email/SMS content.

PLACEHOLDER SYNTAX:
- Use [[placeholder_name]] in your content
- Placeholders are replaced with subscriber data when sending

STANDARD PLACEHOLDERS:
- [[email]] - Subscriber email address
- [[first_name]] or [[fname]] - First name
- [[last_name]] or [[lname]] - Last name  
- [[phone]] - Phone number
- [[!fname]] - First name in vocative case (Polish)

SYSTEM PLACEHOLDERS:
- [[unsubscribe_link]] or [[unsubscribe]] - Unsubscribe link (REQUIRED for compliance)
- [[manage]] - Manage preferences link

GENDER-BASED FORMS (Polish):
- {{male_form|female_form}} - e.g., {{Drogi|Droga}} or {{Byłeś|Byłaś}}

EXAMPLE EMAIL CONTENT:
\`\`\`html
<p>{{Drogi|Droga}} [[first_name]],</p>
<p>Dziękujemy za subskrypcję!</p>
<p><a href="[[unsubscribe_link]]">Wypisz się</a></p>
\`\`\``, {}, async () => {
        try {
            const placeholders = await api.listPlaceholders();
            const formatted = {
                system_placeholders: placeholders.system.map(p => ({
                    placeholder: p.placeholder,
                    label: p.label,
                    description: getPlaceholderDescription(p.name),
                })),
                custom_fields: placeholders.custom.map(p => ({
                    placeholder: p.placeholder,
                    label: p.label,
                    field_type: p.field_type,
                })),
                special_syntax: [
                    {
                        syntax: '{{male|female}}',
                        description: 'Gender-based text variation. First value for male, second for female.',
                        example: '{{Drogi|Droga}} [[first_name]] → "Droga Anna" or "Drogi Jan"',
                    },
                    {
                        syntax: '[[!fname]]',
                        description: 'First name in vocative case (Polish grammar)',
                        example: 'Cześć [[!fname]]! → "Cześć Anno!" (from "Anna")',
                    },
                ],
                usage_example: `Subject: {{Drogi|Droga}} [[first_name]], sprawdź naszą ofertę!
Content: <p>Cześć [[first_name]]!</p><p><a href="[[unsubscribe_link]]">Wypisz się</a></p>`,
            };
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify(formatted, null, 2),
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
function getPlaceholderDescription(name) {
    const descriptions = {
        email: 'Subscriber email address',
        fname: 'First name',
        '!fname': 'First name in vocative case (Polish)',
        lname: 'Last name',
        phone: 'Phone number',
        sex: 'Gender (M/F)',
        unsubscribe: 'Unsubscribe link - REQUIRED for email compliance',
        manage: 'Link to manage subscription preferences',
    };
    return descriptions[name] || '';
}
//# sourceMappingURL=placeholders.js.map