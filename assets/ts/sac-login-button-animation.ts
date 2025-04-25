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

/**
 * Represents the core application logic for handling login button interactions and form submissions.
 */
class SacLoginButtonAnimation {
    private static readonly formSelectors = ['form.sac-oidc-login-be', 'form.sac-oidc-login-fe'];
    private static readonly buttonSelector = 'button.sac-login-button--button';

    /**
     * Initialize the app by attaching event listeners to login buttons.
     */
    public init(): void {
        // Select all login buttons and attach the click event
        const buttons: NodeListOf<HTMLButtonElement> = this.getLoginButtons();

        for (const button of buttons) {
            window.addEventListener('pageshow', (event: PageTransitionEvent) => this.removeLoadingState(button));
            button.addEventListener('click', (event: MouseEvent) => this.handleLoginClick(event));
        }
    }

    /**
     * Event handler for login button click.
     * @param event - The mouse click event.
     */
    private handleLoginClick(event: MouseEvent): void {
        event.preventDefault();
        event.stopPropagation();

        const loginButton = event.currentTarget as HTMLButtonElement;
        this.handleFormSubmission(loginButton);
    }

    /**
     * Handles form submission logic including button state management.
     * @param loginButton - The clicked login button.
     * @returns Whether the submission process was initiated.
     */
    private handleFormSubmission(loginButton: HTMLButtonElement): boolean {
        if (this.isButtonLoading(loginButton)) {
            return false; // Abort if the button is already in a loading state
        }

        this.setLoadingState(loginButton);

        // Delay form submission to allow animations
        window.setTimeout(() => {
            this.submitForms(loginButton, SacLoginButtonAnimation.formSelectors);
        }, 1000);

        return true;
    }

    /**
     * Retrieve login buttons from the DOM.
     * @returns A list of login buttons.
     */
    private getLoginButtons(): NodeListOf<HTMLButtonElement> {
        return document.querySelectorAll(SacLoginButtonAnimation.buttonSelector);
    }

    /**
     * Checks if a button is already in the "loading" state.
     * @param button - The button to check.
     * @returns Whether the button is in a loading state.
     */
    private isButtonLoading(button: HTMLButtonElement): boolean {
        return button.classList.contains('button--loading');
    }

    /**
     * Updates the button UI to indicate the loading state.
     * @param button - The button to update.
     */
    private setLoadingState(button: HTMLButtonElement): void {
        button.classList.add('button--loading');
        button.setAttribute('disabled', '');
    }

    /**
     * Updates the button UI to indicate the loading state.
     * @param button - The button to update.
     */
    private removeLoadingState(button: HTMLButtonElement): void {
        button.classList.remove('button--loading');
        button.removeAttribute('disabled');
    }

    /**
     * Finds the closest parent forms for the given selectors and submits them.
     * @param button - The button used to locate forms.
     * @param formSelectors - An array of form selectors to match.
     */
    private submitForms(button: HTMLButtonElement, formSelectors: string[]): void {
        formSelectors.forEach((selector) => {
            const form = button.closest<HTMLFormElement>(selector);
            if (form) {
                form.submit();
            }
        });
    }
}

// Initialize and start the application
const app = new SacLoginButtonAnimation();
window.addEventListener('DOMContentLoaded', () => app.init());
