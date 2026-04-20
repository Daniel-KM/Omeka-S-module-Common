$(document).ready(function() {
    $('#content').on('click', '.quick-search', function(e) {
        e.preventDefault();
        var sidebar = $('#sidebar-search');
        if (sidebar.hasClass('active')) {
            Omeka.closeSidebar(sidebar);
            return;
        }
        $('.sidebar.active').not(sidebar).each(function () {
            Omeka.closeSidebar($(this));
        });
        Omeka.openSidebar(sidebar);
        $('body').one('o:sidebar-opened', '.sidebar', function () {
            if (!sidebar.is(this)) {
                Omeka.closeSidebar(sidebar);
            }
        });
    });

    // Strip empty inputs from the submitted query so that "All" radios
    // (value="") and unfilled fields don't pollute the URL. "0" is kept because
    // it is a meaningful filter value. Use delegation on document to catch the
    // submit regardless of when the sidebar form enters the DOM.
    $(document).on('submit', '#quick-search-form', function() {
        $(this).find(':input[name]:not([name=""]):not(:disabled)').each(function() {
            const input = $(this);
            const val = input.val();
            if (input.is('[type="submit"]')
                || val === ''
                || val === null
                || (Array.isArray(val) && !val.length)
            ) {
                input.prop('name', '');
            }
        });
    });
});
