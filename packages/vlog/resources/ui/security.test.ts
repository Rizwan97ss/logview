import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Log content is untrusted, so the interface must only ever render it as
 * text, which Preact escapes. These checks stop raw HTML rendering from
 * being added to any component.
 */
describe('interface source', () => {
    const directory = join(import.meta.dirname, 'components');
    const files = readdirSync(directory).filter((file) => file.endsWith('.tsx'));

    it.each(files)('%s does not render raw HTML', (file) => {
        const source = readFileSync(join(directory, file), 'utf8');

        expect(source).not.toMatch(/dangerouslySetInnerHTML|\.innerHTML|\.outerHTML|insertAdjacentHTML|document\.write/);
    });
});
