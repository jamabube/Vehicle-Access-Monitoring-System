/**
 * CSP-safe "confirm before submit" behaviour.
 *
 * The app's CSP (see SecurityHeaders middleware) only allows script-src
 * 'self', so inline onsubmit="return confirm(...)" / onclick="..." handlers
 * are blocked by the browser and never run. Forms/buttons that need a
 * confirmation dialog should use a data-confirm="Message" attribute instead;
 * this delegated listener intercepts the submit and shows the prompt.
 */
document.addEventListener('submit', function (event) {
    var form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    var message = form.getAttribute('data-confirm');

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
