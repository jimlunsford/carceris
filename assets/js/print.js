(function () {
    function attachPrintButton() {
        var button = document.getElementById('print-page-button');

        if (!button) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            window.focus();
            window.print();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachPrintButton);
    } else {
        attachPrintButton();
    }
})();
