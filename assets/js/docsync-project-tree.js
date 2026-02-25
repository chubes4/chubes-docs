/**
 * DocSync Project Tree — Collapse/Expand
 *
 * Handles section toggle buttons in the project tree sidebar.
 * Sections containing the current page are auto-expanded on load
 * (via server-rendered classes). This JS handles user interactions.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var tree = document.querySelector('.docsync-project-tree');
		if (!tree) return;

		// Delegate click events on toggle buttons.
		tree.addEventListener('click', function (e) {
			var toggle = e.target.closest('.docsync-tree-toggle');
			if (!toggle) return;

			var section = toggle.parentElement;
			if (!section) return;

			var isExpanded = section.classList.contains('docsync-tree-expanded');

			if (isExpanded) {
				section.classList.remove('docsync-tree-expanded');
				section.classList.add('docsync-tree-collapsed');
				toggle.setAttribute('aria-expanded', 'false');
			} else {
				section.classList.remove('docsync-tree-collapsed');
				section.classList.add('docsync-tree-expanded');
				toggle.setAttribute('aria-expanded', 'true');
			}
		});

		// Scroll the current page item into view if it's off-screen
		// in the sidebar.
		var currentItem = tree.querySelector('.docsync-tree-current');
		if (currentItem) {
			var sidebar = tree.closest('.docsync-doc-sidebar');
			if (sidebar && sidebar.scrollHeight > sidebar.clientHeight) {
				currentItem.scrollIntoView({ block: 'center', behavior: 'instant' });
			}
		}
	});
})();
