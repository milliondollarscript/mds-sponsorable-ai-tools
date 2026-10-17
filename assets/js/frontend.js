(function () {
    var messages = window.mdsSponsorableTools || {};

    function message(key, fallback) {
        return messages[key] || fallback;
    }

    function format(template, values) {
        return template.replace(/%(\d+)\$s/g, function (match, position) {
            var index = parseInt(position, 10) - 1;
            return values[index] === undefined ? match : String(values[index]);
        });
    }

    function number(form, name, fallback) {
        var value = parseFloat((form.querySelector('[name="' + name + '"]') || {}).value || '');
        return Number.isFinite(value) ? value : fallback;
    }

    function text(form, name, fallback) {
        var field = form.querySelector('[name="' + name + '"]');
        return field && field.value ? field.value.trim() : fallback;
    }

    function setResult(tool, message) {
        var output = tool.querySelector('.mds-sponsorable-ai-tools__output');
        if (output) {
            output.textContent = message;
        }
        var leadResult = tool.querySelector('.mds-sponsorable-ai-tools__lead input[name="result"]');
        if (leadResult) {
            leadResult.value = message;
        }
    }

    function money(value, currency) {
        var amount = Number.isFinite(value) ? value : 0;
        var formatted = amount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return currency ? currency + ' ' + formatted : formatted;
    }

    document.addEventListener('submit', function (event) {
        var leadForm = event.target.closest('.mds-sponsorable-ai-tools__lead form');
        if (leadForm) {
            var savedResult = leadForm.querySelector('input[name="result"]');
            if (!savedResult || !savedResult.value.trim()) {
                event.preventDefault();
                var leadTool = leadForm.closest('.mds-sponsorable-ai-tools__tool');
                var leadOutput = leadTool ? leadTool.querySelector('.mds-sponsorable-ai-tools__output') : null;
                if (leadOutput) {
                    leadOutput.textContent = message('runFirst', 'Run the tool before requesting a saved result.');
                }
                var runButton = leadTool ? leadTool.querySelector('.mds-sponsorable-ai-tools__form button[type="submit"]') : null;
                if (runButton) {
                    runButton.focus();
                }
            }
            return;
        }

        var form = event.target.closest('.mds-sponsorable-ai-tools__form');
        if (!form) {
            return;
        }

        event.preventDefault();
        var tool = form.closest('.mds-sponsorable-ai-tools__tool');
        var kind = tool ? tool.getAttribute('data-tool-kind') : '';
        var currency = tool ? tool.getAttribute('data-tool-currency') || '' : '';
        var result = '';

        if ('budget_estimator' === kind) {
            var audience = Math.max(1, number(form, 'audience', 1000));
            var budget = Math.max(0, number(form, 'budget', 250));
            var days = Math.max(1, number(form, 'days', 14));
            var daily = budget / days;
            var perThousand = budget / (audience / 1000);
            result = format(message('budgetResult', 'Estimated daily budget: %1$s. Budget per 1,000 visitors: %2$s. Use this as a simple planning estimate, then confirm pricing and placement terms.'), [money(daily, currency), money(perThousand, currency)]);
        } else if ('launch_planner' === kind) {
            var date = text(form, 'launch_date', message('launchDateFallback', 'the launch date'));
            var goal = text(form, 'goal', message('goalFallback', 'the primary campaign goal'));
            result = format(message('launchResult', 'Plan around %1$s: confirm the offer, prepare sponsor creative, schedule placement review, publish approved assets, and review results against %2$s.'), [date, goal]);
        } else if ('sponsor_fit_quiz' === kind) {
            var businessType = text(form, 'business_type', message('sponsorFallback', 'This sponsor'));
            var sponsorGoal = text(form, 'goal', message('visibilityFallback', 'visibility'));
            result = format(message('sponsorResult', '%1$s looks best suited for a %2$s package with clear disclosure, simple creative, and a short review checkpoint.'), [businessType, sponsorGoal]);
        } else {
            var topic = text(form, 'topic', message('topicFallback', 'this topic'));
            var tone = text(form, 'tone', message('toneFallback', 'practical'));
            result = format(message('copyResult', 'Draft angle: a %1$s guide about %2$s with one useful takeaway, one sponsor-supported resource, and a clear disclosure.'), [tone, topic]);
        }

        setResult(tool, result);
    });
}());
