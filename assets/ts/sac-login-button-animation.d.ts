/**
 * Represents the core application logic for handling login button interactions and form submissions.
 */
declare class SacLoginButtonAnimation {
    private static readonly formSelectors;
    private static readonly buttonSelector;
    /**
     * Initialize the app by attaching event listeners to login buttons.
     */
    init(): void;
    /**
     * Event handler for login button click.
     * @param event - The mouse click event.
     */
    private handleLoginClick;
    /**
     * Handles form submission logic including button state management.
     * @param loginButton - The clicked login button.
     * @returns Whether the submission process was initiated.
     */
    private handleFormSubmission;
    /**
     * Retrieve login buttons from the DOM.
     * @returns A list of login buttons.
     */
    private getLoginButtons;
    /**
     * Checks if a button is already in the "loading" state.
     * @param button - The button to check.
     * @returns Whether the button is in a loading state.
     */
    private isButtonLoading;
    /**
     * Updates the button UI to indicate the loading state.
     * @param button - The button to update.
     */
    private setLoadingState;
    /**
     * Updates the button UI to indicate the loading state.
     * @param button - The button to update.
     */
    private removeLoadingState;
    /**
     * Finds the closest parent forms for the given selectors and submits them.
     * @param button - The button used to locate forms.
     * @param formSelectors - An array of form selectors to match.
     */
    private submitForms;
}
declare const app: SacLoginButtonAnimation;
