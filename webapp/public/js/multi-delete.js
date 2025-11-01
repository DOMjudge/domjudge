function initializeMultiDelete(options) {
    var $deleteButton = $(options.buttonSelector);
    var checkboxClass = options.checkboxClass;
    var deleteUrl = options.deleteUrl;

    function toggleDeleteButton() {
        var checkedCount = $('.' + checkboxClass + ':checked').length;
        $deleteButton.prop('disabled', checkedCount === 0);
    }

    $(document).on('change', '.' + checkboxClass, function() {
        var table = $(this).closest('table');
        var $tableCheckboxes = table.find('.' + checkboxClass);
        var $selectAllInTable = table.find('.select-all');
        $selectAllInTable.prop('checked', $tableCheckboxes.length > 0 && $tableCheckboxes.length === $tableCheckboxes.filter(':checked').length);
        toggleDeleteButton();
    });

    $(document).on('change', '.select-all', function() {
        var table = $(this).closest('table');
        table.find('.' + checkboxClass).prop('checked', $(this).is(':checked'));
        toggleDeleteButton();
    });

    toggleDeleteButton();

    $deleteButton.on('click', function () {
        var ids = $('.' + checkboxClass + ':checked').map(function () {
            return 'ids[]=' + $(this).val();
        }).get();
        if (ids.length === 0) return;

        var url = deleteUrl + '?' + ids.join('&');

        var $tempLink = $('<a>', {
            'href': url,
            'data-ajax-modal': ''
        }).hide().appendTo('body');

        $tempLink.trigger('click');
        $tempLink.remove();
    });
}
