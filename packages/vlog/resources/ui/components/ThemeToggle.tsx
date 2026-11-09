import { useEffect, useState } from 'preact/hooks';

type Theme = 'system' | 'light' | 'dark';

const STORAGE_KEY = 'vlog-theme';
const NEXT: Record<Theme, Theme> = { system: 'light', light: 'dark', dark: 'system' };
const LABELS: Record<Theme, string> = { system: 'Theme: system', light: 'Theme: light', dark: 'Theme: dark' };
const ICONS: Record<Theme, string> = { system: '◐', light: '☀', dark: '☾' };

function readTheme(): Theme {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' ? stored : 'system';
    } catch {
        return 'system';
    }
}

/**
 * Switches between the system, light and dark themes, remembering the choice
 * in this browser.
 */
export function ThemeToggle() {
    const [theme, setTheme] = useState<Theme>(readTheme);

    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const apply = () => {
            const dark = theme === 'dark' || (theme === 'system' && media.matches);
            document.documentElement.classList.toggle('dark', dark);
        };

        apply();
        media.addEventListener('change', apply);

        try {
            if (theme === 'system') {
                window.localStorage.removeItem(STORAGE_KEY);
            } else {
                window.localStorage.setItem(STORAGE_KEY, theme);
            }
        } catch {
            // Storage can be unavailable, such as in private windows.
        }

        return () => media.removeEventListener('change', apply);
    }, [theme]);

    return (
        <button
            type="button"
            onClick={() => setTheme(NEXT[theme])}
            title={LABELS[theme]}
            aria-label={LABELS[theme]}
            class="rounded-md px-2 py-1 text-sm text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
        >
            {ICONS[theme]}
        </button>
    );
}
