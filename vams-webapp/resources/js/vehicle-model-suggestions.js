/**
 * Vehicle "Model" datalist suggestions, filtered by the chosen "Make"
 * (vehicles create/edit form).
 *
 * Kept in an external, Vite-bundled script (rather than an inline <script>)
 * because the app's CSP (see SecurityHeaders middleware) only allows
 * script-src 'self' -- inline scripts are blocked by the browser and would
 * silently fail to run.
 */
document.addEventListener('DOMContentLoaded', function () {
    const modelsField = document.getElementById('vehicle-models-data');
    const makeField = document.getElementById('make');
    const modelList = document.getElementById('model-options');
    if (!modelsField || !makeField || !modelList) return;

    const MODELS = JSON.parse(modelsField.textContent);

    // Every known model, used before a make is chosen so the field is never
    // empty of suggestions.
    const everyModel = [...new Set(Object.values(MODELS).flat())].sort();

    function suggestionsFor(make) {
        const key = Object.keys(MODELS).find(function (m) {
            return m.toLowerCase() === String(make || '').trim().toLowerCase();
        });

        return key ? MODELS[key] : everyModel;
    }

    function refresh() {
        const options = suggestionsFor(makeField.value);

        modelList.innerHTML = '';
        options.forEach(function (name) {
            const option = document.createElement('option');
            option.value = name;
            modelList.appendChild(option);
        });
    }

    makeField.addEventListener('input', refresh);
    makeField.addEventListener('change', refresh);
    refresh();
});
