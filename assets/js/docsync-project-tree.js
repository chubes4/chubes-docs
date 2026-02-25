/**
 * DocSync Project Tree — Collapse/Expand + Mobile Sidebar Toggle
 *
 * Handles section toggle buttons in the project tree sidebar and
 * the mobile sidebar toggle that shows/hides the navigation panel.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		// === Mobile sidebar toggle ===
		var sidebarToggle = document.querySelector('.docsync-sidebar-toggle');
		var sidebar = document.querySelector('.docsync-doc-sidebar');

		if (sidebarToggle && sidebar) {
			sidebarToggle.addEventListener('click', function () {
				var isOpen = sidebar.classList.contains('docsync-sidebar-open');

				if (isOpen) {
					sidebar.classList.remove('docsync-sidebar-open');
					sidebarToggle.setAttribute('aria-expanded', 'false');
				} else {
					sidebar.classList.add('docsync-sidebar-open');
					sidebarToggle.setAttribute('aria-expanded', 'true');
				}
			});
		}

		// === Project tree section toggles ===
		var tree = document.querySelector('.docsync-project-tree');
		if (!tree) return;

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
		// in the sidebar (desktop only — mobile sidebar starts collapsed).
		var currentItem = tree.querySelector('.docsync-tree-current');
		if (currentItem && sidebar && !sidebar.classList.contains('docsync-sidebar-open')) {
			var isDesktop = window.matchMedia('(min-width: 1025px)').matches;
			if (isDesktop && sidebar.scrollHeight > sidebar.clientHeight) {
				currentItem.scrollIntoView({ block: 'center', behavior: 'instant' });
			}
		}
	});
})();
