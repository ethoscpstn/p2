// Dark Mode Toggle with LocalStorage Persistence
(function() {
  'use strict';

  // Check for saved theme preference or default to light mode
  const currentTheme = localStorage.getItem('theme') || 'light';

  // Apply theme on page load
  document.documentElement.setAttribute('data-theme', currentTheme);

  // Wait for DOM to load
  document.addEventListener('DOMContentLoaded', function() {
    // Create toggle button
    const toggleButton = document.createElement('button');
    toggleButton.className = 'dark-mode-toggle';
    toggleButton.setAttribute('aria-label', 'Toggle dark mode');
    toggleButton.innerHTML = `
      <span class="icon-moon">🌙</span>
      <span class="icon-sun">☀️</span>
    `;

    document.body.appendChild(toggleButton);

    // Toggle theme function
    function toggleTheme() {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);

      // Optional: Add a subtle animation
      document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
      setTimeout(() => {
        document.body.style.transition = '';
      }, 300);
    }

    // Add click event listener
    toggleButton.addEventListener('click', toggleTheme);

    // Optional: Keyboard shortcut (Ctrl/Cmd + Shift + D)
    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        toggleTheme();
      }
    });
  });
})();
