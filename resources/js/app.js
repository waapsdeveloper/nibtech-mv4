require('./bootstrap');

document.querySelectorAll('textarea').forEach(function(textarea) {
    textarea.addEventListener('keydown', function(event) {
        if (event.ctrlKey && event.key === 'Enter') {
            event.preventDefault();
            textarea.closest('form').submit();
        }
    });
});
