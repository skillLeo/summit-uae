/**
 * Desktop header menus (Design 1.1, review findings A11Y-01, LANG-01 and SEC-01): the language menu and the
 * Practice Areas dropdown open on a real click and from the keyboard, stay open, and close on a second click,
 * on Escape and on a click outside the component.
 */
import { test, expect } from '@playwright/test';

const MENUS = [
    { name: 'language menu', button: '[data-lang-button]', panel: '#lang-menu-list' },
    { name: 'Practice Areas dropdown', button: '.site-nav__toggle', panel: '#nav-panel-services' },
];

for (const locale of ['en', 'ar', 'ru']) {
    for (const menu of MENUS) {
        test(`${locale}: the ${menu.name} opens on click and by keyboard and closes on Escape and outside clicks`, async ({ page, baseURL }) => {
            test.skip((page.viewportSize()?.width ?? 0) < 768, 'phones use the language sheet and the menu instead of dropdowns');
            await page.goto(`${baseURL}/${locale}/`);
            await page.waitForFunction((sel) => document.querySelector(sel)?.closest('[x-data]')?._x_dataStack !== undefined, menu.button);

            const button = page.locator(menu.button).first();
            const panel = page.locator(menu.panel);
            // Where the full navigation does not fit (Russian below 1440 px), the header shows the menu button instead.
            test.skip(!(await button.isVisible()), 'the navigation is collapsed into the menu at this width in this language');

            // A real click opens it, and it is still open once the click has fully settled.
            await button.click();
            await expect(button).toHaveAttribute('aria-expanded', 'true');
            await expect(panel).toBeVisible();
            await page.waitForTimeout(300);
            await expect(panel).toBeVisible();

            // A second click on the toggle closes it.
            await button.click();
            await expect(button).toHaveAttribute('aria-expanded', 'false');
            await expect(panel).toBeHidden();

            // A click outside the component (the empty page margin at the left edge, below the header) closes it.
            await button.click();
            await expect(panel).toBeVisible();
            await page.mouse.click(4, 600);
            await expect(panel).toBeHidden();

            // Keyboard: Enter and Space open it, Escape closes it.
            await button.focus();
            await page.keyboard.press('Enter');
            await expect(panel).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(panel).toBeHidden();
            await button.focus();
            await page.keyboard.press('Space');
            await expect(panel).toBeVisible();
        });
    }
}
