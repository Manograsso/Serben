(function () {
    'use strict';

    function findField(button, id) {
        var form = button.closest('form');
        var selectors = [
            '#' + id,
            '[name="' + id + '"]',
            '[name="form_fields[' + id + ']"]',
            '[data-serben-field="' + id + '"]'
        ];
        var root = form || document;
        for (var i = 0; i < selectors.length; i++) {
            var field = root.querySelector(selectors[i]);
            if (field) return field;
        }
        if (form) {
            for (var j = 0; j < selectors.length; j++) {
                var fallback = document.querySelector(selectors[j]);
                if (fallback) return fallback;
            }
        }
        return null;
    }

    function valueOf(field) {
        if (!field) return '';
        if (field.type === 'radio') {
            var checked = document.querySelector('[name="' + field.name + '"]:checked');
            return checked ? checked.value : '';
        }
        if (field.type === 'checkbox') return field.checked ? field.value : '';
        return field.value || '';
    }

    function clearErrors(button) {
        var form = button.closest('form') || document;
        form.querySelectorAll('.serben-field-error').forEach(function (el) { el.remove(); });
        form.querySelectorAll('.serben-invalid-field').forEach(function (el) { el.classList.remove('serben-invalid-field'); });
    }

    function showFieldErrors(button, errors) {
        Object.keys(errors || {}).forEach(function (apiField) {
            var map = SerbenRegistration.fieldMap || {};
            var elementorId = Object.keys(map).find(function (key) { return map[key] === apiField; });
            if (!elementorId) return;
            var field = findField(button, elementorId);
            if (!field) return;
            field.classList.add('serben-invalid-field');
            var error = document.createElement('small');
            error.className = 'serben-field-error';
            error.textContent = errors[apiField];
            field.insertAdjacentElement('afterend', error);
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.serben-register-submit');
        if (!button) return;
        event.preventDefault();

        clearErrors(button);
        var message = button.parentElement.querySelector('.serben-register-message');
        var fields = {};
        var map = SerbenRegistration.fieldMap || {};
        Object.keys(map).forEach(function (elementorId) {
            fields[map[elementorId]] = valueOf(findField(button, elementorId));
        });

        var originalText = button.textContent;
        button.disabled = true;
        button.textContent = button.dataset.loadingText || 'Enviando...';
        message.className = 'serben-register-message';
        message.textContent = '';

        var payload = new URLSearchParams();
        payload.append('action', 'serben_external_register');
        payload.append('nonce', SerbenRegistration.nonce);
        Object.keys(fields).forEach(function (key) { payload.append('fields[' + key + ']', fields[key]); });

        fetch(SerbenRegistration.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: payload.toString()
        }).then(function (response) {
            return response.json().then(function (json) { return {ok: response.ok, json: json}; });
        }).then(function (result) {
            var data = result.json && result.json.data ? result.json.data : {};
            if (result.ok && result.json.success) {
                message.className = 'serben-register-message serben-success';
                message.textContent = data.message || 'Cadastro realizado com sucesso.';
                var url = button.dataset.successUrl;
                if (url) window.setTimeout(function () { window.location.href = url; }, 900);
                return;
            }
            message.className = 'serben-register-message serben-error';
            message.textContent = data.message || 'Não foi possível concluir o cadastro.';
            showFieldErrors(button, data.errors || {});
        }).catch(function () {
            message.className = 'serben-register-message serben-error';
            message.textContent = 'Falha de comunicação. Tente novamente.';
        }).finally(function () {
            button.disabled = false;
            button.textContent = originalText;
        });
    });
}());
