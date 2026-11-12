(function () {
    'use strict';
    document.querySelectorAll('[data-institution-access]').forEach(function (container) {
        var mode = container.querySelector('[data-scope-mode]');
        var fields = container.querySelector('[data-scope-fields]');
        var rows = container.querySelector('[data-scope-rows]');
        var addButton = container.querySelector('[data-add-scope]');
        function refresh() {
            var restricted = mode.value === 'restricted';
            fields.hidden = !restricted;
            fields.disabled = !restricted;
            rows.querySelectorAll('input[name="scope_university[]"]').forEach(function (input) { input.required = restricted; });
            addButton.disabled = rows.children.length >= 200;
        }
        mode.addEventListener('change', refresh);
        addButton.addEventListener('click', function () {
            if (rows.children.length >= 200) { return; }
            var row = document.createElement('tr');
            row.setAttribute('data-scope-row', '');
            row.innerHTML = '<td><input class="form-control" name="scope_university[]" placeholder="U-1234" maxlength="100" aria-label="University AISHE code" required></td>' +
                '<td><input class="form-control" name="scope_institution[]" placeholder="Blank = all institutions in this university" maxlength="100" aria-label="Institution code"></td>' +
                '<td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-scope>Remove</button></td>';
            rows.appendChild(row);
            refresh();
            row.querySelector('input').focus();
        });
        rows.addEventListener('click', function (event) {
            var button = event.target.closest('[data-remove-scope]');
            if (button) { button.closest('[data-scope-row]').remove(); refresh(); }
        });
        refresh();
    });
}());
