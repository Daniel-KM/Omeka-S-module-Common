'use strict';

(function() {

    // Close the page action menu when an entry opens the sidebar: such an entry
    // does not navigate, so the core closes the menu only on a click outside of
    // it and the open menu overlaps the sidebar.
    document.addEventListener('click', function(e) {
        if (e.target.closest('.page-action-menu .sidebar-content')
            && window.Omeka
        ) {
            window.Omeka.closeOpenPageActionsMenu();
        }
    });

})();
