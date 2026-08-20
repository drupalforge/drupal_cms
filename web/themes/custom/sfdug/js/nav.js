/**
 * @file
 * Mobile navigation toggle.
 *
 * The static prototype hid the nav entirely below 760px with no replacement,
 * so this behaviour has no counterpart there.
 */

((Drupal, once) => {
  Drupal.behaviors.sfdugNav = {
    attach(context) {
      once('sfdug-nav', '[data-nav-toggle]', context).forEach((toggle) => {
        const nav = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!nav) {
          return;
        }

        const setOpen = (open) => {
          toggle.setAttribute('aria-expanded', String(open));
          nav.classList.toggle('is-open', open);
        };

        toggle.addEventListener('click', () => {
          setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        // Escape closes it and returns focus to the button.
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
          }
        });

        // Following a link should not leave the panel hanging open.
        nav.addEventListener('click', (e) => {
          if (e.target.closest('a')) {
            setOpen(false);
          }
        });

        // Reset when the viewport grows back past the breakpoint
        // (kept in step with the 900px query in sections.css).
        window.matchMedia('(min-width: 901px)').addEventListener('change', (e) => {
          if (e.matches) {
            setOpen(false);
          }
        });
      });
    },
  };
})(Drupal, once);

/**
 * YouTube only generates maxresdefault.jpg for videos uploaded above 720p, so
 * older recordings 404. Fall back to hqdefault, which always exists.
 */
((Drupal, once) => {
  Drupal.behaviors.sfdugThumbFallback = {
    attach(context) {
      once('sfdug-thumb', '.card-thumb img[src*="img.youtube.com"]', context).forEach((img) => {
        img.addEventListener('error', () => {
          if (img.dataset.fallbackApplied) {
            img.remove();
            return;
          }
          img.dataset.fallbackApplied = '1';
          img.src = img.src.replace('maxresdefault.jpg', 'hqdefault.jpg');
        });
      });
    },
  };
})(Drupal, once);
