/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

"use strict";

// The click handlers are delegated to the document, so that they keep working
// in the Contao 6 back end, where Turbo Drive replaces the page body without
// firing DOMContentLoaded again.
(() => {

    document.addEventListener('click', (e: MouseEvent) => {
        if (!(e.target instanceof Element)) {
            return;
        }

        // Handle Contao frontend logout
        const feLink = e.target.closest<HTMLElement>('.trigger-ids-kill-session');

        if (feLink) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            if (feLink.hasAttribute('data-href')) {
                feLink.textContent = feLink.dataset.logoutLabel ?? 'du wirst abgemeldet …';
                feLink.removeAttribute('data-href');
                logout('frontend', feLink.getAttribute('href'), feLink.dataset.targetpath || null);
            }

            return;
        }

        // Handle Contao backend logout
        const beLink = e.target.closest<HTMLAnchorElement>('#tmenu a[href$="contao/logout"]');

        if (beLink) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            logout('backend', beLink.getAttribute('href'), null);
        }
    }, true);

    /**
     * 1. Retrieve the Hitobito logout URI
     * 2. Redirect to the Hitobito logout endpoint.
     * 3. Thanks to the post_logout_redirect_uri query param, Hitobito redirects back to Contao.
     *
     * @param contaoScope - Scope of logout ('frontend' or 'backend')
     * @param contaoLogoutEndpoint - URL of the Contao logout endpoint
     * @param postLogoutRedirectUri - URL for post logout redirection
     */
    async function logout(
        contaoScope: string,
        contaoLogoutEndpoint: string | null,
        postLogoutRedirectUri: string | null = null
    ): Promise<void> {

        let logoutUrl = `/_oauth2_login/hitobito/${contaoScope}/logout`;

        if (contaoScope === 'frontend' && postLogoutRedirectUri !== null) {
            logoutUrl = `/_oauth2_login/hitobito/frontend/logout?post_logout_redirect_uri=${btoa(postLogoutRedirectUri)}`;
        }

        try {
            // Retrieve the Hitobito logout URI
            const response = await fetch(logoutUrl);

            if (!response.ok) {
                const errorMsg = `Could not fetch the Hitobito logout URL. Server returned status: ${response.status}`;
                return handleError(contaoScope, errorMsg);
            }

            const json = await response.json();

            // Call the Contao logout endpoint
            if (contaoLogoutEndpoint) {
                await fetch(contaoLogoutEndpoint);
            }

            // Redirect to the Hitobito logout endpoint
            window.location.href = json['logoutUri'];
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'An unknown error occurred.';
            handleError(contaoScope, errorMessage);
        }
    }

    /**
     * Handle errors during logout
     * @param contaoScope - Scope of logout
     * @param errorMsg - Error message
     */
    function handleError(contaoScope: string, errorMsg: string): void {
        console.error(errorMsg);
        performContaoDefaultLogout(contaoScope);
    }

    /**
     * Perform default Contao logout
     * @param contaoScope - Scope of logout
     */
    function performContaoDefaultLogout(contaoScope: string): void {
        if (contaoScope === 'frontend') {
            window.location.href = '/_contao/logout';
        } else {
            window.location.href = '/contao/logout';
        }
    }
})();
